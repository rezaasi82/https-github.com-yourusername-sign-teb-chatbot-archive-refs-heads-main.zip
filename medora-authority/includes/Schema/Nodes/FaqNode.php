<?php

declare(strict_types=1);

namespace Medora\Authority\Schema\Nodes;

use Medora\Authority\Schema\NodeInterface;
use Medora\Authority\Schema\SchemaContext;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Extracts a `FAQPage` from question-shaped headings.
 *
 * Question headings followed by an answer are already the most citable
 * structure a page can have; marking them up makes that structure explicit
 * rather than something the model has to infer from formatting.
 *
 * Editor-curated FAQs (stored in post meta) always take precedence over
 * extracted ones — inference should never overwrite an explicit decision.
 */
final class FaqNode implements NodeInterface
{
    private const MIN_ANSWER_WORDS = 8;
    private const MAX_QUESTIONS    = 12;

    public function id(): string
    {
        return 'faq';
    }

    public function appliesTo(SchemaContext $context): bool
    {
        return $context->post instanceof WP_Post && $context->isSingular;
    }

    public function build(SchemaContext $context): array
    {
        $post = $context->post;

        if (! $post instanceof WP_Post) {
            return [];
        }

        $pairs = $this->curated($post) ?: $this->extracted($post);

        if (count($pairs) < 2) {
            // A single Q&A is not an FAQ page; emitting one invites a
            // structured-data warning without any upside.
            return [];
        }

        $questions = [];

        foreach (array_slice($pairs, 0, self::MAX_QUESTIONS) as $pair) {
            $questions[] = [
                '@type'          => 'Question',
                'name'           => $pair['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $pair['answer'],
                ],
            ];
        }

        return [[
            '@type'      => 'FAQPage',
            '@id'        => $context->id('faq'),
            'isPartOf'   => ['@id' => $context->id('webpage')],
            'mainEntity' => $questions,
        ]];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function curated(WP_Post $post): array
    {
        $stored = get_post_meta($post->ID, '_medora_faq', true);

        if (! is_array($stored)) {
            return [];
        }

        $pairs = [];

        foreach ($stored as $entry) {
            $question = trim((string) ($entry['question'] ?? ''));
            $answer   = trim((string) ($entry['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $pairs[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $pairs;
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function extracted(WP_Post $post): array
    {
        // Capture each heading with everything up to the next heading, so the
        // answer is the whole section rather than the first paragraph only.
        $pattern = '#<h([2-4])[^>]*>(.*?)</h\1>(.*?)(?=<h[2-4][\s>]|$)#is';

        if (preg_match_all($pattern, $post->post_content, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $pairs = [];

        foreach ($matches as $match) {
            $heading = Text::plain($match[2] ?? '');
            $body    = Text::plain($match[3] ?? '');

            if (! $this->isQuestion($heading)) {
                continue;
            }

            if (Text::wordCount($body) < self::MIN_ANSWER_WORDS) {
                continue;
            }

            $pairs[] = [
                'question' => $heading,
                // Answers longer than ~50 words get truncated in rich results
                // anyway; a tight answer is more likely to be quoted whole.
                'answer'   => Text::truncate($body, 600),
            ];
        }

        return $pairs;
    }

    private function isQuestion(string $heading): bool
    {
        if ($heading === '') {
            return false;
        }

        // Explicit question marks, Latin or Arabic.
        if (preg_match('/[?؟]\s*$/u', $heading) === 1) {
            return true;
        }

        // Interrogative openers in the three shipped locales.
        return preg_match(
            '/^\s*(what|why|how|when|where|who|which|can|do|does|is|are|should)\b|^\s*(چه|چرا|چگونه|چطور|کی|کجا|آیا|چند)\b|^\s*(ما|لماذا|كيف|متى|أين|هل)\b/iu',
            $heading
        ) === 1;
    }
}
