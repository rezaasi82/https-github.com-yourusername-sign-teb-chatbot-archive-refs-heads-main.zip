<?php
/**
 * Daily analytics rollup worker.
 *
 * Runs on the clx_daily_rollup cron and writes one aggregated row per
 * (day, metric) into clx_analytics, so long-range dashboards read a few
 * indexed rows instead of scanning the raw tables.
 *
 * @package Clinovix
 */

namespace Clinovix\Jobs;

if (! defined('ABSPATH')) {
    exit;
}

class Rollup
{
    public const CRON = 'clx_daily_rollup';

    public function register(): void
    {
        add_action(self::CRON, [$this, 'run']);
    }

    /**
     * Roll up yesterday (final) and today (partial) so the current day is live.
     */
    public function run(): void
    {
        try {
            $repo = new \Clinovix\Database\AnalyticsRepository();
            foreach ([gmdate('Y-m-d', time() - DAY_IN_SECONDS), gmdate('Y-m-d')] as $day) {
                $repo->record_day($day, $this->day_metrics($day));
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Clinovix] rollup failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @return array<string,int>
     */
    private function day_metrics(string $day): array
    {
        global $wpdb;
        $conv   = \Clinovix\Database\Schema::conversations_table();
        $events = \Clinovix\Database\Schema::events_table();
        $start  = $day . ' 00:00:00';
        $end    = $day . ' 23:59:59';

        $conv_where = static function (string $extra) use ($wpdb, $conv, $start, $end) {

            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$conv} WHERE created_at BETWEEN %s AND %s {$extra}", $start, $end)
            );
        };
        $event_count = static function (string $type) use ($wpdb, $events, $start, $end) {
            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE type = %s AND created_at BETWEEN %s AND %s", $type, $start, $end)
            );
        };

        return [
            'conversations'  => $conv_where(''),
            'leads'          => $conv_where('AND is_lead = 1'),
            'hot'            => $conv_where("AND lead_score = 'hot'"),
            'click_booking'  => $event_count('booking'),
            'click_whatsapp' => $event_count('whatsapp'),
            'click_call'     => $event_count('call'),
            'click_bale'     => $event_count('bale'),
        ];
    }
}
