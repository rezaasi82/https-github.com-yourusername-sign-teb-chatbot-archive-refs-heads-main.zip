<?php

declare(strict_types=1);

namespace Medora\Authority\Performance;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Cron;
use Medora\Authority\Core\Options;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Performance\Jobs\RebuildGraphJob;
use Medora\Authority\Support\Cache;
use Medora\Authority\Support\Hash;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Performance foundation: caching, the background queue and the cron ticks
 * that drain it.
 *
 * This module boots before everything else because every other module either
 * caches through it or defers work onto its queue.
 */
final class PerformanceModule extends AbstractModule
{
    public function id(): string
    {
        return 'performance';
    }

    public function title(): string
    {
        return __('Performance & Queues', 'medora-authority');
    }

    public function description(): string
    {
        return __('Object caching, background workers and scheduled maintenance.', 'medora-authority');
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton(Cache::class, static fn (): Cache => new Cache());
        $container->singleton(JobQueue::class, static fn (): JobQueue => new JobQueue());
        $container->singleton(
            Hash::class,
            static fn (Container $c): Hash => new Hash($c->get(Options::class))
        );
        $container->singleton(
            QueueWorker::class,
            static fn (Container $c): QueueWorker => new QueueWorker($c->get(JobQueue::class), $c)
        );
    }

    public function boot(Container $container): void
    {
        add_action(Cron::QUEUE_WORKER, static function () use ($container): void {
            $container->get(QueueWorker::class)->run();
        });

        add_action(Cron::REBUILD_GRAPH, static function () use ($container): void {
            $container->get(JobQueue::class)->push(RebuildGraphJob::class);
        });

        // Settings changes can invalidate almost every cached report, and the
        // cost of a cold cache is far lower than the cost of a stale dashboard.
        add_action('medora_settings_updated', static function () use ($container): void {
            $container->get(Cache::class)->flush();
        });

        // WP-CLI gives operators a way to drain the queue without waiting for
        // a cron tick, which is what large migrations actually need.
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('medora queue', static function (array $args, array $assoc) use ($container): void {
                $result = $container->get(QueueWorker::class)->run((int) ($assoc['max'] ?? 100));

                \WP_CLI::success(sprintf('Processed %d job(s), %d failed.', $result['processed'], $result['failed']));
            });
        }
    }
}
