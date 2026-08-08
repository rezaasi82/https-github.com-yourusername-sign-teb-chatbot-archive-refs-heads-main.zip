<?php

namespace SignTeb\VideoHub\Admin;

use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Db\AnalyticsRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 11 — the read half of analytics: totals, leaderboards and a
 * 14-day play trend.
 */
class AnalyticsPage
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی لازم را ندارید.', 'signteb-video-hub'));
        }

        $repository = new AnalyticsRepository();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only range filter.
        $days = isset($_GET['range']) ? (int) $_GET['range'] : 30;
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $data = [
            'enabled'  => $this->settings->bool('analytics_enabled'),
            'days'     => $days,
            'totals'   => $repository->totals($days),
            'by_plays' => $repository->top_videos($days, 15, 'plays'),
            'by_ctr'   => $repository->top_videos($days, 10, 'ctr'),
            'by_watch' => $repository->top_videos($days, 10, 'watch'),
            'trend'    => $repository->daily_plays(14),
        ];

        require STVH_DIR . 'includes/Admin/views/analytics.php';
    }
}
