<?php

declare(strict_types=1);

namespace Medora\Authority\Geo;

use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Counts the surfaces on a page that an assistant can lift wholesale.
 *
 * A model composing an answer reaches for structure before it reaches for
 * prose: a table of options, a numbered procedure, a bulleted set of criteria,
 * a one-line definition. Those survive summarisation intact, because there is
 * nothing to summarise — they are already the short form.
 *
 * This is not a demand that every page become a list. It is a check that a long
 * page which *is* enumerating something has said so in markup rather than in
 * paragraphs, because the paragraph version gets paraphrased and the markup
 * version gets quoted.
 */
final class StructureAnalyzer
{
    /** A list of one or two items is a sentence with bullets on it. */
    private const MIN_LIST_ITEMS = 3;

    /** Below this a page is short enough that prose is the right shape. */
    private const LONG_FORM_WORDS = 800;

    /**
     * @return array{
     *     tables: int, ordered_lists: int, unordered_lists: int,
     *     definition_lists: int, question_headings: int, headings: int,
     *     surfaces: int, words: int, is_long_form: bool, has_steps: bool
     * }
     */
    public function analyze(WP_Post $post): array
    {
        $html  = $post->post_content;
        $words = Text::wordCount(Text::plain($html));

        $tables      = $this->countTag($html, 'table');
        $ordered     = $this->countLists($html, 'ol');
        $unordered   = $this->countLists($html, 'ul');
        $definitions = $this->countTag($html, 'dl');

        $headings         = $this->headings($html);
        $questionHeadings = 0;

        foreach ($headings as $heading) {
            if ($this->isQuestion($heading)) {
                $questionHeadings++;
            }
        }

        return [
            'tables'            => $tables,
            'ordered_lists'     => $ordered,
            'unordered_lists'   => $unordered,
            'definition_lists'  => $definitions,
            'question_headings' => $questionHeadings,
            'headings'          => count($headings),
            'surfaces'          => $tables + $ordered + $unordered + $definitions,
            'words'             => $words,
            'is_long_form'      => $words >= self::LONG_FORM_WORDS,

            // An ordered list is the only markup that carries sequence. A
            // procedure written as prose reads fine and gets cited as a
            // paraphrase with the steps out of order.
            'has_steps'         => $ordered > 0,
        ];
    }

    private function countTag(string $html, string $tag): int
    {
        return preg_match_all('#<' . $tag . '[\s>]#i', $html) ?: 0;
    }

    /**
     * Lists are counted only when they carry enough items to be worth
     * retrieving, so a two-item bullet list does not read as structure.
     */
    private function countLists(string $html, string $tag): int
    {
        if (preg_match_all('#<' . $tag . '\b[^>]*>(.*?)</' . $tag . '>#is', $html, $matches) === false) {
            return 0;
        }

        $count = 0;

        foreach ($matches[1] ?? [] as $inner) {
            if ((preg_match_all('#<li[\s>]#i', $inner) ?: 0) >= self::MIN_LIST_ITEMS) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function headings(string $html): array
    {
        preg_match_all('#<h[23][^>]*>(.*?)</h[23]>#is', $html, $matches);

        return array_map(
            static fn (string $heading): string => trim(Text::plain($heading)),
            $matches[1] ?? []
        );
    }

    private function isQuestion(string $heading): bool
    {
        if ($heading === '') {
            return false;
        }

        // Persian and Arabic question marks are different characters, and
        // Persian questions frequently carry no mark at all — the interrogative
        // word is what makes them questions.
        if (preg_match('/[?؟]\s*$/u', $heading) === 1) {
            return true;
        }

        $first = Text::words(Text::normalize($heading))[0] ?? '';

        return isset(self::interrogatives()[$first]);
    }

    /**
     * Folded through `Text::normalize` rather than written pre-folded, so the
     * list keeps matching if normalisation learns another character. "آیا"
     * normalises to "ایا"; a hand-folded literal would silently stop matching
     * the day that changed.
     *
     * @return array<string, true>
     */
    private static function interrogatives(): array
    {
        /** @var array<string, true>|null $cache */
        static $cache = null;

        if ($cache === null) {
            $cache = [];

            foreach ([
                'what', 'why', 'how', 'when', 'where', 'who', 'which', 'can', 'is',
                'does', 'do', 'should', 'are',
                'چه', 'چرا', 'چگونه', 'چطور', 'کی', 'کجا', 'کدام', 'آیا', 'چند',
                'ما', 'لماذا', 'كيف', 'متى', 'أين', 'هل',
            ] as $word) {
                $cache[Text::normalize($word)] = true;
            }
        }

        return $cache;
    }
}
