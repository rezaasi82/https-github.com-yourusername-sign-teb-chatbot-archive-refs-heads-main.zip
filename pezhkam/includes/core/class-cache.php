<?php
/**
 * Small caching helper over the transient / object cache.
 *
 * Keys are namespaced by a version counter so a single bump invalidates every
 * cached value at once (called when leads/messages change). Uses the object
 * cache transparently when the host provides one.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Cache
{
    private const VERSION_OPTION = 'pzk_cache_version';

    public static function version(): int
    {
        $v = (int) get_option(self::VERSION_OPTION, 1);
        return $v > 0 ? $v : 1;
    }

    /**
     * Invalidate every cached value (cheap: one option bump).
     */
    public static function flush(): void
    {
        update_option(self::VERSION_OPTION, self::version() + 1, false);
    }

    /**
     * Remember the result of $callback for $ttl seconds under $key.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        $full   = self::key($key);
        $cached = get_transient($full);
        if ($cached !== false) {
            return $cached['v'] ?? null;
        }
        $value = $callback();
        set_transient($full, ['v' => $value], max(30, $ttl));
        return $value;
    }

    public static function forget(string $key): void
    {
        delete_transient(self::key($key));
    }

    private static function key(string $key): string
    {
        return 'pzk_c' . self::version() . '_' . md5($key);
    }
}
