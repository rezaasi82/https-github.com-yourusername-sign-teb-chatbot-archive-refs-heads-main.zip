<?php

declare(strict_types=1);

namespace Medora\Authority\Semantic;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Support\Chunker;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Measures how well a page conveys *knowledge*, not how well it targets a
 * keyword.
 *
 * Five metrics, each answering a question an answer engine implicitly asks:
 *
 * - **Knowledge density** — how much of the text is substantive rather than
 *   filler. Long pages that say little rank poorly with retrievers because
 *   every chunk is mostly padding.
 * - **Topic coverage** — does the page address the sub-topics a reader (and a
 *   model) expects alongside its subject?
 * - **Answer readiness** — is there a direct, extractable answer near the top?
 * - **Context quality** — does the page define its terms and state its scope,
 *   or does it assume the reader already knows?
 * - **Chunk integrity** — do the retrieval chunks stand alone?
 */
final class SemanticAnalyzer
{
    public function __construct(
        private readonly EntityRepository $entities,
        private readonly ReadabilityAnalyzer $readability,
        private readonly Chunker $chunker,
        private readonly TopicCoverage $coverage,
    ) {
    }

    /**
     * @return array{
     *     score: float,
     *     word_count: int,
     *     knowledge_density: float,
     *     topic_coverage: float,
     *     answer_readiness: float,
     *     context_quality: float,
     *     chunk_integrity: float,
     *     readability: array<string, mixed>,
     *     missing_concepts: list<string>,
     *     missing_facets: list<string>,
     *     chunks: int
     * }
     */
    public function analyze(WP_Post $post): array
    {
        $html      = $post->post_content;
        $plain     = Text::plain($html);
        $wordCount = Text::wordCount($plain);
        $chunks    = $this->chunker->chunk($html);
        $entities  = $this->entities->forObject('post', $post->ID, 30);

        $density   = $this->knowledgeDensity($plain, $entities);
        $coverage  = $this->coverage->forPost($post, $entities);
        $answer    = $this->answerReadiness($post, $plain);
        $context   = $this->contextQuality($html, $plain, $entities);
        $integrity = $this->chunkIntegrity($chunks);
        $reading   = $this->readability->analyze($html);

        // Weights reflect what moves AI citation the most: a page has to be
        // answerable and self-contained before depth matters.
        $score = ($answer * 0.28)
            + ($integrity * 0.22)
            + ($density * 0.20)
            + ($coverage['score'] * 0.18)
            + ($context * 0.12);

        return [
            'score'             => round(min(100.0, max(0.0, $score)), 1),
            'word_count'        => $wordCount,
            'knowledge_density' => round($density, 1),
            'topic_coverage'    => round($coverage['score'], 1),
            'answer_readiness'  => round($answer, 1),
            'context_quality'   => round($context, 1),
            'chunk_integrity'   => round($integrity, 1),
            'readability'       => $reading,
            // Two forms, deliberately. `missing_concepts` is display text and
            // is what the API and dashboard show; `missing_facets` is the
            // stable slug, and is what anything matching on identity must use.
            'missing_concepts'  => TopicCoverage::labels($coverage['missing']),
            'missing_facets'    => $coverage['missing'],
            'chunks'            => count($chunks),
        ];
    }

    /**
     * Ratio of distinct content words and recognised entities to total words.
     *
     * A page that repeats the same twenty words for 2,000 words scores low;
     * one that introduces and uses many distinct concepts scores high.
     *
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $entities
     */
    private function knowledgeDensity(string $plain, array $entities): float
    {
        $tokens = Text::tokens($plain);
        $total  = count($tokens);

        if ($total < 50) {
            // Too short to measure meaningfully — and too short to be cited.
            return $total === 0 ? 0.0 : 25.0;
        }

        $distinct = count(array_unique($tokens));
        $ttr      = $distinct / $total;

        // Type-token ratio falls naturally with length, so it is normalised
        // against an expected curve rather than compared to a flat target.
        $expected  = max(0.18, 0.62 - (log($total, 10) * 0.10));
        $lexical   = min(1.0, $ttr / $expected);

        // Entity coverage: recognised things per 100 words, capped at 5.
        $entityRate = min(1.0, (count($entities) / max(1, $total / 100)) / 5);

        return (($lexical * 0.6) + ($entityRate * 0.4)) * 100;
    }

