<?php

declare(strict_types=1);

namespace Medora\Authority\Score\Scorers;

use Medora\Authority\Score\Deduction;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use Medora\Authority\Semantic\SemanticAnalyzer;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Translates the semantic analysis into scored, actionable deductions.
 */
final class SemanticStrengthScorer implements ScorerInterface
{
    public function __construct(private readonly SemanticAnalyzer $analyzer)
    {
    }

    public function id(): string
    {
        return 'semantic_strength';
    }

    public function label(): string
    {
        return __('Semantic Strength', 'medora-authority');
    }

    public function weight(): float
    {
        return 0.18;
    }

    public function appliesTo(WP_Post $post): bool
    {
        return true;
    }

    public function score(WP_Post $post): ScoreComponent
    {
        $analysis   = $this->analyzer->analyze($post);
        $deductions = [];

        if ($analysis['answer_readiness'] < 55) {
            $deductions[] = new Deduction(
                'buried_answer',
                __('The answer is buried', 'medora-authority'),
                round((55 - $analysis['answer_readiness']) * 0.5, 1),
                __('Open with a 30–70 word direct answer to the question in the title. Assistants quote the top of a page far more often than the middle.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        }

        if ($analysis['chunk_integrity'] < 60) {
            $deductions[] = new Deduction(
                'weak_chunks',
                __('Sections do not stand alone', 'medora-authority'),
                round((60 - $analysis['chunk_integrity']) * 0.4, 1),
                __('Add subheadings and avoid opening paragraphs with "this" or "it" — retrievers surface single sections without their context.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        }

        if ($analysis['knowledge_density'] < 50) {
            $deductions[] = new Deduction(
                'low_density',
                __('Low knowledge density', 'medora-authority'),
                round((50 - $analysis['knowledge_density']) * 0.3, 1),
                __('The page repeats a narrow vocabulary. Introduce the related concepts, measures and named things a specialist would expect.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        if ($analysis['missing_concepts'] !== []) {
            $deductions[] = new Deduction(
                'missing_facets',
                __('Expected sub-topics are missing', 'medora-authority'),
                min(20.0, count($analysis['missing_concepts']) * 4.0),
                sprintf(
                    /* translators: %s: comma-separated list of missing facets. */
                    __('Not covered: %s. Each one is a query this page currently cannot answer.', 'medora-authority'),
                    implode('، ', $analysis['missing_concepts'])
                ),
                Deduction::SEVERITY_MEDIUM
            );
        }

        foreach ($analysis['readability']['notes'] as $index => $note) {
            $deductions[] = new Deduction(
                'readability_' . $index,
                __('Structure', 'medora-authority'),
                4.0,
                $note,
                Deduction::SEVERITY_LOW
            );
        }

        if ($analysis['word_count'] < 300) {
            $deductions[] = new Deduction(
                'too_short',
                __('Too short to establish authority', 'medora-authority'),
                15.0,
                __('Under 300 words rarely carries enough substance to be cited over a competing source.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        }

        return new ScoreComponent(
            $this->id(),
            $this->label(),
            $analysis['score'],
            $this->weight(),
            $deductions,
            [
                'word_count'        => $analysis['word_count'],
                'chunks'            => $analysis['chunks'],
                'knowledge_density' => $analysis['knowledge_density'],
                'topic_coverage'    => $analysis['topic_coverage'],
                'answer_readiness'  => $analysis['answer_readiness'],
                'chunk_integrity'   => $analysis['chunk_integrity'],
                'missing_concepts'  => $analysis['missing_concepts'],
                'readability'       => $analysis['readability'],
            ]
        );
    }
}
