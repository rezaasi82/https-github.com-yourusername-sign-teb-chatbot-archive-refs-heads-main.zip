<?php

namespace SignTeb\VideoHub\Ai;

use SignTeb\VideoHub\Core\PostType;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Keeps a generated article from competing with pages the site already has.
 *
 * Writing an 800-word article on the same keyword as an existing page splits
 * the signals between two URLs: Google picks one, usually not the one you
 * wanted, and both rank worse than the single page would have. The video page
 * itself is the first thing an article about that video would cannibalise.
 *
 * So before generating, the site is searched for content already targeting the
 * keyword, and the model is told to take a different angle and link to those
 * pages instead of re-covering them.
 */
class CannibalizationGuard
{
    private const MAX_COMPETITORS = 6;

    /**
     * Existing content targeting the same keyword.
     *
     * @return array<int,array{id:int,title:string,url:string,type:string}>
     */
    public function competitors(int $post_id, string $keyword): array
    {
        if ($keyword === '') {
            return [];
        }

        $query = new WP_Query([
            'post_type'      => ['post', 'page', PostType::POST_TYPE],
            'post_status'    => 'publish',
            'posts_per_page' => self::MAX_COMPETITORS + 1,
            's'              => $keyword,
            'no_found_rows'  => true,
            'post__not_in'   => [$post_id],
        ]);

        $out = [];
        foreach ($query->posts as $post) {
            $out[] = [
                'id'    => (int) $post->ID,
                'title' => (string) get_the_title($post),
                'url'   => (string) get_permalink($post),
                'type'  => (string) $post->post_type,
            ];

            if (count($out) >= self::MAX_COMPETITORS) {
                break;
            }
        }

        /**
         * Adjust the pages an article must not compete with.
         *
         * @param array<int,array{id:int,title:string,url:string,type:string}> $out
         */
        return apply_filters('stvh_cannibalization_competitors', $out, $post_id, $keyword);
    }

    /**
     * Prompt fragment describing what already exists and how to stay clear of
     * it. Empty when the site has nothing on this keyword yet.
     */
    public function instruction(int $post_id, string $keyword): string
    {
        $competitors = $this->competitors($post_id, $keyword);
        if ($competitors === []) {
            return '';
        }

        $lines = [];
        foreach ($competitors as $item) {
            $lines[] = sprintf('- %s — %s', $item['title'], $item['url']);
        }

        return implode("\n", [
            '',
            'هشدار هم‌نوع‌خواری کلمه کلیدی (cannibalization):',
            'این صفحات از قبل روی همین موضوع در سایت منتشر شده‌اند:',
            implode("\n", $lines),
            '',
            'قواعد اجباری:',
            '- عنوان یا زاویه‌ی این صفحات را تکرار نکن. مقاله باید زاویه‌ی متفاوتی داشته باشد',
            '  (مثلاً تجربه‌ی بیمار، مراحل آماده‌سازی، مقایسه، یا پرسش‌های بعد از درمان).',
            '- مطالبی که این صفحات کامل پوشش داده‌اند را دوباره و مفصل ننویس؛ خلاصه اشاره کن و',
            '  با لینک به همان صفحه ارجاع بده.',
            '- حداقل به دو مورد از آدرس‌های بالا لینک بده و از متن لینک توصیفی استفاده کن.',
            '- صفحه‌ی ویدئوی اصلی همچنان باید مرجع اصلی این کلمه کلیدی بماند؛ مقاله نقش مکمل دارد.',
        ]);
    }

    /**
     * Human-readable warning for the editor screen.
     *
     * @return array{count:int,items:array<int,array{id:int,title:string,url:string,type:string}>}
     */
    public function report(int $post_id): array
    {
        $competitors = $this->competitors($post_id, FocusKeyword::for_post($post_id));

        return ['count' => count($competitors), 'items' => $competitors];
    }
}
