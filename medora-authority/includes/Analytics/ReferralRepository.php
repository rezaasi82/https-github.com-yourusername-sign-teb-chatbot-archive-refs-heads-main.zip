<?php

declare(strict_types=1);

namespace Medora\Authority\Analytics;

use Medora\Authority\Core\Tables;

if (! defined('ABSPATH')) {
    exit;
}

final class ReferralRepository
{
    public function record(string $slug, string $referrerHost, string $landingUri, int $objectId, string $visitorHash): void
    {
        global $wpdb;

        $wpdb->insert(
            Tables::name(Tables::REFERRALS),
            [
                'source_slug'   => substr($slug, 0, 64),
                'referrer_host' => substr($referrerHost, 0, 191),
                'landing_uri'   => substr($landingUri, 0, 255),
                'object_id'     => $objectId,
                'visitor_hash'  => substr($visitorHash, 0, 64),
                'occurred_at'   => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
    }

    /**
     * @return list<array{source_slug: string, label: string, visits: int, visitors: int}>
     */
    public function totalsBySource(int $days = 30): array
    {
        global $wpdb;

        $table = Tables::name(Tables::REFERRALS);
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source_slug, COUNT(*) AS visits, COUNT(DISTINCT visitor_hash) AS visitors
                 FROM {$table}
                 WHERE occurred_at >= %s
                 GROUP BY source_slug
                 ORDER BY visits DESC",
                $since
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'source_slug' => (string) $row['source_slug'],
            'label'       => ReferralDetector::label((string) $row['source_slug']),
            'visits'      => (int) $row['visits'],
            'visitors'    => (int) $row['visitors'],
        ], $rows);
    }

    /**
     * @return list<array{day: string, source_slug: string, visits: int}>
     */
    public function dailySeries(int $days = 30): array
    {
        global $wpdb;

        $table = Tables::name(Tables::REFERRALS);
        $since = gmdate('Y-m-d 00:00:00', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(occurred_at) AS day, source_slug, COUNT(*) AS visits
                 FROM {$table}
                 WHERE occurred_at >= %s
                 GROUP BY day, source_slug
                 ORDER BY day ASC",
                $since
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'day'         => (string) $row['day'],
            'source_slug' => (string) $row['source_slug'],
            'visits'      => (int) $row['visits'],
        ], $rows);
    }

    /**
     * The pages assistants actually send people to — the closest available
     * proxy for "which of our pages are being cited".
     *
     * @return list<array{object_id: int, title: string, url: string, visits: int}>
     */
    public function topLandingPages(int $days = 30, int $limit = 15): array
    {
        global $wpdb;

        $table = Tables::name(Tables::REFERRALS);
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, landing_uri, COUNT(*) AS visits
                 FROM {$table}
                 WHERE occurred_at >= %s
                 GROUP BY object_id, landing_uri
                 ORDER BY visits DESC
                 LIMIT %d",
                $since,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        $results = [];

        foreach ($rows as $row) {
            $objectId = (int) $row['object_id'];
            $post     = $objectId > 0 ? get_post($objectId) : null;

            $results[] = [
                'object_id' => $objectId,
                'title'     => $post !== null ? get_the_title($post) : (string) $row['landing_uri'],
                'url'       => $post !== null ? (string) get_permalink($post) : home_url((string) $row['landing_uri']),
                'visits'    => (int) $row['visits'],
            ];
        }

        return $results;
    }

    public function countSince(int $days = 30): int
    {
        global $wpdb;

        $table = Tables::name(Tables::REFERRALS);
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE occurred_at >= %s", $since)
        );
    }

    public function prune(int $retentionDays): int
    {
        global $wpdb;

        $table  = Tables::name(Tables::REFERRALS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE occurred_at < %s", $cutoff));
    }
}
