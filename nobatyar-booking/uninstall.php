<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('nobatyar_license_check');
wp_clear_scheduled_hook('nobatyar_send_reminders');

delete_option('nobatyar_db_version');
delete_option('nobatyar_terminology_overrides');
