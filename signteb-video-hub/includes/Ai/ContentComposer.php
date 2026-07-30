<?php

namespace SignTeb\VideoHub\Ai;

use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Writes the AI output into the post body.
 *
 * Aparat descriptions are frequently empty, so post_content was empty too.
 * That left the editor blank and gave every content-analysis tool — RankMath
 * included — nothing to score, which is why a fully populated video still
 * reported "content too short" and a missing keyword.
 *
 * Rendering the AI panels on the front end did not solve it: those are built
 * at display time and never reach the stored content that SEO plugins, search
 * dialogs and excerpt generation actually read.
 *
 * A body written here is flagged so the front end knows not to repeat it, and
 * so a later regeneration does not overwrite prose an editor has since
 * rewritten by hand.
 */
class ContentComposer
{
    public const FLAG = '_stvh_content_from_ai';

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function is_enabled(): bool
    {
        return $this->settings->bool('ai_write_content');
    }

    /**
     * Compose and store the body when that is safe to do.
     *
     * @return bool True when the content was written.
     */
    public function maybe_write(int $post_id): bool
    {
        if (! $this->is_enabled() || $post_id <= 0) {
            return false;
        }
        if (! $this->may_overwrite($post_id)) {
            return false;
        }

        $body = $this->compose($post_id);
        if ($body === '') {
            return false;
        }

        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $body,
        ]);

        update_post_meta($post_id, self::FLAG, '1');

        return true;
    }

    /**
     * Only an empty body, or one this class wrote itself, may be replaced.
     * Anything an editor typed is theirs.
     */
    private function may_overwrite(int $post_id): bool
    {
        $content = trim((string) get_post_field('post_content', $post_id));

        if ($content === '') {
            return true;
        }

        return (string) get_post_meta($post_id, self::FLAG, true) === '1';
    }

    /**
     * True when the stored body already contains the AI panels, so the
     * front-end block should not render them a second time.
     */
    public static function owns_content(int $post_id): bool
    {
        return (string) get_post_meta($post_id, self::FLAG, true) === '1';
    }

    /**
     * Build the editorial body: summary, key points, then the FAQ as real
     * headings so the questions are indexable text rather than script data.
     */
    public function compose(int $post_id): string
    {
        $summary   = VideoMeta::summary($post_id);
        $keypoints = VideoMeta::keypoints($post_id);
        $faq       = VideoMeta::faq($post_id);

        if ($summary === '' && $keypoints === [] && $faq === []) {
            return '';
        }

        // Generic headings ("نکات مهم این ویدئو") carry no keyword, which is
        // one of the checks an SEO plugin runs against the body. Weave the
        // focus keyword in when there is one.
        $keyword = FocusKeyword::for_post($post_id);

        $parts = [];

        if ($summary !== '') {
            $parts[] = '<p>' . esc_html($summary) . '</p>';
        }

        if ($keypoints !== []) {
            $parts[] = '<h2>' . esc_html(
                $keyword !== ''
                    /* translators: %s: focus keyword */
                    ? sprintf(__('نکات مهم درباره %s', 'signteb-video-hub'), $keyword)
                    : __('نکات مهم این ویدئو', 'signteb-video-hub')
            ) . '</h2>';
            $items   = '';
            foreach ($keypoints as $point) {
                $items .= '<li>' . esc_html($point) . '</li>';
            }
            $parts[] = '<ul>' . $items . '</ul>';
        }

        if ($faq !== []) {
            $parts[] = '<h2>' . esc_html(
                $keyword !== ''
                    /* translators: %s: focus keyword */
                    ? sprintf(__('سوالات متداول درباره %s', 'signteb-video-hub'), $keyword)
                    : __('سوالات متداول', 'signteb-video-hub')
            ) . '</h2>';
            foreach ($faq as $item) {
                $parts[] = '<h3>' . esc_html($item['q']) . '</h3>';
                $parts[] = '<p>' . esc_html($item['a']) . '</p>';
            }
        }

        /**
         * Adjust the composed post body.
         *
         * @param string $body
         * @param int    $post_id
         */
        return (string) apply_filters('stvh_composed_content', implode("\n\n", $parts), $post_id);
    }
}
