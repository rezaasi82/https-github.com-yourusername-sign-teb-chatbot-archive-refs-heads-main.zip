<?php

declare(strict_types=1);

namespace Medora\Authority\Llm;

use Medora\Authority\Security\AuditLogRepository;
use Medora\Authority\Support\Text;
use RuntimeException;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Rewrites the extractive prompt pack into fluent prose — where, and only
 * where, the rewrite can be shown to be faithful.
 *
 * The extractive pack is the baseline, not the fallback of last resort: this
 * class starts from it, asks the model to improve specific fields, checks each
 * returned field against the page independently, and keeps the extractive
 * value for any field that fails. A page can therefore end up with a generated
 * summary and an extractive canonical answer. That is the intended outcome —
 * partial adoption beats an all-or-nothing gate that discards three good
 * fields because a fourth invented a number.
 *
 * Nothing here runs on a page view. Generation is queued work.
 */
final class PackGenerator
{
    /**
     * How much of the page to send. Long enough that the model sees the whole
     * argument on a typical article, bounded so a 20,000-word pillar page does
     * not turn one summary into a very expensive request.
     */
    private const SOURCE_TOKEN_BUDGET = 12000;

    public function __construct(
        private readonly LlmProviderInterface $provider,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $pack
     * @return array<string, mixed>
     */
    public function enhance(array $pack, WP_Post $post): array
    {
        $source = $this->source($post);

        // Below roughly a screenful there is nothing to summarise that the
        // extractive pass has not already found, and short pages are where
        // models are most tempted to pad.
        if (Text::wordCount($source) < 120) {
            return $pack;
        }

        try {
            $result = $this->provider->complete(new GenerationRequest(
                system: $this->system($post),
                prompt: $this->prompt($post, $pack, $source),
                source: $source,
                schema: $this->schema(),
                maxTokens: 8000,
                purpose: 'prompt_pack',
            ));
        } catch (RuntimeException $exception) {
            // Rethrown so the queue records the failure and backs off. The
            // extractive pack is already saved, so the page is never left
            // without one.
            $this->audit->record('llm.failed', 'post', $post->ID, [
                'model' => $this->provider->model(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($result->refused()) {
            $this->audit->record('llm.refused', 'post', $post->ID, ['model' => $result->model]);

            return $pack;
        }

        if (! $result->usable() || $result->structured === null) {
            $this->audit->record('llm.unusable', 'post', $post->ID, [
                'model'       => $result->model,
                'stop_reason' => $result->stopReason,
            ]);

            return $pack;
        }

        $adopted = [];

        foreach (['summary', 'canonical_answer'] as $field) {
            $candidate = trim((string) ($result->structured[$field] ?? ''));

            if ($candidate === '' || $candidate === $pack[$field]) {
                continue;
            }

            $check = Grounding::check($candidate, $source);

            if (! $check['supported']) {
                $this->audit->record('llm.rejected', 'post', $post->ID, [
                    'field'            => $field,
                    'model'            => $result->model,
                    'support_ratio'    => $check['ratio'],
                    'invented_numbers' => $check['invented_numbers'],
                ]);

                continue;
            }

            $pack[$field] = $candidate;
            $adopted[]    = $field;
        }

        $filled = $this->fillAnswers($pack, $result->structured, $source, $post, $result->model);

        if ($adopted !== [] || $filled > 0) {
            $this->audit->record('llm.generated', 'post', $post->ID, [
                'model'          => $result->model,
                'fields'         => $adopted,
                'answers_filled' => $filled,
                'usage'          => $result->usage,
            ]);
        }

        return $pack;
    }

    /**
     * Adopt generated answers only for questions the extractive pass left
     * blank, and only for questions already in the pack.
     *
     * The question set comes from the site's own headings and FAQ blocks. A
     * model inventing questions the publisher never asked would be putting
     * words in their mouth in the most literal sense, so extra questions in
     * the response are dropped without comment.
     *
     * @param array<string, mixed> $pack
     * @param array<string, mixed> $structured
     */
    private function fillAnswers(array &$pack, array $structured, string $source, WP_Post $post, string $model): int
    {
        $generated = [];

        foreach ((array) ($structured['questions'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = Text::normalize((string) ($row['question'] ?? ''));

            if ($key !== '') {
                $generated[$key] = trim((string) ($row['answer'] ?? ''));
            }
        }

        $filled = 0;

        foreach ($pack['questions'] as $index => $question) {
            if (trim((string) $question['answer']) !== '') {
                continue;
            }

            $candidate = $generated[Text::normalize((string) $question['question'])] ?? '';

            if ($candidate === '') {
                continue;
            }

            if (! Grounding::check($candidate, $source)['supported']) {
                $this->audit->record('llm.rejected', 'post', $post->ID, [
                    'field'    => 'question_answer',
                    'model'    => $model,
                    'question' => $question['question'],
                ]);

                continue;
            }

            $pack['questions'][$index]['answer'] = $candidate;
            $filled++;
        }

        return $filled;
    }

    private function system(WP_Post $post): string
    {
        $language = $this->language($post);

        // Kept byte-identical across every post on the site so the cached
        // system block actually hits — anything post-specific belongs in the
        // user message.
        return implode("\n", [
            'You write structured summaries of web pages so that AI assistants can quote them accurately.',
            '',
            'Absolute constraints:',
            '- Use only information stated in the page provided. Add nothing from your own knowledge, however certain you are of it.',
            '- Never state a number, dose, price, duration, percentage or date that does not appear in the page.',
            '- Never introduce a named entity — person, organisation, product, place, condition — that the page does not name.',
            '- Do not soften, strengthen, or generalise a claim. "Often reduces" must not become "reduces".',
            '- Do not add advice, reassurance, warnings, or calls to action that the page does not contain.',
            '- Prefer the page\'s own terminology over synonyms, including for medical and technical terms.',
            '- If the page does not answer a question, return an empty string for that answer. An empty answer is a correct answer.',
            '',
            'Write in ' . $language . ', matching the register and formality of the page.',
            'Your output is checked against the source automatically; text that introduces unsupported material is discarded.',
        ]);
    }

    /**
     * @param array<string, mixed> $pack
     */
    private function prompt(WP_Post $post, array $pack, string $source): string
    {
        $questions = [];

        foreach ($pack['questions'] as $question) {
            if (trim((string) $question['answer']) === '') {
                $questions[] = '- ' . $question['question'];
            }
        }

        $sections = [
            'PAGE TITLE: ' . $post->post_title,
            '',
            'PAGE CONTENT:',
            $source,
            '',
            'TASK:',
            '1. summary — two or three sentences describing what this page establishes. Reads as prose, not as a list of keywords.',
            '2. canonical_answer — the single passage an assistant should quote when asked what this page is about. One paragraph, self-contained, understandable without the surrounding page.',
        ];

        if ($questions !== []) {
            $sections[] = '3. questions — answer each of the following from the page, in one or two sentences. Return an empty answer for any the page does not address:';
            $sections[] = implode("\n", $questions);
        } else {
            $sections[] = '3. questions — return an empty array.';
        }

        return implode("\n", $sections);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['summary', 'canonical_answer', 'questions'],
            'properties'           => [
                'summary' => [
                    'type'        => 'string',
                    'description' => 'Two or three sentences. Empty string if the page is too thin to summarise.',
                ],
                'canonical_answer' => [
                    'type'        => 'string',
                    'description' => 'One self-contained paragraph. Empty string if the page has no single answer.',
                ],
                'questions' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['question', 'answer'],
                        'properties'           => [
                            'question' => ['type' => 'string'],
                            'answer'   => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function source(WP_Post $post): string
    {
        $plain = Text::plain($post->post_content);

        if (Text::estimateTokens($plain) <= self::SOURCE_TOKEN_BUDGET) {
            return $plain;
        }

        // Truncated by characters against an estimated token budget. Cutting
        // the tail rather than sampling the middle keeps the opening — which
        // is what a summary is mostly drawn from — intact, and the grounding
        // check then runs against the same truncated text the model saw, so a
        // sentence supported only by the discarded tail is correctly rejected.
        $ratio = self::SOURCE_TOKEN_BUDGET / max(1, Text::estimateTokens($plain));

        return Text::truncate($plain, (int) (mb_strlen($plain, 'UTF-8') * $ratio));
    }

    private function language(WP_Post $post): string
    {
        /**
         * Filter the language the generated pack is written in.
         *
         * @param string  $language Human-readable language name.
         * @param WP_Post $post
         */
        return (string) apply_filters(
            'medora_llm_language',
            $this->languageName(get_locale()),
            $post
        );
    }

    private function languageName(string $locale): string
    {
        return match (substr($locale, 0, 2)) {
            'fa'    => 'Persian (فارسی)',
            'ar'    => 'Arabic (العربية)',
            'tr'    => 'Turkish',
            'fr'    => 'French',
            'de'    => 'German',
            'es'    => 'Spanish',
            default => 'English',
        };
    }
}
