<?php

declare(strict_types=1);

namespace Medora\Authority\Vector\Providers;

use Medora\Authority\Support\Text;
use Medora\Authority\Vector\EmbeddingProviderInterface;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Offline embeddings via the hashing trick (a.k.a. feature hashing).
 *
 * This is the default provider, and it is deliberately not a neural model. It
 * needs no API key, no outbound request and no per-token cost, which means
 * semantic search, related-content and internal linking work on every install
 * from the first minute — including on sites that cannot legally or practically
 * send content to a third-party API.
 *
 * What it does: project unigrams and bigrams into a fixed-width space with a
 * signed hash, weight by sub-linear term frequency, then L2-normalise. The
 * result captures lexical and short-phrase overlap well. What it does not do is
 * capture paraphrase across different vocabulary — for that, configure the
 * OpenAI-compatible provider, which uses the same interface and the same index.
 */
final class HashingEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private readonly int $dimensions = 512)
    {
    }

    public function id(): string
    {
        return 'hashing';
    }

    public function label(): string
    {
        return __('Built-in (offline, no API key)', 'medora-authority');
    }

    public function dimensions(): int
    {
        return max(64, $this->dimensions);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function embed(string $text): array
    {
        $dimensions = $this->dimensions();
        $vector     = array_fill(0, $dimensions, 0.0);
        $tokens     = Text::tokens($text);

        if ($tokens === []) {
            return $vector;
        }

        $features = array_count_values($tokens);

        // Bigrams add a little word-order sensitivity, which is what keeps
        // "kidney stone" from matching a page that merely mentions kidneys and
        // stones separately. Weighted below unigrams because they are sparser.
        $total = count($tokens);

        for ($i = 0; $i < $total - 1; $i++) {
            $bigram             = $tokens[$i] . '_' . $tokens[$i + 1];
            $features[$bigram]  = ($features[$bigram] ?? 0) + 1;
        }

        foreach ($features as $feature => $count) {
            $feature = (string) $feature;
            $hash    = crc32($feature);
            $index   = $hash % $dimensions;
            $sign    = ($hash & 0x80000000) !== 0 ? -1.0 : 1.0;

            // Sub-linear scaling: the tenth occurrence of a word says much less
            // than the second, exactly as in classic tf-idf.
            $weight = (1.0 + log((float) $count)) * (str_contains($feature, '_') ? 0.6 : 1.0);

            $vector[$index] += $sign * $weight;
        }

        return $this->normalize($vector);
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    /**
     * @param list<float> $vector
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $magnitude = 0.0;

        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }

        $magnitude = sqrt($magnitude);

        if ($magnitude <= 0.0) {
            return $vector;
        }

        return array_map(static fn (float $value): float => $value / $magnitude, $vector);
    }
}
