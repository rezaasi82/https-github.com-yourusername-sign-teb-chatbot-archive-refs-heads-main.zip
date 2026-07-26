<?php
/**
 * \Medora\Database\EventRepository — records CTA/channel click events for analytics.
 *
 * One row per tracked interaction (booking / whatsapp / call / bale). Powers
 * the professional analytics dashboard (conversion + channel breakdown).
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Database;

if (! defined('ABSPATH')) {
    exit;
}

class EventRepository
{
    /** Allowed event types (whitelist — never trust the client). */
    public const TYPES = ['booking', 'whatsapp', 'call', 'bale'];

    public function record(string $type, ?int $conversation_id = null): bool
    {
        if (! in_array($type, self::TYPES, true)) {
            return false;
        }
        global $wpdb;
        $wpdb->insert(
            \Medora\Database\Schema::events_table(),
            [
                'conversation_id' => $conversation_id ?: null,
                'type'            => $type,
                'created_at'      => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );
        return true;
    }

    /**
     * Click totals per type over the last N days.
     *
     * @return array<string,int>
     */
    public function counts(int $days = 30): array
    {
        global $wpdb;
        $table = \Medora\Database\Schema::events_table();
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT type, COUNT(*) AS c FROM {$table} WHERE created_at >= %s GROUP BY type",
                $since
            )
        ) ?: [];

        $out = array_fill_keys(self::TYPES, 0);
        foreach ($rows as $row) {
            if (isset($out[$row->type])) {
                $out[$row->type] = (int) $row->c;
            }
        }
        return $out;
    }

    /**
     * Daily total click counts for the last N days (for the trend chart).
     *
     * @return array<string,int> date (Y-m-d) => count
     */
    public function daily(int $days = 14): array
    {
        global $wpdb;
        $table = \Medora\Database\Schema::events_table();
        $since = gmdate('Y-m-d 00:00:00', time() - (($days - 1) * DAY_IN_SECONDS));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table}
                 WHERE created_at >= %s GROUP BY DATE(created_at)",
                $since
            )
        ) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[$row->d] = (int) $row->c;
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day       = gmdate('Y-m-d', time() - ($i * DAY_IN_SECONDS));
            $out[$day] = $map[$day] ?? 0;
        }
        return $out;
    }
}
