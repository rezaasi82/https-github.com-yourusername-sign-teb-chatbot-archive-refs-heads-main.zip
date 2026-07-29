<?php

namespace SignTeb\VideoHub\Front;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Db\VideoRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Markup for every front-end component (features 6, 7, 8, 15).
 *
 * Cards are rendered identically by PHP and by the REST filter response, so
 * an Ajax-filtered grid is indistinguishable from a server-rendered one — no
 * duplicated template in JavaScript.
 */
class Renderer
{
    private Settings $settings;
    private VideoRepository $videos;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->videos   = new VideoRepository();
    }

    /**
     * The configured theme, normalized.
     *
     * Every `.stvh-scope` root must carry this — the dark rules are keyed on
     * `[data-theme]`, so a scope without it is permanently stuck on the light
     * palette no matter what the visitor or the setting says.
     */
    public function theme(): string
    {
        $theme = $this->settings->str('dark_mode');

        return in_array($theme, ['auto', 'dark', 'light'], true) ? $theme : 'auto';
    }

    /**
     * Full hub component: toolbar (search + filters) plus the results grid.
     *
     * @param array<string,mixed> $args
     */
    public function hub(array $args = []): string
    {
        $args = $this->normalize($args);
        Assets::force();

        $result = $this->videos->query([
            'per_page' => $args['per_page'],
            'page'     => 1,
            'topic'    => $args['topic'],
            'orderby'  => $args['orderby'],
        ]);

        ob_start();
        ?>
        <section class="stvh-hub stvh-scope"
                 data-stvh-hub
                 data-theme="<?php echo esc_attr($args['theme']); ?>"
                 data-layout="<?php echo esc_attr($args['layout']); ?>"
                 data-per-page="<?php echo esc_attr((string) $args['per_page']); ?>"
                 data-topic="<?php echo esc_attr((string) $args['topic']); ?>"
                 data-orderby="<?php echo esc_attr($args['orderby']); ?>"
                 data-pages="<?php echo esc_attr((string) $result['pages']); ?>">

            <?php if ($args['title'] !== '') : ?>
                <header class="stvh-hub__head">
                    <h2 class="stvh-hub__title"><?php echo esc_html($args['title']); ?></h2>
                    <?php if ($args['subtitle'] !== '') : ?>
                        <p class="stvh-hub__subtitle"><?php echo esc_html($args['subtitle']); ?></p>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <?php if ($args['show_search'] || $args['show_filters']) : ?>
                <div class="stvh-toolbar">
                    <?php if ($args['show_search']) : ?>
                        <div class="stvh-search">
                            <svg class="stvh-search__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M10 2a8 8 0 1 0 4.9 14.3l5.4 5.4 1.4-1.4-5.4-5.4A8 8 0 0 0 10 2zm0 2a6 6 0 1 1 0 12 6 6 0 0 1 0-12z"/>
                            </svg>
                            <label class="screen-reader-text" for="stvh-search-<?php echo esc_attr($args['uid']); ?>">
                                <?php esc_html_e('جستجوی ویدئو', 'signteb-video-hub'); ?>
                            </label>
                            <input type="search"
                                   id="stvh-search-<?php echo esc_attr($args['uid']); ?>"
                                   class="stvh-search__input"
                                   data-stvh-search
                                   autocomplete="off"
                                   placeholder="<?php esc_attr_e('جستجو در ویدئوها… مثلاً رفلاکس', 'signteb-video-hub'); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($args['show_filters']) : ?>
                        <?php echo $this->filters((string) $args['topic']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="stvh-status" data-stvh-status role="status" aria-live="polite"></div>

            <div class="stvh-results stvh-results--<?php echo esc_attr($args['layout']); ?>" data-stvh-results>
                <?php echo $this->cards($result['ids']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <?php if ($result['ids'] === []) : ?>
                <p class="stvh-empty"><?php esc_html_e('هنوز ویدئویی منتشر نشده است.', 'signteb-video-hub'); ?></p>
            <?php endif; ?>

            <?php if ($result['pages'] > 1) : ?>
                <div class="stvh-more-wrap">
                    <button type="button" class="stvh-more" data-stvh-more>
                        <?php esc_html_e('نمایش بیشتر', 'signteb-video-hub'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Topic chips (feature 7).
     */
    public function filters(string $active = ''): string
    {
        $terms = get_terms([
            'taxonomy'   => PostType::TAXONOMY,
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (is_wp_error($terms) || $terms === []) {
            return '';
        }

        ob_start();
        ?>
        <div class="stvh-filters" role="group" aria-label="<?php esc_attr_e('فیلتر موضوعی', 'signteb-video-hub'); ?>">
            <button type="button"
                    class="stvh-chip<?php echo ($active === '' || $active === 'all') ? ' is-active' : ''; ?>"
                    data-stvh-filter="all"
                    aria-pressed="<?php echo ($active === '' || $active === 'all') ? 'true' : 'false'; ?>">
                <?php esc_html_e('همه', 'signteb-video-hub'); ?>
            </button>
            <?php foreach ($terms as $term) : ?>
                <?php $is_active = ((string) $term->slug === $active || (string) $term->term_id === $active); ?>
                <button type="button"
                        class="stvh-chip<?php echo $is_active ? ' is-active' : ''; ?>"
                        data-stvh-filter="<?php echo esc_attr($term->slug); ?>"
                        aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                    <?php echo esc_html($term->name); ?>
                    <span class="stvh-chip__count"><?php echo esc_html(number_format_i18n($term->count)); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,int> $ids
     */
    public function cards(array $ids): string
    {
        $out = '';
        foreach ($ids as $id) {
            $out .= $this->card((int) $id);
        }
        return $out;
    }

    public function card(int $post_id): string
    {
        $title     = (string) get_the_title($post_id);
        $permalink = (string) get_permalink($post_id);
        $thumbnail = VideoMeta::thumbnail($post_id);
        $duration  = VideoMeta::duration_human($post_id);
        $summary   = VideoMeta::summary($post_id);
        $excerpt   = $summary !== '' ? $summary : (string) get_the_excerpt($post_id);
        $terms     = get_the_terms($post_id, PostType::TAXONOMY);

        ob_start();
        ?>
        <article class="stvh-card" data-stvh-card data-video-id="<?php echo esc_attr((string) $post_id); ?>">
            <a class="stvh-card__media" href="<?php echo esc_url($permalink); ?>" data-stvh-click
               aria-label="<?php echo esc_attr($title); ?>">
                <?php if ($thumbnail !== '') : ?>
                    <?php // The link already carries the title as its aria-label, so alt="" avoids ?>
                    <?php // a duplicate announcement and a wall of alt text when the poster 404s. ?>
                    <img class="stvh-card__thumb"
                         src="<?php echo esc_url($thumbnail); ?>"
                         alt=""
                         loading="lazy" decoding="async" referrerpolicy="no-referrer"
                         width="640" height="360">
                <?php else : ?>
                    <span class="stvh-card__thumb stvh-card__thumb--empty" aria-hidden="true"></span>
                <?php endif; ?>

                <span class="stvh-card__play" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M8 5v14l11-7z"/></svg>
                </span>

                <?php if ($duration !== '') : ?>
                    <span class="stvh-card__duration"><?php echo esc_html($duration); ?></span>
                <?php endif; ?>
            </a>

            <div class="stvh-card__body">
                <h3 class="stvh-card__title">
                    <a href="<?php echo esc_url($permalink); ?>" data-stvh-click><?php echo esc_html($title); ?></a>
                </h3>

                <?php if ($excerpt !== '') : ?>
                    <p class="stvh-card__excerpt"><?php echo esc_html(wp_trim_words($excerpt, 22, '…')); ?></p>
                <?php endif; ?>

                <div class="stvh-card__meta">
                    <?php if (is_array($terms) && $terms !== []) : ?>
                        <span class="stvh-card__topic"><?php echo esc_html($terms[0]->name); ?></span>
                    <?php endif; ?>
                    <time datetime="<?php echo esc_attr((string) get_the_date('c', $post_id)); ?>">
                        <?php echo esc_html((string) get_the_date('', $post_id)); ?>
                    </time>
                </div>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Click-to-load player facade: the provider iframe is only injected after
     * the visitor asks for it, which keeps third-party requests (and their
     * cookies) off the initial page load.
     */
    public function player(int $post_id): string
    {
        $embed = VideoMeta::embed_url($post_id);
        if ($embed === '') {
            return '';
        }

        Assets::force();

        $title     = (string) get_the_title($post_id);
        $thumbnail = VideoMeta::thumbnail($post_id);

        ob_start();
        ?>
        <div class="stvh-player stvh-scope"
             data-theme="<?php echo esc_attr($this->theme()); ?>"
             data-stvh-player
             data-video-id="<?php echo esc_attr((string) $post_id); ?>"
             data-embed="<?php echo esc_url($embed); ?>"
             data-title="<?php echo esc_attr($title); ?>">
            <button type="button" class="stvh-player__facade" data-stvh-play>
                <?php if ($thumbnail !== '') : ?>
                    <?php // alt="" on purpose: the button already has an accessible name, and a ?>
                    <?php // failed provider image would otherwise dump the whole title over the poster. ?>
                    <img src="<?php echo esc_url($thumbnail); ?>" alt=""
                         loading="lazy" decoding="async" referrerpolicy="no-referrer">
                <?php endif; ?>
                <span class="stvh-player__button" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M8 5v14l11-7z"/></svg>
                </span>
                <span class="screen-reader-text"><?php esc_html_e('پخش ویدئو', 'signteb-video-hub'); ?></span>
            </button>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Summary, key points, FAQ and internal links for a single video.
     */
    public function ai_block(int $post_id): string
    {
        $summary   = VideoMeta::summary($post_id);
        $keypoints = VideoMeta::keypoints($post_id);
        $faq       = VideoMeta::faq($post_id);
        $links     = VideoMeta::links($post_id);

        if ($summary === '' && $keypoints === [] && $faq === [] && $links === []) {
            return '';
        }

        Assets::force();

        ob_start();
        ?>
        <div class="stvh-ai stvh-scope" data-theme="<?php echo esc_attr($this->theme()); ?>">
            <?php if ($summary !== '') : ?>
                <section class="stvh-panel stvh-panel--summary">
                    <h2 class="stvh-panel__title"><?php esc_html_e('خلاصه ویدئو', 'signteb-video-hub'); ?></h2>
                    <p><?php echo esc_html($summary); ?></p>
                </section>
            <?php endif; ?>

            <?php if ($keypoints !== []) : ?>
                <section class="stvh-panel stvh-panel--points">
                    <h2 class="stvh-panel__title"><?php esc_html_e('نکات مهم', 'signteb-video-hub'); ?></h2>
                    <ul class="stvh-points">
                        <?php foreach ($keypoints as $point) : ?>
                            <li><?php echo esc_html($point); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($faq !== []) : ?>
                <section class="stvh-panel stvh-panel--faq">
                    <h2 class="stvh-panel__title"><?php esc_html_e('سوالات متداول', 'signteb-video-hub'); ?></h2>
                    <?php foreach ($faq as $index => $item) : ?>
                        <details class="stvh-faq"<?php echo $index === 0 ? ' open' : ''; ?>>
                            <summary class="stvh-faq__q"><?php echo esc_html($item['q']); ?></summary>
                            <div class="stvh-faq__a"><?php echo esc_html($item['a']); ?></div>
                        </details>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ($links !== []) : ?>
                <section class="stvh-panel stvh-panel--links">
                    <h2 class="stvh-panel__title"><?php esc_html_e('مطالب مرتبط', 'signteb-video-hub'); ?></h2>
                    <ul class="stvh-links">
                        <?php foreach ($links as $link) : ?>
                            <li>
                                <a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['anchor']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Normalize and clamp component arguments from shortcodes/widgets.
     *
     * @param array<string,mixed> $args
     * @return array{title:string,subtitle:string,layout:string,theme:string,per_page:int,topic:string,orderby:string,show_search:bool,show_filters:bool,uid:string}
     */
    public function normalize(array $args): array
    {
        $layouts = ['grid', 'list', 'carousel', 'slider'];
        $orders  = ['date', 'popular', 'title', 'duration', 'random'];

        $layout  = (string) ($args['layout'] ?? 'grid');
        $orderby = (string) ($args['orderby'] ?? 'date');
        $theme   = (string) ($args['theme'] ?? $this->settings->str('dark_mode'));

        return [
            'title'        => sanitize_text_field((string) ($args['title'] ?? '')),
            'subtitle'     => sanitize_text_field((string) ($args['subtitle'] ?? '')),
            'layout'       => in_array($layout, $layouts, true) ? $layout : 'grid',
            'theme'        => in_array($theme, ['auto', 'dark', 'light'], true) ? $theme : 'auto',
            'per_page'     => max(1, min(48, (int) ($args['per_page'] ?? $this->settings->int('cards_per_page')))),
            'topic'        => sanitize_title((string) ($args['topic'] ?? '')),
            'orderby'      => in_array($orderby, $orders, true) ? $orderby : 'date',
            'show_search'  => filter_var($args['show_search'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_filters' => filter_var($args['show_filters'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'uid'          => wp_unique_id('h'),
        ];
    }
}
