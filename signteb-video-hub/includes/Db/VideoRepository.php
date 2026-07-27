<?php

namespace SignTeb\VideoHub\Db;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\VideoMeta;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * All video reads/writes against the post store. Keeps WP_Query and meta keys
 * out of the rest of the plugin.
 */
class VideoRepository
{
    /**
     * Existing post id for a provider video, or 0.
     */
    public function find_by_source(string $source, string $source_id): int
    {
        if ($source_id === '') {
            return 0;
        }

        $query = new WP_Query([
            'post_type'              => PostType::POST_TYPE,
            'post_status'            => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'meta_query'             => [
                'relation' => 'AND',
                ['key' => VideoMeta::SOURCE, 'value' => $source],
                ['key' => VideoMeta::SOURCE_ID, 'value' => $source_id],
            ],
        ]);

        return $query->posts === [] ? 0 : (int) $query->posts[0];
    }

    /**
     * Videos for the grid/archive/REST filter.
     *
     * @param array{
     *   topic?:string|int,
     *   search?:string,
     *   per_page?:int,
     *   page?:int,
     *   orderby?:string,
     *   exclude?:array<int,int>,
     *   include?:array<int,int>
     * } $args
     *
     * @return array{ids:array<int,int>,total:int,pages:int}
     */
    public function query(array $args = []): array
    {
        $per_page = max(1, min(48, (int) ($args['per_page'] ?? 12)));
        $page     = max(1, (int) ($args['page'] ?? 1));

        $query_args = [
            'post_type'      => PostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'fields'         => 'ids',
        ];

        $topic = $args['topic'] ?? '';
        if ($topic !== '' && $topic !== 'all') {
            $query_args['tax_query'] = [[
                'taxonomy' => PostType::TAXONOMY,
                'field'    => is_numeric($topic) ? 'term_id' : 'slug',
                'terms'    => is_numeric($topic) ? (int) $topic : (string) $topic,
            ]];
        }

        $search = trim((string) ($args['search'] ?? ''));
        if ($search !== '') {
            $query_args['s'] = $search;
        }

        if (! empty($args['exclude'])) {
            $query_args['post__not_in'] = array_map('intval', (array) $args['exclude']);
        }

        if (! empty($args['include'])) {
            $query_args['post__in'] = array_map('intval', (array) $args['include']);
            $query_args['orderby']  = 'post__in';
        } else {
            $this->apply_order($query_args, (string) ($args['orderby'] ?? 'date'));
        }

        $query = new WP_Query($query_args);

        return [
            'ids'   => array_map('intval', $query->posts),
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * @param array<string,mixed> $query_args
     */
    private function apply_order(array &$query_args, string $orderby): void
    {
        switch ($orderby) {
            case 'popular':
                // Populated by AnalyticsRepository::sync_view_counts().
                $query_args['meta_key'] = '_stvh_views_30d';
                $query_args['orderby']  = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
                break;
            case 'duration':
                $query_args['meta_key'] = VideoMeta::DURATION;
                $query_args['orderby']  = 'meta_value_num';
                $query_args['order']    = 'DESC';
                break;
            case 'title':
                $query_args['orderby'] = 'title';
                $query_args['order']   = 'ASC';
                break;
            case 'random':
                $query_args['orderby'] = 'rand';
                break;
            default:
                $query_args['orderby'] = 'date';
                $query_args['order']   = 'DESC';
        }
    }

    /**
     * Videos sharing topics with the given post — the retrieval half of the
     * medical hub (feature 18) before the AI layer re-ranks them.
     *
     * @return array<int,int>
     */
    public function related(int $post_id, int $limit = 4): array
    {
        $terms = wp_get_post_terms($post_id, PostType::TAXONOMY, ['fields' => 'ids']);
        if (is_wp_error($terms) || $terms === []) {
            return $this->query(['per_page' => $limit, 'exclude' => [$post_id]])['ids'];
        }

        $query = new WP_Query([
            'post_type'      => PostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'post__not_in'   => [$post_id],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => [[
                'taxonomy' => PostType::TAXONOMY,
                'field'    => 'term_id',
                'terms'    => array_map('intval', $terms),
            ]],
        ]);

        return array_map('intval', $query->posts);
    }

    /**
     * Every published video id — used by the sitemap and the Google indexing
     * queue, so it must stay a lightweight id-only query.
     *
     * @return array<int,int>
     */
    public function all_published_ids(int $limit = 2000): array
    {
        $query = new WP_Query([
            'post_type'              => PostType::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'orderby'                => 'date',
            'order'                  => 'DESC',
        ]);

        return array_map('intval', $query->posts);
    }

    /**
     * Videos still missing AI output, oldest first so the backlog drains in
     * publication order.
     *
     * @return array<int,int>
     */
    public function missing_ai(int $limit = 5): array
    {
        $query = new WP_Query([
            'post_type'      => PostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => max(1, $limit),
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    ['key' => VideoMeta::AI_SUMMARY, 'compare' => 'NOT EXISTS'],
                    ['key' => VideoMeta::AI_SUMMARY, 'value' => '', 'compare' => '='],
                ],
                [
                    'relation' => 'OR',
                    ['key' => VideoMeta::AI_STATUS, 'compare' => 'NOT EXISTS'],
                    ['key' => VideoMeta::AI_STATUS, 'value' => 'failed', 'compare' => '!='],
                ],
            ],
        ]);

        return array_map('intval', $query->posts);
    }

    public function count(string $status = 'publish'): int
    {
        $counts = wp_count_posts(PostType::POST_TYPE);
        return (int) ($counts->{$status} ?? 0);
    }

    /**
     * Number of published videos that already carry an AI summary.
     */
    public function count_with_ai(): int
    {
        $query = new WP_Query([
            'post_type'      => PostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'     => VideoMeta::AI_SUMMARY,
                'value'   => '',
                'compare' => '!=',
            ]],
        ]);

        return (int) $query->found_posts;
    }
}
