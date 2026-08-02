<?php

declare(strict_types=1);

namespace Medora\Authority\Vector;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Produces a dense vector representation of a piece of text.
 *
 * Implementations must return L2-normalised vectors of a fixed dimensionality,
 * which lets `Similarity::cosine()` reduce to a dot product.
 */
interface EmbeddingProviderInterface
{
    public function id(): string;

    public function label(): string;

    public function dimensions(): int;

    /** True when the provider is configured and usable right now. */
    public function isAvailable(): bool;

    /**
     * @return list<float> L2-normalised vector of `dimensions()` length.
     */
    public function embed(string $text): array;

    /**
     * Embed several texts. Remote providers should override this to batch into
     * one HTTP request.
     *
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;
}
