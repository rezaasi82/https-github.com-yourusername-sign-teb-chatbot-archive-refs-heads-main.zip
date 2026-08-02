<?php

declare(strict_types=1);

namespace Medora\Authority\Performance;

use Medora\Authority\Core\Container;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A unit of deferred work.
 *
 * Handlers must be idempotent: the queue guarantees at-least-once delivery, so
 * a job that runs twice has to converge to the same state rather than double
 * counting.
 */
interface JobInterface
{
    /**
     * @param array<string, mixed> $payload
     * @throws \Throwable To signal failure and trigger a retry.
     */
    public function handle(array $payload, Container $container): void;
}
