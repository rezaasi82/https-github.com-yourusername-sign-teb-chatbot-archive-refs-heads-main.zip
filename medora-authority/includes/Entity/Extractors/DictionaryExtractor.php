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
 * Gazetteer matching against a controlled vocabulary.
 *
 * The dictionary is supplied through the `medora_entity_dictionary` filter, so
 * the Medical Intelligence module contributes a clinical ontology, a legal
 * vertical could contribute statutes, and a customer can paste their own
 * product catalogue — all without touching this class.
 *
 * Matching is done on normalised text with word-boundary anchoring, which
 * matters for Persian and Arabic where naive `str_contains` produces false
 * positives across word joins.
 */
final class DictionaryExtractor implements ExtractorInterface
{
    /** @var array<string, array{name: string, type: string, same_as: list<string>}>|null */
    private ?array $dictionary = null;

    public function id(): string
    {
        return 'dictionary';
    }

    public function priority(): int
    {
        return 80;
    }

    public function extract(WP_Post $post, string $plainText): array
    {
        $dictionary = $this->dictionary();

        if ($dictionary === []) {
            return [];
        }

        $haystack   = Text::normalize($plainText . ' ' . $post->post_title);
        $candidates = [];

        foreach ($dictionary as $needle => $term) {
            $count = $this->countOccurrences($haystack, $needle);

            if ($count === 0) {
                continue;
            }

            $candidates[] = new Candidate(
                entity: new Entity(
                    name: $term['name'],
                    type: $term['type'],
                    sameAs: $term['same_as'],
                    meta: ['matched' => $needle],
                ),
                occurrences: $count,
                // A controlled-vocabulary hit is strong evidence, but weaker
                // than an editor explicitly assigning a term.
                confidence: 0.85,
                source: $this->id(),
            );
        }

        return $candidates;
    }

    /**
     * @return array<string, array{name: string, type: string, same_as: list<string>}>
     *         Normalised surface form => canonical term.
     */
    private function dictionary(): array
    {
        if ($this->dictionary !== null) {
            return $this->dictionary;
        }

        /**
         * Contribute vocabulary to the dictionary extractor.
         *
         * Each entry is keyed by canonical name and may declare aliases:
         *
         *     $terms['Crohn\'s disease'] = [
         *         'type'    => EntityType::MEDICAL_CONDITION,
         *         'aliases' => ['بیماری کرون', 'regional enteritis'],
         *         'same_as' => ['https://www.wikidata.org/wiki/Q1088087'],
         *     ];
         *
         * @param array<string, array{type?: string, aliases?: list<string>, same_as?: list<string>}> $terms
         */
        $terms = (array) apply_filters('medora_entity_dictionary', []);

        $flat = [];

        foreach ($terms as $canonical => $definition) {
            $canonical = (string) $canonical;
            $type      = (string) ($definition['type'] ?? EntityType::TOPIC);
            $sameAs    = array_values(array_map('strval', (array) ($definition['same_as'] ?? [])));
            $surfaces  = array_merge([$canonical], array_map('strval', (array) ($definition['aliases'] ?? [])));

            foreach ($surfaces as $surface) {
                $key = Text::normalize($surface);

                // Single characters and very short tokens produce noise that
                // swamps the salience calculation.
                if (mb_strlen($key, 'UTF-8') < 3) {
                    continue;
                }

                $flat[$key] = ['name' => $canonical, 'type' => $type, 'same_as' => $sameAs];
            }
        }

        return $this->dictionary = $flat;
    }

    /**
     * Word-boundary-anchored count over already-normalised text.
     */
    private function countOccurrences(string $haystack, string $needle): int
    {
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/u';

        return (int) preg_match_all($pattern, $haystack);
    }
}
