<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 * Drops the plugin tables and removes every option it created.
 *
 * @package Pezhkam
 */

if (! defined('WP_UNINSTALL_PLUGIN') || ! defined('ABSPATH')) {
    exit;
}

if (! defined('PZK_DIR')) {
    define('PZK_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/includes/class-autoloader.php';
\Pezhkam\Autoloader::register();

if (class_exists('\Pezhkam\Database\Schema')) {
    \Pezhkam\Database\Schema::uninstall();
}

$options = [
    'pzk_settings',
    'pzk_api_key_anthropic_enc',
    'pzk_api_key_openai_enc',
    'pzk_api_key_gapgpt_enc',
    'pzk_webhook_secret_enc',
    'pzk_gsheet_secret_enc',
    'pzk_cloud_secret_enc',
    'pzk_sms_key_enc',
    'pzk_sms_secret_enc',
    'pzk_msgr_bale_token_enc',
    'pzk_msgr_tg_token_enc',
    'pzk_cloud_registered',
    'pzk_fallback_salt',
    'pzk_license',
    'pzk_license_remote',
    'pzk_trial_used',
    'pzk_db_version',
];
foreach ($options as $option) {
    delete_option($option);
}

// Cached, regenerable transients.
foreach (['pzk_update_feed', 'pzk_seo_ideas'] as $transient) {
    delete_transient($transient);
}

// Per-user "chats seen" markers.
delete_metadata('user', 0, 'pzk_chats_seen_at', '', true);
