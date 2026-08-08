<?php

namespace SignTeb\VideoHub\Ai;

use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Helpers\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 3 — summary, key points and FAQ for an imported video.
 *
 * The FAQ output feeds the FAQPage schema, so it is generated in the same
 * call: one request produces everything the SEO layer needs.
 */
class SummaryGenerator
{
    private AiManager $ai;

    public function __construct(?AiManager $ai = null)
    {
        $this->ai = $ai ?? new AiManager();
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function generate(int $post_id): array
    {
        $post = get_post($post_id);
        if ($post === null) {
            return ['ok' => false, 'error' => 'ویدئو پیدا نشد.'];
        }

        $result = $this->ai->ask(
            $this->ai->system_prompt('summary'),
            $this->prompt($post_id),
            ['max_tokens' => 2000, 'temperature' => 0.4]
        );

        if (! $result['ok']) {
            update_post_meta($post_id, VideoMeta::AI_STATUS, 'failed');
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'خطای هوش مصنوعی.')];
        }

        $parsed = $this->parse((string) $result['content']);
        if ($parsed['summary'] === '') {
            update_post_meta($post_id, VideoMeta::AI_STATUS, 'failed');
            Logger::warning('ai', 'پاسخ خلاصه قابل تجزیه نبود.', ['post_id' => $post_id]);
            return ['ok' => false, 'error' => 'ساختار پاسخ مدل معتبر نبود.'];
        }

        update_post_meta($post_id, VideoMeta::AI_SUMMARY, $parsed['summary']);
        update_post_meta($post_id, VideoMeta::AI_KEYPOINTS, $parsed['keypoints']);
        update_post_meta($post_id, VideoMeta::AI_FAQ, $parsed['faq']);
        update_post_meta($post_id, VideoMeta::AI_STATUS, 'done');
        update_post_meta($post_id, VideoMeta::AI_UPDATED, current_time('mysql'));

        // The excerpt drives search results and social cards; an AI summary is
        // strictly better than a truncated provider description.
        if (trim((string) $post->post_excerpt) === '') {
            wp_update_post([
                'ID'           => $post_id,
                'post_excerpt' => wp_trim_words($parsed['summary'], 45, '…'),
            ]);
        }

        // Aparat descriptions are often empty, which left post_content empty
        // and gave RankMath nothing to analyse. Write the real body.
        (new ContentComposer())->maybe_write($post_id);

        do_action('stvh_ai_summary_generated', $post_id, $parsed);

        return ['ok' => true];
    }

    private function prompt(int $post_id): string
    {
        $title       = (string) get_the_title($post_id);
        $description = wp_strip_all_tags((string) get_post_field('post_content', $post_id));
        $description = mb_substr($description, 0, 3000);
        $duration    = VideoMeta::duration_human($post_id);

        return implode("\n", [
            'اطلاعات ویدئوی پزشکی:',
            'عنوان: ' . $title,
            $duration !== '' ? 'مدت: ' . $duration : '',
            'توضیحات منتشرشده: ' . ($description !== '' ? $description : '(ندارد)'),
            '',
            'بر اساس همین اطلاعات، خروجی را دقیقاً به صورت JSON و بدون هیچ متن اضافه بده:',
            '{',
            '  "summary": "خلاصه ۳ تا ۵ جمله‌ای از موضوع ویدئو",',
            '  "keypoints": ["۴ تا ۶ نکته مهم، هر کدام یک جمله کوتاه"],',
            '  "faq": [{"q": "سوال متداول بیمار", "a": "پاسخ کوتاه ۲ تا ۳ جمله‌ای"}]',
            '}',
            '',
            'حداقل ۳ و حداکثر ۶ سوال متداول بنویس. اگر توضیحات کافی نبود، فقط بر اساس عنوان و دانش عمومی پزشکی و بدون ادعای غیرمستند بنویس.',
        ]);
    }

    /**
     * @return array{summary:string,keypoints:array<int,string>,faq:array<int,array{q:string,a:string}>}
     */
    private function parse(string $raw): array
    {
        $data = Json::extract($raw);

        $summary = trim(wp_strip_all_tags((string) ($data['summary'] ?? '')));

        $keypoints = [];
        foreach ((array) ($data['keypoints'] ?? []) as $point) {
            $point = trim(wp_strip_all_tags((string) $point));
            if ($point !== '') {
                $keypoints[] = $point;
            }
        }

        $faq = [];
        foreach ((array) ($data['faq'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = trim(wp_strip_all_tags((string) ($item['q'] ?? $item['question'] ?? '')));
            $a = trim(wp_strip_all_tags((string) ($item['a'] ?? $item['answer'] ?? '')));
            if ($q !== '' && $a !== '') {
                $faq[] = ['q' => $q, 'a' => $a];
            }
        }

        return [
            'summary'   => $summary,
            'keypoints' => array_slice($keypoints, 0, 8),
            'faq'       => array_slice($faq, 0, 8),
        ];
    }
}
