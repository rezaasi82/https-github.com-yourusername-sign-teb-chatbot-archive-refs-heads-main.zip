<?php

declare(strict_types=1);

namespace Medora\Authority\Entity\Extractors;

use Medora\Authority\Entity\Candidate;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Entity\ExtractorInterface;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Statistical fallback for entities no other extractor knows about.
 *
 * Two independent signals are combined:
 *
 * 1. **Structural prominence** — phrases used as headings. An H2 is an
 *    editorial claim that a section is *about* something, which is exactly the
 *    unit an answer engine chunks on.
 * 2. **Repeated capitalised n-grams** — the classic proper-noun heuristic, run
 *    only on scripts that have letter case. Persian and Arabic have no case
 *    distinction, so for those the extractor falls back to repetition and
 *    heading position alone rather than emitting nothing.
 *
 * Confidence is deliberately low: these candidates are meant to be reviewed in
 * the entity explorer, not trusted blindly.
 */
final class HeuristicExtractor implements ExtractorInterface
{
    private const MIN_OCCURRENCES = 2;
    private const MAX_CANDIDATES  = 25;

    public function id(): string
    {
        return 'heuristic';
    }

    public function priority(): int
    {
        return 10;
    }

    public function extract(WP_Post $post, string $plainText): array
    {
        $scores = [];

        foreach ($this->headingPhrases($post->post_content) as $phrase) {
            $key           = Text::normalize($phrase);
            $scores[$key] ??= ['name' => $phrase, 'weight' => 0.0, 'occurrences' => 0];
            $scores[$key]['weight'] += 2.0;
        }

        foreach ($this->repeatedProperNouns($plainText) as $phrase => $count) {
            $key           = Text::normalize($phrase);
            $scores[$key] ??= ['name' => $phrase, 'weight' => 0.0, 'occurrences' => 0];
            $scores[$key]['weight']      += min(3.0, $count / 2);
            $scores[$key]['occurrences'] += $count;
        }

        if ($scores === []) {
            return [];
        }

        uasort($scores, static fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);

        $candidates = [];

        foreach (array_slice($scores, 0, self::MAX_CANDIDATES, true) as $entry) {
            $candidates[] = new Candidate(
                entity: new Entity(
                    name: $entry['name'],
                    type: EntityType::TOPIC,
                    meta: ['inferred' => true],
                ),
                occurrences: max(1, $entry['occurrences']),
                // Capped low on purpose: an unreviewed heuristic entity should
                // never outrank an editor-assigned term.
                confidence: min(0.6, 0.25 + ($entry['weight'] / 20)),
                source: $this->id(),
            );
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private function headingPhrases(string $content): array
    {
        preg_match_all('#<h([2-4])[^>]*>(.*?)</h\1>#is', $content, $matches);

        $phrases = [];

        foreach ($matches[2] ?? [] as $heading) {
            $text = Text::plain($heading);

            // Skip generic section labels and anything long enough to be a
            // sentence rather than a concept.
            if ($text === '' || Text::wordCount($text) > 8) {
                continue;
            }

            $phrases[] = $text;
        }

        return $phrases;
    }

    /**
     * @return array<string, int> phrase => occurrences
     */
    private function repeatedProperNouns(string $text): array
    {
        $counts = [];

        // Bigrams first: multi-word entities carry more meaning than a single
        // capitalised token, which is often just a sentence opener.
        foreach ([2, 1] as $size) {
            foreach (Text::ngrams($text, $size) as $ngram) {
                if (! $this->looksLikeEntity($ngram)) {
                    continue;
                }

                $counts[$ngram] = ($counts[$ngram] ?? 0) + 1;
            }
        }

        return array_filter($counts, static fn (int $count): bool => $count >= self::MIN_OCCURRENCES);
    }

    private function looksLikeEntity(string $phrase): bool
    {
        if (mb_strlen($phrase, 'UTF-8') < 4) {
            return false;
        }

        // Numeric noise, e.g. "2024 2025".
        if (preg_match('/^[\p{N}\s\-]+$/u', $phrase) === 1) {
            return false;
        }

        $words = explode(' ', $phrase);

        foreach ($words as $word) {
            if (in_array(Text::normalize($word), Text::tokens($word), true) === false) {
                // Word was filtered out as a stop word.
                return false;
            }
        }

        // Cased scripts: require every word to be capitalised.
        if (preg_match('/\p{Lu}/u', $phrase) === 1) {
            foreach ($words as $word) {
                if (preg_match('/^\p{Lu}/u', $word) !== 1) {
                    return false;
                }
            }

            return true;
        }

        // Caseless scripts (Persian, Arabic, CJK): accept multi-word phrases
        // only, since a single repeated caseless word is usually a common noun.
        return count($words) > 1 && preg_match('/[\x{0600}-\x{06FF}\x{4E00}-\x{9FFF}]/u', $phrase) === 1;
    }
}
