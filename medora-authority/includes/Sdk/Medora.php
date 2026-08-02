<?php

declare(strict_types=1);

namespace Medora\Authority\Sdk;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Plugin;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Graph\GraphExporter;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Prompt\PromptContextBuilder;
use Medora\Authority\Score\AuthorityScoreCalculator;
use Medora\Authority\Vector\VectorIndex;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The public developer facade.
 *
 * Third-party code should depend on this class and on the documented hooks —
 * never on the internal repositories, whose signatures are free to change
 * between minor versions. Every method here degrades gracefully when the
 * module behind it is disabled, so an integration does not need to check the
 * module registry before every call.
 *
 * @example
 *   $score = \Medora\Authority\Sdk\Medora::score( get_post() );
 *   $pack  = \Medora\Authority\Sdk\Medora::promptPack( get_post() );
 *   $hits  = \Medora\Authority\Sdk\Medora::search( 'liver biopsy recovery' );
 */
final class Medora
{
    public static function container(): Container
    {
        return Plugin::instance()->container();
    }

    public static function isModuleActive(string $moduleId): bool
    {
        return self::container()->get(ModuleRegistry::class)->isBooted($moduleId);
    }

    /**
     * The AI Authority Score for a post, or null when scoring is unavailable.
     *
     * @return array<string, mixed>|null
     */
    public static function score(WP_Post $post, bool $refresh = false): ?array
    {
        if (! self::isModuleActive('score')) {
            return null;
        }

        return self::container()->get(AuthorityScoreCalculator::class)->analyze($post, $refresh);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function promptPack(WP_Post $post): ?array
    {
        if (! self::isModuleActive('prompt')) {
            return null;
        }

        return self::container()->get(PromptContextBuilder::class)->build($post);
    }

    /**
     * Entities attached to a post, most salient first.
     *
     * @return list<array{entity: Entity, salience: float, occurrences: int}>
     */
    public static function entitiesFor(WP_Post $post, int $limit = 20): array
    {
        if (! self::isModuleActive('entity')) {
            return [];
        }

        return self::container()->get(EntityRepository::class)->forObject('post', $post->ID, $limit);
    }

    public static function findEntity(string $name, ?string $type = null): ?Entity
    {
        if (! self::isModuleActive('entity')) {
            return null;
        }

        return self::container()->get(EntityRepository::class)->findByName($name, $type);
    }

    /**
     * Semantic search over the site.
     *
     * @return list<array{object_id: int, score: float, excerpt: string, title: string, url: string}>
     */
    public static function search(string $query, int $limit = 10): array
    {
        if (! self::isModuleActive('vector')) {
            return [];
        }

        return self::container()->get(VectorIndex::class)->search($query, $limit, ['object_type' => 'post']);
    }

    /**
     * Semantically related posts.
     *
     * @return list<array{object_id: int, score: float, excerpt: string, title: string, url: string}>
     */
    public static function related(WP_Post $post, int $limit = 5): array
    {
        if (! self::isModuleActive('vector')) {
            return [];
        }

        return self::container()->get(VectorIndex::class)->related($post->ID, $limit);
    }

    /**
     * The whole knowledge graph as Schema.org JSON-LD.
     *
     * @return array<string, mixed>
     */
    public static function knowledgeGraph(int $limit = 500): array
    {
        if (! self::isModuleActive('graph')) {
            return ['@context' => 'https://schema.org', '@graph' => []];
        }

        return self::container()->get(GraphExporter::class)->toJsonLd($limit);
    }
}
