<?php
/**
 * SWC_Activator — runs on activation: builds tables and seeds defaults.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Activator
{
    public static function activate(): void
    {
        SWC_Schema::install();
        self::seed_default_settings();
        flush_rewrite_rules();
    }

    /**
     * Re-run install() when the stored schema version is behind (idempotent).
     */
    public static function maybe_upgrade(): void
    {
        if (get_option('swc_db_version') !== SWC_Schema::DB_VERSION) {
            SWC_Schema::install();
        }
    }

    public static function default_settings(): array
    {
        return [
            'enabled'            => 1,

            // --- AI provider ---
            'provider'           => 'anthropic', // anthropic | openai
            'model_anthropic'    => 'claude-haiku-4-5-20251001',
            'model_openai'       => 'gpt-4o-mini',

            // --- Personality / language ---
            'tone'               => 'friendly', // friendly | formal
            'language'           => 'auto',     // auto | fa | ar | en
            'rate_limit_per_min' => 8,

            // --- White-label appearance ---
            'bot_name'           => __('دستیار هوشمند', 'signteb-web-chat'),
            'avatar_url'         => '',
            'widget_color'       => '#0f1f3d', // primary (navy)
            'accent_color'       => '#c8a04e', // secondary (gold)
            'direction'          => 'rtl',      // rtl | ltr
            'brand_footer'       => '',         // empty = no footer (white-label)
            'use_bundled_font'   => 1,

            // --- Messaging ---
            'welcome_message'    => __('سلام! 👋 چطور می‌تونم کمکتون کنم؟', 'signteb-web-chat'),
            'quick_replies'      => "هزینه ویزیت\nآدرس کلینیک\nرزرو نوبت",
            'business_hours'     => '',
            'offhours_message'   => __('در حال حاضر خارج از ساعت کاری هستیم، اما می‌تونم به سوالاتتون پاسخ بدم.', 'signteb-web-chat'),

            // --- Clinic content (manual mode — this plugin is standalone) ---
            'clinic_name'        => get_bloginfo('name'),
            'specialty'          => '',
            'phone'              => '',
            'whatsapp'           => '',
            'address'            => '',
            'emergency_number'   => '115',
            'booking_url'        => '',
            'manual_services'    => '', // one "name | price" per line
        ];
    }

    private static function seed_default_settings(): void
    {
        $existing = get_option(SWC_Settings::OPTION, []);
        if (! is_array($existing)) {
            $existing = [];
        }
        update_option(SWC_Settings::OPTION, array_merge(self::default_settings(), $existing));
    }
}
