<?php

namespace SignTeb\VideoHub\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Tolerant JSON handling for AI responses and third-party APIs.
 */
class Json
{
    /**
     * Decode to an array, never throwing and never returning null.
     *
     * @return array<mixed>
     */
    public static function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Models sometimes wrap JSON in prose or a ```json fence. Pull out the
     * first balanced object/array and decode that.
     *
     * @return array<mixed>
     */
    public static function extract(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $direct = json_decode($raw, true);
        if (is_array($direct)) {
            return $direct;
        }

        if (preg_match('/```(?:json)?\s*(.+?)```/s', $raw, $m)) {
            $fenced = json_decode(trim($m[1]), true);
            if (is_array($fenced)) {
                return $fenced;
            }
        }

        $start = strcspn($raw, '{[');
        if ($start >= strlen($raw)) {
            return [];
        }
        $open  = $raw[$start];
        $close = $open === '{' ? '}' : ']';
        $depth = 0;
        $len   = strlen($raw);
        for ($i = $start; $i < $len; $i++) {
            if ($raw[$i] === $open) {
                $depth++;
            } elseif ($raw[$i] === $close) {
                $depth--;
                if ($depth === 0) {
                    $candidate = json_decode(substr($raw, $start, $i - $start + 1), true);
                    return is_array($candidate) ? $candidate : [];
                }
            }
        }

        return [];
    }

    /**
     * Encode for embedding in HTML (schema blocks, data attributes).
     */
    public static function encode(mixed $value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '{}';
    }

    /**
     * Fetch a nested value with a dotted path: Json::dig($data, 'items.0.id').
     */
    public static function dig(array $data, string $path, mixed $default = null): mixed
    {
        $cursor = $data;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }
}
