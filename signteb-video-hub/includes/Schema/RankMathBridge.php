<?php

namespace SignTeb\VideoHub\Schema;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * RankMath-specific coexistence.
 *
 * When RankMath discovers the video post type it adds those URLs to its own
 * XML sitemap. That duplicates our /video-sitemap.xml, and RankMath's entries
 * are the poorer of the two: they carry no <video:*> tags, so they tell Google
 * nothing about the thumbnail, duration or player.
 *
 * We therefore remove the videos from RankMath's sitemap — but only while our
 * own sitemap is switched on, so turning ours off hands discovery straight
 * back to RankMath rather than leaving the videos in neither sitemap.
 */
class RankMathBridge
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function register(): void
    {
        if (! SeoCompat::rankmath_active()) {
            return;
        }

        add_filter('rank_math/sitemap/exclude_post_type', [$this, 'exclude_from_sitemap'], 10, 2);
    }

    /**
     * @param bool   $exclude Whether RankMath already excludes this post type.
     * @param string $type    Post type name.
     */
    public function exclude_from_sitemap($exclude, $type): bool
    {
        if ($type !== PostType::POST_TYPE || ! $this->settings->bool('sitemap_enabled')) {
            return (bool) $exclude;
        }

        /**
         * Keep the videos in RankMath's sitemap despite our own being active.
         *
         * @param bool $exclude
         */
        return (bool) apply_filters('stvh_rankmath_exclude_from_sitemap', true);
    }
}
