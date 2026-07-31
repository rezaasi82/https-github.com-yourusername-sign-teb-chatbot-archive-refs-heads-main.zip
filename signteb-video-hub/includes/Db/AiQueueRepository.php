<?php

namespace SignTeb\VideoHub\Db;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Work queue for AI jobs (summary, internal links, article draft).
 *
 * AI calls are slow and rate-limited, so sync never calls a model inline: it
 * enqueues, and the cron worker drains a few jobs per run.
 */
class AiQueueRepository
{
    public const TASKS       = ['summary', 'links', 'article'];
    public const MAX_ATTEMPTS = 3;

    /**
     * Idempotent — re-queuing a pending job is a no-op, and a previously
     * failed job is reset so the admin's "retry" button works.
     */
    public function enqueue(int $video_id, string $task): bool
    {
        if ($video_id <= 0 || ! in_array($task, self::TASKS, true)) {
            return false;
        }

        global $wpdb;
        $table = Schema::ai_queue_table();
        $now   = current_time('mysql');

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT id, status FROM {$table} WHERE video_id = %d AND task = %s", $video_id, $task), // phpcs:ignore WordPress.DB.PreparedSQL
            ARRAY_A
        );

        if ($existing === null) {
            return false !== $wpdb->insert(
                $table,
                [
                    'video_id'   => $video_id,
                    'task'       => $task,
                    'status'     => 'pending',
                    'attempts'   => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s', '%d', '%s', '%s']
            );
        }

        if (($existing['status'] ?? '') === 'pending') {
            return true;
        }

        return false !== $wpdb->update(
            $table,
            ['status' => 'pending', 'attempts' => 0, 'message' => null, 'updated_at' => $now],
            ['id' => (int) $existing['id']],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Claim the next batch of pending jobs, marking them running so a second
     * overlapping cron run cannot pick up the same work.
     *
     * @return array<int,array{id:int,video_id:int,task:string,attempts:int}>
     */
    public function claim(int $limit = 3): array
    {
        global $wpdb;
        $table = Schema::ai_queue_table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, video_id, task, attempts FROM {$table}
                 WHERE status = 'pending' AND attempts < %d
                 ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
                self::MAX_ATTEMPTS,
                max(1, $limit)
            ),
            ARRAY_A
        );

        $claimed = [];
        foreach ((array) $rows as $row) {
            $id      = (int) $row['id'];
            $updated = $wpdb->update(
                $table,
                ['status' => 'running', 'attempts' => (int) $row['attempts'] + 1, 'updated_at' => current_time('mysql')],
                ['id' => $id, 'status' => 'pending'],
                ['%s', '%d', '%s'],
                ['%d', '%s']
            );
            if ($updated) {
                $claimed[] = [
                    'id'       => $id,
                    'video_id' => (int) $row['video_id'],
                    'task'     => (string) $row['task'],
                    'attempts' => (int) $row['attempts'] + 1,
                ];
            }
        }

        return $claimed;
    }

    /**
     * Hand a claimed job back without counting it as an attempt.
     *
     * A worker that stops on its time budget has claimed jobs it never ran;
     * leaving them 'running' would strand them until the stale sweep, half an
     * hour later.
     */
    public function release(int $id): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . Schema::ai_queue_table() . '
                 SET status = %s, attempts = GREATEST(attempts - 1, 0), updated_at = %s
                 WHERE id = %d AND status = %s',
                'pending',
                current_time('mysql'),
                $id,
                'running'
            )
        );
    }

    public function complete(int $id): void
    {
        $this->finish($id, 'done', '');
    }

    /**
     * A failed job goes back to pending until it exhausts its attempts, so a
     * transient API error self-heals on the next cron tick.
     */
    public function fail(int $id, string $message, int $attempts): void
    {
        $status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
        $this->finish($id, $status, $message);
    }

    private function finish(int $id, string $status, string $message): void
    {
        global $wpdb;

        $wpdb->update(
            Schema::ai_queue_table(),
            [
                'status'     => $status,
                'message'    => mb_substr($message, 0, 500),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * @return array{pending:int,running:int,done:int,failed:int}
     */
    public function counts(): array
    {
        global $wpdb;
        $table = Schema::ai_queue_table();

        $rows   = $wpdb->get_results("SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL
        $counts = ['pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0];

        foreach ((array) $rows as $row) {
            $status = (string) $row['status'];
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row['c'];
            }
        }

        return $counts;
    }

    /**
     * Jobs stuck in `running` (a fatal error mid-request) are recovered here.
     */
    public function requeue_stale(int $older_than_minutes = 30): int
    {
        global $wpdb;
        $table  = Schema::ai_queue_table();
        $cutoff = gmdate('Y-m-d H:i:s', (int) current_time('timestamp') - ($older_than_minutes * MINUTE_IN_SECONDS));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'pending', updated_at = %s
                 WHERE status = 'running' AND updated_at < %s AND attempts < %d", // phpcs:ignore WordPress.DB.PreparedSQL
                current_time('mysql'),
                $cutoff,
                self::MAX_ATTEMPTS
            )
        );
    }

    public function delete_for_video(int $video_id): void
    {
        global $wpdb;
        $wpdb->delete(Schema::ai_queue_table(), ['video_id' => $video_id], ['%d']);
    }
}
