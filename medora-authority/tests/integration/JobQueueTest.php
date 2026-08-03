<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Integration;

use Medora\Authority\Core\Container;
use Medora\Authority\Performance\JobInterface;
use Medora\Authority\Performance\JobQueue;
use Medora\Authority\Performance\QueueWorker;
use RuntimeException;

/**
 * A queue that loses jobs, runs them twice, or wedges rows in `running`
 * forever fails silently — analysis just quietly stops being current. These
 * tests pin the claiming and retry semantics against the real table.
 */
final class JobQueueTest extends MedoraTestCase
{
    private JobQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = $this->container->get(JobQueue::class);

        RecordingJob::$handled = [];
        FailingJob::$attempts  = 0;
    }

    public function test_push_and_claim_round_trip(): void
    {
        $this->queue->push(RecordingJob::class, ['id' => 7]);

        $claimed = $this->queue->claim();

        $this->assertCount(1, $claimed);
        $this->assertSame(RecordingJob::class, $claimed[0]['handler']);
        $this->assertSame(['id' => 7], $claimed[0]['payload']);
        $this->assertSame(1, $claimed[0]['attempts']);
    }

    public function test_identical_pending_jobs_are_collapsed(): void
    {
        // Re-saving a post five times in a minute must produce one analysis.
        $first = $this->queue->push(RecordingJob::class, ['post_id' => 1]);

        foreach (range(1, 4) as $ignored) {
            $this->assertSame($first, $this->queue->push(RecordingJob::class, ['post_id' => 1]));
        }

        $this->assertSame(1, $this->queue->stats()['pending']);
    }

    public function test_different_payloads_are_not_collapsed(): void
    {
        $this->queue->push(RecordingJob::class, ['post_id' => 1]);
        $this->queue->push(RecordingJob::class, ['post_id' => 2]);

        $this->assertSame(2, $this->queue->stats()['pending']);
    }

    public function test_claiming_marks_jobs_running_so_a_second_worker_cannot_take_them(): void
    {
        $this->queue->push(RecordingJob::class, ['post_id' => 1]);

        $first  = $this->queue->claim();
        $second = $this->queue->claim();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second, 'A claimed job must not be handed out twice.');
        $this->assertSame(1, $this->queue->stats()['running']);
    }

    public function test_delayed_jobs_are_not_claimed_early(): void
    {
        $this->queue->push(RecordingJob::class, ['post_id' => 1], HOUR_IN_SECONDS);

        $this->assertCount(0, $this->queue->claim());
        $this->assertSame(1, $this->queue->stats()['pending']);
    }

    public function test_failure_returns_the_job_to_the_queue_with_backoff(): void
    {
        $this->queue->push(RecordingJob::class, ['post_id' => 1]);
        $claimed = $this->queue->claim()[0];

        $this->queue->fail($claimed['id'], $claimed['attempts'], 'boom');

        $this->assertSame(1, $this->queue->stats()['pending']);
        // Back-off pushes availability into the future, so it is not
        // immediately re-claimable.
        $this->assertCount(0, $this->queue->claim());
    }

    public function test_exhausted_retries_mark_the_job_failed(): void
    {
        $this->queue->push(RecordingJob::class, ['post_id' => 1]);
        $claimed = $this->queue->claim()[0];

        $this->queue->fail($claimed['id'], 3, 'boom');

        $stats = $this->queue->stats();
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(0, $stats['pending']);
    }

    public function test_stalled_jobs_are_recovered(): void
    {
        global $wpdb;

        $this->queue->push(RecordingJob::class, ['post_id' => 1]);
        $claimed = $this->queue->claim()[0];

        // Simulate a worker that died mid-run.
        $wpdb->update(
            \Medora\Authority\Core\Tables::name(\Medora\Authority\Core\Tables::JOBS),
            ['reserved_at' => gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS)],
            ['id' => $claimed['id']]
        );

        $this->assertSame(1, $this->queue->recoverStalled(900));
        $this->assertSame(1, $this->queue->stats()['pending']);
    }

    public function test_worker_executes_and_completes_jobs(): void
    {
        $this->queue->push(RecordingJob::class, ['post_id' => 42]);
        $this->queue->push(RecordingJob::class, ['post_id' => 43]);

        $result = $this->container->get(QueueWorker::class)->run();

        $this->assertSame(2, $result['processed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([42, 43], RecordingJob::$handled);

        // Completed jobs leave the table entirely.
        $this->assertSame(['pending' => 0, 'running' => 0, 'failed' => 0], $this->queue->stats());
    }

    public function test_worker_records_a_throwing_job_as_failed_without_stopping(): void
    {
        $this->queue->push(FailingJob::class, ['n' => 1]);
        $this->queue->push(RecordingJob::class, ['post_id' => 9]);

        $result = $this->container->get(QueueWorker::class)->run();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['processed'], 'One bad job must not abort the batch.');
        $this->assertSame([9], RecordingJob::$handled);
    }

    public function test_purge_failed_clears_the_dead_letter_rows(): void
    {
        $this->queue->push(RecordingJob::class, ['post_id' => 1]);
        $claimed = $this->queue->claim()[0];
        $this->queue->fail($claimed['id'], 3, 'boom');

        $this->assertSame(1, $this->queue->purgeFailed());
        $this->assertSame(0, $this->queue->stats()['failed']);
    }
}

/** @internal */
final class RecordingJob implements JobInterface
{
    /** @var list<int> */
    public static array $handled = [];

    public function handle(array $payload, Container $container): void
    {
        self::$handled[] = (int) ($payload['post_id'] ?? 0);
    }
}

/** @internal */
final class FailingJob implements JobInterface
{
    public static int $attempts = 0;

    public function handle(array $payload, Container $container): void
    {
        self::$attempts++;

        throw new RuntimeException('deliberate failure');
    }
}
