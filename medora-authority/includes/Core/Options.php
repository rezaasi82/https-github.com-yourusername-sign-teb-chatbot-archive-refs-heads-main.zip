<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Typed accessor over a single autoloaded WordPress option.
 *
 * Everything lives under one option key so a settings read costs one row from
 * the autoload cache rather than one query per setting — the single biggest
 * avoidable cost in SEO plugins that register dozens of separate options.
 */
final class Options
{
    public const OPTION_KEY = 'medora_settings';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->cache === null) {
            $stored      = get_option(self::OPTION_KEY, []);
            $this->cache = is_array($stored) ? $stored : [];
        }

        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? $default;

        /**
         * Filter a single Medora setting at read time. White-label and SaaS
         * deployments use this to force values without writing to the option.
         *
         * @param mixed  $value
         * @param string $key
         */
        return apply_filters('medora_option', $value, $key);
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['1', 1, 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $settings       = $this->all();
        $settings[$key] = $value;

        $this->replace($settings);
    }

    /** @param array<string, mixed> $settings */
    public function merge(array $settings): void
    {
        $this->replace(array_merge($this->all(), $settings));
    }

    /** @param array<string, mixed> $settings */
    public function replace(array $settings): void
    {
        $this->cache = $settings;

        update_option(self::OPTION_KEY, $settings, true);

        do_action('medora_settings_updated', $settings);
    }

    public function forget(string $key): void
    {
        $settings = $this->all();
        unset($settings[$key]);

        $this->replace($settings);
    }

    /** Drop the in-process cache; used by tests and long-running workers. */
    public function flush(): void
    {
        $this->cache = null;
    }

    /**
     * Defaults applied on activation. Kept here so the installer, the setup
     * wizard and the REST settings controller share one source of truth.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // Product mode.
            'site_mode'            => 'general',   // general | medical
            'experience_mode'      => 'beginner',  // beginner | professional | agency | enterprise
            'onboarded'            => false,

            // Publisher identity — the anchor of the whole knowledge graph.
            'organization_type'          => 'Organization',
            'organization_name'          => '',
            'organization_url'           => '',
            'organization_logo_id'       => 0,
            'organization_phone'         => '',
            'organization_email'         => '',
            'organization_address'       => [],
            'organization_profiles'      => [],
            'organization_specialties'   => [],
            'organization_accreditation' => '',

            // Crawler policy.
            'crawler_policy'        => 'allow',    // allow | selective | block
            'crawler_overrides'     => [],
            'crawler_delay_seconds' => 10,

            // Published artefacts.
            'llms_txt_enabled'     => true,
            'llms_txt_description' => '',
            'llms_txt_notes'       => '',
            'llms_post_types'      => [],
            'sitemap_enabled'      => true,
            'schema_enabled'       => true,
            'analytics_enabled'    => true,

            // Embeddings. The key itself is deliberately absent — it belongs in
            // MEDORA_EMBEDDING_API_KEY, and the security scanner flags the
            // database fallback.
            'embedding_provider'   => 'hashing',
            'embedding_dimensions' => 512,
            'embedding_base_url'   => 'https://api.openai.com/v1',
            'embedding_model'      => 'text-embedding-3-small',

            // Operations.
            'default_language' => 'en',
            'retention_days'   => 180,
            'white_label'      => [],
            'modules'          => [],
        ];
    }
}
