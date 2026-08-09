<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 * Drops the plugin tables and removes every option it created.
 *
 * @package Medora
 */

if (! defined('WP_UNINSTALL_PLUGIN') || ! defined('ABSPATH')) {
    exit;
}

if (! defined('MDR_DIR')) {
    define('MDR_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/includes/class-autoloader.php';
\Medora\Autoloader::register();

if (class_exists('\Medora\Database\Schema')) {
    \Medora\Database\Schema::uninstall();
}

$options = [
    'mdr_settings',
    'mdr_api_key_anthropic_enc',
    'mdr_api_key_openai_enc',
    'mdr_api_key_gapgpt_enc',
    'mdr_webhook_secret_enc',
    'mdr_gsheet_secret_enc',
    'mdr_cloud_secret_enc',
    'mdr_sms_key_enc',
    'mdr_sms_secret_enc',
    'mdr_msgr_bale_token_enc',
    'mdr_msgr_tg_token_enc',
    'mdr_cloud_registered',
    'mdr_fallback_salt',
    'mdr_license',
    'mdr_license_remote',
    'mdr_trial_used',
    'mdr_db_version',
];
foreach ($options as $option) {
    delete_option($option);
}

// Cached, regenerable transients.
foreach (['mdr_update_feed', 'mdr_seo_ideas'] as $transient) {
    delete_transient($transient);
}

// Per-user "chats seen" markers.
delete_metadata('user', 0, 'mdr_chats_seen_at', '', true);
