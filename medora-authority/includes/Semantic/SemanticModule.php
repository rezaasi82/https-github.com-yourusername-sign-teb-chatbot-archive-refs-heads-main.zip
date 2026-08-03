<?php

declare(strict_types=1);

namespace Medora\Authority\Semantic;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Support\Chunker;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Semantic Engine — scores how well content conveys knowledge to a machine.
 */
final class SemanticModule extends AbstractModule
{
    public function id(): string
    {
        return 'semantic';
    }

    public function title(): string
    {
        return __('Semantic Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Measure knowledge density, topic coverage, answer readiness and chunk quality.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['entity'];
    }

    public function register(Container $container): void
    {
        $container->singleton(ReadabilityAnalyzer::class, static fn (): ReadabilityAnalyzer => new ReadabilityAnalyzer());
        $container->singleton(Chunker::class, static fn (): Chunker => new Chunker());

        $container->singleton(
            TopicCoverage::class,
            static fn (Container $c): TopicCoverage => new TopicCoverage($c->get(Options::class))
        );

        $container->singleton(
            SemanticAnalyzer::class,
            static fn (Container $c): SemanticAnalyzer => new SemanticAnalyzer(
                $c->get(EntityRepository::class),
                $c->get(ReadabilityAnalyzer::class),
                $c->get(Chunker::class),
                $c->get(TopicCoverage::class)
            )
        );
    }
}
