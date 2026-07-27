<?php

namespace SignTeb\VideoHub\Rest\Controllers;

use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Db\VideoRepository;
use SignTeb\VideoHub\Front\Renderer;
use SignTeb\VideoHub\Rest\RestNamespace;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Public read endpoint behind the Ajax filter and live search (features 7, 8).
 *
 * It returns rendered card HTML rather than raw JSON records so the filtered
 * grid is byte-identical to the server-rendered one.
 */
class VideoController
{
    private Settings $settings;
    private VideoRepository $videos;
    private Renderer $renderer;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->videos   = new VideoRepository();
        $this->renderer = new Renderer($this->settings);
    }

    public function register_routes(): void
    {
        register_rest_route(RestNamespace::NAME, '/videos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
            'args'                => [
                'topic'    => ['type' => 'string', 'default' => ''],
                'search'   => ['type' => 'string', 'default' => ''],
                'page'     => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 12],
                'orderby'  => ['type' => 'string', 'default' => 'date'],
            ],
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'topic'    => sanitize_title((string) $request->get_param('topic')),
            'search'   => sanitize_text_field((string) $request->get_param('search')),
            'page'     => max(1, (int) $request->get_param('page')),
            'per_page' => max(1, min(48, (int) $request->get_param('per_page'))),
            'orderby'  => sanitize_key((string) $request->get_param('orderby')),
        ];

        // Search results are per-query and short-lived; browse results are
        // stable enough to cache for a few minutes.
        $ttl   = $args['search'] !== '' ? 2 * MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS;
        $cache = new CacheManager($this->settings);

        $payload = $cache->remember('videos_' . md5(wp_json_encode($args)), $ttl, function () use ($args): array {
            $result = $this->videos->query($args);

            return [
                'html'  => $this->renderer->cards($result['ids']),
                'total' => $result['total'],
                'pages' => $result['pages'],
                'page'  => $args['page'],
                'count' => count($result['ids']),
            ];
        });

        return new WP_REST_Response($payload, 200);
    }
}
