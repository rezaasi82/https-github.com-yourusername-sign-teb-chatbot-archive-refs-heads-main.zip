<?php

namespace SignTeb\VideoHub\Db;

use SignTeb\VideoHub\Helpers\Format;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Analytics writes and aggregates (feature 11).
 *
 * Events are stored raw and rolled up on read; the 30-day view count is also
 * mirrored into post meta so "popular" ordering stays a plain meta query.
 */
class AnalyticsRepository
{
    public const EVENTS = ['impression', 'click', 'play', 'heartbeat'];

    public function record(int $video_id, string $event, int $seconds = 0, string $session = '', string $referer = ''): bool
    {
        if ($video_id <= 0 || ! in_array($event, self::EVENTS, true)) {
            return false;
        }

        global $wpdb;

        $inserted = $wpdb->insert(
            Schema::analytics_table(),
            [
                'video_id'     => $video_id,
                'event'        => $event,
                'seconds'      => max(0, min(86400, $seconds)),
                'session_hash' => $session !== '' ? md5($session) : null,
                'referer'      => $referer !== '' ? substr($referer, 0, 255) : null,
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s']
        );

        return $inserted !== false;
    }

    /**
     * Site-wide totals for the analytics screen.
     *
     * @return array{impressions:int,clicks:int,plays:int,watch_seconds:int,ctr:float,play_rate:float,avg_watch:int}
     */
    public function totals(int $days = 30): array
    {
        global $wpdb;
        $table = Schema::analytics_table();
        $since = $this->since($days);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event, COUNT(*) AS c, SUM(seconds) AS s FROM {$table} WHERE created_at >= %s GROUP BY event", // phpcs:ignore WordPress.DB.PreparedSQL
                $since
            ),
            ARRAY_A
        );

        $counts = ['impression' => 0, 'click' => 0, 'play' => 0, 'heartbeat' => 0];
        $watch  = 0;
        foreach ((array) $rows as $row) {
            $event = (string) ($row['event'] ?? '');
            if (isset($counts[$event])) {
                $counts[$event] = (int) $row['c'];
            }
            if ($event === 'heartbeat') {
                $watch = (int) $row['s'];
            }
        }

        return [
            'impressions'   => $counts['impression'],
            'clicks'        => $counts['click'],
            'plays'         => $counts['play'],
            'watch_seconds' => $watch,
            'ctr'           => Format::rate($counts['click'], $counts['impression']),
            'play_rate'     => Format::rate($counts['play'], $counts['click']),
            'avg_watch'     => $counts['play'] > 0 ? (int) round($watch / $counts['play']) : 0,
        ];
    }

    /**
     * Per-video leaderboard.
     *
     * @return array<int,array{video_id:int,impressions:int,clicks:int,plays:int,watch_seconds:int,ctr:float}>
     */
    public function top_videos(int $days = 30, int $limit = 20, string $order_by = 'plays'): array
    {
        global $wpdb;
        $table = Schema::analytics_table();
        $since = $this->since($days);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT video_id,
                        SUM(event = 'impression') AS impressions,
                        SUM(event = 'click') AS clicks,
                        SUM(event = 'play') AS plays,
                        SUM(CASE WHEN event = 'heartbeat' THEN seconds ELSE 0 END) AS watch_seconds
                 FROM {$table}
                 WHERE created_at >= %s
                 GROUP BY video_id
                 ORDER BY plays DESC
                 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
                $since,
                max(1, $limit)
            ),
            ARRAY_A
        );

        $out = [];
        foreach ((array) $rows as $row) {
            $impressions = (int) $row['impressions'];
            $clicks      = (int) $row['clicks'];
            $out[]       = [
                'video_id'      => (int) $row['video_id'],
                'impressions'   => $impressions,
                'clicks'        => $clicks,
                'plays'         => (int) $row['plays'],
                'watch_seconds' => (int) $row['watch_seconds'],
                'ctr'           => Format::rate($clicks, $impressions),
            ];
        }

        if ($order_by === 'ctr') {
            usort($out, static fn(array $a, array $b): int => $b['ctr'] <=> $a['ctr']);
        } elseif ($order_by === 'watch') {
            usort($out, static fn(array $a, array $b): int => $b['watch_seconds'] <=> $a['watch_seconds']);
        }

        return $out;
    }

    /**
     * Daily play counts for the dashboard sparkline.
     *
     * @return array<string,int> date => plays
     */
    public function daily_plays(int $days = 14): array
    {
        global $wpdb;
        $table = Schema::analytics_table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c
                 FROM {$table}
                 WHERE event = 'play' AND created_at >= %s
                 GROUP BY DATE(created_at)
                 ORDER BY d ASC", // phpcs:ignore WordPress.DB.PreparedSQL
                $this->since($days)
            ),
            ARRAY_A
        );

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[gmdate('Y-m-d', strtotime("-{$i} days", (int) current_time('timestamp')))] = 0;
        }
        foreach ((array) $rows as $row) {
            $date = (string) $row['d'];
            if (isset($series[$date])) {
                $series[$date] = (int) $row['c'];
            }
        }

        return $series;
    }

    /**
     * Mirror the trailing-30-day play count into post meta so the "popular"
     * sort does not need a join. Called from the daily maintenance cron.
     */
    public function sync_view_counts(): int
    {
        $top     = $this->top_videos(30, 500);
        $updated = 0;
        foreach ($top as $row) {
            update_post_meta($row['video_id'], '_stvh_views_30d', $row['plays']);
            $updated++;
        }
        return $updated;
    }

    /**
     * Drop events older than the retention window (privacy + table size).
     */
    public function prune(int $retention_days): int
    {
        if ($retention_days <= 0) {
            return 0;
        }

        global $wpdb;
        $table = Schema::analytics_table();

        return (int) $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $this->since($retention_days)) // phpcs:ignore WordPress.DB.PreparedSQL
        );
    }

    private function since(int $days): string
    {
        $timestamp = (int) current_time('timestamp') - (max(1, $days) * DAY_IN_SECONDS);
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
