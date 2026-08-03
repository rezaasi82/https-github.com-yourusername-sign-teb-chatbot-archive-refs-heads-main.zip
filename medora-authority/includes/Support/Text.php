<?php

declare(strict_types=1);

namespace Medora\Authority\Support;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Multibyte-safe text utilities shared by the semantic, entity, vector and
 * prompt engines.
 *
 * All methods are UTF-8 aware and behave correctly for Persian and Arabic —
 * the platform's primary non-Latin markets — which is why word boundaries are
 * detected with a Unicode-aware regex instead of `str_word_count()`.
 */
final class Text
{
    /**
     * Latin + Persian stop words. Deliberately conservative: over-filtering
     * destroys the topical signal the semantic scorers depend on.
     *
     * @var list<string>
     */
    private const STOP_WORDS = [
        // English
        'a', 'about', 'after', 'all', 'also', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'because',
        'been', 'but', 'by', 'can', 'do', 'does', 'for', 'from', 'had', 'has', 'have', 'he', 'her',
        'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'may', 'more', 'most', 'no', 'not',
        'of', 'on', 'one', 'or', 'other', 'our', 'out', 'over', 'said', 'she', 'should', 'so', 'some',
        'such', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this',
        'those', 'to', 'up', 'use', 'used', 'was', 'we', 'were', 'what', 'when', 'which', 'who',
        'will', 'with', 'would', 'you', 'your',
        // Persian / Arabic
        'از', 'با', 'برای', 'به', 'تا', 'در', 'را', 'روی', 'که', 'کە', 'های', 'هم', 'یا', 'یک',
        'این', 'آن', 'است', 'بود', 'شد', 'شود', 'می', 'نیز', 'ولی', 'اما', 'اگر', 'چه', 'هر',
        'و', 'علی', 'عن', 'فی', 'من', 'ما', 'هذا', 'ذلك', 'التي', 'الذي',
    ];

    /** Strip markup, shortcodes and blocks down to readable prose. */
    public static function plain(string $html): string
    {
        $text = strip_shortcodes($html);
        $text = (string) preg_replace('/<!--\s*\/?wp:.*?-->/s', ' ', $text);
        $text = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $text);
        $text = wp_strip_all_tags($text, true);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Normalise for comparison: lowercase, Arabic/Persian character folding,
     * diacritics and zero-width joiners removed.
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

        // Fold the Arabic forms of characters that Persian writes differently,
        // so "علي" and "علی" resolve to the same entity.
        $value = strtr($value, [
            'ي' => 'ی',
            'ك' => 'ک',
            'ۀ' => 'ه',
            'ة' => 'ه',
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ؤ' => 'و',
            "\u{200c}" => ' ',
            "\u{200f}" => '',
            "\u{200e}" => '',
        ]);

        // Harakat / tashkil.
        $value = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * @return list<string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'\x{200c}-]*/u', $text, $matches);

        return $matches[0] ?? [];
    }

    /**
     * Content words only: normalised, stop words and single characters removed.
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $stop   = array_flip(self::STOP_WORDS);
        $tokens = [];

        foreach (self::words(self::normalize($text)) as $word) {
            if (mb_strlen($word, 'UTF-8') < 2 || isset($stop[$word])) {
                continue;
            }

            $tokens[] = $word;
        }

        return $tokens;
    }

    /**
     * Term frequencies, highest first.
     *
     * @return array<string, int>
     */
    public static function termFrequencies(string $text): array
    {
        $counts = array_count_values(self::tokens($text));
        arsort($counts);

        return $counts;
    }

    /**
     * Contiguous word n-grams, used for multi-word entity candidates.
     *
     * @return list<string>
     */
    public static function ngrams(string $text, int $size): array
    {
        $words = self::words($text);
        $total = count($words);

        if ($size < 1 || $total < $size) {
            return [];
        }

        $ngrams = [];

        for ($i = 0; $i <= $total - $size; $i++) {
            $ngrams[] = implode(' ', array_slice($words, $i, $size));
        }

        return $ngrams;
    }

    /**
     * Sentence split that tolerates Latin, Persian (`؟`, `۔`) and Arabic
     * punctuation.
     *
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $parts     = preg_split('/(?<=[.!?؟۔])\s+/u', self::plain($text)) ?: [];
        $sentences = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $sentences[] = $part;
            }
        }

        return $sentences;
    }

    public static function wordCount(string $text): int
    {
        return count(self::words(self::plain($text)));
    }

    /** Truncate on a word boundary without breaking multibyte sequences. */
    public static function truncate(string $text, int $length, string $suffix = '…'): string
    {
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        $cut   = mb_substr($text, 0, $length, 'UTF-8');
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');

        if ($space !== false && $space > (int) ($length * 0.6)) {
            $cut = mb_substr($cut, 0, $space, 'UTF-8');
        }

        return rtrim($cut, " ,.;:—-") . $suffix;
    }

    /** Stable content fingerprint used to skip redundant re-analysis. */
    public static function hash(string $content): string
    {
        return sha1(self::normalize(self::plain($content)));
    }

    /**
     * Approximate token count for LLM context budgeting.
     *
     * Latin text averages ~4 characters per token; Persian and Arabic sit
     * closer to ~2.5 because of how BPE vocabularies segment the script, so
     * the divisor is chosen from the dominant script rather than fixed.
     */
    public static function estimateTokens(string $text): int
    {
        $length = mb_strlen($text, 'UTF-8');

        if ($length === 0) {
            return 0;
        }

        $nonLatin = preg_match_all('/[\x{0600}-\x{06FF}\x{4E00}-\x{9FFF}]/u', $text);
        $divisor  = ($nonLatin / max($length, 1)) > 0.3 ? 2.5 : 4.0;

        return (int) ceil($length / $divisor);
    }
}
