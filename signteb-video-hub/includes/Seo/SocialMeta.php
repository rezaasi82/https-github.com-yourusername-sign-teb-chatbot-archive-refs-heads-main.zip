<?php

namespace SignTeb\VideoHub\Seo;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Schema\SeoCompat;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 14 — OpenGraph and Twitter cards for video pages.
 *
 * Telegram and WhatsApp both read OpenGraph, so `og:video` plus a large
 * `og:image` is what produces a playable preview in those apps; no separate
 * tag family is needed for them.
 *
 * When RankMath/Yoast is active they own the base tags and we only add the
 * video-specific ones they omit.
 */
class SocialMeta
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function register(): void
    {
        if (! $this->settings->bool('social_meta')) {
            return;
        }
        add_action('wp_head', [$this, 'output'], 5);
    }

    public function output(): void
    {
        if (! is_singular(PostType::POST_TYPE)) {
            return;
        }

        $post_id = get_queried_object_id();
        if ($post_id <= 0) {
            return;
        }

        $data      = VideoMeta::seo_payload($post_id);
        $delegated = SeoCompat::owns_social_meta();
        $tags      = [];

        if (! $delegated) {
            $tags['og:type']        = 'video.other';
            $tags['og:title']       = $data['title'];
            $tags['og:description'] = wp_trim_words($data['description'], 40, '…');
            $tags['og:url']         = $data['url'];
            $tags['og:site_name']   = (string) get_bloginfo('name');
            $tags['og:locale']      = 'fa_IR';

            if ($data['thumbnail'] !== '') {
                $tags['og:image']        = $data['thumbnail'];
                $tags['og:image:width']  = '1280';
                $tags['og:image:height'] = '720';
                $tags['og:image:alt']    = $data['title'];
            }
        }

        // Always ours: the video tags no general SEO plugin emits for a CPT.
        if ($data['embed'] !== '') {
            $tags['og:video']            = $data['embed'];
            $tags['og:video:secure_url'] = $data['embed'];
            $tags['og:video:type']       = 'text/html';
            $tags['og:video:width']      = '1280';
            $tags['og:video:height']     = '720';
        }

        $published = strtotime($data['published']);
        if ($published !== false) {
            $tags['video:release_date'] = wp_date('c', $published);
        }
        if ($data['duration'] > 0) {
            $tags['video:duration'] = (string) $data['duration'];
        }

        $terms = wp_get_post_terms($post_id, PostType::TAXONOMY, ['fields' => 'names']);
        $tag_index = 0;
        if (! is_wp_error($terms)) {
            foreach ($terms as $name) {
                $tags['video:tag:' . $tag_index++] = $name;
            }
        }

        /**
         * Adjust the emitted social tags.
         *
         * @param array<string,string> $tags property => content
         */
        $tags = apply_filters('stvh_social_meta_tags', $tags, $post_id);

        foreach ($tags as $property => $content) {
            if ($content === '') {
                continue;
            }
            // Numbered video:tag keys collapse back to repeated properties.
            $property = preg_replace('/:\d+$/', '', (string) $property);
            printf(
                '<meta property="%s" content="%s" />' . "\n",
                esc_attr((string) $property),
                esc_attr((string) $content)
            );
        }

        if ($delegated) {
            return;
        }

        $twitter = [
            'twitter:card'        => $data['embed'] !== '' ? 'player' : 'summary_large_image',
            'twitter:title'       => $data['title'],
            'twitter:description' => wp_trim_words($data['description'], 30, '…'),
        ];
        if ($data['thumbnail'] !== '') {
            $twitter['twitter:image'] = $data['thumbnail'];
        }
        if ($data['embed'] !== '') {
            $twitter['twitter:player']        = $data['embed'];
            $twitter['twitter:player:width']  = '1280';
            $twitter['twitter:player:height'] = '720';
        }

        foreach ($twitter as $name => $content) {
            if ($content === '') {
                continue;
            }
            printf('<meta name="%s" content="%s" />' . "\n", esc_attr($name), esc_attr((string) $content));
        }
    }
}
