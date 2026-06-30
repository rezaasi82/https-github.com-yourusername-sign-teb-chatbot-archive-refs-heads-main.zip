<?php

namespace STMC_Chat\Core;

use STMC_Chat\Database\Schema;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Runs on activation: builds tables (the activation-hook pattern from the
 * mother project's appointment bug) and seeds default settings.
 */
class Activator
{
    public static function activate(): void
    {
        Schema::install();
        self::seed_default_settings();
        flush_rewrite_rules();
    }

    /**
     * If the stored DB version is behind, re-run install (idempotent dbDelta).
     */
    public static function maybe_upgrade(): void
    {
        if (get_option('stmc_chat_db_version') !== Schema::DB_VERSION) {
            Schema::install();
        }
    }

    private static function seed_default_settings(): void
    {
        $defaults = [
            'enabled'            => 1,
            'provider'           => 'anthropic',
            'model'              => 'claude-opus-4-8',
            'tone'               => 'friendly', // friendly | formal
            'language'           => 'auto',     // auto | fa | ar | en
            'widget_color'       => '#0f1f3d',  // navy
            'accent_color'       => '#c8a04e',  // gold
            'welcome_message'    => 'سلام! 👋 من دستیار هوشمند کلینیک هستم. چطور می‌تونم کمکتون کنم؟',
            'quick_replies'      => "رزرو نوبت\nهزینه ویزیت\nآدرس کلینیک",
            'business_hours'     => '',
            'offhours_message'   => 'در حال حاضر خارج از ساعت کاری هستیم، اما می‌تونم به سوالاتتون پاسخ بدم و برای شما نوبت ثبت کنم.',
            'rate_limit_per_min' => 8,
            // Fallback business profile (used when Medical Core is inactive).
            'clinic_name'        => get_bloginfo('name'),
            'specialty'          => '',
            'phone'              => '',
            'whatsapp'           => '',
            'address'            => '',
            'emergency_number'   => '115',
            'booking_url'        => '',
            'manual_services'    => '', // free text, one "name | price" per line
        ];

        $existing = get_option('stmc_chat_settings', []);
        if (! is_array($existing)) {
            $existing = [];
        }
        update_option('stmc_chat_settings', array_merge($defaults, $existing));
    }
}
