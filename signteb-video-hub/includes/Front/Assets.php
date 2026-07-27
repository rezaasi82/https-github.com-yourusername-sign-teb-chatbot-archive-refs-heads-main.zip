<?php

namespace SignTeb\VideoHub\Front;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Conditional asset loading: the CSS/JS only ship on pages that actually
 * render a video component. A hub plugin that adds weight to every page on the
 * site is a performance regression disguised as a feature.
 */
class Assets
{
    public const HANDLE = 'stvh-front';

    private Settings $settings;

    /** Set by Renderer/Elementor when a component renders after wp_head. */
    private static bool $forced = false;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue']);
        // Components rendered mid-page (widgets, Elementor, late shortcodes)
        // flag themselves and are picked up here.
        add_action('wp_footer', [$this, 'enqueue_if_forced'], 1);
    }

    /**
     * Called by any component that renders markup depending on these assets.
     */
    public static function force(): void
    {
        self::$forced = true;

        if (did_action('wp_enqueue_scripts') && ! wp_style_is(self::HANDLE, 'enqueued')) {
            (new self())->enqueue();
        }
    }

    public function enqueue_if_forced(): void
    {
        if (self::$forced && ! wp_style_is(self::HANDLE, 'enqueued')) {
            $this->enqueue();
        }
    }

    public function maybe_enqueue(): void
    {
        if ($this->should_load()) {
            $this->enqueue();
        }
    }

    private function should_load(): bool
    {
        if (is_singular(PostType::POST_TYPE) || is_post_type_archive(PostType::POST_TYPE) || is_tax(PostType::TAXONOMY)) {
            return true;
        }

        $post = get_post();
        if ($post instanceof \WP_Post) {
            $content = (string) $post->post_content;
            foreach (['signteb_videos', 'signteb_video', 'signteb_medical_hub'] as $shortcode) {
                if (has_shortcode($content, $shortcode)) {
                    return true;
                }
            }
            if (function_exists('has_block') && has_block('signteb/video-hub', $post)) {
                return true;
            }
        }

        /**
         * Force the front-end assets onto a page we could not detect.
         *
         * @param bool $load
         */
        return (bool) apply_filters('stvh_load_assets', false);
    }

    public function enqueue(): void
    {
        wp_enqueue_style(
            self::HANDLE,
            STVH_URL . 'assets/css/front.css',
            [],
            STVH_VERSION
        );

        wp_enqueue_script(
            self::HANDLE,
            STVH_URL . 'assets/js/front.js',
            [],
            STVH_VERSION,
            true
        );

        wp_localize_script(self::HANDLE, 'STVH', [
            'restUrl'   => esc_url_raw(rest_url('signteb-video/v1/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'analytics' => $this->settings->bool('analytics_enabled'),
            'i18n'      => [
                'loading'   => __('در حال بارگذاری…', 'signteb-video-hub'),
                'empty'     => __('ویدئویی مطابق جستجوی شما پیدا نشد.', 'signteb-video-hub'),
                'error'     => __('دریافت ویدئوها ناموفق بود. دوباره تلاش کنید.', 'signteb-video-hub'),
                'more'      => __('نمایش بیشتر', 'signteb-video-hub'),
                'play'      => __('پخش ویدئو', 'signteb-video-hub'),
            ],
        ]);

        $this->inline_theme();
    }

    /**
     * The accent colour is a setting, so it is the one piece of styling that
     * cannot live in the static stylesheet. Dark mode is handled by a
     * data-theme attribute on the container instead (see Renderer).
     */
    private function inline_theme(): void
    {
        $accent = $this->settings->str('accent_color');
        if (! preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) {
            $accent = '#0a84ff';
        }

        wp_add_inline_style(self::HANDLE, ':root{--stvh-accent:' . $accent . ';}');
    }
}
