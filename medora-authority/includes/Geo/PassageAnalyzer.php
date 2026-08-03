<?php

declare(strict_types=1);

namespace Medora\Authority\Geo;

use Medora\Authority\Support\Chunker;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Judges each passage of a page as the unit it is actually retrieved as.
 *
 * Every other scorer in this plugin reads a page. A retriever does not: it
 * pulls one chunk, hands that chunk to a model, and the model answers from it
 * alone. A passage that opens "However, this is rarely the case" is a perfectly
 * good sentence inside the article and useless on its own — the subject is two
 * paragraphs up, in a chunk that was not retrieved.
 *
 * So this class asks one question of every chunk: read cold, with nothing
 * around it, does this passage say what it is about? The checks are lints, not
 * judgements of writing quality — each one names a specific edit, and none of
 * them fire on prose that simply happens to be plain.
 */
final class PassageAnalyzer
{
    /** Below this, a passage is a fragment rather than an answer. */
    private const MIN_WORDS = 40;

    /**
     * Pronouns that cannot introduce a noun. Sentence-initially they always
     * point outside the passage, whatever follows — "It affects roughly a
     * quarter of adults" names nothing a retriever can match.
     */
    private const PRONOUNS = [
        'it', 'they', 'them',
        'آنها', 'اینها', 'او', 'ایشان', 'وی',
    ];

    /**
     * Demonstratives, which can either stand alone or introduce a noun. Only
     * the first use is a problem: "This condition affects…" is self-contained
     * because the noun arrives immediately, while "This is why it matters" is
     * a sentence about something in a different chunk.
     */
    private const DEMONSTRATIVES = [
        'this', 'that', 'these', 'those', 'such', 'both',
        'این', 'آن', 'همین', 'همان', 'چنین',
        'هذا', 'هذه', 'ذلك', 'تلك', 'هؤلاء',
    ];

    /**
     * Sentence-initial connectives, which are unresolvable however the
     * sentence continues: they assert a relationship to an argument the
     * passage does not contain.
     */
    private const CONNECTIVES = [
        'however', 'therefore', 'thus', 'hence', 'moreover', 'furthermore',
        'consequently', 'nevertheless', 'nonetheless', 'instead', 'besides',
        'اما', 'ولی', 'بنابراین', 'همچنین', 'درنتیجه', 'پس', 'وگرنه', 'اگرچه',
        'لكن', 'لذلك', 'أيضا', 'كذلك',
    ];

    /**
     * References to a position on the page. Meaningless once the passage has
     * been lifted out of it.
     */
    private const DEICTIC = [
        'as mentioned above', 'as noted above', 'as we saw', 'as discussed',
        'see below', 'see above', 'the table above', 'the list below',
        'earlier in this article', 'later in this article', 'in the next section',
        'در بالا', 'در ادامه', 'در ادامه مقاله', 'همان طور که گفته شد',
        'همان طور که اشاره شد', 'در بخش قبل', 'در جدول بالا', 'در زیر آمده',
    ];

    public function __construct(private readonly Chunker $chunker)
    {
    }

    /**
     * @return array{
     *     post_id: int,
     *     passages: list<array{
     *         index: int, heading: string, excerpt: string, words: int,
     *         score: float, issues: list<array{code: string, label: string, fix: string}>
     *     }>,
     *     clean: int,
     *     total: int,
     *     ratio: float
     * }
     */
    public function analyze(WP_Post $post): array
    {
        $chunks     = $this->chunker->chunk($post->post_content);
        $titleWords = $this->wordSet($post->post_title);
        $passages   = [];
        $clean      = 0;

        foreach ($chunks as $chunk) {
            $issues = $this->issuesFor($chunk, $titleWords);
            $score  = 100.0;

            foreach ($issues as $issue) {
                $score -= $issue['points'];
            }

            $score = max(0.0, $score);

            if ($issues === []) {
                $clean++;
            }

            $passages[] = [
                'index'   => $chunk['index'],
                'heading' => $chunk['heading'],
                'excerpt' => Text::truncate($chunk['text'], 220),
                'words'   => Text::wordCount($chunk['text']),
                'score'   => round($score, 1),
                'issues'  => array_map(
                    static fn (array $issue): array => [
                        'code'  => $issue['code'],
                        'label' => $issue['label'],
                        'fix'   => $issue['fix'],
                    ],
                    $issues
                ),
            ];
        }

        $total = count($passages);

        return [
            'post_id'  => $post->ID,
            'passages' => $passages,
            'clean'    => $clean,
            'total'    => $total,
            'ratio'    => $total > 0 ? round($clean / $total, 4) : 0.0,
        ];
    }

