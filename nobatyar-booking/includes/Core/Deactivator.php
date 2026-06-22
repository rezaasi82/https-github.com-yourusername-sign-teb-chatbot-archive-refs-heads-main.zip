<?php

namespace Nobatyar\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('nobatyar_license_check');
        wp_clear_scheduled_hook('nobatyar_send_reminders');
    }
}
