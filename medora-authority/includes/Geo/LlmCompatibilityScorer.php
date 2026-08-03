<?php

declare(strict_types=1);

namespace Medora\Authority\Geo;

use Medora\Authority\Score\Deduction;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Would this page survive being read one passage at a time?
 *
 * The other dimensions score the page as a document. This one scores it as the
 * fragments a retriever will actually hand to a model, which is the only form
 * most AI answers ever see it in. A page can be well-sourced, well-structured
 * and semantically rich and still score badly here, because its paragraphs only
 * make sense in order.
 *
 * The deductions name passages by index, so the fix list points at a specific
 * paragraph rather than at the page in general.
 */
final class LlmCompatibilityScorer implements ScorerInterface
{
    /** Report at most this many passages by name before summarising the rest. */
    private const NAMED_PASSAGES = 5;

    public function __construct(
        private readonly PassageAnalyzer $passages,
        private readonly StructureAnalyzer $structure,
    ) {
    }

    public function id(): string
    {
        return 'llm_compatibility';
    }

    public function label(): string
    {
        return __('LLM Compatibility', 'medora-authority');
    }

    public function weight(): float
    {
        return 0.14;
    }

    public function appliesTo(WP_Post $post): bool
    {
        return true;
    }

    public function score(WP_Post $post): ScoreComponent
    {
        $report    = $this->passages->analyze($post);
        $structure = $this->structure->analyze($post);

        if ($report['total'] === 0) {
            return new ScoreComponent(
                $this->id(),
                $this->label(),
                0.0,
                $this->weight(),
                [new Deduction(
                    'no_passages',
                    __('Page has no retrievable content', 'medora-authority'),
                    100,
                    __('There is nothing here to chunk. Add body copy — a page built entirely from shortcodes or embeds is invisible to retrieval.', 'medora-authority'),
                    Deduction::SEVERITY_CRITICAL
                )],
                ['passages' => 0]
            );
        }

        $score      = 100.0;
        $deductions = [];

        // The headline number: what fraction of this page's passages stand on
        // their own. Scaled to 55 points because it is the dimension — the
        // structure checks below are secondary to it.
        $broken = $report['total'] - $report['clean'];

        if ($broken > 0) {
            $penalty = (int) round(55 * ($broken / $report['total']));

            $score       -= $penalty;
            $deductions[] = new Deduction(
                'passages_not_self_contained',
                sprintf(
                    /* translators: 1: number of passages, 2: total passages. */
                    __('%1$d of %2$d passages do not stand on their own', 'medora-authority'),
                    $broken,
                    $report['total']
                ),
                $penalty,
                $this->passageAdvice($report),
                $broken / $report['total'] > 0.5 ? Deduction::SEVERITY_HIGH : Deduction::SEVERITY_MEDIUM
            );
        }

        if ($structure['is_long_form'] && $structure['surfaces'] === 0) {
            $score       -= 20;
            $deductions[] = new Deduction(
                'no_structured_surfaces',
                __('Long page with nothing an assistant can lift whole', 'medora-authority'),
                20,
                __('Add a table, a numbered procedure or a bulleted set of criteria. Structure is quoted intact; prose gets paraphrased.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        if ($structure['headings'] === 0) {
            $score       -= 15;
            $deductions[] = new Deduction(
                'no_headings',
                __('No H2 or H3 headings', 'medora-authority'),
                15,
                __('Headings are where chunk boundaries come from. Without them the page is split mid-argument.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        } elseif ($structure['question_headings'] === 0) {
            $score       -= 10;
            $deductions[] = new Deduction(
                'no_question_headings',
                __('No heading is phrased as a question', 'medora-authority'),
                10,
                __('Phrase at least one H2 the way a person would ask it. A question heading makes the passage under it a direct answer.', 'medora-authority'),
                Deduction::SEVERITY_LOW
            );
        }

        return new ScoreComponent(
            $this->id(),
            $this->label(),
            max(0.0, $score),
            $this->weight(),
            $deductions,
            [
                'passages'          => $report['total'],
                'self_contained'    => $report['clean'],
                'self_contained_pct' => (int) round($report['ratio'] * 100),
                'structured_surfaces' => $structure['surfaces'],
                'question_headings' => $structure['question_headings'],
                'has_steps'         => $structure['has_steps'],
            ]
        );
    }

    /**
     * Name the worst passages rather than restating the count.
     *
     * "6 of 14 passages are not self-contained" is a measurement; "passage 3
     * under 'Treatment' opens with a pronoun" is an edit.
     *
     * @param array{passages: list<array<string, mixed>>} $report
     */
    private function passageAdvice(array $report): string
    {
        $worst = array_filter(
            $report['passages'],
            static fn (array $passage): bool => $passage['issues'] !== []
        );

        usort($worst, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        $lines = [];

        foreach (array_slice($worst, 0, self::NAMED_PASSAGES) as $passage) {
            $lines[] = sprintf(
                /* translators: 1: passage number, 2: heading or a placeholder, 3: first issue. */
                __('Passage %1$d (%2$s): %3$s', 'medora-authority'),
                $passage['index'] + 1,
                $passage['heading'] !== '' ? $passage['heading'] : __('no heading', 'medora-authority'),
                $passage['issues'][0]['label']
            );
        }

        $remaining = count($worst) - count($lines);

        if ($remaining > 0) {
            $lines[] = sprintf(
                /* translators: %d: number of further passages. */
                __('…and %d more, listed in full on the Passages tab.', 'medora-authority'),
                $remaining
            );
        }

        return implode(' ', $lines);
    }
}
