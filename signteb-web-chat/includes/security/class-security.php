<?php
/**
 * SWC_Security — generic rate limiting and brute-force lockout.
 *
 * Sensitive endpoints call guard()/note_failure() so repeated invalid or
 * unauthorized requests from one IP get throttled and then temporarily locked
 * out, with the event written to the audit log.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Security
{
    private const MAX_FAILURES = 8;      // failures within the window before lockout
    private const WINDOW       = 600;    // 10 minutes
    private const LOCK         = 900;    // 15 minute lockout

    public static function ip(): string
    {
        return class_exists('SWC_Sanitizer') ? SWC_Sanitizer::client_ip() : '0.0.0.0';
    }

    /**
     * Sliding-window rate limit. Returns true when the call is allowed.
     */
    public static function rate_limit(string $bucket, int $max, int $window = 60): bool
    {
        $key   = 'swc_rl_' . md5($bucket . '|' . self::ip());
        $count = (int) get_transient($key);
        if ($count >= max(1, $max)) {
            return false;
        }
        set_transient($key, $count + 1, $window);
        return true;
    }

    public static function is_locked(): bool
    {
        return (bool) get_transient('swc_lock_' . md5(self::ip()));
    }

    /**
     * Record a failed/unauthorized attempt; lock the IP after too many.
     */
    public static function note_failure(string $context): void
    {
        $ip    = self::ip();
        $key   = 'swc_fail_' . md5($ip);
        $count = (int) get_transient($key) + 1;
        set_transient($key, $count, self::WINDOW);

        if ($count >= self::MAX_FAILURES) {
            set_transient('swc_lock_' . md5($ip), 1, self::LOCK);
            delete_transient($key);
            if (class_exists('SWC_Audit_Log')) {
                SWC_Audit_Log::record('security_lockout', ['object' => $context, 'severity' => 'critical', 'detail' => 'IP temporarily locked after repeated failures']);
            }
        } elseif (class_exists('SWC_Audit_Log')) {
            SWC_Audit_Log::record('auth_denied', ['object' => $context, 'severity' => 'warning', 'detail' => 'attempt ' . $count]);
        }
    }
}
