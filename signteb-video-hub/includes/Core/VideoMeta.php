<?php

namespace SignTeb\VideoHub\Core;

use SignTeb\VideoHub\Helpers\Format;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Single source of truth for the video meta keys, plus typed readers.
 *
 * Every other subsystem (schema, sitemap, templates, AI) reads video data
 * through here, so a storage change never ripples outward.
 */
class VideoMeta
{
    public const SOURCE       = '_stvh_source';        // aparat | youtube | manual
    public const SOURCE_ID    = '_stvh_source_id';     // provider-side video id / hash
    public const SOURCE_URL   = '_stvh_source_url';    // watch page on the provider
    public const EMBED_URL    = '_stvh_embed_url';
    public const THUMBNAIL    = '_stvh_thumbnail';
    public const DURATION     = '_stvh_duration';      // seconds
    public const PUBLISHED_AT = '_stvh_published_at';  // Y-m-d H:i:s (site time)
    public const SOURCE_VIEWS = '_stvh_source_views';
    public const CONTENT_HASH = '_stvh_content_hash';  // change detection for sync

    public const AI_SUMMARY  = '_stvh_ai_summary';
    public const AI_KEYPOINTS = '_stvh_ai_keypoints';  // array<string>
    public const AI_FAQ      = '_stvh_ai_faq';         // array<array{q:string,a:string}>
    public const AI_LINKS    = '_stvh_ai_links';       // array<array{url:string,anchor:string}>
    public const AI_ARTICLE  = '_stvh_ai_article';
    public const AI_STATUS   = '_stvh_ai_status';      // '' | pending | done | failed
    public const AI_UPDATED  = '_stvh_ai_updated';

    public static function source(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::SOURCE, true);
    }

    public static function source_id(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::SOURCE_ID, true);
    }

    public static function embed_url(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::EMBED_URL, true);
    }

    public static function source_url(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::SOURCE_URL, true);
    }

    public static function thumbnail(int $post_id): string
    {
        $url = (string) get_post_meta($post_id, self::THUMBNAIL, true);
        if ($url === '' && has_post_thumbnail($post_id)) {
            $url = (string) get_the_post_thumbnail_url($post_id, 'large');
        }
        return $url;
    }

    public static function duration(int $post_id): int
    {
        return (int) get_post_meta($post_id, self::DURATION, true);
    }

    public static function duration_human(int $post_id): string
    {
        return Format::duration(self::duration($post_id));
    }

    public static function duration_iso(int $post_id): string
    {
        return Format::iso8601_duration(self::duration($post_id));
    }

    /**
     * Upload date for schema/sitemap. Falls back to the WordPress post date so
     * a manually added video is never schema-invalid.
     */
    public static function published_at(int $post_id): string
    {
        $value = (string) get_post_meta($post_id, self::PUBLISHED_AT, true);
        if ($value === '') {
            $value = (string) get_post_field('post_date', $post_id);
        }
        return $value;
    }

    public static function summary(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::AI_SUMMARY, true);
    }

    /** @return array<int,string> */
    public static function keypoints(int $post_id): array
    {
        $value = get_post_meta($post_id, self::AI_KEYPOINTS, true);
        return is_array($value) ? array_values(array_filter(array_map('strval', $value))) : [];
    }

    /** @return array<int,array{q:string,a:string}> */
    public static function faq(int $post_id): array
    {
        $value = get_post_meta($post_id, self::AI_FAQ, true);
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q !== '' && $a !== '') {
                $out[] = ['q' => $q, 'a' => $a];
            }
        }
        return $out;
    }

    /** @return array<int,array{url:string,anchor:string}> */
    public static function links(int $post_id): array
    {
        $value = get_post_meta($post_id, self::AI_LINKS, true);
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url    = esc_url_raw((string) ($item['url'] ?? ''));
            $anchor = trim((string) ($item['anchor'] ?? ''));
            if ($url !== '' && $anchor !== '') {
                $out[] = ['url' => $url, 'anchor' => $anchor];
            }
        }
        return $out;
    }

    public static function article(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::AI_ARTICLE, true);
    }

    public static function has_ai(int $post_id): bool
    {
        return self::summary($post_id) !== '';
    }

    /**
     * Poster/description payload shared by schema, sitemap and social meta.
     *
     * @return array{title:string,description:string,thumbnail:string,embed:string,duration:int,published:string,url:string}
     */
    public static function seo_payload(int $post_id): array
    {
        $summary     = self::summary($post_id);
        $description = $summary !== '' ? $summary : (string) get_the_excerpt($post_id);
        if ($description === '') {
            $description = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 40, '…');
        }

        return [
            'title'       => (string) get_the_title($post_id),
            'description' => wp_strip_all_tags($description),
            'thumbnail'   => self::thumbnail($post_id),
            'embed'       => self::embed_url($post_id),
            'duration'    => self::duration($post_id),
            'published'   => self::published_at($post_id),
            'url'         => (string) get_permalink($post_id),
        ];
    }
}
