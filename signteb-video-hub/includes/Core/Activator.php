<?php

namespace SignTeb\VideoHub\Core;

use SignTeb\VideoHub\Cron\Scheduler;
use SignTeb\VideoHub\Db\Schema;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Activation and version upgrades.
 */
class Activator
{
    public static function activate(): void
    {
        Schema::install();

        // The rewrite rules for /videos/ and /video-sitemap.xml only exist
        // once the post type and taxonomy have been registered.
        $post_type = new PostType();
        $post_type->register_post_type();
        $post_type->register_taxonomy();
        PostType::seed_topics();

        add_rewrite_rule('^' . \SignTeb\VideoHub\Seo\VideoSitemap::PATH . '$', 'index.php?' . \SignTeb\VideoHub\Seo\VideoSitemap::QUERY_VAR . '=1', 'top');
        flush_rewrite_rules(false);

        self::seed_settings();

        (new Scheduler())->schedule_all();

        update_option('stvh_version', STVH_VERSION, false);
    }

    /**
     * Runs on every boot; cheap when nothing changed.
     */
    public static function maybe_upgrade(): void
    {
        if (Schema::needs_upgrade()) {
            Schema::install();
        }

        if (get_option('stvh_version') !== STVH_VERSION) {
            update_option('stvh_version', STVH_VERSION, false);
            // Rewrite rules can change between versions; regenerate once.
            add_action('shutdown', static function (): void {
                flush_rewrite_rules(false);
            });
        }

        (new Scheduler())->schedule_all();
    }

    /**
     * Write the defaults on first activation so the settings screen never
     * renders against an empty option.
     */
    private static function seed_settings(): void
    {
        $existing = get_option('stvh_settings', null);
        if (is_array($existing) && $existing !== []) {
            return;
        }

        Settings::save(Settings::DEFAULTS);
    }
}
