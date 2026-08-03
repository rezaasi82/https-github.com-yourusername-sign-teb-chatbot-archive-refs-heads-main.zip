<?php

declare(strict_types=1);

namespace Medora\Authority\Geo;

use Medora\Authority\Core\Container;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Score\AuthorityScoreCalculator;
use Medora\Authority\Support\Chunker;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * GEO Optimizer — generative engine optimisation.
 *
 * The distinction from the rest of the platform is the unit of analysis. Every
 * other module treats the page as the thing being evaluated. This one treats
 * the *passage* as the thing, because that is what a retriever returns and what
 * a model answers from. The two views disagree often enough to be worth
 * separating: a page can be thorough, sourced and well-linked while most of its
 * paragraphs are unquotable on their own.
 *
 * Adds one score dimension, `llm_compatibility`, and one endpoint. It writes
 * nothing and changes no output — the entire module is measurement, because the
 * fixes it asks for are editorial and belong to the author.
 */
final class GeoModule extends AbstractModule
{
    public function id(): string
    {
        return 'geo';
    }

    public function title(): string
    {
        return __('GEO Optimizer', 'medora-authority');
    }

    public function description(): string
    {
        return __('Score every passage on whether it stands on its own once retrieved out of the page.', 'medora-authority');
    }

    public function dependencies(): array
    {
        // Chunker is bound by the Semantic Engine, and the whole module is an
        // opinion about chunks; sharing one chunker means the passages scored
        // here are byte-for-byte the ones the Vector Engine indexes.
        return ['semantic', 'score'];
    }

    public function requiredTier(): string
    {
        return LicenseTier::PRO;
    }

    public function register(Container $container): void
    {
        $container->singleton(
            PassageAnalyzer::class,
            static fn (Container $c): PassageAnalyzer => new PassageAnalyzer($c->get(Chunker::class))
        );

        $container->singleton(
            StructureAnalyzer::class,
            static fn (): StructureAnalyzer => new StructureAnalyzer()
        );

        $container->singleton(
            LlmCompatibilityScorer::class,
            static fn (Container $c): LlmCompatibilityScorer => new LlmCompatibilityScorer(
                $c->get(PassageAnalyzer::class),
                $c->get(StructureAnalyzer::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        add_action(
            'medora_register_scorers',
            static function (AuthorityScoreCalculator $calculator) use ($container): void {
                $calculator->addScorer($container->get(LlmCompatibilityScorer::class));
            }
        );
    }
}
