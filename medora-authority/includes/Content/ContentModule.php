<?php

declare(strict_types=1);

namespace Medora\Authority\Content;

use Medora\Authority\Citation\CitationRepository;
use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Graph\RelationRepository;
use Medora\Authority\Graph\RelationType;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Prompt\PromptPackRepository;
use Medora\Authority\Score\AuthorityScoreCalculator;
use Medora\Authority\Semantic\SemanticAnalyzer;
use WP_Post;

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

        $container->singleton(
            ContentBrief::class,
            static fn (Container $c): ContentBrief => new ContentBrief(
                $c->get(SemanticAnalyzer::class),
                $c->get(EntityRepository::class),
                $c->get(PromptPackRepository::class),
                $c->get(RecommendationEngine::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        // The brief asks for related entities through a filter rather than
        // depending on the Graph module directly, so it degrades to "no
        // suggestions" instead of fataling when the graph is off.
        add_filter('medora_brief_related_entities', static function (array $neighbours, Entity $subject) use ($container): array {
            if (! $container->get(ModuleRegistry::class)->isBooted('graph')) {
                return $neighbours;
            }

            $entities  = $container->get(EntityRepository::class);
            $relations = $container->get(RelationRepository::class);

            foreach ($relations->forEntity($subject->id, 20) as $edge) {
                $other = $entities->find($edge['other_id']);

                if ($other === null) {
                    continue;
                }

                $neighbours[] = [
                    'entity' => $other,
                    'why'    => sprintf(
                        /* translators: 1: relationship, 2: subject name. */
                        __('%1$s %2$s elsewhere on this site.', 'medora-authority'),
                        RelationType::label($edge['predicate']),
                        $subject->name
                    ),
                ];
            }

            return $neighbours;
        }, 10, 2);

        add_filter('medora_brief_citation_count', static function (int $count, WP_Post $post) use ($container): int {
            if (! $container->get(ModuleRegistry::class)->isBooted('citation')) {
                return $count;
            }

            return $container->get(CitationRepository::class)->countForObject('post', $post->ID);
        }, 10, 2);

        add_filter('medora_brief_citations_required', static function (bool $required) use ($container): bool {
            // Health content is held to primary literature; general content is
            // only asked for one outbound source.
            return $required || $container->get(Options::class)->getString('site_mode') === 'medical';
        });
    }
}
