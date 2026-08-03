<?php

declare(strict_types=1);

namespace Medora\Authority\Vector;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vector maths and the binary encoding used for storage.
 *
 * Vectors are stored as packed little-endian float32 rather than JSON: a
 * 1536-dimension vector is 6 KB packed against roughly 30 KB as a JSON array,
 * and decoding is a single `unpack()` instead of a JSON parse per row. On a
 * site with 5,000 chunks that is the difference between a usable search and an
 * unusable one.
 */
final class Similarity
{
    /** @param list<float> $vector */
    public static function pack(array $vector): string
    {
        return pack('g*', ...$vector);
    }

    /** @return list<float> */
    public static function unpack(string $binary): array
    {
        if ($binary === '') {
            return [];
        }

        $values = unpack('g*', $binary);

        return $values === false ? [] : array_values(array_map('floatval', $values));
    }

    /**
     * Cosine similarity in −1..1.
     *
     * Providers return L2-normalised vectors, so this is a plain dot product;
     * the magnitude guard only exists for hand-constructed vectors in tests.
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function cosine(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        // Already unit length in the common case: sqrt(1)*sqrt(1) == 1.
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /** @param list<float> $vector */
    public static function magnitude(array $vector): float
    {
        return sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
    }

    /**
     * Mean of several vectors, re-normalised — used to build a single
     * document-level vector from its chunks.
     *
     * @param list<list<float>> $vectors
     * @return list<float>
     */
    public static function centroid(array $vectors): array
    {
        if ($vectors === []) {
            return [];
        }

        $dimensions = count($vectors[0]);
        $sum        = array_fill(0, $dimensions, 0.0);

        foreach ($vectors as $vector) {
            for ($i = 0; $i < $dimensions; $i++) {
                $sum[$i] += $vector[$i] ?? 0.0;
            }
        }

        $count     = count($vectors);
        $mean      = array_map(static fn (float $v): float => $v / $count, $sum);
        $magnitude = self::magnitude($mean);

        return $magnitude > 0.0
            ? array_map(static fn (float $v): float => $v / $magnitude, $mean)
            : $mean;
    }
}
