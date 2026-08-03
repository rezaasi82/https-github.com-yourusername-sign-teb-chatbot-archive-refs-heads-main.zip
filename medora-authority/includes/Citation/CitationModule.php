<?php

declare(strict_types=1);

namespace Medora\Authority\Citation;

use Medora\Authority\Core\Container;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Support\Cache;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Citation Engine.
 */
final class CitationModule extends AbstractModule
{
    public function id(): string
    {
        return 'citation';
    }

    public function title(): string
    {
        return __('Citation Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Resolve DOIs and PubMed identifiers into structured, scored, schema-ready references.', 'medora-authority');
    }

    public function requiredTier(): string
    {
        return LicenseTier::PRO;
    }

    public function register(Container $container): void
    {
        $container->singleton(CitationRepository::class, static fn (): CitationRepository => new CitationRepository());
        $container->singleton(CitationFormatter::class, static fn (): CitationFormatter => new CitationFormatter());
        $container->singleton(CitationQualityScorer::class, static fn (): CitationQualityScorer => new CitationQualityScorer());
        $container->singleton(
            CrossRefResolver::class,
            static fn (Container $c): CrossRefResolver => new CrossRefResolver($c->get(Cache::class))
        );
    }

    public function boot(Container $container): void
    {
        // Feed stored citations into the article's JSON-LD.
        add_filter('medora_schema_citations', static function (array $citations, WP_Post $post) use ($container): array {
            foreach ($container->get(CitationRepository::class)->forObject('post', $post->ID) as $citation) {
                $citations[] = $citation->toSchemaNode();
            }

            return $citations;
        }, 10, 2);

        add_action('deleted_post', static function (int $postId) use ($container): void {
            $container->get(CitationRepository::class)->deleteForObject('post', $postId);
        });
    }
}
