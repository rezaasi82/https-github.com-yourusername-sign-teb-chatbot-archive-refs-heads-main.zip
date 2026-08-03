<?php

declare(strict_types=1);

namespace Medora\Authority\Crawler;

use Medora\Authority\Core\Tables;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Persistence for AI crawler hits.
 *
 * Every query in this class is a prepared statement. Table names cannot be
 * bound as parameters, so they are always produced by `Tables::name()` from a
 * class constant — never from request input.
 */
final class CrawlLogRepository
{
    /**
     * @param array{
     *     crawler_slug: string,
     *     vendor?: string,
     *     request_uri?: string,
     *     object_id?: int,
     *     status_code?: int,
     *     decision?: string,
     *     ip_hash?: string
     * } $hit
     */
    public function record(array $hit): void
    {
        global $wpdb;

        $wpdb->insert(
            Tables::name(Tables::CRAWLER_HITS),
            [
                'crawler_slug' => substr($hit['crawler_slug'], 0, 64),
                'vendor'       => substr($hit['vendor'] ?? '', 0, 64),
                'request_uri'  => substr($hit['request_uri'] ?? '', 0, 255),
                'object_id'    => (int) ($hit['object_id'] ?? 0),
                'status_code'  => (int) ($hit['status_code'] ?? 200),
                'decision'     => substr($hit['decision'] ?? CrawlerPolicy::ALLOW, 0, 16),
                'ip_hash'      => substr($hit['ip_hash'] ?? '', 0, 64),
                'hit_at'       => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Hit counts per crawler over a window.
     *
     * @return list<array{crawler_slug: string, vendor: string, hits: int, last_seen: string}>
     */
    public function totalsByCrawler(int $days = 30): array
    {
        global $wpdb;

        $table = Tables::name(Tables::CRAWLER_HITS);
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT crawler_slug, vendor, COUNT(*) AS hits, MAX(hit_at) AS last_seen
                 FROM {$table}
                 WHERE hit_at >= %s
                 GROUP BY crawler_slug, vendor
                 ORDER BY hits DESC",
                $since
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'crawler_slug' => (string) $row['crawler_slug'],
            'vendor'       => (string) $row['vendor'],
            'hits'         => (int) $row['hits'],
            'last_seen'    => (string) $row['last_seen'],
        ], $rows);
    }

    /**
     * Daily hit counts, suitable for a stacked area chart.
     *
     * @return list<array{day: string, crawler_slug: string, hits: int}>
     */
    public function dailySeries(int $days = 30): array
    {
        global $wpdb;

        $table = Tables::name(Tables::CRAWLER_HITS);
        $since = gmdate('Y-m-d 00:00:00', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(hit_at) AS day, crawler_slug, COUNT(*) AS hits
                 FROM {$table}
                 WHERE hit_at >= %s
                 GROUP BY day, crawler_slug
                 ORDER BY day ASC",
                $since
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'day'          => (string) $row['day'],
            'crawler_slug' => (string) $row['crawler_slug'],
            'hits'         => (int) $row['hits'],
        ], $rows);
    }

    /**
     * The URLs AI crawlers request most — the pages most likely to be cited.
     *
     * @return list<array{request_uri: string, object_id: int, hits: int}>
     */
    public function topPaths(int $days = 30, int $limit = 20): array
    {
        global $wpdb;

        $table = Tables::name(Tables::CRAWLER_HITS);
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT request_uri, object_id, COUNT(*) AS hits
                 FROM {$table}
                 WHERE hit_at >= %s
                 GROUP BY request_uri, object_id
                 ORDER BY hits DESC
                 LIMIT %d",
                $since,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'request_uri' => (string) $row['request_uri'],
            'object_id'   => (int) $row['object_id'],
            'hits'        => (int) $row['hits'],
        ], $rows);
    }

    public function countSince(int $days = 30): int
    {
        global $wpdb;

        $table = Tables::name(Tables::CRAWLER_HITS);
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE hit_at >= %s", $since)
        );
    }

    /** Delete rows older than the retention window. */
    public function prune(int $retentionDays): int
    {
        global $wpdb;

        $table  = Tables::name(Tables::CRAWLER_HITS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE hit_at < %s", $cutoff)
        );
    }
}
