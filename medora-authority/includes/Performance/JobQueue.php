<?php

declare(strict_types=1);

namespace Medora\Authority\Performance;

use Medora\Authority\Core\Tables;
use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Durable database-backed job queue.
 *
 * WordPress cron is not a scheduler — it only fires when someone visits the
 * site, and `wp_schedule_single_event` silently drops duplicates. Analysis
 * work is therefore held in a real table with explicit status, attempt counts
 * and exponential back-off, and cron is used purely as the tick that drains it.
 */
final class JobQueue
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_FAILED  = 'failed';

    private const MAX_ATTEMPTS = 3;

    /**
     * Enqueue a job.
     *
     * @param class-string<JobInterface> $handler
     * @param array<string, mixed>       $payload
     * @param int                        $delay   Seconds to wait before the job becomes eligible.
     */
    public function push(string $handler, array $payload = [], int $delay = 0, string $queue = 'default'): int
    {
        global $wpdb;

        // Collapse duplicates: re-saving a post five times in a minute should
        // produce one analysis, not five.
        $existing = $this->findPending($handler, $payload);

        if ($existing > 0) {
            return $existing;
        }

        $wpdb->insert(
            Tables::name(Tables::JOBS),
            [
                'queue'        => substr($queue, 0, 64),
                'handler'      => $handler,
                'payload'      => Arr::toJson($payload),
                'status'       => self::STATUS_PENDING,
                'attempts'     => 0,
                'available_at' => gmdate('Y-m-d H:i:s', time() + max(0, $delay)),
                'created_at'   => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Atomically claim up to `$limit` due jobs.
     *
     * Claiming is a two-step compare-and-set on the row id, which is safe
     * against a second worker (or a second cron tick) running concurrently.
     *
     * @return list<array{id: int, handler: string, payload: array<string, mixed>, attempts: int}>
     */
    public function claim(int $limit = 10): array
    {
        global $wpdb;

        $table = Tables::name(Tables::JOBS);
        $now   = gmdate('Y-m-d H:i:s');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, handler, payload, attempts
                 FROM {$table}
                 WHERE status = %s AND available_at <= %s
                 ORDER BY id ASC
                 LIMIT %d",
                self::STATUS_PENDING,
                $now,
                max(1, $limit)
            ),
            ARRAY_A
        ) ?: [];

        $claimed = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
            $updated = (int) $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET status = %s, reserved_at = %s, attempts = attempts + 1
                     WHERE id = %d AND status = %s",
                    self::STATUS_RUNNING,
                    $now,
                    $id,
                    self::STATUS_PENDING
                )
            );

            if ($updated !== 1) {
                // Another worker won the race.
                continue;
            }

            $claimed[] = [
                'id'       => $id,
                'handler'  => (string) $row['handler'],
                'payload'  => Arr::fromJson($row['payload']),
                'attempts' => (int) $row['attempts'] + 1,
            ];
        }

        return $claimed;
    }

    public function complete(int $id): void
    {
        global $wpdb;

        $wpdb->delete(Tables::name(Tables::JOBS), ['id' => $id], ['%d']);
    }

    /**
     * Release a failed job back to the queue with exponential back-off, or
     * mark it permanently failed once attempts are exhausted.
     */
    public function fail(int $id, int $attempts, string $error): void
    {
        global $wpdb;

        $table = Tables::name(Tables::JOBS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $wpdb->update(
                $table,
                [
                    'status'     => self::STATUS_FAILED,
                    'last_error' => mb_substr($error, 0, 1000),
                ],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );

            return;
        }

        // 1 min, 4 min, 9 min…
        $backoff = MINUTE_IN_SECONDS * ($attempts ** 2);

        $wpdb->update(
            $table,
            [
                'status'       => self::STATUS_PENDING,
                'available_at' => gmdate('Y-m-d H:i:s', time() + $backoff),
                'reserved_at'  => null,
                'last_error'   => mb_substr($error, 0, 1000),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Requeue jobs whose worker died mid-run.
     *
     * @return int Number of jobs recovered.
     */
    public function recoverStalled(int $olderThanSeconds = 900): int
    {
        global $wpdb;

        $table  = Tables::name(Tables::JOBS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - $olderThanSeconds);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = %s, reserved_at = NULL
                 WHERE status = %s AND reserved_at < %s",
                self::STATUS_PENDING,
                self::STATUS_RUNNING,
                $cutoff
            )
        );
    }

    /** @return array{pending: int, running: int, failed: int} */
    public function stats(): array
    {
        global $wpdb;

        $table = Tables::name(Tables::JOBS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A) ?: [];

        $stats = ['pending' => 0, 'running' => 0, 'failed' => 0];

        foreach ($rows as $row) {
            $stats[(string) $row['status']] = (int) $row['total'];
        }

        return $stats;
    }

    public function purgeFailed(): int
    {
        global $wpdb;

        return (int) $wpdb->delete(Tables::name(Tables::JOBS), ['status' => self::STATUS_FAILED], ['%s']);
    }

    /** @param array<string, mixed> $payload */
    private function findPending(string $handler, array $payload): int
    {
        global $wpdb;

        $table = Tables::name(Tables::JOBS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE handler = %s AND payload = %s AND status = %s LIMIT 1",
                $handler,
                Arr::toJson($payload),
                self::STATUS_PENDING
            )
        );
    }
}
