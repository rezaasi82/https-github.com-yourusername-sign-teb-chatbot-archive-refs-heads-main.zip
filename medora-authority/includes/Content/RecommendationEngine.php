<?php

declare(strict_types=1);

namespace Medora\Authority\Content;

use Medora\Authority\Prompt\PromptPackRepository;
use Medora\Authority\Score\AuthorityScoreCalculator;
use Medora\Authority\Semantic\SemanticAnalyzer;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Turns analysis into an ordered, concrete to-do list for one page.
 *
 * The ordering is by *estimated points recovered per unit of effort*, not by
 * severity alone. A critical issue that needs a week of rewriting ranks below
 * a high-severity one that takes two minutes — which is what an editor
 * working through a backlog actually needs.
 */
final class RecommendationEngine
{
    /** Rough effort weights, in relative units. */
    private const EFFORT = [
        'settings'       => 1,
        'author-profile' => 2,
        'entity-editor'  => 2,
        'citations'      => 3,
        'editor'         => 5,
    ];

    public function __construct(
        private readonly AuthorityScoreCalculator $calculator,
        private readonly SemanticAnalyzer $semantic,
        private readonly PromptPackRepository $packs,
    ) {
    }

    /**
     * @return array{
     *     post_id: int,
     *     score: float,
     *     grade: string,
     *     potential_score: float,
     *     actions: list<array<string, mixed>>,
     *     missing_concepts: list<string>
     * }
     */
    public function forPost(WP_Post $post): array
    {
        $analysis = $this->calculator->analyze($post);
        $semantic = $this->semantic->analyze($post);

        $actions = [];

        foreach ($analysis['deductions'] as $deduction) {
            $location = (string) ($deduction['fix_location'] ?? 'editor');
            $points   = (float) ($deduction['points'] ?? 0);
            $effort   = self::EFFORT[$location] ?? 5;

            $actions[] = [
                'code'           => (string) ($deduction['code'] ?? ''),
                'title'          => (string) ($deduction['label'] ?? ''),
                'recommendation' => (string) ($deduction['recommendation'] ?? ''),
                'severity'       => (string) ($deduction['severity'] ?? 'medium'),
                'component'      => (string) ($deduction['component'] ?? ''),
                'points'         => $points,
                'effort'         => $effort,
                'impact_ratio'   => round($points / $effort, 2),
                'fix_location'   => $location,
            ];
        }

        // Missing sub-topics are not deductions but they are the highest-value
        // content work available, so they are promoted into the action list.
        foreach ($semantic['missing_concepts'] as $concept) {
            $actions[] = [
                'code'           => 'add_section_' . sanitize_key($concept),
                'title'          => sprintf(
                    /* translators: %s: sub-topic name. */
                    __('Add a section on %s', 'medora-authority'),
                    $concept
                ),
                'recommendation' => __('This is a question readers and assistants ask about this subject that the page currently cannot answer.', 'medora-authority'),
                'severity'       => 'medium',
                'component'      => 'semantic_strength',
                'points'         => 4.0,
                'effort'         => 5,
                'impact_ratio'   => 0.8,
                'fix_location'   => 'editor',
            ];
        }

        usort($actions, static fn (array $a, array $b): int => $b['impact_ratio'] <=> $a['impact_ratio']);

        $recoverable = array_sum(array_column($actions, 'points'));

        return [
            'post_id'          => $post->ID,
            'score'            => (float) $analysis['overall'],
            'grade'            => (string) $analysis['grade'],
            // What the page could reach if every listed action were completed.
            'potential_score'  => round(min(100.0, (float) $analysis['overall'] + $recoverable), 1),
            'actions'          => array_slice($actions, 0, 20),
            'missing_concepts' => $semantic['missing_concepts'],
        ];
    }

    /**
     * Answer-first rewrite guidance for the opening of a page.
     *
     * @return array{needs_rewrite: bool, current_opening: string, guidance: string}
     */
    public function answerFirstGuidance(WP_Post $post): array
    {
        $pack     = $this->packs->find('post', $post->ID);
        $semantic = $this->semantic->analyze($post);
        $opening  = $pack === null ? '' : $pack['canonical_answer'];

        $needsRewrite = $semantic['answer_readiness'] < 60;

        return [
            'needs_rewrite'   => $needsRewrite,
            'current_opening' => $opening,
            'guidance'        => $needsRewrite
                ? sprintf(
                    /* translators: %s: page title. */
                    __('Open with 30–70 words that answer "%s" directly, before any background. Name the subject in the first sentence and avoid opening with "This article will…".', 'medora-authority'),
                    get_the_title($post)
                )
                : __('The opening already answers the question directly.', 'medora-authority'),
        ];
    }
}
