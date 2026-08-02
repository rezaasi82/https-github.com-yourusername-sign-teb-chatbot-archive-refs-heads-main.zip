<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

final class Activator
{
    public static function activate(): void
    {
        self::checkRequirements();
        self::installTables();

        $options  = new Options();
        $settings = array_merge(Options::defaults(), $options->all());

        // Persist the install fingerprint used for licence binding and for the
        // hashing salt applied to visitor IPs.
        $settings['installed_at'] ??= gmdate('Y-m-d H:i:s');
        $settings['install_hash'] ??= wp_generate_password(32, false, false);

        $options->replace($settings);

        Capabilities::install();
        Cron::schedule();

        update_option('medora_db_version', MEDORA_DB_VERSION, true);
        update_option('medora_needs_onboarding', empty($settings['onboarded']), false);

        // Rewrites for llms.txt and the AI sitemaps are registered by their
        // modules on `init`, which has not run yet during activation.
        set_transient('medora_flush_rewrites', 1, HOUR_IN_SECONDS);
    }

    /**
     * Public so `Migrator` can re-run it on upgrade. `dbDelta()` only adds
     * missing tables, columns and indexes — it never drops data.
     */
    public static function installTables(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (Tables::definitions() as $statement) {
            dbDelta($statement);
        }
    }

    private static function checkRequirements(): void
    {
        $errors = [];

        if (version_compare(PHP_VERSION, '8.2', '<')) {
            $errors[] = __('Medora Authority requires PHP 8.2 or newer.', 'medora-authority');
        }

        if (version_compare(get_bloginfo('version'), '6.4', '<')) {
            $errors[] = __('Medora Authority requires WordPress 6.4 or newer.', 'medora-authority');
        }

        if (! extension_loaded('json')) {
            $errors[] = __('Medora Authority requires the PHP JSON extension.', 'medora-authority');
        }

        if ($errors === []) {
            return;
        }

        deactivate_plugins(MEDORA_PLUGIN_BASENAME);
        wp_die(esc_html(implode(' ', $errors)));
    }
}
