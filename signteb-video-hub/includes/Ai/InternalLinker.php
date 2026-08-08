<?php

namespace SignTeb\VideoHub\Ai;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Helpers\Json;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 4 — internal linking.
 *
 * The model only ever *chooses* from a candidate list built from real site
 * URLs; anything it returns that is not in that list is discarded. That makes
 * hallucinated links structurally impossible rather than merely unlikely.
 */
class InternalLinker
{
    private const MAX_CANDIDATES = 60;
    private const MAX_LINKS      = 6;
    private const CACHE_KEY      = 'stvh_link_candidates';

    private AiManager $ai;
    private Settings $settings;

    public function __construct(?AiManager $ai = null, ?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->ai       = $ai ?? new AiManager($this->settings);
    }

    /**
     * @return array{ok:bool,links?:array<int,array{url:string,anchor:string}>,error?:string}
     */
    public function generate(int $post_id): array
    {
        $candidates = $this->candidates($post_id);
        if ($candidates === []) {
            return ['ok' => false, 'error' => 'صفحه‌ای برای لینک داخلی پیدا نشد.'];
        }

        $result = $this->ai->ask(
            $this->ai->system_prompt('links'),
            $this->prompt($post_id, $candidates),
            ['max_tokens' => 1200, 'temperature' => 0.2]
        );

        if (! $result['ok']) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'خطای هوش مصنوعی.')];
        }

        $links = $this->parse((string) $result['content'], $candidates);
        if ($links === []) {
            // Falling back to topical matching keeps the block populated even
            // when the model returns nothing usable.
            $links = $this->fallback_links($candidates);
        }

        update_post_meta($post_id, VideoMeta::AI_LINKS, $links);
        do_action('stvh_ai_links_generated', $post_id, $links);

        return ['ok' => true, 'links' => $links];
    }

    /**
     * Real, linkable URLs on this site: manual map first (highest editorial
     * value), then topic archives, then recent pages and posts.
     *
     * @return array<string,string> url => title
     */
    public function candidates(int $exclude_post_id = 0): array
    {
        $cached = get_transient(self::CACHE_KEY);
        $items  = is_array($cached) ? $cached : $this->build_candidates();

        if (! is_array($cached)) {
            set_transient(self::CACHE_KEY, $items, 6 * HOUR_IN_SECONDS);
        }

        if ($exclude_post_id > 0) {
            unset($items[(string) get_permalink($exclude_post_id)]);
        }

        return array_slice($items, 0, self::MAX_CANDIDATES, true);
    }

    /**
     * @return array<string,string>
     */
    private function build_candidates(): array
    {
        $items = $this->manual_map();

        foreach ((array) get_terms(['taxonomy' => PostType::TAXONOMY, 'hide_empty' => false]) as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }
            $link = get_term_link($term);
            if (! is_wp_error($link)) {
                $items[(string) $link] = sprintf('ویدئوهای %s', $term->name);
            }
        }

        $query = new WP_Query([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => 40,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        foreach ($query->posts as $post) {
            $items[(string) get_permalink($post)] = (string) get_the_title($post);
        }

        /**
         * Adjust the internal-link candidate pool.
         *
         * @param array<string,string> $items url => title
         */
        return apply_filters('stvh_internal_link_candidates', $items);
    }

    /**
     * Admin-provided map, one per line: `/fibroscan/ | فیبرواسکن کبد`.
     *
     * @return array<string,string>
     */
    private function manual_map(): array
    {
        $raw = $this->settings->str('internal_link_map');
        if ($raw === '') {
            return [];
        }

        $items = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $url   = $parts[0];
            $title = $parts[1] ?? $parts[0];

            if (! str_starts_with($url, 'http')) {
                $url = home_url('/' . ltrim($url, '/'));
            }
            $items[esc_url_raw($url)] = $title;
        }

        return $items;
    }

    /**
     * @param array<string,string> $candidates
     */
    private function prompt(int $post_id, array $candidates): string
    {
        $lines = [];
        $index = 1;
        foreach ($candidates as $url => $title) {
            $lines[] = sprintf('%d) %s — %s', $index++, $title, $url);
        }

        $summary = VideoMeta::summary($post_id);
        $body    = $summary !== '' ? $summary : wp_strip_all_tags((string) get_post_field('post_content', $post_id));

        return implode("\n", [
            'ویدئو:',
            'عنوان: ' . get_the_title($post_id),
            'موضوع: ' . mb_substr($body, 0, 1200),
            '',
            'فهرست صفحات موجود سایت:',
            implode("\n", $lines),
            '',
            sprintf('حداکثر %d مورد از مرتبط‌ترین صفحات بالا را انتخاب کن.', self::MAX_LINKS),
            'فقط از همین آدرس‌ها استفاده کن و آدرس جدید نساز.',
            'خروجی دقیقاً JSON زیر باشد و هیچ متن دیگری ننویس:',
            '{"links": [{"url": "آدرس دقیق از فهرست", "anchor": "متن لینک فارسی و طبیعی، حداکثر ۶ کلمه"}]}',
        ]);
    }

    /**
     * @param array<string,string> $candidates
     * @return array<int,array{url:string,anchor:string}>
     */
    private function parse(string $raw, array $candidates): array
    {
        $data  = Json::extract($raw);
        $links = $data['links'] ?? $data;
        if (! is_array($links)) {
            return [];
        }

        $out  = [];
        $seen = [];
        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }
            $url    = esc_url_raw(trim((string) ($link['url'] ?? '')));
            $anchor = trim(wp_strip_all_tags((string) ($link['anchor'] ?? '')));

            if ($url === '' || $anchor === '' || isset($seen[$url])) {
                continue;
            }
            // The allow-list check: invented URLs never reach the database.
            if (! isset($candidates[$url])) {
                continue;
            }

            $seen[$url] = true;
            $out[]      = ['url' => $url, 'anchor' => $anchor];

            if (count($out) >= self::MAX_LINKS) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string,string> $candidates
     * @return array<int,array{url:string,anchor:string}>
     */
    private function fallback_links(array $candidates): array
    {
        $out = [];
        foreach (array_slice($candidates, 0, 3, true) as $url => $title) {
            $out[] = ['url' => $url, 'anchor' => $title];
        }
        return $out;
    }

    public static function flush_candidates(): void
    {
        delete_transient(self::CACHE_KEY);
    }
}
