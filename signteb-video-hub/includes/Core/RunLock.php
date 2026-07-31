<?php

namespace SignTeb\VideoHub\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A short-lived lock so two overlapping runs of the same job cannot stack.
 *
 * Once a request is slow, more requests arrive, and each can trigger the same
 * due cron event — every one of them repeating the same slow HTTP work. That
 * is how a merely slow site becomes an unreachable one. The first holder wins;
 * the rest return immediately.
 */
class RunLock
{
    private string $key;

    public function __construct(string $name)
    {
        $this->key = 'stvh_lock_' . sanitize_key($name);
    }

    /**
     * Try to take the lock. False means another run already holds it.
     *
     * @param int $ttl Seconds after which an abandoned lock expires on its own,
     *                 so a fatal error mid-run cannot block the job forever.
     */
    public function acquire(int $ttl = 300): bool
    {
        if (get_transient($this->key) !== false) {
            return false;
        }

        set_transient($this->key, time(), max(30, $ttl));

        return true;
    }

    public function release(): void
    {
        delete_transient($this->key);
    }

    public function is_held(): bool
    {
        return get_transient($this->key) !== false;
    }
}
