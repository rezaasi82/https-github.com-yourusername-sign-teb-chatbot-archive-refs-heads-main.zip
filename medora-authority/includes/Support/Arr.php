<?php

declare(strict_types=1);

namespace Medora\Authority\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class Arr
{
    /**
     * Read a nested value with dot notation.
     *
     * @param array<mixed> $array
     */
    public static function get(array $array, string $path, mixed $default = null): mixed
    {
        $cursor = $array;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Decode a JSON column, always returning an array.
     *
     * @return array<mixed>
     */
    public static function fromJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<mixed> $value */
    public static function toJson(array $value): string
    {
        return (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Remove null, empty-string and empty-array members, recursively.
     *
     * Schema.org output is judged partly on the absence of empty properties,
     * so every node is passed through this before printing.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function compact(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = self::compact($value);
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Sort an array of rows by a numeric column, descending.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sortByDesc(array $rows, string $column): array
    {
        usort($rows, static fn (array $a, array $b): int => ($b[$column] ?? 0) <=> ($a[$column] ?? 0));

        return $rows;
    }
}
