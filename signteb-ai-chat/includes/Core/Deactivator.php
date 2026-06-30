<?php

namespace STMC_Chat\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Deactivation cleanup. Tables are intentionally NOT dropped here — data is
 * only removed on uninstall (see uninstall.php) so a deactivate/reactivate
 * cycle never destroys conversation history.
 */
class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('stmc_chat_license_check');
        flush_rewrite_rules();
    }
}
