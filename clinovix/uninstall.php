<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 * Drops the plugin tables and removes every option it created.
 *
 * @package Clinovix
 */

if (! defined('WP_UNINSTALL_PLUGIN') || ! defined('ABSPATH')) {
    exit;
}

if (! defined('CLX_DIR')) {
    define('CLX_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/includes/class-autoloader.php';
\Clinovix\Autoloader::register();

if (class_exists('\Clinovix\Database\Schema')) {
    \Clinovix\Database\Schema::uninstall();
}

$options = [
    'clx_settings',
    'clx_api_key_anthropic_enc',
    'clx_api_key_openai_enc',
    'clx_api_key_gapgpt_enc',
    'clx_webhook_secret_enc',
    'clx_gsheet_secret_enc',
    'clx_cloud_secret_enc',
    'clx_sms_key_enc',
    'clx_sms_secret_enc',
    'clx_msgr_bale_token_enc',
    'clx_msgr_tg_token_enc',
    'clx_cloud_registered',
    'clx_fallback_salt',
    'clx_license',
    'clx_license_remote',
    'clx_trial_used',
    'clx_db_version',
];
foreach ($options as $option) {
    delete_option($option);
}

// Cached, regenerable transients.
foreach (['clx_update_feed', 'clx_seo_ideas'] as $transient) {
    delete_transient($transient);
}

// Per-user "chats seen" markers.
delete_metadata('user', 0, 'clx_chats_seen_at', '', true);
