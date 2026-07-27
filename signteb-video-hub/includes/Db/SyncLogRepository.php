<?php

namespace SignTeb\VideoHub\Db;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Sync history — powers "آخرین همگام‌سازی" on the dashboard.
 */
class SyncLogRepository
{
    /**
     * @param array{source:string,status:string,imported:int,updated:int,skipped:int,duration_ms:int,message?:string} $entry
     */
    public function add(array $entry): void
    {
        global $wpdb;

        $wpdb->insert(
            Schema::sync_log_table(),
            [
                'source'      => substr($entry['source'], 0, 32),
                'status'      => in_array($entry['status'], ['success', 'partial', 'failed'], true) ? $entry['status'] : 'failed',
                'imported'    => max(0, (int) $entry['imported']),
                'updated'     => max(0, (int) $entry['updated']),
                'skipped'     => max(0, (int) $entry['skipped']),
                'duration_ms' => max(0, (int) $entry['duration_ms']),
                'message'     => mb_substr((string) ($entry['message'] ?? ''), 0, 1000),
                'created_at'  => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
        );

        $this->prune();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 10): array
    {
        global $wpdb;
        $table = Schema::sync_log_table();

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max(1, $limit)), // phpcs:ignore WordPress.DB.PreparedSQL
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function last(): ?array
    {
        $rows = $this->recent(1);
        return $rows[0] ?? null;
    }

    /**
     * Keep the table bounded — this is diagnostics, not an audit trail.
     */
    private function prune(int $keep = 200): void
    {
        global $wpdb;
        $table = Schema::sync_log_table();

        $cutoff = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d", $keep) // phpcs:ignore WordPress.DB.PreparedSQL
        );

        if ($cutoff > 0) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id <= %d", $cutoff)); // phpcs:ignore WordPress.DB.PreparedSQL
        }
    }
}
