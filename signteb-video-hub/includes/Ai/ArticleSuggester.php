<?php

namespace SignTeb\VideoHub\Ai;

use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 9 — an ~800-word article draft derived from a published video.
 *
 * The draft is stored on the video and can be pushed into a real post from the
 * admin screen; it is never auto-published, because medical copy needs a human
 * review before it goes live.
 */
class ArticleSuggester
{
    private AiManager $ai;
    private Settings $settings;

    public function __construct(?AiManager $ai = null, ?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->ai       = $ai ?? new AiManager($this->settings);
    }

    /**
     * @return array{ok:bool,article?:string,error?:string}
     */
    public function generate(int $post_id): array
    {
        if (get_post($post_id) === null) {
            return ['ok' => false, 'error' => 'ویدئو پیدا نشد.'];
        }

        $result = $this->ai->ask(
            $this->ai->system_prompt('article'),
            $this->prompt($post_id),
            ['max_tokens' => 4000, 'temperature' => 0.6]
        );

        if (! $result['ok']) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'خطای هوش مصنوعی.')];
        }

        $article = $this->clean((string) $result['content']);
        if ($article === '') {
            return ['ok' => false, 'error' => 'پاسخ مدل خالی بود.'];
        }

        update_post_meta($post_id, VideoMeta::AI_ARTICLE, $article);
        do_action('stvh_ai_article_generated', $post_id, $article);

        return ['ok' => true, 'article' => $article];
    }

    /**
     * Turn the stored draft into a real draft post linked back to the video.
     *
     * @return array{ok:bool,post_id?:int,error?:string}
     */
    public function publish_as_draft(int $video_id): array
    {
        $article = VideoMeta::article($video_id);
        if ($article === '') {
            return ['ok' => false, 'error' => 'پیش‌نویس مقاله‌ای برای این ویدئو وجود ندارد.'];
        }

        $existing = (int) get_post_meta($video_id, '_stvh_article_post_id', true);
        if ($existing > 0 && get_post($existing) !== null) {
            return ['ok' => true, 'post_id' => $existing];
        }

        $embed = VideoMeta::embed_url($video_id);
        $body  = $article;
        if ($embed !== '') {
            $body .= "\n\n" . sprintf(
                '<figure class="stvh-article-video"><iframe src="%s" loading="lazy" allowfullscreen title="%s"></iframe></figure>',
                esc_url($embed),
                esc_attr((string) get_the_title($video_id))
            );
        }

        $post_id = wp_insert_post([
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => (string) get_the_title($video_id),
            'post_content' => $body,
            'post_excerpt' => wp_trim_words(wp_strip_all_tags(VideoMeta::summary($video_id)), 40, '…'),
        ], true);

        if (is_wp_error($post_id)) {
            return ['ok' => false, 'error' => $post_id->get_error_message()];
        }

        update_post_meta($video_id, '_stvh_article_post_id', (int) $post_id);
        update_post_meta((int) $post_id, '_stvh_source_video_id', $video_id);

        return ['ok' => true, 'post_id' => (int) $post_id];
    }

    private function prompt(int $post_id): string
    {
        $summary   = VideoMeta::summary($post_id);
        $keypoints = VideoMeta::keypoints($post_id);
        $booking   = $this->settings->str('booking_url');

        $context = $summary !== ''
            ? $summary
            : wp_strip_all_tags((string) get_post_field('post_content', $post_id));

        return implode("\n", [
            'موضوع ویدئو: ' . get_the_title($post_id),
            'خلاصه: ' . mb_substr($context, 0, 1500),
            $keypoints !== [] ? 'نکات کلیدی: ' . implode(' | ', $keypoints) : '',
            '',
            FocusKeyword::heading_instruction($post_id),
            (new CannibalizationGuard())->instruction($post_id, FocusKeyword::for_post($post_id)),
            '',
            'یک مقاله وبلاگی حدود ۸۰۰ کلمه بر اساس همین ویدئو بنویس.',
            'ساختار مورد نیاز:',
            '- یک پاراگراف مقدمه بدون تیتر',
            '- ۴ تا ۶ بخش با تیتر <h2>',
            '- در صورت نیاز زیرتیتر <h3> و فهرست <ul><li>',
            '- یک بخش «سوالات متداول» با <h2> و پرسش‌ها به صورت <h3>',
            '- پاراگراف پایانی با دعوت به مشاوره' . ($booking !== '' ? sprintf(' و لینک رزرو نوبت: %s', $booking) : ''),
            '',
            'خروجی فقط HTML ساده باشد (h2, h3, p, ul, li, strong).',
            'تگ <html>، <body>، بلوک کد یا توضیح اضافه ننویس.',
        ]);
    }

    /**
     * Strip code fences and anything outside the allowed editorial tag set.
     */
    private function clean(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/```(?:html)?\s*(.+?)```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $allowed = [
            'h2'     => [],
            'h3'     => [],
            'h4'     => [],
            'p'      => [],
            'ul'     => [],
            'ol'     => [],
            'li'     => [],
            'strong' => [],
            'em'     => [],
            'br'     => [],
            'a'      => ['href' => [], 'title' => [], 'rel' => []],
            'figure' => ['class' => []],
            'iframe' => ['src' => [], 'loading' => [], 'allowfullscreen' => [], 'title' => []],
        ];

        return trim(wp_kses($raw, $allowed));
    }
}
