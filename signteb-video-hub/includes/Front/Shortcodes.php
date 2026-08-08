<?php

namespace SignTeb\VideoHub\Front;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Db\VideoRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Front-end entry points: shortcodes plus the automatic single-video layout.
 */
class Shortcodes
{
    private Settings $settings;
    private Renderer $renderer;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->renderer = new Renderer($this->settings);
    }

    public function register(): void
    {
        add_shortcode('signteb_videos', [$this, 'videos']);
        add_shortcode('signteb_video', [$this, 'single_video']);
        add_shortcode('signteb_medical_hub', [$this, 'medical_hub']);
        add_shortcode('signteb_video_gallery', [$this, 'gallery']);

        // Player, AI panels and the hub are appended to a video's own page
        // unless the active theme provides its own template.
        add_filter('the_content', [$this, 'append_video_layout'], 20);
    }

    /**
     * [signteb_videos layout="grid" topic="liver" per_page="12" orderby="popular"]
     *
     * @param array<string,string>|string $atts
     */
    public function videos($atts = []): string
    {
        $atts = shortcode_atts([
            'title'        => '',
            'subtitle'     => '',
            'layout'       => 'grid',
            'style'        => '',
            'theme'        => '',
            'topic'        => '',
            'per_page'     => (string) $this->settings->int('cards_per_page'),
            'orderby'      => 'date',
            'show_search'  => 'yes',
            'show_filters' => 'yes',
        ], is_array($atts) ? $atts : [], 'signteb_videos');

        return $this->renderer->hub($atts);
    }

    /**
     * [signteb_video_gallery] — the whole library on one page.
     *
     * Same engine as [signteb_videos], with the defaults a full index wants:
     * every video rather than one page of them, and the animated accent border
     * on each card.
     *
     * @param array<string,string>|string $atts
     */
    public function gallery($atts = []): string
    {
        $atts = shortcode_atts([
            'title'        => '',
            'subtitle'     => '',
            'layout'       => 'grid',
            'style'        => 'luxe',
            'theme'        => '',
            'topic'        => '',
            'per_page'     => '48',
            'orderby'      => 'date',
            'show_search'  => 'yes',
            'show_filters' => 'yes',
        ], is_array($atts) ? $atts : [], 'signteb_video_gallery');

        return $this->renderer->hub($atts);
    }

    /**
     * [signteb_video id="123"] — a single embedded player.
     *
     * @param array<string,string>|string $atts
     */
    public function single_video($atts = []): string
    {
        $atts = shortcode_atts(['id' => '0'], is_array($atts) ? $atts : [], 'signteb_video');

        $post_id = (int) $atts['id'];
        if ($post_id <= 0) {
            $latest  = (new VideoRepository())->query(['per_page' => 1])['ids'];
            $post_id = $latest[0] ?? 0;
        }

        if ($post_id <= 0 || get_post_type($post_id) !== PostType::POST_TYPE) {
            return '';
        }

        return $this->renderer->player($post_id);
    }

    /**
     * [signteb_medical_hub] — the related-everything block (feature 18).
     *
     * @param array<string,string>|string $atts
     */
    public function medical_hub($atts = []): string
    {
        $atts = shortcode_atts([
            'title'     => __('ادامه مسیر شما', 'signteb-video-hub'),
            'videos'    => '3',
            'articles'  => '3',
            'questions' => '4',
            'post_id'   => '0',
        ], is_array($atts) ? $atts : [], 'signteb_medical_hub');

        $post_id = (int) $atts['post_id'] ?: get_the_ID();
        if (! $post_id) {
            return '';
        }

        return (new MedicalHub($this->settings))->render((int) $post_id, $atts);
    }

    /**
     * Compose the single-video page without requiring a theme template.
     */
    public function append_video_layout(string $content): string
    {
        if (! is_singular(PostType::POST_TYPE) || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        /**
         * Disable the automatic single-video layout (themes that ship their
         * own single-stvh_video template should turn this off).
         *
         * @param bool $enabled
         */
        if (! apply_filters('stvh_auto_single_layout', true)) {
            return $content;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return $content;
        }

        return $this->renderer->player((int) $post_id)
            . '<div class="stvh-description">' . $content . '</div>'
            . $this->renderer->ai_block((int) $post_id)
            . (new MedicalHub($this->settings))->render((int) $post_id);
    }
}
