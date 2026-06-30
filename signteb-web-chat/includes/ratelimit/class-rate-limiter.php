<?php
/**
 * SWC_Rate_Limiter — per-IP / per-session throttle on a public endpoint.
 *
 * Backed by the transient API (object cache when available) to protect the API
 * budget against abuse on an anonymous endpoint.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Rate_Limiter
{
    private int $max_per_minute;

    public function __construct(int $max_per_minute = 8)
    {
        $this->max_per_minute = max(1, $max_per_minute);
    }

    /**
     * @return bool true if allowed, false if throttled
     */
    public function allow(string $identifier): bool
    {
        $key   = 'swc_rl_' . md5($identifier);
        $count = (int) get_transient($key);

        if ($count >= $this->max_per_minute) {
            return false;
        }

        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }
}
