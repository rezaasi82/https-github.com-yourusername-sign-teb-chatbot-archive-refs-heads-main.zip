<?php

namespace SignTeb\VideoHub\Cache;

use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Two jobs: a namespaced transient cache for expensive queries, and purging
 * the site's page caches when video content changes (feature 10).
 */
class CacheManager
{
    private const PREFIX      = 'stvh_c_';
    private const VERSION_KEY = 'stvh_cache_version';

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    /**
     * Cached value or the result of $callback, stored for $ttl seconds.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $full   = $this->key($key);
        $cached = get_transient($full);

        if ($cached !== false) {
            return $cached;
        }

        $value = $callback();
        if ($value !== null) {
            set_transient($full, $value, max(60, $ttl));
        }

        return $value;
    }

    public function forget(string $key): void
    {
        delete_transient($this->key($key));
    }

    /**
     * Version-namespaced key: bumping the version invalidates every entry at
     * once without a LIKE delete across the options table.
     */
    private function key(string $key): string
    {
        return self::PREFIX . $this->version() . '_' . md5($key);
    }

    private function version(): int
    {
        $version = (int) get_option(self::VERSION_KEY, 1);
        return $version > 0 ? $version : 1;
    }

    public function flush(): void
    {
        update_option(self::VERSION_KEY, $this->version() + 1, false);
    }

    /**
     * Flush our own cache and ask any page cache in front of WordPress to
     * drop its copies. Every integration is guarded so an absent plugin is a
     * no-op rather than a fatal.
     */
    public function purge_all(): void
    {
        $this->flush();

        if (! $this->settings->bool('cache_purge')) {
            return;
        }

        // LiteSpeed Cache
        if (defined('LSCWP_V') || has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }

        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // Autoptimize
        if (class_exists('\autoptimizeCache') && method_exists('\autoptimizeCache', 'clearall')) {
            \autoptimizeCache::clearall();
        }

        $this->purge_cloudflare();

        do_action('stvh_cache_purged');
    }

    /**
     * Purge a single post across the same integrations.
     */
    public function purge_post(int $post_id): void
    {
        $this->flush();

        if (! $this->settings->bool('cache_purge') || $post_id <= 0) {
            return;
        }

        do_action('litespeed_purge_post', $post_id);

        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($post_id);
        }
        if (function_exists('w3tc_flush_post')) {
            w3tc_flush_post($post_id);
        }
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change($post_id);
        }
    }

    /**
     * Cloudflare needs an API call — there is no local hook to fire.
     */
    private function purge_cloudflare(): void
    {
        $zone  = $this->settings->str('cloudflare_zone');
        $token = $this->settings->secret('cloudflare_token');

        if ($zone === '' || $token === '') {
            return;
        }

        // Bounded so a Cloudflare outage cannot slow every sync run.
        if (get_transient('stvh_cf_purged') !== false) {
            return;
        }
        set_transient('stvh_cf_purged', 1, 5 * MINUTE_IN_SECONDS);

        $response = wp_remote_post(
            sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', rawurlencode($zone)),
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(['purge_everything' => true]),
            ]
        );

        if (is_wp_error($response)) {
            \SignTeb\VideoHub\Core\Logger::warning('cache', 'پاک‌سازی کش Cloudflare ناموفق بود: ' . $response->get_error_message());
        }
    }

    /**
     * Which page-cache integrations are actually live — shown on the
     * dashboard so the admin knows what a purge will touch.
     *
     * @return array<string,bool>
     */
    public static function detected(): array
    {
        return [
            'LiteSpeed Cache' => defined('LSCWP_V'),
            'WP Rocket'       => function_exists('rocket_clean_domain'),
            'W3 Total Cache'  => function_exists('w3tc_flush_all'),
            'WP Super Cache'  => function_exists('wp_cache_clear_cache'),
            'Autoptimize'     => class_exists('\autoptimizeCache'),
        ];
    }
}
