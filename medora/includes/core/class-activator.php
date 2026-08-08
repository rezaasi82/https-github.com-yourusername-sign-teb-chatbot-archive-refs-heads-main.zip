<?php
/**
 * Runs on activation: builds tables and seeds defaults.
 *
 * @package Medora
 */

namespace Medora\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Activator
{
    /** Option prefix used by the pre-rebrand build this plugin grew out of. */
    private const LEGACY_PREFIX = 'swc_';

    /** Set once the legacy import has run, so it never runs twice. */
    private const IMPORT_FLAG = 'mdr_legacy_imported';

    public static function activate(): void
    {
        \Medora\Database\Schema::install();
        self::import_legacy_install();
        self::seed_default_settings();
        if (! wp_next_scheduled(\Medora\Jobs\Rollup::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', \Medora\Jobs\Rollup::CRON);
        }
        flush_rewrite_rules();
    }

    /**
     * Re-run install() when the stored schema version is behind (idempotent).
     */
    public static function maybe_upgrade(): void
    {
        if (get_option('mdr_db_version') !== \Medora\Database\Schema::DB_VERSION) {
            \Medora\Database\Schema::install();
        }
        if (! wp_next_scheduled(\Medora\Jobs\Rollup::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', \Medora\Jobs\Rollup::CRON);
        }
        self::import_legacy_install();
        self::retire_dead_models();
    }

    /**
     * Carry a pre-rebrand install across.
     *
     * The rebrand changed the plugin folder, its option keys and its table
     * prefix, so WordPress treats this as a different plugin: activating it
     * beside the old one would otherwise open on an empty dashboard with no
     * settings, no API keys and no conversations.
     *
     * Everything is copied, never moved, so the old plugin keeps working and
     * can be removed afterwards without taking the imported data with it.
     * Rows are only copied into a table that is still empty, and options only
     * where this build has not stored its own value, so a repeat run is a
     * no-op rather than a duplicate.
     */
    private static function import_legacy_install(): void
    {
        global $wpdb;

        if (get_option(self::IMPORT_FLAG)) {
            return;
        }

        // Nothing to import unless the old build actually ran on this site.
        if (get_option(self::LEGACY_PREFIX . 'settings') === false) {
            return;
        }

        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like(self::LEGACY_PREFIX) . '%'
            )
        ) ?: [];

        foreach ($names as $old_name) {
            $new_name = 'mdr_' . substr((string) $old_name, strlen(self::LEGACY_PREFIX));
            if (get_option($new_name) !== false) {
                continue;
            }
            add_option($new_name, get_option($old_name), '', false);
        }

        foreach (self::legacy_tables() as $old_table => $new_table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old_table)) !== $old_table) {
                continue;
            }
            if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$new_table}") > 0) {
                continue;
            }
            // Both schemas are generated from the same definition, so the
            // column order matches and a straight copy is safe.
            $wpdb->query("INSERT INTO {$new_table} SELECT * FROM {$old_table}");
        }

        update_option(self::IMPORT_FLAG, 1, false);
    }

    /**
     * @return array<string, string> legacy table name => current table name
     */
    private static function legacy_tables(): array
    {
        $tables = [
            \Medora\Database\Schema::conversations_table(),
            \Medora\Database\Schema::messages_table(),
            \Medora\Database\Schema::events_table(),
            \Medora\Database\Schema::sync_logs_table(),
            \Medora\Database\Schema::analytics_table(),
            \Medora\Database\Schema::jobs_table(),
            \Medora\Database\Schema::branches_table(),
            \Medora\Database\Schema::audit_logs_table(),
        ];

        $map = [];
        foreach ($tables as $table) {
            $map[str_replace('mdr_', self::LEGACY_PREFIX, $table)] = $table;
        }

        return $map;
    }

    /**
     * Model ids that a provider no longer serves. A site left on one of these
     * gets an API error on every message and only ever sees the "can't answer
     * right now" fallback, so they are rewritten to their live replacement.
     */
    private static function retire_dead_models(): void
    {
        $replacements = [
            'claude-opus-4-8' => 'claude-opus-5',
        ];

        $settings = get_option(\Medora\Core\Settings::OPTION, []);
        if (! is_array($settings)) {
            return;
        }

        $changed = false;
        foreach (['model_anthropic', 'model_openai', 'model_gapgpt', 'model_gemini'] as $key) {
            $current = (string) ($settings[$key] ?? '');
            if (isset($replacements[$current])) {
                $settings[$key] = $replacements[$current];
                $changed        = true;
            }
        }

        if ($changed) {
            update_option(\Medora\Core\Settings::OPTION, $settings);
        }
    }

    public static function default_settings(): array
    {
        return [
            'float_enabled'      => 1,
            'shortcode_enabled'  => 1,

            // --- AI provider ---
            'provider'           => 'anthropic', // anthropic | openai | gapgpt | gemini
            'model_anthropic'    => 'claude-haiku-4-5-20251001',
            'model_openai'       => 'gpt-4o-mini',
            'model_gapgpt'       => 'gpt-4o-mini',
            'model_gemini'       => 'gemini-2.5-flash',

            // --- Personality / language ---
            'tone'               => 'friendly', // friendly | formal
            'language'           => 'auto',     // auto | fa | ar | en
            'rate_limit_per_min' => 8,

            // --- White-label appearance ---
            'bot_name'           => __('دستیار هوشمند', 'medora'),
            'avatar_url'         => '',
            'widget_color'       => '#0f1f3d', // primary (navy)
            'accent_color'       => '#c8a04e', // secondary (gold)
            'direction'          => 'rtl',      // rtl | ltr
            'brand_footer'       => '',         // empty = no footer (white-label)
            'use_bundled_font'   => 1,

            // --- Lead capture + communication channels ---
            'lead_capture'       => 1,
            'ch_booking'         => 1,
            'ch_whatsapp'        => 1,
            'ch_call'            => 1,
            'ch_bale'            => 0,
            'bale_url'           => '',

            // --- Messaging ---
            'welcome_message'    => __('سلام! 👋 چطور می‌تونم کمکتون کنم؟', 'medora'),
            'quick_replies'      => "هزینه ویزیت\nآدرس کلینیک\nرزرو نوبت",
            'business_hours'     => '',
            'offhours_message'   => __('در حال حاضر خارج از ساعت کاری هستیم، اما می‌تونم به سوالاتتون پاسخ بدم.', 'medora'),

            // --- Clinic content (manual mode — this plugin is standalone) ---
            'clinic_name'        => get_bloginfo('name'),
            'specialty'          => '',
            'phone'              => '',
            'whatsapp'           => '',
            'address'            => '',
            'emergency_number'   => '115',
            'booking_url'        => '',
            'manual_services'    => '', // one "name | price" per line
            'avg_service_price'  => 0,  // Toman — used for the revenue estimate
        ];
    }

    private static function seed_default_settings(): void
    {
        $existing = get_option(\Medora\Core\Settings::OPTION, []);
        if (! is_array($existing)) {
            $existing = [];
        }
        update_option(\Medora\Core\Settings::OPTION, array_merge(self::default_settings(), $existing));
    }
}
