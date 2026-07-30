<?php

namespace SignTeb\VideoHub\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Thin, typed accessor over the stvh_settings option array.
 *
 * Secrets (AI key, YouTube key, Cloudflare token, Google service account) live
 * in their own encrypted options — never inside the settings array, which is
 * dumped verbatim into admin views.
 */
class Settings
{
    private const OPTION = 'stvh_settings';

    /** option name => human label, used by the settings screen and the dashboard. */
    public const SECRETS = [
        'ai_api_key'         => 'stvh_ai_api_key_enc',
        'youtube_api_key'    => 'stvh_youtube_api_key_enc',
        'cloudflare_token'   => 'stvh_cloudflare_token_enc',
        'google_service_json' => 'stvh_google_service_json_enc',
    ];

    public const DEFAULTS = [
        'enabled'              => 1,
        'aparat_username'      => '',
        'youtube_channel'      => '',
        'sync_interval'        => 'stvh_12h',
        'sync_limit'           => 30,
        'auto_publish'         => 1,
        'import_thumbnails'    => 1,
        'ai_enabled'           => 0,
        'ai_provider'          => 'anthropic',
        'ai_model'             => '',
        'ai_base_url'          => '',
        'ai_auto_summary'      => 1,
        'ai_auto_links'        => 1,
        'ai_auto_article'      => 0,
        'ai_write_content'     => 1,
        'ai_batch_size'        => 3,
        'internal_link_map'    => '',
        'schema_enabled'       => 1,
        'physician_name'       => '',
        'physician_specialty'  => '',
        'physician_url'        => '',
        'clinic_name'          => '',
        'social_meta'          => 1,
        'sitemap_enabled'      => 1,
        'analytics_enabled'    => 1,
        'analytics_retention'  => 180,
        'google_indexing'      => 0,
        'cache_purge'          => 1,
        'cloudflare_zone'      => '',
        'dark_mode'            => 'auto',
        'accent_color'         => '#0a84ff',
        'cards_per_page'       => 12,
        'booking_url'          => '',
        'hub_enabled'          => 1,
    ];

    private array $data;

    public function __construct()
    {
        $stored     = get_option(self::OPTION, []);
        $this->data = is_array($stored) ? $stored : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        if ($default !== null) {
            return $default;
        }
        return self::DEFAULTS[$key] ?? '';
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function str(string $key): string
    {
        return trim((string) $this->get($key));
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge(self::DEFAULTS, $this->data);
    }

    public function is_enabled(): bool
    {
        return $this->bool('enabled');
    }

    /**
     * Persist the non-secret settings array. Values are expected to be
     * sanitized by the caller (SettingsPage).
     *
     * @param array<string,mixed> $values
     */
    public static function save(array $values): void
    {
        update_option(self::OPTION, $values, false);
    }

    /**
     * Decrypted secret by logical name (see self::SECRETS).
     */
    public function secret(string $name): string
    {
        $option = self::SECRETS[$name] ?? '';
        if ($option === '') {
            return '';
        }
        $stored = get_option($option, '');
        if (! is_string($stored) || $stored === '') {
            return '';
        }
        return Encryption::decrypt($stored);
    }

    public function has_secret(string $name): bool
    {
        return $this->secret($name) !== '';
    }

    /**
     * Store (or clear) a secret. An empty string deletes the option so the
     * "not configured" state stays honest on the dashboard.
     */
    public static function save_secret(string $name, string $plain): void
    {
        $option = self::SECRETS[$name] ?? '';
        if ($option === '') {
            return;
        }
        $plain = trim($plain);
        if ($plain === '') {
            delete_option($option);
            return;
        }
        update_option($option, Encryption::encrypt($plain), false);
    }
}
