<?php

declare(strict_types=1);

namespace Medora\Authority\Support;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Thin cache facade over the WordPress object cache.
 *
 * When a persistent backend (Redis, Memcached) is installed, values live there
 * automatically; otherwise entries fall back to transients so the cache still
 * survives across requests on shared hosting. Reads always consult a
 * request-local map first, which matters for the scorers that resolve the same
 * entity dozens of times while walking a page.
 */
final class Cache
{
    public const GROUP = 'medora';

    /** @var array<string, mixed> */
    private array $local = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->local)) {
            return $this->local[$key];
        }

        $found = false;
        $value = wp_cache_get($key, self::GROUP, false, $found);

        if (! $found) {
            $value = get_transient($this->transientKey($key));

            if ($value === false) {
                return $default;
            }
        }

        return $this->local[$key] = $value;
    }

    public function set(string $key, mixed $value, int $ttl = HOUR_IN_SECONDS): void
    {
        $this->local[$key] = $value;

        wp_cache_set($key, $value, self::GROUP, $ttl);

        if (! wp_using_ext_object_cache()) {
            set_transient($this->transientKey($key), $value, $ttl);
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key, null);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function forget(string $key): void
    {
        unset($this->local[$key]);

        wp_cache_delete($key, self::GROUP);
        delete_transient($this->transientKey($key));
    }

    /**
     * Invalidate everything by rotating the namespace salt. Cheaper and safer
     * than enumerating keys, which is impossible against a shared object cache.
     */
    public function flush(): void
    {
        $this->local = [];

        wp_cache_set('namespace', (string) microtime(true), self::GROUP);

        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::GROUP);
        }
    }

    private function transientKey(string $key): string
    {
        // Transient keys are capped at 172 characters; hashing keeps composite
        // keys (which embed post ids and content hashes) inside the limit.
        return 'medora_' . md5($key);
    }
}
