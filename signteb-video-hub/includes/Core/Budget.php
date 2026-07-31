<?php

namespace SignTeb\VideoHub\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A wall-clock budget for background work.
 *
 * WP-Cron is not a background worker. When a host blocks loopback requests —
 * common behind Cloudflare and on Iranian shared hosting — WordPress falls
 * back to running due cron events inside an ordinary visitor request. Any work
 * that takes minutes then becomes a page that never loads.
 *
 * Every long-running job therefore checks a budget and stops cleanly when it
 * is spent; the remaining items are picked up by the next tick. Progress is
 * slower, but a page request can never be held open for minutes.
 */
class Budget
{
    /** Leave this much of the PHP limit unused so the shutdown path survives. */
    private const SAFETY_MARGIN = 5;

    private float $started;
    private int $seconds;

    public function __construct(int $seconds = 20)
    {
        $this->started = microtime(true);
        $this->seconds = max(5, $this->clamp_to_php_limit($seconds));
    }

    /**
     * True while there is time left to start another unit of work.
     *
     * Takes the cost of the next item so a job that needs ten seconds does not
     * start with three left and blow the budget anyway.
     */
    public function allows(int $next_item_seconds = 0): bool
    {
        return ($this->elapsed() + $next_item_seconds) < $this->seconds;
    }

    public function exhausted(): bool
    {
        return ! $this->allows();
    }

    public function elapsed(): float
    {
        return microtime(true) - $this->started;
    }

    public function remaining(): float
    {
        return max(0.0, $this->seconds - $this->elapsed());
    }

    /**
     * A per-request HTTP timeout that cannot outlast the budget itself.
     */
    public function http_timeout(int $preferred): int
    {
        return max(3, (int) min($preferred, floor($this->remaining())));
    }

    /**
     * Never budget more than PHP will actually allow. max_execution_time of 0
     * means unlimited (CLI), in which case the caller's figure stands.
     */
    private function clamp_to_php_limit(int $seconds): int
    {
        $limit = (int) ini_get('max_execution_time');

        if ($limit <= 0) {
            return $seconds;
        }

        return (int) min($seconds, max(5, $limit - self::SAFETY_MARGIN));
    }
}
