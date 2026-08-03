<?php

declare(strict_types=1);

namespace Medora\Authority\Llm\Providers;

use Medora\Authority\Core\Options;
use Medora\Authority\Llm\GenerationRequest;
use Medora\Authority\Llm\GenerationResult;
use Medora\Authority\Llm\LlmProviderInterface;
use RuntimeException;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Claude, over the Messages API.
 *
 * Called with `wp_remote_post` rather than the official `anthropic-ai/sdk`
 * package, for the same reason `OpenAiEmbeddingProvider` is: this plugin ships
 * with zero runtime PHP dependencies (see docs/04-security.md). A WordPress
 * plugin is installed by unzipping it — Composer is not available at the
 * install site, so a bundled `vendor/` would be the only option, and two
 * plugins bundling different versions of the same package in one PHP process
 * is a fatal error nobody can debug. The request shape below is pinned to
 * `anthropic-version: 2023-06-01`, which is what makes the raw call safe.
 */
final class AnthropicProvider implements LlmProviderInterface
{
    private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-opus-5';

    public function __construct(private readonly Options $options)
    {
    }

    public function id(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return __('Anthropic (Claude)', 'medora-authority');
    }

    public function isAvailable(): bool
    {
        return $this->apiKey() !== '';
    }

    public function model(): string
    {
        $model = trim($this->options->getString('llm_model', self::DEFAULT_MODEL));

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    public function complete(GenerationRequest $request): GenerationResult
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Medora: no Anthropic API key configured.');
        }

        $response = wp_remote_post(self::ENDPOINT, [
            // Long enough for a thinking model on a long page, short enough to
            // fail before PHP's own execution limit does. Not streamed: every
            // Medora completion is a few hundred words against a bounded
            // max_tokens, so the timeout headroom is ample and a streaming
            // reader on top of wp_remote_post would be machinery with nothing
            // to buy it.
            'timeout' => 120,
            'headers' => [
                'x-api-key'         => $this->apiKey(),
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ],
            'body' => (string) wp_json_encode($this->body($request), JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Medora LLM request failed: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code !== 200 || ! is_array($body)) {
            throw new RuntimeException($this->errorMessage($code, $body));
        }

        return $this->parse($body, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(GenerationRequest $request): array
    {
        $body = [
            'model'      => $this->model(),
            'max_tokens' => $request->maxTokens,

            // A structured system block rather than a bare string, so the
            // instructions — which are identical for every post on the site —
            // are cached across the whole re-generation sweep.
            'system' => [
                [
                    'type'          => 'text',
                    'text'          => $request->system,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],

            'messages' => [
                ['role' => 'user', 'content' => $request->prompt],
            ],

            // Adaptive thinking: the model decides how much reasoning the page
            // warrants. `budget_tokens` is deliberately absent — current models
            // reject it with a 400 — as are `temperature`, `top_p` and `top_k`,
            // which are likewise rejected. There is also no assistant prefill;
            // the schema below is what constrains the shape of the answer.
            'thinking' => ['type' => 'adaptive'],
        ];

        if ($request->schema !== null) {
            $body['output_config'] = [
                'format' => [
                    'type'   => 'json_schema',
                    'schema' => $request->schema,
                ],
            ];
        }

        /**
         * Filter the outgoing Messages API request body.
         *
         * @param array<string, mixed> $body
         * @param GenerationRequest    $request
         */
        return (array) apply_filters('medora_llm_request_body', $body, $request);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function parse(array $body, GenerationRequest $request): GenerationResult
    {
        $stopReason = (string) ($body['stop_reason'] ?? GenerationResult::STOP_END_TURN);
        $usage      = [
            'input_tokens'  => (int) ($body['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($body['usage']['output_tokens'] ?? 0),
        ];

        // Checked before touching `content`: on a refusal the content array is
        // not the answer, and reading it as one is how a refusal ends up
        // published as a page summary.
        if ($stopReason === GenerationResult::STOP_REFUSAL) {
            return new GenerationResult('', $this->model(), GenerationResult::STOP_REFUSAL, null, $usage);
        }

        $text = '';

        foreach ((array) ($body['content'] ?? []) as $block) {
            // The content array is polymorphic — thinking blocks and, in other
            // configurations, tool-use blocks share it with text.
            if (! is_array($block) || ($block['type'] ?? '') !== 'text') {
                continue;
            }

            $text .= (string) ($block['text'] ?? '');
        }

        $structured = null;

        if ($request->expectsJson() && $stopReason !== GenerationResult::STOP_MAX) {
            $decoded = json_decode(trim($text), true);

            // A schema was requested, so anything other than an object here
            // means the contract was not honoured. Left as null rather than
            // coerced; the caller falls back to its extractive path.
            $structured = is_array($decoded) ? $decoded : null;
        }

        return new GenerationResult($text, $this->model(), $stopReason, $structured, $usage);
    }

    /**
     * @param mixed $body
     */
    private function errorMessage(int $code, $body): string
    {
        $detail = is_array($body) ? (string) ($body['error']['message'] ?? '') : '';

        $hint = match (true) {
            $code === 401 => ' Check MEDORA_LLM_API_KEY.',
            $code === 429 => ' Rate limited; the queue will retry.',
            $code >= 500  => ' Upstream error; the queue will retry.',
            default       => '',
        };

        return sprintf('Medora LLM API returned HTTP %d.%s %s', $code, $hint, $detail);
    }

    private function apiKey(): string
    {
        // Same precedence as the embedding key: environment first, then a
        // constant in wp-config.php, and only then the database — which the
        // security scanner flags, because options land in every backup.
        $fromEnv = (string) getenv('MEDORA_LLM_API_KEY');

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        if (defined('MEDORA_LLM_API_KEY')) {
            return (string) constant('MEDORA_LLM_API_KEY');
        }

        return $this->options->getString('llm_api_key');
    }
}
