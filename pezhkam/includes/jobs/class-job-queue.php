<?php
/**
 * Lightweight background job queue.
 *
 * Heavy bulk work (generating many PDFs, syncing many leads) is enqueued and
 * drained in small batches on the pzk_process_jobs cron, so an admin request
 * never blocks or times out at scale. Each firing processes a chunk and
 * reschedules itself while jobs remain.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Jobs;

if (! defined('ABSPATH')) {
    exit;
}

class JobQueue
{
    public const CRON  = 'pzk_process_jobs';
    private const BATCH = 10;
    private const MAX_ATTEMPTS = 3;

    public function register(): void
    {
        add_action(self::CRON, [$this, 'process']);
    }

    /**
     * Add a job. Returns the job id.
     */
    public function enqueue(string $type, array $payload): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(
            \Pezhkam\Database\Schema::jobs_table(),
            [
                'type'       => substr($type, 0, 32),
                'payload'    => wp_json_encode($payload),
                'status'     => 'queued',
                'attempts'   => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
        return (int) $wpdb->insert_id;
    }

    /**
     * Ensure the drain cron is scheduled soon.
     */
    public function schedule_soon(): void
    {
        if (! wp_next_scheduled(self::CRON)) {
            wp_schedule_single_event(time() + 5, self::CRON);
        }
    }

    public function pending_count(): int
    {
        global $wpdb;
        $table = \Pezhkam\Database\Schema::jobs_table();

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'queued'");
    }

    /**
     * Process one batch, then reschedule if work remains.
     */
    public function process(): void
    {
        global $wpdb;
        $table = \Pezhkam\Database\Schema::jobs_table();

        $jobs = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE status = 'queued' ORDER BY id ASC LIMIT %d", self::BATCH)
        ) ?: [];

        foreach ($jobs as $job) {
            $wpdb->update($table, ['status' => 'processing', 'updated_at' => current_time('mysql')], ['id' => $job->id], ['%s', '%s'], ['%d']);
            $ok = false;
            try {
                $ok = $this->handle($job->type, json_decode((string) $job->payload, true) ?: []);
            } catch (\Throwable $e) {
                $ok = false;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Pezhkam] job #' . $job->id . ' error: ' . $e->getMessage());
                }
            }

            $attempts = (int) $job->attempts + 1;
            if ($ok) {
                $wpdb->update($table, ['status' => 'done', 'attempts' => $attempts, 'updated_at' => current_time('mysql')], ['id' => $job->id], ['%s', '%d', '%s'], ['%d']);
            } elseif ($attempts < self::MAX_ATTEMPTS) {
                $wpdb->update($table, ['status' => 'queued', 'attempts' => $attempts, 'updated_at' => current_time('mysql')], ['id' => $job->id], ['%s', '%d', '%s'], ['%d']);
            } else {
                $wpdb->update($table, ['status' => 'failed', 'attempts' => $attempts, 'updated_at' => current_time('mysql')], ['id' => $job->id], ['%s', '%d', '%s'], ['%d']);
            }
        }

        // Housekeeping: trim old finished jobs.

        $wpdb->query("DELETE FROM {$table} WHERE status IN ('done','failed') AND updated_at < UTC_TIMESTAMP() - INTERVAL 7 DAY");

        if ($this->pending_count() > 0) {
            wp_schedule_single_event(time() + 15, self::CRON);
        }
    }

    /**
     * Dispatch a single job to the right handler.
     */
    private function handle(string $type, array $payload): bool
    {
        if ($type !== 'export') {
            return true; // unknown types are treated as no-ops (drained safely)
        }
        $lead_id = absint($payload['lead_id'] ?? 0);
        $op      = sanitize_key($payload['op'] ?? '');
        if ($lead_id <= 0) {
            return false;
        }

        $manager = new \Pezhkam\Export\ExportManager();
        switch ($op) {
            case 'pdf':
                return ! empty($manager->export_pdf($lead_id)['ok']);
            case 'webhook':
            case 'resend':
                return ! empty($manager->export_webhook($lead_id, 'manual')['ok']);
            case 'gsheet':
                return ! empty($manager->export_google_sheet($lead_id)['ok']);
            default:
                return false;
        }
    }
}
