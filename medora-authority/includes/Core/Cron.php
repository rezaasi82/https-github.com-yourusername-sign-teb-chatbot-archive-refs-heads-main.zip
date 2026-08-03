<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Cron event names and scheduling. Handlers live in the modules that own the
 * work, so a disabled module simply stops listening.
 */
final class Cron
{
    public const QUEUE_WORKER   = 'medora_queue_worker';
    public const DAILY_ANALYSIS = 'medora_daily_analysis';
    public const REBUILD_GRAPH  = 'medora_rebuild_graph';
    public const LICENSE_CHECK  = 'medora_license_check';
    public const PRUNE_LOGS     = 'medora_prune_logs';

    public const INTERVAL_FIVE_MINUTES = 'medora_five_minutes';

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public static function registerSchedules(array $schedules): array
    {
        $schedules[self::INTERVAL_FIVE_MINUTES] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every five minutes (Medora)', 'medora-authority'),
        ];

        return $schedules;
    }

    public static function schedule(): void
    {
        $events = [
            self::QUEUE_WORKER   => self::INTERVAL_FIVE_MINUTES,
            self::DAILY_ANALYSIS => 'daily',
            self::REBUILD_GRAPH  => 'daily',
            self::LICENSE_CHECK  => 'daily',
            self::PRUNE_LOGS     => 'daily',
        ];

        foreach ($events as $hook => $recurrence) {
            if (wp_next_scheduled($hook) === false) {
                // Spread the daily events so a site does not run every heavy
                // job in the same minute.
                wp_schedule_event(time() + random_int(60, 3600), $recurrence, $hook);
            }
        }
    }

    public static function unschedule(): void
    {
        foreach ([self::QUEUE_WORKER, self::DAILY_ANALYSIS, self::REBUILD_GRAPH, self::LICENSE_CHECK, self::PRUNE_LOGS] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
