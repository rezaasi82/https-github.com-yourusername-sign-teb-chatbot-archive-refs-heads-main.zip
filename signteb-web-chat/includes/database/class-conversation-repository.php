<?php
/**
 * SWC_Conversation_Repository — repository for chat conversations.
 *
 * All SQL is prepared and centralized here (Repository pattern, not Active
 * Record) so query logic lives in one auditable place.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Conversation_Repository
{
    /**
     * Find an open conversation by session id, or create one.
     *
     * @return int conversation id
     */
    public function find_or_create(string $session_id, array $meta = []): int
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE session_id = %s AND status = 'open' ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        if ($existing) {
            return (int) $existing;
        }

        $now = current_time('mysql');
        $wpdb->insert(
            $table,
            [
                'session_id'    => $session_id,
                'visitor_ip'    => $meta['ip'] ?? null,
                'user_id'       => $meta['user_id'] ?? null,
                'language'      => $meta['language'] ?? 'fa',
                'page_url'      => $meta['page_url'] ?? null,
                'status'        => 'open',
                'message_count' => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function touch(int $conversation_id): void
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET message_count = message_count + 1, updated_at = %s WHERE id = %d",
                current_time('mysql'),
                $conversation_id
            )
        );
    }

    public function mark_lead(int $conversation_id, string $cta_type): void
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $wpdb->update(
            $table,
            ['is_lead' => 1, 'cta_type' => $cta_type, 'updated_at' => current_time('mysql')],
            ['id' => $conversation_id],
            ['%d', '%s', '%s'],
            ['%d']
        );
    }

    /** @return array<int,object> */
    public function paginate(int $page = 1, int $per_page = 20, array $filters = []): array
    {
        global $wpdb;
        $table  = SWC_Schema::conversations_table();
        $offset = max(0, ($page - 1) * $per_page);
        $where  = empty($filters['leads_only']) ? '1=1' : 'is_lead = 1';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        ) ?: [];
    }

    public function count(array $filters = []): int
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $where = empty($filters['leads_only']) ? '1=1' : 'is_lead = 1';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    }

    public function get(int $conversation_id): ?object
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $conversation_id)
        );
        return $row ?: null;
    }

    /**
     * Aggregate stats for the dashboard (ROI proof).
     *
     * @return array{conversations:int,leads:int,conversion_rate:float}
     */
    public function stats(int $days = 30): array
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $since)
        );
        $leads = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND is_lead = 1", $since)
        );

        return [
            'conversations'   => $total,
            'leads'           => $leads,
            'conversion_rate' => $total > 0 ? round(($leads / $total) * 100, 1) : 0.0,
        ];
    }
}
