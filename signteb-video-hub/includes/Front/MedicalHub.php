<?php

namespace SignTeb\VideoHub\Front;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Db\VideoRepository;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 18 — the AI Medical Hub block.
 *
 * Given any page (a video, an article, a condition page) it assembles the
 * related article, video, questions, physician, services and booking CTA into
 * one end-of-page block. Retrieval is topic-first and keyword-second, so it
 * works on pages that have no video taxonomy at all.
 */
class MedicalHub
{
    private Settings $settings;
    private VideoRepository $videos;
    private Renderer $renderer;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->videos   = new VideoRepository();
        $this->renderer = new Renderer($this->settings);
    }

    /**
     * @param array<string,mixed> $args
     */
    public function render(int $post_id, array $args = []): string
    {
        if (! $this->settings->bool('hub_enabled') || $post_id <= 0) {
            return '';
        }

        $videos    = $this->related_videos($post_id, (int) ($args['videos'] ?? 3));
        $articles  = $this->related_articles($post_id, (int) ($args['articles'] ?? 3));
        $questions = $this->related_questions($videos, (int) ($args['questions'] ?? 4));
        $services  = $this->services($post_id);
        $booking   = $this->settings->str('booking_url');
        $physician = $this->settings->str('physician_name');

        if ($videos === [] && $articles === [] && $questions === [] && $services === [] && $booking === '') {
            return '';
        }

        Assets::force();

        $title = (string) ($args['title'] ?? __('ادامه مسیر شما', 'signteb-video-hub'));

        ob_start();
        ?>
        <section class="stvh-medhub stvh-scope" data-theme="<?php echo esc_attr($this->settings->str('dark_mode')); ?>">
            <header class="stvh-medhub__head">
                <h2 class="stvh-medhub__title"><?php echo esc_html($title); ?></h2>
            </header>

            <div class="stvh-medhub__grid">
                <?php if ($videos !== []) : ?>
                    <div class="stvh-medhub__col stvh-medhub__col--videos">
                        <h3 class="stvh-medhub__label"><?php esc_html_e('ویدئوهای مرتبط', 'signteb-video-hub'); ?></h3>
                        <ul class="stvh-medhub__list">
                            <?php foreach ($videos as $video_id) : ?>
                                <li class="stvh-medhub__item">
                                    <a href="<?php echo esc_url((string) get_permalink($video_id)); ?>" data-stvh-click data-video-id="<?php echo esc_attr((string) $video_id); ?>">
                                        <?php $thumb = VideoMeta::poster($video_id, 'medium'); ?>
                                        <?php if ($thumb !== '') : ?>
                                            <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" width="160" height="90">
                                        <?php endif; ?>
                                        <span class="stvh-medhub__item-title"><?php echo esc_html((string) get_the_title($video_id)); ?></span>
                                        <?php $duration = VideoMeta::duration_human($video_id); ?>
                                        <?php if ($duration !== '') : ?>
                                            <span class="stvh-medhub__badge"><?php echo esc_html($duration); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($articles !== []) : ?>
                    <div class="stvh-medhub__col stvh-medhub__col--articles">
                        <h3 class="stvh-medhub__label"><?php esc_html_e('مقالات مرتبط', 'signteb-video-hub'); ?></h3>
                        <ul class="stvh-medhub__list stvh-medhub__list--text">
                            <?php foreach ($articles as $article_id) : ?>
                                <li class="stvh-medhub__item">
                                    <a href="<?php echo esc_url((string) get_permalink($article_id)); ?>">
                                        <?php echo esc_html((string) get_the_title($article_id)); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($questions !== []) : ?>
                    <div class="stvh-medhub__col stvh-medhub__col--questions">
                        <h3 class="stvh-medhub__label"><?php esc_html_e('سوالات مرتبط', 'signteb-video-hub'); ?></h3>
                        <?php foreach ($questions as $item) : ?>
                            <details class="stvh-faq">
                                <summary class="stvh-faq__q"><?php echo esc_html($item['q']); ?></summary>
                                <div class="stvh-faq__a"><?php echo esc_html($item['a']); ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($physician !== '' || $services !== [] || $booking !== '') : ?>
                <div class="stvh-medhub__cta">
                    <?php if ($physician !== '') : ?>
                        <div class="stvh-medhub__doctor">
                            <span class="stvh-medhub__doctor-name"><?php echo esc_html($physician); ?></span>
                            <?php $specialty = $this->settings->str('physician_specialty'); ?>
                            <?php if ($specialty !== '') : ?>
                                <span class="stvh-medhub__doctor-specialty"><?php echo esc_html($specialty); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($services !== []) : ?>
                        <ul class="stvh-medhub__services">
                            <?php foreach ($services as $service) : ?>
                                <li><a href="<?php echo esc_url($service['url']); ?>"><?php echo esc_html($service['anchor']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($booking !== '') : ?>
                        <a class="stvh-btn stvh-btn--primary" href="<?php echo esc_url($booking); ?>">
                            <?php esc_html_e('رزرو نوبت', 'signteb-video-hub'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @return array<int,int>
     */
    private function related_videos(int $post_id, int $limit): array
    {
        $limit = max(1, min(8, $limit));

        if (get_post_type($post_id) === PostType::POST_TYPE) {
            return $this->videos->related($post_id, $limit);
        }

        // Non-video page: match on the topic slugs/names present in the title.
        $matched = $this->match_topics((string) get_the_title($post_id));
        if ($matched !== []) {
            $query = new WP_Query([
                'post_type'      => PostType::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'tax_query'      => [[
                    'taxonomy' => PostType::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => $matched,
                ]],
            ]);
            if ($query->posts !== []) {
                return array_map('intval', $query->posts);
            }
        }

        return $this->videos->query(['per_page' => $limit])['ids'];
    }

    /**
     * @return array<int,int>
     */
    private function related_articles(int $post_id, int $limit): array
    {
        $limit    = max(1, min(8, $limit));
        $keywords = $this->keywords((string) get_the_title($post_id));

        if ($keywords !== []) {
            $query = new WP_Query([
                'post_type'      => ['post', 'page'],
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'post__not_in'   => [$post_id],
                's'              => implode(' ', array_slice($keywords, 0, 3)),
            ]);
            if ($query->posts !== []) {
                return array_map('intval', $query->posts);
            }
        }

        $recent = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post__not_in'   => [$post_id],
        ]);

        return array_map('intval', $recent->posts);
    }

    /**
     * Pull FAQ entries off the related videos — the questions are already
     * generated and reviewed, so the hub reuses them rather than inventing new
     * ones at render time.
     *
     * @param array<int,int> $video_ids
     * @return array<int,array{q:string,a:string}>
     */
    private function related_questions(array $video_ids, int $limit): array
    {
        $questions = [];
        foreach ($video_ids as $video_id) {
            foreach (VideoMeta::faq($video_id) as $item) {
                $questions[$item['q']] = $item;
                if (count($questions) >= max(1, $limit)) {
                    break 2;
                }
            }
        }
        return array_values($questions);
    }

    /**
     * Service links come from the admin's internal-link map: those are the
     * commercially meaningful URLs, so they are the right CTA targets.
     *
     * @return array<int,array{url:string,anchor:string}>
     */
    private function services(int $post_id): array
    {
        $links = VideoMeta::links($post_id);
        if ($links !== []) {
            return array_slice($links, 0, 4);
        }

        $raw = $this->settings->str('internal_link_map');
        if ($raw === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $url   = str_starts_with($parts[0], 'http') ? $parts[0] : home_url('/' . ltrim($parts[0], '/'));
            $out[] = ['url' => esc_url_raw($url), 'anchor' => $parts[1] ?? $parts[0]];
            if (count($out) >= 4) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<int,int> term ids
     */
    private function match_topics(string $text): array
    {
        $text  = mb_strtolower($text);
        $terms = get_terms(['taxonomy' => PostType::TAXONOMY, 'hide_empty' => true]);
        if (is_wp_error($terms)) {
            return [];
        }

        $matched = [];
        foreach ($terms as $term) {
            if (str_contains($text, mb_strtolower($term->name)) || str_contains($text, $term->slug)) {
                $matched[] = (int) $term->term_id;
            }
        }
        return $matched;
    }

    /**
     * @return array<int,string>
     */
    private function keywords(string $text): array
    {
        $text  = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return array_values(array_filter(
            $words,
            static fn(string $word): bool => mb_strlen($word) > 3
        ));
    }
}
