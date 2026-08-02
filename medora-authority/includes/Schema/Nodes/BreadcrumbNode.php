<?php

declare(strict_types=1);

namespace Medora\Authority\Schema\Nodes;

use Medora\Authority\Schema\NodeInterface;
use Medora\Authority\Schema\SchemaContext;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Breadcrumb trail.
 *
 * Breadcrumbs tell an AI system where a page sits in the site's hierarchy,
 * which is how it decides whether the page is a broad overview or a leaf
 * detail — and therefore which query depth to cite it for.
 */
final class BreadcrumbNode implements NodeInterface
{
    public function id(): string
    {
        return 'breadcrumb';
    }

    public function appliesTo(SchemaContext $context): bool
    {
        return $context->post instanceof WP_Post && ! $context->isFront;
    }

    public function build(SchemaContext $context): array
    {
        $post = $context->post;

        if (! $post instanceof WP_Post) {
            return [];
        }

        $trail = [['name' => __('Home', 'medora-authority'), 'url' => home_url('/')]];

        foreach ($this->ancestors($post) as $ancestor) {
            $trail[] = $ancestor;
        }

        $trail[] = ['name' => get_the_title($post), 'url' => (string) get_permalink($post)];

        $items = [];

        foreach ($trail as $position => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $crumb['name'],
                'item'     => $crumb['url'],
            ];
        }

        return [[
            '@type'           => 'BreadcrumbList',
            '@id'             => $context->id('breadcrumb'),
            'itemListElement' => $items,
        ]];
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function ancestors(WP_Post $post): array
    {
        $crumbs = [];

        // Hierarchical types have real parents; flat types borrow their
        // primary term as a stand-in for hierarchy.
        if (is_post_type_hierarchical($post->post_type)) {
            foreach (array_reverse(get_post_ancestors($post)) as $ancestorId) {
                $ancestor = get_post($ancestorId);

                if ($ancestor instanceof WP_Post) {
                    $crumbs[] = ['name' => get_the_title($ancestor), 'url' => (string) get_permalink($ancestor)];
                }
            }

            return $crumbs;
        }

        $taxonomies = get_object_taxonomies($post->post_type, 'names');
        $primary    = null;

        foreach (['category', ...$taxonomies] as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);

            if (is_array($terms) && $terms !== []) {
                $primary = $terms[0];
                break;
            }
        }

        if ($primary instanceof WP_Term) {
            $link = get_term_link($primary);

            if (! is_wp_error($link)) {
                $crumbs[] = ['name' => $primary->name, 'url' => $link];
            }
        }

        return $crumbs;
    }
}
