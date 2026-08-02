<?php

declare(strict_types=1);

namespace Medora\Authority\Prompt;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds the LLM-facing representation of a page: summary, canonical answer,
 * fact sheet, question pack and a packed context window.
 *
 * Everything is derived extractively from the page's own words. That is a
 * deliberate product decision, not a limitation — a generated paraphrase would
 * introduce claims the publisher never made, which on a medical site is a
 * liability rather than a feature. Sites that do want generative rewriting can
 * hook `medora_prompt_pack` and call whatever model they like.
 */
final class PromptContextBuilder
{
    private const CONTEXT_TOKEN_BUDGET = 1200;

    public function __construct(
        private readonly PromptPackRepository $repository,
        private readonly EntityRepository $entities,
    ) {
    }

    /**
     * @return array{
     *     summary: string,
     *     canonical_answer: string,
     *     questions: list<array{question: string, answer: string}>,
     *     facts: list<string>,
     *     context_window: string,
     *     entities: list<string>
     * }
     */
    public function build(WP_Post $post, bool $force = false): array
    {
        $hash   = Text::hash($post->post_title . "\n" . $post->post_content);
        $cached = $this->repository->find('post', $post->ID);

        if (! $force && $cached !== null && $cached['content_hash'] === $hash) {
            return $this->shape($cached, $post);
        }

        $plain     = Text::plain($post->post_content);
        $sentences = Text::sentences($plain);
        $entities  = $this->entities->forObject('post', $post->ID, 12);

        $pack = [
            'summary'          => $this->summary($post, $sentences),
            'canonical_answer' => $this->canonicalAnswer($post, $sentences),
            'questions'        => $this->questionPack($post),
            'facts'            => $this->facts($plain, $sentences),
            'context_window'   => '',
            'entities'         => array_map(
                static fn (array $row): string => $row['entity']->name,
                $entities
            ),
        ];

        $pack['context_window'] = $this->contextWindow($post, $pack, $entities);

        /**
         * Filter the generated prompt pack. Hook here to replace the extractive
         * summary with a model-generated one.
         *
         * @param array<string, mixed> $pack
         * @param WP_Post              $post
         */
        $pack = (array) apply_filters('medora_prompt_pack', $pack, $post);

        $this->repository->save('post', $post->ID, $pack, $hash);

        // Mirrored into post meta so schema, llms.txt and the sitemap can read
        // the summary without a join.
        update_post_meta($post->ID, '_medora_ai_summary', $pack['summary']);

        return $this->shape($pack, $post);
    }

    /**
     * The two-to-three sentence description of what this page establishes.
     *
     * @param list<string> $sentences
     */
    private function summary(WP_Post $post, array $sentences): string
    {
        if ($post->post_excerpt !== '') {
            return Text::truncate(Text::plain($post->post_excerpt), 300);
        }

        if ($sentences === []) {
            return '';
        }

        // Rank sentences by overlap with the title and by position, then keep
        // them in document order so the summary still reads as prose.
        $titleTokens = Text::tokens($post->post_title);
        $scored      = [];

        foreach (array_slice($sentences, 0, 20) as $position => $sentence) {
            $tokens  = Text::tokens($sentence);
            $overlap = $titleTokens === [] ? 0 : count(array_intersect($titleTokens, $tokens));
            $words   = count($tokens);

            // Very short and very long sentences both make poor summaries.
            $lengthFit = $words >= 8 && $words <= 40 ? 1.0 : 0.4;
            $recency   = 1.0 - ($position / 20);

            $scored[$position] = (($overlap * 2.0) + ($recency * 1.5)) * $lengthFit;
        }

        arsort($scored);

        $chosen = array_slice(array_keys($scored), 0, 3);
        sort($chosen);

        $summary = implode(' ', array_map(static fn (int $i): string => $sentences[$i], $chosen));

        return Text::truncate($summary, 320);
    }

    /**
     * The single passage most likely to be quoted verbatim by an assistant.
     *
     * @param list<string> $sentences
     */
    private function canonicalAnswer(WP_Post $post, array $sentences): string
    {
        // An explicitly marked answer always wins — it is the editor telling us
        // what the page's conclusion is.
        $curated = (string) get_post_meta($post->ID, '_medora_canonical_answer', true);

        if ($curated !== '') {
            return $curated;
        }

        if (preg_match('#<[^>]*class="[^"]*medora-answer[^"]*"[^>]*>(.*?)</#is', $post->post_content, $match) === 1) {
            return Text::truncate(Text::plain($match[1]), 400);
        }

        if ($sentences === []) {
            return '';
        }

        $titleTokens = Text::tokens($post->post_title);
        $best        = '';
        $bestScore   = -1.0;

        foreach (array_slice($sentences, 0, 12) as $position => $sentence) {
            $tokens = Text::tokens($sentence);

            if ($tokens === []) {
                continue;
            }

            $overlap = $titleTokens === [] ? 0.0 : count(array_intersect($titleTokens, $tokens)) / count($titleTokens);
            $words   = count($tokens);

            // Declarative, self-contained, near the top.
            $score = ($overlap * 3.0)
                + (1.0 - ($position / 12))
                + (($words >= 12 && $words <= 45) ? 1.0 : 0.0)
                - (preg_match('/^\s*(this|that|it|these|این|آن)\b/iu', $sentence) === 1 ? 1.5 : 0.0);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $sentence;
            }
        }

