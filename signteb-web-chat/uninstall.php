<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 * Drops the plugin tables and removes every option it created.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! defined('SWC_DIR')) {
    define('SWC_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/includes/class-autoloader.php';
SWC_Autoloader::register();

if (class_exists('SWC_Schema')) {
    SWC_Schema::uninstall();
}

$options = [
    'swc_settings',
    'swc_api_key_anthropic_enc',
    'swc_api_key_openai_enc',
    'swc_api_key_gapgpt_enc',
    'swc_webhook_secret_enc',
    'swc_gsheet_secret_enc',
    'swc_cloud_secret_enc',
    'swc_cloud_registered',
    'swc_fallback_salt',
    'swc_license',
    'swc_license_remote',
    'swc_trial_used',
    'swc_db_version',
];
foreach ($options as $option) {
    delete_option($option);
}

// Cached, regenerable transients.
foreach (['swc_update_feed', 'swc_seo_ideas'] as $transient) {
    delete_transient($transient);
}
