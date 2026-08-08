<?php

namespace SignTeb\VideoHub\Core;

use SignTeb\VideoHub\Cron\Scheduler;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Deactivation: stop background work and clean up rewrite rules.
 *
 * No data is removed here — that only happens on uninstall.
 */
class Deactivator
{
    public static function deactivate(): void
    {
        Scheduler::unschedule_all();

        delete_transient('stvh_sitemap_xml');
        delete_transient('stvh_google_token');

        flush_rewrite_rules(false);
    }
}
