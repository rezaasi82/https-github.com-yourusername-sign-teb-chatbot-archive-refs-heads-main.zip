<?php

declare(strict_types=1);

namespace Medora\Authority\Content;

use Medora\Authority\Core\Container;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Prompt\PromptPackRepository;
use Medora\Authority\Score\AuthorityScoreCalculator;
use Medora\Authority\Semantic\SemanticAnalyzer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Content Optimizer.
 */
final class ContentModule extends AbstractModule
{
    public function id(): string
    {
        return 'content';
    }

    public function title(): string
    {
        return __('AI Content Optimizer', 'medora-authority');
    }

    public function description(): string
    {
        return __('An ordered, effort-weighted to-do list for every page.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['score', 'semantic'];
    }

    public function requiredTier(): string
    {
        return LicenseTier::PRO;
    }

    public function register(Container $container): void
    {
        $container->singleton(
            RecommendationEngine::class,
            static fn (Container $c): RecommendationEngine => new RecommendationEngine(
                $c->get(AuthorityScoreCalculator::class),
                $c->get(SemanticAnalyzer::class),
                $c->get(PromptPackRepository::class)
            )
        );
    }
}
