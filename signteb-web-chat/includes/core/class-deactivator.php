<?php
/**
 * SWC_Deactivator — deactivation cleanup.
 *
 * Tables are intentionally NOT dropped here; data is only removed on uninstall
 * (uninstall.php) so a deactivate/reactivate cycle never destroys history.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('swc_license_check');
        flush_rewrite_rules();
    }
}
