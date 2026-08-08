<?php

namespace SignTeb\VideoHub\Cron;

use SignTeb\VideoHub\Ai\AiManager;
use SignTeb\VideoHub\Ai\ArticleSuggester;
use SignTeb\VideoHub\Ai\InternalLinker;
use SignTeb\VideoHub\Ai\SummaryGenerator;
use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\Budget;
use SignTeb\VideoHub\Core\RunLock;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Db\AiQueueRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Drains a few AI jobs per cron tick.
 *
 * The batch size is deliberately small: an AI call can take 30+ seconds and a
 * shared host will kill a long-running cron request, so throughput comes from
 * many small runs rather than one big one.
 */
class AiWorker
{
    private Settings $settings;
    private AiQueueRepository $queue;
    private AiManager $ai;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->queue    = new AiQueueRepository();
        $this->ai       = new AiManager($this->settings);
    }

    /**
     * @return array{processed:int,failed:int}
     */
    public function run(int $budget_seconds = 25): array
    {
        if (! $this->ai->is_enabled()) {
            return ['processed' => 0, 'failed' => 0];
        }

        $lock = new RunLock('ai');
        if (! $lock->acquire()) {
            return ['processed' => 0, 'failed' => 0];
        }

        $budget = new Budget($budget_seconds);

        $this->queue->requeue_stale();
        $this->backfill();

        $batch     = max(1, min(10, $this->settings->int('ai_batch_size')));
        $jobs      = $this->queue->claim($batch);
        $processed = 0;
        $failed    = 0;

        foreach ($jobs as $job) {
            // A model call can take most of a minute. Never begin one the
            // budget cannot cover — the job stays pending for the next tick.
            if (! $budget->allows(20)) {
                $this->queue->release($job['id']);
                continue;
            }

            $result = $this->run_job($job['video_id'], $job['task']);

            if ($result['ok']) {
                $this->queue->complete($job['id']);
                $processed++;
                continue;
            }

            $this->queue->fail($job['id'], (string) ($result['error'] ?? ''), $job['attempts']);
            $failed++;
            Logger::warning('ai', (string) ($result['error'] ?? 'خطای نامشخص'), [
                'video_id' => $job['video_id'],
                'task'     => $job['task'],
            ]);
        }

        $lock->release();

        if ($processed > 0) {
            (new CacheManager($this->settings))->purge_all();
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function run_job(int $video_id, string $task): array
    {
        return match ($task) {
            'summary' => (new SummaryGenerator($this->ai))->generate($video_id),
            'links'   => (new InternalLinker($this->ai, $this->settings))->generate($video_id),
            'article' => (new ArticleSuggester($this->ai, $this->settings))->generate($video_id),
            default   => ['ok' => false, 'error' => 'نوع کار نامعتبر: ' . $task],
        };
    }

    /**
     * Videos that predate the AI settings (or were imported while AI was off)
     * are pulled into the queue here, so enabling AI backfills the archive
     * without an admin having to click through every video.
     */
    private function backfill(): void
    {
        if (! $this->settings->bool('ai_auto_summary')) {
            return;
        }

        $repository = new \SignTeb\VideoHub\Db\VideoRepository();
        foreach ($repository->missing_ai(5) as $video_id) {
            $this->queue->enqueue($video_id, 'summary');
            if ($this->settings->bool('ai_auto_links')) {
                $this->queue->enqueue($video_id, 'links');
            }
        }
    }
}
