<?php
/**
 * Deactivation cleanup.
 *
 * Tables are intentionally NOT dropped here; data is only removed on uninstall
 * (uninstall.php) so a deactivate/reactivate cycle never destroys history.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('swc_license_check');
        wp_clear_scheduled_hook('swc_webhook_retry');
        wp_clear_scheduled_hook('swc_cloud_heartbeat');
        wp_clear_scheduled_hook('swc_cloud_install');
        wp_clear_scheduled_hook('swc_daily_rollup');
        wp_clear_scheduled_hook('swc_process_jobs');
        flush_rewrite_rules();
    }
}
