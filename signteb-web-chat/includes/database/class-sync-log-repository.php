<?php
/**
 * Records export / integration attempts and results.
 *
 * One row per attempt to send a lead to an external target (webhook, Google
 * Sheets) or to generate a PDF. Powers the sync-status badges and the retry
 * of failed jobs.
 *
 * @package Medora
 */

namespace Medora\Database;

if (! defined('ABSPATH')) {
    exit;
}

class SyncLogRepository
{
    public const PROVIDERS = ['webhook', 'google_sheets', 'pdf'];
    public const STATUSES  = ['pending', 'queued', 'success', 'failed'];

    /**
     * Insert a log row and return its id.
     */
    public function add(int $lead_id, string $provider, string $event, string $status, array $extra = []): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(
            \Medora\Database\Schema::sync_logs_table(),
            [
                'lead_id'     => $lead_id,
                'provider'    => $provider,
                'event'       => $event,
                'status'      => in_array($status, self::STATUSES, true) ? $status : 'pending',
                'attempts'    => (int) ($extra['attempts'] ?? 0),
                'response'    => isset($extra['response']) ? mb_substr((string) $extra['response'], 0, 5000) : null,
                'duration_ms' => isset($extra['duration_ms']) ? (int) $extra['duration_ms'] : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s']
        );
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, string $status, array $extra = []): void
    {
        global $wpdb;
        $data    = ['status' => $status, 'updated_at' => current_time('mysql')];
        $formats = ['%s', '%s'];
        if (isset($extra['attempts'])) {
            $data['attempts'] = (int) $extra['attempts'];
            $formats[]        = '%d';
        }
        if (isset($extra['response'])) {
            $data['response'] = mb_substr((string) $extra['response'], 0, 5000);
            $formats[]        = '%s';
        }
        if (isset($extra['duration_ms'])) {
            $data['duration_ms'] = (int) $extra['duration_ms'];
            $formats[]           = '%d';
        }
        $wpdb->update(\Medora\Database\Schema::sync_logs_table(), $data, ['id' => $id], $formats, ['%d']);
    }

    /**
     * Latest status per provider for a lead (for the admin badges).
     *
     * @return array<string,object> provider => row
     */
    public function latest_for_lead(int $lead_id): array
    {
        global $wpdb;
        $table = \Medora\Database\Schema::sync_logs_table();
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.* FROM {$table} s
                 INNER JOIN (
                     SELECT provider, MAX(id) AS max_id FROM {$table}
                     WHERE lead_id = %d GROUP BY provider
                 ) m ON m.max_id = s.id",
                $lead_id
            )
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[$row->provider] = $row;
        }
        return $out;
    }

    /**
     * Latest status per provider for MANY leads in a single query (avoids the
     * N+1 pattern when rendering the leads list).
     *
     * @param array<int,int> $lead_ids
     * @return array<int,array<string,string>> lead_id => [provider => status]
     */
    public function latest_for_leads(array $lead_ids): array
    {
        $lead_ids = array_values(array_unique(array_map('intval', $lead_ids)));
        if ($lead_ids === []) {
            return [];
        }
        global $wpdb;
        $table        = \Medora\Database\Schema::sync_logs_table();
        $placeholders = implode(',', array_fill(0, count($lead_ids), '%d'));


        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.lead_id, s.provider, s.status FROM {$table} s
                 INNER JOIN (
                     SELECT lead_id, provider, MAX(id) AS max_id FROM {$table}
                     WHERE lead_id IN ({$placeholders}) GROUP BY lead_id, provider
                 ) m ON m.max_id = s.id",
                ...$lead_ids
            )
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->lead_id][(string) $row->provider] = (string) $row->status;
        }
        return $out;
    }

    public function has_success(int $lead_id, string $provider): bool
    {
        global $wpdb;
        $table = \Medora\Database\Schema::sync_logs_table();
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE lead_id = %d AND provider = %s AND status = 'success' LIMIT 1",
                $lead_id,
                $provider
            )
        );
    }

    /** @return array<int,object> */
    public function failed(string $provider, int $limit = 50): array
    {
        global $wpdb;
        $table = \Medora\Database\Schema::sync_logs_table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE provider = %s AND status = 'failed' ORDER BY id DESC LIMIT %d",
                $provider,
                $limit
            )
        ) ?: [];
    }

    /** @return array<int,object> */
    public function recent(int $limit = 100): array
    {
        global $wpdb;
        $table = \Medora\Database\Schema::sync_logs_table();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit)
        ) ?: [];
    }
}
