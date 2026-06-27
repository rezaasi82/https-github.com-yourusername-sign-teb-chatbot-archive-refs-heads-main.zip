<?php

namespace Nobatyar\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Re-runs dbDelta() against the current table definitions whenever the
 * stored nobatyar_db_version option is behind NOBATYAR_DB_VERSION. dbDelta()
 * only adds missing tables/columns — it never drops or truncates — so this
 * is safe on a site with live data, unlike Activator::activate() which only
 * fires once at plugin activation and is never seen again on existing
 * installs that are simply updated to a newer plugin version.
 */
class Migrator
{
    public static function maybe_upgrade(): void
    {
        $current = get_option('nobatyar_db_version');

        if ($current === NOBATYAR_DB_VERSION) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        Activator::create_tables();

        update_option('nobatyar_db_version', NOBATYAR_DB_VERSION);
    }
}
