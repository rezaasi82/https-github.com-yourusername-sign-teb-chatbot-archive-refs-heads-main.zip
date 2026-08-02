<?php

declare(strict_types=1);

namespace Medora\Authority\Semantic;

use Medora\Authority\Support\Text;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Structural readability, measured for machines rather than for humans.
 *
 * Classic formulas (Flesch, Gunning Fog) are English-specific and depend on
 * syllable counting that is meaningless in Persian or Arabic. What actually
 * determines whether an LLM can extract a clean answer is structural: sentence
 * length, paragraph length, heading density and list usage. Those are
 * script-independent, so that is what is measured here.
 */
final class ReadabilityAnalyzer
{
    /**
     * @return array{
     *     score: float,
     *     avg_sentence_words: float,
     *     avg_paragraph_words: float,
     *     heading_density: float,
     *     list_ratio: float,
     *     long_sentences: int,
     *     notes: list<string>
     * }
     */
    public function analyze(string $html): array
    {
        $plain     = Text::plain($html);
        $sentences = Text::sentences($plain);
        $words     = Text::wordCount($plain);

        if ($words === 0 || $sentences === []) {
            return [
                'score'               => 0.0,
                'avg_sentence_words'  => 0.0,
                'avg_paragraph_words' => 0.0,
                'heading_density'     => 0.0,
                'list_ratio'          => 0.0,
                'long_sentences'      => 0,
                'notes'               => [__('No readable content found.', 'medora-authority')],
            ];
        }

        $sentenceLengths = array_map([Text::class, 'wordCount'], $sentences);
        $avgSentence     = array_sum($sentenceLengths) / count($sentenceLengths);
        $longSentences   = count(array_filter($sentenceLengths, static fn (int $n): bool => $n > 30));

        $paragraphs      = max(1, (int) preg_match_all('#<p[\s>]#i', $html));
        $avgParagraph    = $words / $paragraphs;

        $headings        = (int) preg_match_all('#<h[2-4][\s>]#i', $html);
        $headingDensity  = $words > 0 ? ($headings / ($words / 250)) : 0.0;

        $listItems       = (int) preg_match_all('#<li[\s>]#i', $html);
        $listRatio       = $words > 0 ? min(1.0, ($listItems * 15) / $words) : 0.0;

        $notes = [];
        $score = 100.0;

        // 15–22 words per sentence is the band where extractive answers stay
        // intact: shorter fragments lose context, longer ones get truncated.
        if ($avgSentence > 25) {
            $penalty = min(25.0, ($avgSentence - 25) * 2);
            $score  -= $penalty;
            $notes[] = __('Sentences are long. Split them so each carries one claim an AI can quote.', 'medora-authority');
        } elseif ($avgSentence < 8) {
            $score  -= 8.0;
            $notes[] = __('Sentences are very short, which fragments the context around each claim.', 'medora-authority');
        }

        if ($avgParagraph > 120) {
            $score  -= 15.0;
            $notes[] = __('Paragraphs are long. Aim for 40–80 words so each becomes a clean retrieval chunk.', 'medora-authority');
        }

        if ($headingDensity < 0.5) {
            $score  -= 20.0;
            $notes[] = __('Too few subheadings. Add an H2 or H3 roughly every 250 words to give retrievers clear chunk boundaries.', 'medora-authority');
        }

        if ($listRatio < 0.05 && $words > 600) {
            $score  -= 8.0;
            $notes[] = __('No lists or tables. Structured blocks are extracted far more reliably than prose.', 'medora-authority');
        }

        if ($longSentences > 0) {
            $score -= min(10.0, $longSentences * 2.0);
        }

        return [
            'score'               => round(max(0.0, min(100.0, $score)), 1),
            'avg_sentence_words'  => round($avgSentence, 1),
            'avg_paragraph_words' => round($avgParagraph, 1),
            'heading_density'     => round($headingDensity, 2),
            'list_ratio'          => round($listRatio, 3),
            'long_sentences'      => $longSentences,
            'notes'               => $notes,
        ];
    }
}
