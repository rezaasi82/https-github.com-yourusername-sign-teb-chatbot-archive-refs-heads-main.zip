<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 * Drops the plugin tables and removes all options.
 *
 * @package STMC_Chat
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/Core/Autoloader.php';

if (! defined('STMC_CHAT_DIR')) {
    define('STMC_CHAT_DIR', plugin_dir_path(__FILE__));
}

\STMC_Chat\Core\Autoloader::register();
\STMC_Chat\Database\Schema::uninstall();

delete_option('stmc_chat_settings');
delete_option('stmc_chat_api_key_enc');
delete_option('stmc_chat_fallback_key_enc');
delete_option('stmc_chat_fallback_salt');
delete_option('stmc_chat_license');
delete_option('stmc_chat_db_version');
