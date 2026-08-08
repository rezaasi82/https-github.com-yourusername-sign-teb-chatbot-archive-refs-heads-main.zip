<?php

namespace SignTeb\VideoHub\Ai;

use SignTeb\VideoHub\Helpers\Format;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves the focus keyword a page is targeting.
 *
 * SEO plugins score a page on whether the keyword appears in the headings, so
 * generated copy has to know what that keyword is. RankMath and Yoast each
 * store it in their own meta key; when neither is set the video title, minus
 * its author suffix, is the best available stand-in.
 */
class FocusKeyword
{
    private const RANKMATH = 'rank_math_focus_keyword';
    private const YOAST    = '_yoast_wpseo_focuskw';

    /**
     * The keyword for a post, or '' when nothing usable exists.
     */
    public static function for_post(int $post_id): string
    {
        // RankMath stores a comma-separated list; the first entry is primary.
        $rankmath = (string) get_post_meta($post_id, self::RANKMATH, true);
        if ($rankmath !== '') {
            $primary = trim((string) explode(',', $rankmath)[0]);
            if ($primary !== '') {
                return $primary;
            }
        }

        $yoast = trim((string) get_post_meta($post_id, self::YOAST, true));
        if ($yoast !== '') {
            return $yoast;
        }

        return self::from_title($post_id);
    }

    /**
     * Fall back to the title with punctuation and the author suffix removed —
     * "درمان ریفلاکس معده چیست؟ | دکتر …" becomes "درمان ریفلاکس معده چیست".
     */
    private static function from_title(int $post_id): string
    {
        $slug = Format::slug((string) get_the_title($post_id));

        return $slug === '' ? '' : str_replace('-', ' ', rawurldecode($slug));
    }

    /**
     * Prompt fragment instructing the model to place the keyword in headings.
     * Empty when there is no keyword, so callers can concatenate blindly.
     */
    public static function heading_instruction(int $post_id): string
    {
        $keyword = self::for_post($post_id);
        if ($keyword === '') {
            return '';
        }

        return implode("\n", [
            '',
            sprintf('کلمه کلیدی اصلی: «%s»', $keyword),
            'الزامات سئو:',
            sprintf('- کلمه کلیدی «%s» باید در اولین پاراگراف بیاید.', $keyword),
            sprintf('- حداقل دو تیتر <h2> باید شامل «%s» یا شکل نزدیک آن باشد.', $keyword),
            sprintf('- حداقل یک زیرتیتر <h3> شامل «%s» باشد.', $keyword),
            '- کلمه کلیدی را طبیعی به کار ببر؛ تکرار مصنوعی و پشت‌سرهم ممنوع است.',
        ]);
    }
}
