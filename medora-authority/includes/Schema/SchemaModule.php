<?php

declare(strict_types=1);

namespace Medora\Authority\Schema;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Schema\Nodes\ArticleNode;
use Medora\Authority\Schema\Nodes\BreadcrumbNode;
use Medora\Authority\Schema\Nodes\EntityNode;
use Medora\Authority\Schema\Nodes\FaqNode;
use Medora\Authority\Schema\Nodes\OrganizationNode;
use Medora\Authority\Schema\Nodes\WebPageNode;
use Medora\Authority\Schema\Nodes\WebSiteNode;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Schema Intelligence — builds, validates and prints the JSON-LD graph.
 */
final class SchemaModule extends AbstractModule
{
    public function id(): string
    {
        return 'schema';
    }

    public function title(): string
    {
        return __('Schema Intelligence', 'medora-authority');
    }

    public function description(): string
    {
        return __('Generate one connected JSON-LD graph per page, validated before it ships.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['entity'];
    }

    public function register(Container $container): void
    {
        $container->singleton(SchemaValidator::class, static fn (): SchemaValidator => new SchemaValidator());

        $container->singleton(SchemaGraph::class, static function (): SchemaGraph {
            $graph = new SchemaGraph();

            $graph->addNode(new OrganizationNode());
            $graph->addNode(new WebSiteNode());
            $graph->addNode(new WebPageNode());
            $graph->addNode(new ArticleNode());
            $graph->addNode(new BreadcrumbNode());
            $graph->addNode(new FaqNode());
            $graph->addNode(new EntityNode());

            /**
             * Register additional schema nodes.
             *
             * @param SchemaGraph $graph
             */
            do_action('medora_register_schema_nodes', $graph);

            return $graph;
        });
    }

    public function boot(Container $container): void
    {
        if (! $container->get(Options::class)->getBool('schema_enabled', true)) {
            return;
        }

        add_action('wp_head', function () use ($container): void {
            $this->printGraph($container);
        }, 5);
    }

    /** Build the context for the current request. */
    public function contextFor(Container $container, ?WP_Post $post): SchemaContext
    {
        $entities = $post instanceof WP_Post
            ? $container->get(EntityRepository::class)->forObject('post', $post->ID, 20)
            : [];

        return new SchemaContext(
            options: $container->get(Options::class),
            post: $post,
            entities: $entities,
            isSingular: is_singular(),
            isFront: is_front_page(),
        );
    }

    private function printGraph(Container $container): void
    {
        if (is_feed() || is_404() || is_search()) {
            return;
        }

        $post    = is_singular() ? get_post() : null;
        $context = $this->contextFor($container, $post instanceof WP_Post ? $post : null);
        $json    = $container->get(SchemaGraph::class)->toJson($context);

        if ($json === '' || $json === 'null') {
            return;
        }

        // wp_json_encode already produced safe JSON; wrapping the script tag in
        // esc_html would corrupt it, so the type attribute is the escape point.
        printf(
            "<script type=\"application/ld+json\" data-medora=\"schema\">%s</script>\n",
            $json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
    }
}
