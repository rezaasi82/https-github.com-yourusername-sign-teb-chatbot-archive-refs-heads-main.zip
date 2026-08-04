<?php
/**
 * Runs on activation: builds tables and seeds defaults.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function activate(): void
    {
        \SignTeb\WebChat\Database\Schema::install();
        self::seed_default_settings();
        if (! wp_next_scheduled(\SignTeb\WebChat\Jobs\Rollup::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', \SignTeb\WebChat\Jobs\Rollup::CRON);
        }
        flush_rewrite_rules();
    }

    /**
     * Re-run install() when the stored schema version is behind (idempotent).
     */
    public static function maybe_upgrade(): void
    {
        if (get_option('swc_db_version') !== \SignTeb\WebChat\Database\Schema::DB_VERSION) {
            \SignTeb\WebChat\Database\Schema::install();
        }
        if (! wp_next_scheduled(\SignTeb\WebChat\Jobs\Rollup::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', \SignTeb\WebChat\Jobs\Rollup::CRON);
        }
    }

    public static function default_settings(): array
    {
        return [
            'float_enabled'      => 1,
            'shortcode_enabled'  => 1,

            // --- AI provider ---
            'provider'           => 'anthropic', // anthropic | openai | gapgpt
            'model_anthropic'    => 'claude-haiku-4-5-20251001',
            'model_openai'       => 'gpt-4o-mini',
            'model_gapgpt'       => 'gpt-4o-mini',

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

            // --- Lead capture + communication channels ---
            'lead_capture'       => 1,
            'ch_booking'         => 1,
            'ch_consult'         => 1,
            'ch_whatsapp'        => 1,
            'ch_call'            => 1,
            'ch_bale'            => 0,
            'bale_url'           => '',

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
            'consult_url'        => '', // online consultation (video/chat visit)
            'manual_services'    => '', // one "name | price" per line
            'avg_service_price'  => 0,  // Toman — used for the revenue estimate
        ];
    }

    private static function seed_default_settings(): void
    {
        $existing = get_option(\SignTeb\WebChat\Core\Settings::OPTION, []);
        if (! is_array($existing)) {
            $existing = [];
        }
        update_option(\SignTeb\WebChat\Core\Settings::OPTION, array_merge(self::default_settings(), $existing));
    }
}
