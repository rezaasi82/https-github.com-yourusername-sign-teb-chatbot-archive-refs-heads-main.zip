<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 * Drops the plugin tables and removes every option it created.
 *
 * @package Pazira
 */

if (! defined('WP_UNINSTALL_PLUGIN') || ! defined('ABSPATH')) {
    exit;
}

if (! defined('PZR_DIR')) {
    define('PZR_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/includes/class-autoloader.php';
\Pazira\Autoloader::register();

if (class_exists('\Pazira\Database\Schema')) {
    \Pazira\Database\Schema::uninstall();
}

$options = [
    'pzr_settings',
    'pzr_api_key_anthropic_enc',
    'pzr_api_key_openai_enc',
    'pzr_api_key_gapgpt_enc',
    'pzr_webhook_secret_enc',
    'pzr_gsheet_secret_enc',
    'pzr_cloud_secret_enc',
    'pzr_sms_key_enc',
    'pzr_sms_secret_enc',
    'pzr_msgr_bale_token_enc',
    'pzr_msgr_tg_token_enc',
    'pzr_cloud_registered',
    'pzr_fallback_salt',
    'pzr_license',
    'pzr_license_remote',
    'pzr_trial_used',
    'pzr_db_version',
];
foreach ($options as $option) {
    delete_option($option);
}

// Cached, regenerable transients.
foreach (['pzr_update_feed', 'pzr_seo_ideas'] as $transient) {
    delete_transient($transient);
}

// Per-user "chats seen" markers.
delete_metadata('user', 0, 'pzr_chats_seen_at', '', true);