    /**
     * @param array{index: int, heading: string, text: string, tokens: int} $chunk
     * @param array<string, true>                                           $titleWords
     * @return list<array{code: string, label: string, fix: string, points: int}>
     */
    private function issuesFor(array $chunk, array $titleWords): array
    {
        $issues = [];
        $text   = $chunk['text'];
        $first  = $this->openingSentence($text, $chunk['heading']);

        if ($chunk['heading'] === '') {
            $issues[] = [
                'code'   => 'no_heading',
                'label'  => __('Passage sits under no heading', 'medora-authority'),
                'fix'    => __('Move this text under an H2, or add one above it. A retrieved passage with no heading arrives with no label.', 'medora-authority'),
                'points' => 15,
            ];
        }

        if (Text::wordCount($text) < self::MIN_WORDS) {
            $issues[] = [
                'code'   => 'too_short',
                'label'  => __('Passage is too short to answer anything', 'medora-authority'),
                'fix'    => __('Expand to at least 40 words, or merge it into the section above. Fragments are retrieved and then discarded.', 'medora-authority'),
                'points' => 20,
            ];
        }

        if ($this->opensWithConnective($first)) {
            $issues[] = [
                'code'   => 'dangling_connective',
                'label'  => __('Opens by continuing an argument that is not here', 'medora-authority'),
                'fix'    => __('Start the passage with a statement rather than "However", "Therefore" or "اما". Read alone, a connective points at nothing.', 'medora-authority'),
                'points' => 25,
            ];
        } elseif ($this->opensWithDanglingPronoun($first)) {
            $issues[] = [
                'code'   => 'dangling_pronoun',
                'label'  => __('Opens with a pronoun whose subject is elsewhere', 'medora-authority'),
                'fix'    => __('Name the subject in the first sentence. "This condition affects…" survives retrieval; "This is why…" does not.', 'medora-authority'),
                'points' => 25,
            ];
        }

        if ($this->hasDeicticReference($text)) {
            $issues[] = [
                'code'   => 'page_reference',
                'label'  => __('Refers to another part of the page', 'medora-authority'),
                'fix'    => __('Replace "as mentioned above" / "در ادامه" with the fact itself. Once retrieved, this passage has no above and no below.', 'medora-authority'),
                'points' => 15,
            ];
        }

        if ($titleWords !== [] && ! $this->mentionsSubject($text, $titleWords)) {
            $issues[] = [
                'code'   => 'subject_absent',
                'label'  => __('Never names the page subject', 'medora-authority'),
                'fix'    => __('Use the subject term once in this passage. A retriever matching a query against this chunk alone has nothing to match on.', 'medora-authority'),
                'points' => 20,
            ];
        }

        return $issues;
    }

    /**
     * The first sentence of the prose, not of the chunk.
     *
     * `Chunker` prepends the section heading to each chunk's text — deliberately,
     * so an embedding of the chunk carries the section it belongs to. That makes
     * the literal first sentence "Treatment." on every chunk under an H2, which
     * would mask every dangling opener on the page. The heading is skipped here
     * rather than removed from the chunker, because the chunker is right: the
     * embedding wants it and this check does not.
     */
    private function openingSentence(string $text, string $heading): string
    {
        $sentences = Text::sentences($text);

        if ($heading === '') {
            return $sentences[0] ?? '';
        }

        $normalisedHeading = Text::normalize(rtrim($heading, " .:،؛?؟!"));

        foreach ($sentences as $sentence) {
            if (Text::normalize(rtrim($sentence, " .:،؛?؟!")) !== $normalisedHeading) {
                return $sentence;
            }
        }

        return '';
    }

    private function opensWithConnective(string $sentence): bool
    {
        $words = Text::words(Text::normalize($sentence));

        return isset($words[0]) && isset(self::folded(self::CONNECTIVES)[$words[0]]);
    }

    /**
     * The word lists above are written the way a human writes them — "آیا",
     * "أيضا" — but they are compared against normalised text, where those have
     * folded to "ایا" and "ایضا". Folding the lists through the same function
     * is what keeps the two in step; hand-folding the literals would rot the
     * moment `Text::normalize` learns another character.
     *
     * @param list<string> $words
     * @return array<string, true>
     */
    private static function folded(array $words): array
    {
        /** @var array<string, array<string, true>> $cache */
        static $cache = [];

        $key = md5(implode('|', $words));

        if (! isset($cache[$key])) {
            $set = [];

            foreach ($words as $word) {
                $set[Text::normalize($word)] = true;
            }

            $cache[$key] = $set;
        }

        return $cache[$key];
    }

    /**
     * Known limitation, accepted deliberately: a demonstrative followed by a
     * genuine noun is passed even when that noun is doing no anchoring work
     * ("این نشان می‌دهد" — "this shows"). Catching those needs a parser, and
     * the version that catches them by pattern also fires on correct writing.
     * A lint people learn to ignore is worse than a lint that misses cases, so
     * this one only reports openers it is sure about.
     */
    private function opensWithDanglingPronoun(string $sentence): bool
    {
        $words = Text::words(Text::normalize($sentence));
        $first = $words[0] ?? '';

        if ($first === '') {
            return false;
        }

        if (isset(self::folded(self::PRONOUNS)[$first])) {
            return true;
        }

        if (! isset(self::folded(self::DEMONSTRATIVES)[$first])) {
            return false;
        }

        // Used as a determiner ("this condition", "این بیماری") the passage
        // names its own subject and stands up. Standing alone ("this is",
        // "این است") it does not. `Text::tokens` drops stop words, so a word
        // that survives it is the noun the determiner is introducing.
        return Text::tokens($words[1] ?? '') === [];
    }

    private function hasDeicticReference(string $text): bool
    {
        $haystack = Text::normalize($text);

        foreach (self::DEICTIC as $phrase) {
            if (str_contains($haystack, Text::normalize($phrase))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, true> $titleWords
     */
    private function mentionsSubject(string $text, array $titleWords): bool
    {
        foreach (Text::tokens($text) as $token) {
            if (isset($titleWords[$token])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function wordSet(string $text): array
    {
        $set = [];

        foreach (Text::tokens($text) as $token) {
            $set[$token] = true;
        }

        return $set;
    }
}
