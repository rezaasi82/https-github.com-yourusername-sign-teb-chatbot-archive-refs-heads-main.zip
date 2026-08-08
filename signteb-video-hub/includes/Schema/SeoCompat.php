<?php

namespace SignTeb\VideoHub\Schema;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 10 — coexist with RankMath and Yoast instead of fighting them.
 *
 * Those plugins already emit WebPage, BreadcrumbList and Organization nodes.
 * Emitting our own would produce duplicate entities in one document, which
 * Google treats as conflicting. So when one of them is active we contribute
 * only the pieces they do not: VideoObject, FAQPage and ItemList.
 */
class SeoCompat
{
    public static function rankmath_active(): bool
    {
        return defined('RANK_MATH_VERSION') || class_exists('\RankMath');
    }

    public static function yoast_active(): bool
    {
        return defined('WPSEO_VERSION') || class_exists('\WPSEO_Options');
    }

    public static function any_seo_plugin(): bool
    {
        return self::rankmath_active() || self::yoast_active() || self::seopress_active();
    }

    public static function seopress_active(): bool
    {
        return defined('SEOPRESS_VERSION');
    }

    /**
     * True when another plugin already owns the page-level entities.
     */
    public static function owns_page_schema(): bool
    {
        /**
         * Force our own WebPage/Breadcrumb output back on.
         *
         * @param bool $owned
         */
        return (bool) apply_filters('stvh_seo_plugin_owns_page_schema', self::any_seo_plugin());
    }

    /**
     * True when another plugin already emits the social/OpenGraph tags.
     */
    public static function owns_social_meta(): bool
    {
        /**
         * Force our own OpenGraph output back on.
         *
         * @param bool $owned
         */
        return (bool) apply_filters('stvh_seo_plugin_owns_social_meta', self::any_seo_plugin());
    }

    /**
     * Human-readable list for the dashboard's compatibility panel.
     *
     * @return array<string,bool>
     */
    public static function detected(): array
    {
        return [
            'RankMath' => self::rankmath_active(),
            'Yoast SEO' => self::yoast_active(),
            'SEOPress'  => self::seopress_active(),
        ];
    }
}
