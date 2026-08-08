<?php

namespace SignTeb\VideoHub\Rest\Controllers;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Db\AnalyticsRepository;
use SignTeb\VideoHub\Rest\RestNamespace;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 11 — the write half of analytics.
 *
 * No IP or user id is stored: the session identifier is a rotating hash the
 * browser generates, and only the hash of that reaches the database.
 */
class AnalyticsController
{
    private Settings $settings;
    private AnalyticsRepository $analytics;

    public function __construct(?Settings $settings = null)
    {
        $this->settings  = $settings ?? new Settings();
        $this->analytics = new AnalyticsRepository();
    }

    public function register_routes(): void
    {
        register_rest_route(RestNamespace::NAME, '/track', [
            'methods'             => 'POST',
            'callback'            => [$this, 'track'],
            'permission_callback' => '__return_true',
            'args'                => [
                'video_id' => ['type' => 'integer', 'required' => true],
                'event'    => ['type' => 'string', 'required' => true],
                'seconds'  => ['type' => 'integer', 'default' => 0],
                'session'  => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    public function track(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->settings->bool('analytics_enabled')) {
            return new WP_REST_Response(['ok' => false, 'code' => 'disabled'], 200);
        }

        $video_id = (int) $request->get_param('video_id');
        $event    = sanitize_key((string) $request->get_param('event'));

        if (get_post_type($video_id) !== PostType::POST_TYPE) {
            return new WP_REST_Response(['ok' => false, 'code' => 'not_found'], 404);
        }
        if (! in_array($event, AnalyticsRepository::EVENTS, true)) {
            return new WP_REST_Response(['ok' => false, 'code' => 'bad_event'], 400);
        }
        if ($this->is_throttled($video_id, $event)) {
            return new WP_REST_Response(['ok' => true, 'throttled' => true], 200);
        }

        $recorded = $this->analytics->record(
            $video_id,
            $event,
            (int) $request->get_param('seconds'),
            sanitize_text_field((string) $request->get_param('session')),
            (string) $request->get_header('referer')
        );

        return new WP_REST_Response(['ok' => $recorded], $recorded ? 200 : 500);
    }

    /**
     * Cheap per-IP flood guard so a scripted client cannot inflate the
     * numbers. Heartbeats are exempt — they are supposed to be frequent.
     */
    private function is_throttled(int $video_id, string $event): bool
    {
        if ($event === 'heartbeat') {
            return false;
        }

        $ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $key = 'stvh_t_' . md5($ip . '|' . $video_id . '|' . $event);

        if (get_transient($key) !== false) {
            return true;
        }

        set_transient($key, 1, 30);

        return false;
    }
}
