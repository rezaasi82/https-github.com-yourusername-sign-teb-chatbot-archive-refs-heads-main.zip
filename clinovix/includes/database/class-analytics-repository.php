<?php
/**
 * Pre-aggregated daily metrics (rollup).
 *
 * Instead of scanning conversations/messages for every dashboard view, a daily
 * cron writes one row per (day, metric). Long-range reads become an indexed
 * sum over a handful of rows — the foundation for 10k+ leads / 100k+ messages.
 *
 * @package Clinovix
 */

namespace Clinovix\Database;

if (! defined('ABSPATH')) {
    exit;
}

class AnalyticsRepository
{
    /**
     * Upsert a set of metrics for a single day.
     *
     * @param array<string,int> $metrics
     */
    public function record_day(string $day, array $metrics): void
    {
        global $wpdb;
        $table = \Clinovix\Database\Schema::analytics_table();
        foreach ($metrics as $metric => $value) {
            $metric = substr((string) $metric, 0, 32);

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (day, metric, value) VALUES (%s, %s, %d)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    $day,
                    $metric,
                    (int) $value
                )
            );
        }
    }

    /**
     * Sum of a metric over the last N days.
     */
    public function sum(string $metric, int $days): int
    {
        global $wpdb;
        $table = \Clinovix\Database\Schema::analytics_table();
        $since = gmdate('Y-m-d', time() - (($days - 1) * DAY_IN_SECONDS));
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(value),0) FROM {$table} WHERE metric = %s AND day >= %s",
                $metric,
                $since
            )
        );
    }

    /**
     * Daily series for a metric (oldest-first), filling gaps with zero.
     *
     * @return array<string,int> Y-m-d => value
     */
    public function series(string $metric, int $days): array
    {
        global $wpdb;
        $table = \Clinovix\Database\Schema::analytics_table();
        $since = gmdate('Y-m-d', time() - (($days - 1) * DAY_IN_SECONDS));
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT day, value FROM {$table} WHERE metric = %s AND day >= %s",
                $metric,
                $since
            )
        ) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[$row->day] = (int) $row->value;
        }
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d       = gmdate('Y-m-d', time() - ($i * DAY_IN_SECONDS));
            $out[$d] = $map[$d] ?? 0;
        }
        return $out;
    }
}
