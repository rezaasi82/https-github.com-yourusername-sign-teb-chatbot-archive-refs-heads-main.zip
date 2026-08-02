<?php

declare(strict_types=1);

namespace Medora\Authority\Support;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Splits content into retrieval-sized chunks.
 *
 * Chunking is the single highest-leverage thing a site can do for AI
 * retrieval. A model does not read a page; a retriever reads a *chunk*. A
 * chunk that starts mid-argument, or that spans three unrelated sections, will
 * either not be retrieved or will be retrieved and quoted out of context.
 *
 * The strategy here is heading-aware with sentence-level packing: chunks break
 * on H2/H3 boundaries first, then pack whole sentences up to the token budget,
 * with a sentence of overlap so a claim split across a boundary survives in at
 * least one chunk intact.
 */
final class Chunker
{
    public const DEFAULT_TARGET_TOKENS = 320;
    public const DEFAULT_OVERLAP       = 1;

    /**
     * @return list<array{index: int, heading: string, text: string, tokens: int}>
     */
    public function chunk(string $html, int $targetTokens = self::DEFAULT_TARGET_TOKENS, int $overlapSentences = self::DEFAULT_OVERLAP): array
    {
        $sections = $this->splitByHeadings($html);
        $chunks   = [];
        $index    = 0;

        foreach ($sections as $section) {
            foreach ($this->packSentences($section['text'], $targetTokens, $overlapSentences) as $text) {
                $chunks[] = [
                    'index'   => $index++,
                    'heading' => $section['heading'],
                    'text'    => $text,
                    'tokens'  => Text::estimateTokens($text),
                ];
            }
        }

        return $chunks;
    }

    /**
     * @return list<array{heading: string, text: string}>
     */
    private function splitByHeadings(string $html): array
    {
        // Capture each H2/H3 and the content that follows it, so a chunk always
        // knows which section it belongs to.
        $pattern = '#<h([23])[^>]*>(.*?)</h\1>#is';
        $parts   = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        if (count($parts) <= 1) {
            $text = Text::plain($html);

            return $text === '' ? [] : [['heading' => '', 'text' => $text]];
        }

        $sections = [];
        $intro    = Text::plain((string) array_shift($parts));

        if ($intro !== '') {
            $sections[] = ['heading' => '', 'text' => $intro];
        }

        // preg_split with DELIM_CAPTURE yields [level, heading, body] triples.
        $total = count($parts);

        for ($i = 0; $i + 2 < $total; $i += 3) {
            $heading = Text::plain((string) ($parts[$i + 1] ?? ''));
            $body    = Text::plain((string) ($parts[$i + 2] ?? ''));

            if ($body === '' && $heading === '') {
                continue;
            }

            $sections[] = ['heading' => $heading, 'text' => trim($heading . '. ' . $body)];
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    private function packSentences(string $text, int $targetTokens, int $overlapSentences): array
    {
        $sentences = Text::sentences($text);

        if ($sentences === []) {
            return [];
        }

        $chunks  = [];
        $current = [];
        $tokens  = 0;

        foreach ($sentences as $sentence) {
            $cost = Text::estimateTokens($sentence);

            // A single sentence longer than the budget still has to ship; a
            // truncated claim is worse than an oversized chunk.
            if ($cost > $targetTokens && $current === []) {
                $chunks[] = $sentence;
                continue;
            }

            if ($tokens + $cost > $targetTokens && $current !== []) {
                $chunks[] = implode(' ', $current);

                $current = $overlapSentences > 0 ? array_slice($current, -$overlapSentences) : [];
                $tokens  = array_sum(array_map([Text::class, 'estimateTokens'], $current));
            }

            $current[] = $sentence;
            $tokens   += $cost;
        }

        if ($current !== []) {
            $chunks[] = implode(' ', $current);
        }

        return array_values(array_filter($chunks, static fn (string $chunk): bool => trim($chunk) !== ''));
    }
}
