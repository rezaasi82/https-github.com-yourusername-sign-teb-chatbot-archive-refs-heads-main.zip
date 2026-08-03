<?php

declare(strict_types=1);

namespace Medora\Authority\Score\Scorers;

use Medora\Authority\Prompt\PromptPackRepository;
use Medora\Authority\Score\Deduction;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * How good is the machine-facing representation of this page?
 */
final class PromptQualityScorer implements ScorerInterface
{
    public function __construct(private readonly PromptPackRepository $packs)
    {
    }

    public function id(): string
    {
        return 'prompt_quality';
    }

    public function label(): string
    {
        return __('Prompt Quality', 'medora-authority');
    }

    public function weight(): float
    {
        return 0.12;
    }

    public function appliesTo(WP_Post $post): bool
    {
        return true;
    }

    public function score(WP_Post $post): ScoreComponent
    {
        $pack = $this->packs->find('post', $post->ID);

        if ($pack === null) {
            return new ScoreComponent(
                $this->id(),
                $this->label(),
                0.0,
                $this->weight(),
                [new Deduction(
                    'no_prompt_pack',
                    __('No prompt pack generated', 'medora-authority'),
                    100,
                    __('Run analysis on this page to generate its summary, canonical answer and fact sheet.', 'medora-authority'),
                    Deduction::SEVERITY_HIGH
                )],
                []
            );
        }

        $score      = 100.0;
        $deductions = [];

        $answerWords = Text::wordCount($pack['canonical_answer']);

        $metrics = [
            'has_summary'   => $pack['summary'] !== '',
            'answer_words'  => $answerWords,
            'fact_count'    => count($pack['facts']),
            'question_count' => count($pack['questions']),
            'context_tokens' => Text::estimateTokens($pack['context_window']),
            'is_stale'      => $pack['content_hash'] !== Text::hash($post->post_title . "\n" . $post->post_content),
        ];

        if ($pack['canonical_answer'] === '') {
            $score -= 35;
            $deductions[] = new Deduction(
                'no_canonical_answer',
                __('No canonical answer', 'medora-authority'),
                35,
                __('Mark a passage with the class "medora-answer", or open the page with a direct, self-contained answer.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        } elseif ($answerWords < 15 || $answerWords > 80) {
            $score -= 12;
            $deductions[] = new Deduction(
                'answer_length',
                __('Canonical answer is poorly sized', 'medora-authority'),
                12,
                __('Aim for 25–60 words: long enough to stand alone, short enough to be quoted whole.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        if ($metrics['fact_count'] < 3) {
            $score -= 20;
            $deductions[] = new Deduction(
                'few_facts',
                __('Few extractable facts', 'medora-authority'),
                20,
                __('Add concrete figures, dates and measures. Sentences without a number are rarely quoted as evidence.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        if ($metrics['question_count'] < 3) {
            $score -= 15;
            $deductions[] = new Deduction(
                'few_questions',
                __('Covers few distinct questions', 'medora-authority'),
                15,
                __('Add question-shaped H2s. Each one is a query the page becomes eligible to answer.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        if ($pack['summary'] === '') {
            $score -= 15;
            $deductions[] = new Deduction(
                'no_summary',
                __('No summary', 'medora-authority'),
                15,
                __('Write an excerpt. It becomes the description in schema, llms.txt and the AI sitemap.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        if ($metrics['is_stale']) {
            $score -= 10;
            $deductions[] = new Deduction(
                'stale_pack',
                __('Prompt pack is out of date', 'medora-authority'),
                10,
                __('The content changed after the pack was generated. Re-run analysis.', 'medora-authority'),
                Deduction::SEVERITY_LOW
            );
        }

        return new ScoreComponent(
            $this->id(),
            $this->label(),
            max(0.0, $score),
            $this->weight(),
            $deductions,
            $metrics
        );
    }
}
