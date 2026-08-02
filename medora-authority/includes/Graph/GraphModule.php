<?php

declare(strict_types=1);

namespace Medora\Authority\Graph;

use Medora\Authority\Core\Container;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Module\AbstractModule;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Graph Engine.
 */
final class GraphModule extends AbstractModule
{
    public function id(): string
    {
        return 'graph';
    }

    public function title(): string
    {
        return __('Knowledge Graph Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Connect entities into a navigable graph and publish it as JSON-LD.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['entity'];
    }

    public function register(Container $container): void
    {
        $container->singleton(RelationRepository::class, static fn (): RelationRepository => new RelationRepository());

        $container->singleton(
            KnowledgeGraphBuilder::class,
            static fn (Container $c): KnowledgeGraphBuilder => new KnowledgeGraphBuilder(
                $c->get(EntityRepository::class),
                $c->get(RelationRepository::class)
            )
        );

        $container->singleton(
            GraphExporter::class,
            static fn (Container $c): GraphExporter => new GraphExporter(
                $c->get(EntityRepository::class),
                $c->get(RelationRepository::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        // Edges are derived from the entity index, so they are rebuilt as soon
        // as that index changes for a post.
        add_action('medora_entities_indexed', static function (WP_Post $post) use ($container): void {
            $container->get(KnowledgeGraphBuilder::class)->buildForPost($post);
        });
    }
}
