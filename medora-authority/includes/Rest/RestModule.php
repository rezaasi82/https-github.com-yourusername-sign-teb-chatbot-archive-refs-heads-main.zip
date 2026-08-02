<?php

declare(strict_types=1);

namespace Medora\Authority\Rest;

use Medora\Authority\Core\Container;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Rest\Controllers\CitationController;
use Medora\Authority\Rest\Controllers\InsightController;
use Medora\Authority\Rest\Controllers\KnowledgeController;
use Medora\Authority\Rest\Controllers\OverviewController;
use Medora\Authority\Rest\Controllers\ScoreController;
use Medora\Authority\Rest\Controllers\SettingsController;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI API — the REST surface for the dashboard and for external consumers.
 */
final class RestModule extends AbstractModule
{
    /** Requests per minute per IP against the public knowledge endpoints. */
    private const PUBLIC_RATE_LIMIT = 120;

    public function id(): string
    {
        return 'rest';
    }

    public function title(): string
    {
        return __('AI API', 'medora-authority');
    }

    public function description(): string
    {
        return __('REST endpoints for the dashboard, and a public knowledge API for AI systems.', 'medora-authority');
    }

    public function boot(Container $container): void
    {
        add_action('rest_api_init', function () use ($container): void {
            foreach ($this->controllers($container) as $controller) {
                $controller->registerRoutes();
            }
        });

        add_filter('rest_pre_dispatch', [$this, 'rateLimit'], 10, 3);
    }

    /**
     * @return list<AbstractController>
     */
    private function controllers(Container $container): array
    {
        /**
         * Register additional REST controllers.
         *
         * @param list<AbstractController> $controllers
         * @param Container                $container
         */
        return (array) apply_filters('medora_rest_controllers', [
            new OverviewController($container),
            new ScoreController($container),
            new KnowledgeController($container),
            new SettingsController($container),
            new CitationController($container),
            new InsightController($container),
        ], $container);
    }

    /**
     * Throttle anonymous access to the public knowledge endpoints.
     *
     * These are open on purpose, which also makes them the cheapest way to
     * scrape a site's whole knowledge graph. Logged-in users are exempt — the
     * dashboard legitimately makes many requests.
     */
    public function rateLimit(mixed $result, mixed $server, mixed $request): mixed
    {
        if ($result !== null || ! $request instanceof \WP_REST_Request) {
            return $result;
        }

        if (! str_starts_with((string) $request->get_route(), '/' . AbstractController::NAMESPACE)) {
            return $result;
        }

        if (is_user_logged_in() || $request->get_method() !== 'GET') {
            return $result;
        }

        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '';

        if ($ip === '') {
            return $result;
        }

        $key   = 'medora_rl_' . md5($ip . gmdate('YmdHi'));
        $count = (int) get_transient($key);

        /**
         * Filter the per-minute public API rate limit.
         *
         * @param int $limit
         */
        $limit = (int) apply_filters('medora_public_rate_limit', self::PUBLIC_RATE_LIMIT);

        if ($count >= $limit) {
            return new WP_Error(
                'medora_rate_limited',
                __('Too many requests. Try again in a minute.', 'medora-authority'),
                ['status' => 429]
            );
        }

        // Two minutes so the window covers the clock-minute rollover.
        set_transient($key, $count + 1, 2 * MINUTE_IN_SECONDS);

        return $result;
    }
}