        return Text::truncate($best, 400);
    }

    /**
     * Questions this page can answer, with their answers.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function questionPack(WP_Post $post): array
    {
        $pairs   = [];
        $pattern = '#<h([2-4])[^>]*>(.*?)</h\1>(.*?)(?=<h[2-4][\s>]|$)#is';

        if (preg_match_all($pattern, $post->post_content, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $heading = Text::plain($match[2] ?? '');
                $body    = Text::plain($match[3] ?? '');

                if ($heading === '' || Text::wordCount($body) < 6) {
                    continue;
                }

                $pairs[] = [
                    // Headings that are not already questions are reframed, so
                    // the pack matches how people actually query an assistant.
                    'question' => $this->asQuestion($heading, $post->post_title),
                    'answer'   => Text::truncate($body, 500),
                ];
            }
        }

        return array_slice($pairs, 0, 10);
    }

    private function asQuestion(string $heading, string $title): string
    {
        if (preg_match('/[?؟]\s*$/u', $heading) === 1) {
            return $heading;
        }

        if (preg_match('/^\s*(what|why|how|when|where|who|which|چه|چرا|چگونه|چطور|کی|کجا|آیا)\b/iu', $heading) === 1) {
            return rtrim($heading, '.') . '?';
        }

        // "Recovery time" on a page titled "Knee replacement" becomes
        // "Recovery time — Knee replacement?" rather than a guessed grammar
        // transform that would be wrong in at least one of the three locales.
        return sprintf('%s — %s?', rtrim($heading, '.'), $title);
    }

    /**
     * Checkable, self-contained statements. These are what a model can cite
     * without needing the surrounding paragraph.
     *
     * @param list<string> $sentences
     * @return list<string>
     */
    private function facts(string $plain, array $sentences): array
    {
        $facts = [];

        foreach ($sentences as $sentence) {
            $words = Text::wordCount($sentence);

            if ($words < 6 || $words > 45) {
                continue;
            }

            // A fact is a sentence carrying a number, a date, a percentage or a
            // named measure — the things that are verifiable.
            $hasFigure = preg_match('/\d/u', $sentence) === 1;

            if (! $hasFigure) {
                continue;
            }

            // Reject sentences opening with a dangling referent.
            if (preg_match('/^\s*(this|that|these|those|it|they|این|آن|همین)\b/iu', $sentence) === 1) {
                continue;
            }

            $facts[] = trim($sentence);

            if (count($facts) >= 12) {
                break;
            }
        }

        return $facts;
    }

    /**
     * A token-budgeted block a retriever can drop straight into a prompt.
     *
     * @param array<string, mixed> $pack
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $entities
     */
    private function contextWindow(WP_Post $post, array $pack, array $entities): string
    {
        $lines = [
            sprintf('# %s', $post->post_title),
            sprintf('Source: %s', get_permalink($post)),
            sprintf('Updated: %s', get_post_modified_time('c', true, $post)),
            '',
        ];

        if ($pack['canonical_answer'] !== '') {
            $lines[] = '## Direct answer';
            $lines[] = $pack['canonical_answer'];
            $lines[] = '';
        }

        if ($pack['summary'] !== '') {
            $lines[] = '## Summary';
            $lines[] = $pack['summary'];
            $lines[] = '';
        }

        if ($entities !== []) {
            $lines[] = '## Entities covered';

            foreach (array_slice($entities, 0, 8) as $row) {
                $lines[] = sprintf('- %s (%s)', $row['entity']->name, $row['entity']->type);
            }

            $lines[] = '';
        }

        if ($pack['facts'] !== []) {
            $lines[] = '## Key facts';

            foreach ($pack['facts'] as $fact) {
                $lines[] = '- ' . $fact;
            }

            $lines[] = '';
        }

        if ($pack['questions'] !== []) {
            $lines[] = '## Questions this page answers';

            foreach ($pack['questions'] as $pair) {
                $lines[] = sprintf('- %s', $pair['question']);
            }
        }

        $context = implode("\n", $lines);

        // Trim from the end rather than mid-section so the block always ends on
        // a complete line.
        while (Text::estimateTokens($context) > self::CONTEXT_TOKEN_BUDGET) {
            $parts = explode("\n", $context);
            array_pop($parts);
            $context = implode("\n", $parts);

            if (count($parts) < 5) {
                break;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $pack
     * @return array<string, mixed>
     */
    private function shape(array $pack, WP_Post $post): array
    {
        return [
            'summary'          => (string) ($pack['summary'] ?? ''),
            'canonical_answer' => (string) ($pack['canonical_answer'] ?? ''),
            'questions'        => array_values((array) ($pack['questions'] ?? [])),
            'facts'            => array_values((array) ($pack['facts'] ?? [])),
            'context_window'   => (string) ($pack['context_window'] ?? ''),
            'entities'         => array_values((array) ($pack['entities'] ?? [])),
            'url'              => (string) get_permalink($post),
            'title'            => get_the_title($post),
        ];
    }
}
