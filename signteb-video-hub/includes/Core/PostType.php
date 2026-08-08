<?php

namespace SignTeb\VideoHub\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the video post type and its topic taxonomy.
 *
 * URL design (feature 17): singles live under /video/{slug}/ while the topic
 * taxonomy owns /videos/{topic}/ and the post type archive owns /videos/.
 * Keeping singles on a different base is what lets every topic page exist as a
 * real, indexable URL without colliding with a video permalink.
 */
class PostType
{
    public const POST_TYPE = 'stvh_video';
    public const TAXONOMY  = 'stvh_topic';
    public const ARCHIVE   = 'videos';

    /**
     * Topics created on activation so /videos/{slug}/ exists from day one.
     *
     * @var array<string,string> slug => Persian name
     */
    public const DEFAULT_TOPICS = [
        'endoscopy'   => 'آندوسکوپی',
        'colonoscopy' => 'کولونوسکوپی',
        'fibroscan'   => 'فیبرواسکن',
        'liver'       => 'کبد',
        'reflux'      => 'رفلاکس',
        'ibs'         => 'روده تحریک‌پذیر',
        'stomach'     => 'معده',
        'cancer'      => 'سرطان دستگاه گوارش',
        'faq'         => 'سوالات متداول',
    ];

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type'], 5);
        add_action('init', [$this, 'register_taxonomy'], 5);
        add_action('init', [$this, 'register_meta'], 6);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => __('ویدئوها', 'signteb-video-hub'),
                'singular_name'      => __('ویدئو', 'signteb-video-hub'),
                'add_new'            => __('افزودن ویدئو', 'signteb-video-hub'),
                'add_new_item'       => __('افزودن ویدئوی جدید', 'signteb-video-hub'),
                'edit_item'          => __('ویرایش ویدئو', 'signteb-video-hub'),
                'search_items'       => __('جستجوی ویدئو', 'signteb-video-hub'),
                'not_found'          => __('ویدئویی یافت نشد.', 'signteb-video-hub'),
                'all_items'          => __('همه ویدئوها', 'signteb-video-hub'),
                'menu_name'          => __('ویدئوها', 'signteb-video-hub'),
            ],
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => 'stvh-hub',
            'show_in_rest'       => true,
            'has_archive'        => self::ARCHIVE,
            'rewrite'            => ['slug' => 'video', 'with_front' => false],
            'menu_icon'          => 'dashicons-video-alt3',
            'supports'           => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
            'taxonomies'         => [self::TAXONOMY],
            'capability_type'    => 'post',
        ]);
    }

    public function register_taxonomy(): void
    {
        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], [
            'labels' => [
                'name'          => __('موضوعات ویدئو', 'signteb-video-hub'),
                'singular_name' => __('موضوع', 'signteb-video-hub'),
                'search_items'  => __('جستجوی موضوع', 'signteb-video-hub'),
                'all_items'     => __('همه موضوعات', 'signteb-video-hub'),
                'edit_item'     => __('ویرایش موضوع', 'signteb-video-hub'),
                'add_new_item'  => __('افزودن موضوع', 'signteb-video-hub'),
                'menu_name'     => __('موضوعات', 'signteb-video-hub'),
            ],
            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => ['slug' => self::ARCHIVE, 'with_front' => false, 'hierarchical' => false],
        ]);
    }

    /**
     * Meta is registered (not just written) so REST, the block editor and
     * third-party SEO plugins can read it without touching the database.
     */
    public function register_meta(): void
    {
        $string = ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'default' => ''];
        $number = ['type' => 'integer', 'single' => true, 'show_in_rest' => true, 'default' => 0];

        foreach (
            [
                VideoMeta::SOURCE,
                VideoMeta::SOURCE_ID,
                VideoMeta::SOURCE_URL,
                VideoMeta::EMBED_URL,
                VideoMeta::THUMBNAIL,
                VideoMeta::PUBLISHED_AT,
                VideoMeta::CONTENT_HASH,
                VideoMeta::AI_SUMMARY,
                VideoMeta::AI_ARTICLE,
                VideoMeta::AI_STATUS,
            ] as $key
        ) {
            register_post_meta(self::POST_TYPE, $key, array_merge($string, [
                'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
            ]));
        }

        foreach ([VideoMeta::DURATION, VideoMeta::SOURCE_VIEWS] as $key) {
            register_post_meta(self::POST_TYPE, $key, array_merge($number, [
                'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
            ]));
        }
    }

    /**
     * Idempotently create the default topic terms.
     */
    public static function seed_topics(): void
    {
        foreach (self::DEFAULT_TOPICS as $slug => $name) {
            if (term_exists($slug, self::TAXONOMY)) {
                continue;
            }
            wp_insert_term($name, self::TAXONOMY, ['slug' => $slug]);
        }
    }
}
