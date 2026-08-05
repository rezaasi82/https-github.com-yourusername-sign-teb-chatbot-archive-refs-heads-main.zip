<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Runs schema and data migrations when the stored DB version trails the
 * shipped one. Safe to call on every request: the version comparison short
 * circuits before any query is issued.
 */
final class Migrator
{
    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option('medora_db_version', '0.0.0');

        if (version_compare($installed, MEDORA_DB_VERSION, '>=')) {
            return;
        }

        // dbDelta is idempotent and additive, so it doubles as the migration
        // path for every release that only adds columns or indexes.
        Activator::installTables();

        foreach (self::migrations() as $version => $migration) {
            if (version_compare($installed, $version, '<')) {
                $migration();
            }
        }

        Capabilities::install();
        Cron::schedule();

        update_option('medora_db_version', MEDORA_DB_VERSION, true);

        /**
         * Fires after a successful upgrade.
         *
         * @param string $from Previously installed DB version.
         * @param string $to   Version now installed.
         */
        do_action('medora_upgraded', $installed, MEDORA_DB_VERSION);
    }

    /**
     * Data migrations keyed by the DB version that introduces them.
     *
     * @return array<string, callable(): void>
     */
    private static function migrations(): array
    {
        return [
            // 0.7.x shipped `default_language => 'en'` as the declared default
            // and read it nowhere. 0.8.0 gives it a job, so a stored 'en' that
            // nobody chose would start declaring English on sites that are not
            // in English. Clearing it restores "follow WordPress", which is
            // what those installs were doing all along.
            '1.1.0' => static function (): void {
                $options = new Options();

                if ($options->getString('default_language') === 'en') {
                    $options->set('default_language', '');
                }
            },
        ];
    }
}
