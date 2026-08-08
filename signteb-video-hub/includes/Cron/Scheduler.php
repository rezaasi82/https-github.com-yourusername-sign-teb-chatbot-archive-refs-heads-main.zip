<?php

namespace SignTeb\VideoHub\Cron;

use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 2 — auto sync every 6 / 12 / 24 hours.
 *
 * Owns the cron vocabulary: the custom intervals, the hook names, and keeping
 * the scheduled events in step with the saved settings.
 */
class Scheduler
{
    public const HOOK_SYNC        = 'stvh_sync_videos';
    public const HOOK_AI          = 'stvh_process_ai_queue';
    public const HOOK_MAINTENANCE = 'stvh_daily_maintenance';
    public const HOOK_INDEXING    = 'stvh_process_indexing_queue';

    /** Interval slug => [seconds, label] */
    public const INTERVALS = [
        'stvh_6h'  => [6 * HOUR_IN_SECONDS, 'هر ۶ ساعت'],
        'stvh_12h' => [12 * HOUR_IN_SECONDS, 'هر ۱۲ ساعت'],
        'stvh_24h' => [DAY_IN_SECONDS, 'هر ۲۴ ساعت'],
    ];

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'add_intervals']);

        add_action(self::HOOK_SYNC, [$this, 'run_sync']);
        add_action(self::HOOK_AI, [$this, 'run_ai_queue']);
        add_action(self::HOOK_MAINTENANCE, [$this, 'run_maintenance']);
        add_action(self::HOOK_INDEXING, [$this, 'run_indexing_queue']);

        // Settings changes must re-anchor the sync event immediately.
        add_action('stvh_settings_saved', [$this, 'reschedule']);
    }

    /**
     * @param array<string,array{interval:int,display:string}> $schedules
     * @return array<string,array{interval:int,display:string}>
     */
    public function add_intervals(array $schedules): array
    {
        foreach (self::INTERVALS as $slug => [$seconds, $label]) {
            $schedules[$slug] = ['interval' => $seconds, 'display' => $label];
        }
        return $schedules;
    }

    /**
     * Idempotent scheduling — safe on every activation and settings save.
     */
    public function schedule_all(): void
    {
        $interval = $this->sync_interval();

        if (! wp_next_scheduled(self::HOOK_SYNC)) {
            wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, $interval, self::HOOK_SYNC);
        }
        if (! wp_next_scheduled(self::HOOK_AI)) {
            wp_schedule_event(time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::HOOK_AI);
        }
        if (! wp_next_scheduled(self::HOOK_MAINTENANCE)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK_MAINTENANCE);
        }
        if (! wp_next_scheduled(self::HOOK_INDEXING)) {
            wp_schedule_event(time() + 15 * MINUTE_IN_SECONDS, 'hourly', self::HOOK_INDEXING);
        }
    }

    public static function unschedule_all(): void
    {
        foreach ([self::HOOK_SYNC, self::HOOK_AI, self::HOOK_MAINTENANCE, self::HOOK_INDEXING] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Re-anchor the sync event when the interval setting changed.
     */
    public function reschedule(): void
    {
        $wanted  = $this->sync_interval();
        $current = wp_get_schedule(self::HOOK_SYNC);

        if ($current !== $wanted) {
            wp_clear_scheduled_hook(self::HOOK_SYNC);
        }

        $this->schedule_all();
    }

    private function sync_interval(): string
    {
        $interval = $this->settings->str('sync_interval');
        return isset(self::INTERVALS[$interval]) ? $interval : 'stvh_12h';
    }

    public function run_sync(): void
    {
        if (! $this->settings->is_enabled()) {
            return;
        }
        (new \SignTeb\VideoHub\Sync\SyncManager($this->settings))->sync_all();
    }

    public function run_ai_queue(): void
    {
        (new AiWorker($this->settings))->run();
    }

    public function run_indexing_queue(): void
    {
        (new \SignTeb\VideoHub\Seo\IndexingQueue($this->settings))->process();
    }

    /**
     * Nightly housekeeping: analytics rollup, retention pruning, stale job
     * recovery, and refreshing the internal-link candidate pool.
     */
    public function run_maintenance(): void
    {
        $analytics = new \SignTeb\VideoHub\Db\AnalyticsRepository();
        $analytics->sync_view_counts();
        $analytics->prune($this->settings->int('analytics_retention'));

        (new \SignTeb\VideoHub\Db\AiQueueRepository())->requeue_stale();
        \SignTeb\VideoHub\Ai\InternalLinker::flush_candidates();

        do_action('stvh_daily_maintenance_done');
    }

    /**
     * Next run timestamps for the dashboard.
     *
     * @return array<string,int>
     */
    public static function next_runs(): array
    {
        return [
            'sync'        => (int) wp_next_scheduled(self::HOOK_SYNC),
            'ai'          => (int) wp_next_scheduled(self::HOOK_AI),
            'maintenance' => (int) wp_next_scheduled(self::HOOK_MAINTENANCE),
            'indexing'    => (int) wp_next_scheduled(self::HOOK_INDEXING),
        ];
    }
}
