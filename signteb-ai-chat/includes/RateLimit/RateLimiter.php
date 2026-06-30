<?php

namespace STMC_Chat\RateLimit;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-IP / per-session sliding-window throttle backed by the object cache
 * (transients fallback). Protects the API budget on a public endpoint.
 */
class RateLimiter
{
    public function __construct(private int $max_per_minute = 8)
    {
        $this->max_per_minute = max(1, $max_per_minute);
    }

    /**
     * @return bool true if the request is allowed, false if throttled
     */
    public function allow(string $identifier): bool
    {
        $key   = 'stmc_chat_rl_' . md5($identifier);
        $count = (int) get_transient($key);

        if ($count >= $this->max_per_minute) {
            return false;
        }

        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }
}
