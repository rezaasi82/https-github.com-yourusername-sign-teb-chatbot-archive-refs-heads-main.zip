<?php

declare(strict_types=1);

namespace Medora\Authority\Score;

use Medora\Authority\Citation\CitationRepository;
use Medora\Authority\Core\Container;
use Medora\Authority\Core\Cron;
use Medora\Authority\Core\Options;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Performance\JobQueue;
use Medora\Authority\Performance\Jobs\AnalyzePostJob;
use Medora\Authority\Prompt\PromptPackRepository;
use Medora\Authority\Score\Scorers\AiReadinessScorer;
use Medora\Authority\Score\Scorers\EntityScorer;
use Medora\Authority\Score\Scorers\KnowledgeQualityScorer;
use Medora\Authority\Score\Scorers\MedicalTrustScorer;
use Medora\Authority\Score\Scorers\PromptQualityScorer;
use Medora\Authority\Score\Scorers\SemanticStrengthScorer;
use Medora\Authority\Semantic\SemanticAnalyzer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Authority Score — the platform's headline metric.
 */
final class ScoreModule extends AbstractModule
{
    public function id(): string
    {
        return 'score';
    }

    public function title(): string
    {
        return __('AI Authority Score', 'medora-authority');
    }

    public function description(): string
    {
        return __('One explainable 0–100 score per page, with every deduction and its fix.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['entity', 'semantic', 'schema'];
    }

    public function register(Container $container): void
    {
        $container->singleton(AnalysisRepository::class, static fn (): AnalysisRepository => new AnalysisRepository());

        $container->singleton(
            AuthorityScoreCalculator::class,
            static function (Container $c): AuthorityScoreCalculator {
                $calculator = new AuthorityScoreCalculator($c->get(AnalysisRepository::class));

                $calculator->addScorer(new AiReadinessScorer($c, $c->get(Options::class)));
                $calculator->addScorer(new EntityScorer($c->get(EntityRepository::class)));
                $calculator->addScorer(new SemanticStrengthScorer($c->get(SemanticAnalyzer::class)));
                $calculator->addScorer(new KnowledgeQualityScorer());
                $calculator->addScorer(new PromptQualityScorer($c->get(PromptPackRepository::class)));
                $calculator->addScorer(new MedicalTrustScorer($c->get(Options::class), $c->get(CitationRepository::class)));

                /**
                 * Register additional scoring dimensions.
                 *
                 * @param AuthorityScoreCalculator $calculator
                 */
                do_action('medora_register_scorers', $calculator);

                return $calculator;
            }
        );
    }

    public function boot(Container $container): void
    {
        add_action('deleted_post', static function (int $postId) use ($container): void {
            $container->get(AnalysisRepository::class)->delete('post', $postId);
        });

        // Nightly refresh of the oldest analyses, so scores track changes in
        // site-wide signals (new entities, changed policy) and not just edits.
        add_action(Cron::DAILY_ANALYSIS, static function () use ($container): void {
            $queue = $container->get(JobQueue::class);

            $posts = get_posts([
                'post_type'      => 'any',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ]);

            foreach ($posts as $postId) {
                $queue->push(AnalyzePostJob::class, ['post_id' => (int) $postId]);
            }
        });
    }
}