    /**
     * Is there a direct answer in the first screenful?
     *
     * Answer engines overwhelmingly quote from the opening of a document. A
     * page that buries its conclusion under 600 words of preamble will lose to
     * a weaker page that states its answer first.
     */
    private function answerReadiness(WP_Post $post, string $plain): float
    {
        $sentences = Text::sentences($plain);

        if ($sentences === []) {
            return 0.0;
        }

        $score = 0.0;
        $lead  = implode(' ', array_slice($sentences, 0, 3));

        // The subject of the title should appear immediately.
        $titleTokens = Text::tokens($post->post_title);
        $leadTokens  = Text::tokens($lead);
        $overlap     = $titleTokens === [] ? 0.0 : count(array_intersect($titleTokens, $leadTokens)) / count($titleTokens);

        $score += $overlap * 35;

        // A usable opening answer is one or two sentences, not a single clause
        // and not a paragraph.
        $leadWords = Text::wordCount($lead);

        if ($leadWords >= 25 && $leadWords <= 90) {
            $score += 25;
        } elseif ($leadWords > 0) {
            $score += 10;
        }

        // Explicit question framing in headings maps directly onto the queries
        // people put to assistants.
        $questionHeadings = (int) preg_match_all('#<h[2-4][^>]*>[^<]*[?؟]#iu', $post->post_content);
        $score += min(20, $questionHeadings * 7);

        // A summary, TL;DR or key-takeaways block is the single most quotable
        // element a page can have.
        if (preg_match('/\b(summary|tl;?dr|key takeaways|in short|خلاصه|چکیده|الخلاصة)\b/iu', $plain) === 1) {
            $score += 20;
        }

        return min(100.0, $score);
    }

    /**
     * Does the page define what it is talking about?
     *
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $entities
     */
    private function contextQuality(string $html, string $plain, array $entities): float
    {
        $score = 40.0; // Baseline: the page exists and has prose.

        // Definitional language near the primary entity.
        if ($entities !== []) {
            $primary = $entities[0]['entity']->name;
            $pattern = '/' . preg_quote(Text::normalize($primary), '/') . '\s+(is|are|means|refers to|عبارت است|یعنی|به معنای)/u';

            if (preg_match($pattern, Text::normalize($plain)) === 1) {
                $score += 20;
            }
        }

        // Outbound citations signal the page situates itself in a literature
        // rather than asserting in a vacuum.
        $externalLinks = (int) preg_match_all('#<a\s[^>]*href=["\']https?://#i', $html);

        if ($externalLinks > 0) {
            $score += min(20.0, $externalLinks * 4.0);
        }

        // Dates, figures and named studies are what make a claim checkable.
        if (preg_match('/\b(19|20)\d{2}\b/', $plain) === 1) {
            $score += 10;
        }

        if (preg_match('/\d+(\.\d+)?\s?%/u', $plain) === 1) {
            $score += 10;
        }

        return min(100.0, $score);
    }

    /**
     * Can each chunk be understood on its own?
     *
     * A chunk that opens with "This is why it matters" is useless in isolation
     * — the retriever will surface it and the model will have no referent.
     *
     * @param list<array{index: int, heading: string, text: string, tokens: int}> $chunks
     */
    private function chunkIntegrity(array $chunks): float
    {
        if ($chunks === []) {
            return 0.0;
        }

        $good = 0;

        foreach ($chunks as $chunk) {
            $points = 0;

            // A chunk inheriting a heading knows what it is about.
            if (trim($chunk['heading']) !== '') {
                $points++;
            }

            // Well-sized: big enough to carry an idea, small enough to be
            // retrieved whole.
            if ($chunk['tokens'] >= 80 && $chunk['tokens'] <= 450) {
                $points++;
            }

            // Does not open with a dangling reference.
            if (preg_match('/^\s*(this|that|these|those|it|they|همین|این|آن)\b/iu', $chunk['text']) !== 1) {
                $points++;
            }

            if ($points >= 2) {
                $good++;
            }
        }

        return ($good / count($chunks)) * 100;
    }
}
