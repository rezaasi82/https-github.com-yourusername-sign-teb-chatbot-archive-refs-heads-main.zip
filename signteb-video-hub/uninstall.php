<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 *
 * Tables, options and transients are removed. Imported videos are left in
 * place: they are ordinary posts and deleting a plugin should not silently
 * destroy a site's content library.
 *
 * @package SignTeb\VideoHub
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! defined('STVH_DIR')) {
    define('STVH_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/includes/Core/Autoloader.php';
\SignTeb\VideoHub\Core\Autoloader::register();

\SignTeb\VideoHub\Db\Schema::uninstall();

$stvh_options = [
    'stvh_settings',
    'stvh_ai_api_key_enc',
    'stvh_youtube_api_key_enc',
    'stvh_cloudflare_token_enc',
    'stvh_google_service_json_enc',
    'stvh_fallback_salt',
    'stvh_log',
    'stvh_last_sync',
    'stvh_last_index_ping',
    'stvh_indexing_queue',
    'stvh_cache_version',
    'stvh_version',
];

foreach ($stvh_options as $stvh_option) {
    delete_option($stvh_option);
}

delete_transient('stvh_sitemap_xml');
delete_transient('stvh_google_token');
delete_transient('stvh_link_candidates');

// Namespaced transients (cached grids, per-channel lookups, throttles) have
// generated keys, so they are cleared with a single sweep.
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_stvh\_%'
        OR option_name LIKE '_transient_timeout_stvh\_%'"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
