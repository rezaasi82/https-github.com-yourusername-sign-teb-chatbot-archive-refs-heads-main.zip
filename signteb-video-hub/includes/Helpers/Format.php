<?php

namespace SignTeb\VideoHub\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pure formatting helpers — no WordPress state, safe to call anywhere.
 */
class Format
{
    /**
     * Seconds → "12:34" / "1:02:03". Empty string when the duration is unknown
     * so callers can skip rendering the badge entirely.
     */
    public static function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }
        $hours = intdiv($seconds, 3600);
        $mins  = intdiv($seconds % 3600, 60);
        $secs  = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $mins, $secs)
            : sprintf('%d:%02d', $mins, $secs);
    }

    /**
     * Seconds → ISO-8601 duration required by schema.org VideoObject.
     */
    public static function iso8601_duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }
        $hours = intdiv($seconds, 3600);
        $mins  = intdiv($seconds % 3600, 60);
        $secs  = $seconds % 60;

        $out = 'PT';
        if ($hours > 0) {
            $out .= $hours . 'H';
        }
        if ($mins > 0) {
            $out .= $mins . 'M';
        }
        if ($secs > 0 || $out === 'PT') {
            $out .= $secs . 'S';
        }
        return $out;
    }

    /**
     * Aparat exposes durations as either raw seconds or "HH:MM:SS".
     */
    public static function parse_duration(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }
        if (! is_string($value) || $value === '') {
            return 0;
        }
        // ISO-8601 (YouTube): PT1H2M3S
        if (preg_match('/^P(?:\d+D)?T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/i', $value, $m)) {
            return ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);
        }
        // Clock notation: 12:34 or 1:02:03
        $parts = array_reverse(array_map('intval', explode(':', $value)));
        $total = 0;
        foreach ($parts as $i => $part) {
            $total += $part * (60 ** $i);
        }
        return max(0, $total);
    }

    /**
     * Provider timestamps arrive as unix seconds, ISO strings, or Persian
     * relative text ("۲ ساعت پیش"). Anything unparseable falls back to now.
     */
    public static function to_mysql_datetime(mixed $value): string
    {
        if (is_numeric($value) && (int) $value > 0) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return gmdate('Y-m-d H:i:s', $ts);
            }
        }
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Compact view counts: 12500 → "12.5K".
     */
    public static function views(int $count): string
    {
        if ($count >= 1000000) {
            return rtrim(rtrim(number_format($count / 1000000, 1), '0'), '.') . 'M';
        }
        if ($count >= 1000) {
            return rtrim(rtrim(number_format($count / 1000, 1), '0'), '.') . 'K';
        }
        return (string) $count;
    }

    /**
     * Build a clean URL slug from a provider title.
     *
     * sanitize_title() alone is not enough for Persian titles: it preserves
     * non-ASCII punctuation, so "…چیست؟ | دکتر" kept the Persian question mark
     * and the pipe, producing an unusable slug. Punctuation is stripped first,
     * then the result is trimmed at a word boundary so the URL stays short
     * enough to read and to share.
     */
    public static function slug(string $title, int $max_length = 60): string
    {
        // Everything after a separator is usually a channel/author suffix.
        $title = (string) preg_split('/\s*[|\x{2013}\x{2014}]\s*/u', $title)[0];

        // Drop punctuation from every script, keep letters, digits and spaces.
        $title = (string) preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $title);
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));

        if ($title === '') {
            return '';
        }

        if (mb_strlen($title) > $max_length) {
            $clipped = mb_substr($title, 0, $max_length);
            $boundary = mb_strrpos($clipped, ' ');
            $title    = $boundary !== false && $boundary > 0 ? mb_substr($clipped, 0, $boundary) : $clipped;
        }

        return sanitize_title($title);
    }

    /**
     * Percentage guarded against division by zero (CTR tiles).
     */
    public static function rate(int $numerator, int $denominator, int $precision = 1): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }
        return round(($numerator / $denominator) * 100, $precision);
    }
}
