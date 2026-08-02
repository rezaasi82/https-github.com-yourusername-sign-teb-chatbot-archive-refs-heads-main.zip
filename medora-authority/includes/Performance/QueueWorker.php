<?php

declare(strict_types=1);

namespace Medora\Authority\Performance;

use Medora\Authority\Core\Container;
use Throwable;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Drains the job queue.
 *
 * The worker self-limits on both wall time and memory. A cron tick on shared
 * hosting has a hard PHP timeout it cannot see, so the budget is deliberately
 * conservative: finish early and let the next tick continue, rather than get
 * killed mid-job and leave rows stuck in `running`.
 */
final class QueueWorker
{
    private const TIME_BUDGET   = 20;   // seconds
    private const MEMORY_MARGIN = 0.80; // stop at 80% of the PHP memory limit

    public function __construct(
        private readonly JobQueue $queue,
        private readonly Container $container,
    ) {
    }

    /**
     * @return array{processed: int, failed: int}
     */
    public function run(int $maxJobs = 25): array
    {
        $this->queue->recoverStalled();

        $started   = microtime(true);
        $processed = 0;
        $failed    = 0;

        while ($processed + $failed < $maxJobs) {
            if ((microtime(true) - $started) > self::TIME_BUDGET || $this->memoryExhausted()) {
                break;
            }

            $jobs = $this->queue->claim(min(5, $maxJobs - $processed - $failed));

            if ($jobs === []) {
                break;
            }

            foreach ($jobs as $job) {
                try {
                    $handler = $this->container->get($job['handler']);

                    if (! $handler instanceof JobInterface) {
                        throw new \RuntimeException(sprintf('Handler "%s" is not a JobInterface.', $job['handler']));
                    }

                    $handler->handle($job['payload'], $this->container);

                    $this->queue->complete($job['id']);
                    $processed++;
                } catch (Throwable $exception) {
                    $this->queue->fail($job['id'], $job['attempts'], $exception->getMessage());
                    $failed++;

                    /**
                     * Fires when a background job throws.
                     *
                     * @param string    $handler
                     * @param Throwable $exception
                     */
                    do_action('medora_job_failed', $job['handler'], $exception);
                }
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    private function memoryExhausted(): bool
    {
        $limit = $this->memoryLimitBytes();

        if ($limit <= 0) {
            return false;
        }

        return memory_get_usage(true) > ($limit * self::MEMORY_MARGIN);
    }

    private function memoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === false || $limit === '-1') {
            return 0;
        }

        return (int) wp_convert_hr_to_bytes($limit);
    }
}
