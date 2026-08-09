<?php
/**
 * Deactivation cleanup.
 *
 * Tables are intentionally NOT dropped here; data is only removed on uninstall
 * (uninstall.php) so a deactivate/reactivate cycle never destroys history.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('pzk_license_check');
        wp_clear_scheduled_hook('pzk_webhook_retry');
        wp_clear_scheduled_hook('pzk_cloud_heartbeat');
        wp_clear_scheduled_hook('pzk_cloud_install');
        wp_clear_scheduled_hook('pzk_daily_rollup');
        wp_clear_scheduled_hook('pzk_process_jobs');
        flush_rewrite_rules();
    }
}
