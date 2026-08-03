<?php

declare(strict_types=1);

namespace Medora\Authority\Vector\Providers;

use Medora\Authority\Core\Options;
use Medora\Authority\Vector\EmbeddingProviderInterface;
use RuntimeException;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Embeddings from any OpenAI-compatible `/embeddings` endpoint.
 *
 * The base URL is configurable, so this one class also covers Azure OpenAI,
 * self-hosted vLLM / Ollama gateways and the several regional providers that
 * mirror the same wire format — which matters for customers who cannot reach
 * api.openai.com directly.
 */
final class OpenAiEmbeddingProvider implements EmbeddingProviderInterface
{
    private const BATCH_SIZE = 64;

    public function __construct(private readonly Options $options)
    {
    }

    public function id(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return __('OpenAI-compatible API', 'medora-authority');
    }

    public function dimensions(): int
    {
        return max(64, $this->options->getInt('embedding_dimensions', 1536));
    }

    public function isAvailable(): bool
    {
        return $this->apiKey() !== '';
    }

    public function embed(string $text): array
    {
        $vectors = $this->embedBatch([$text]);

        return $vectors[0] ?? array_fill(0, $this->dimensions(), 0.0);
    }

    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        if (! $this->isAvailable()) {
            throw new RuntimeException('Medora: no embedding API key configured.');
        }

        $vectors = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            foreach ($this->request($batch) as $vector) {
                $vectors[] = $vector;
            }
        }

        return $vectors;
    }

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    private function request(array $texts): array
    {
        $response = wp_remote_post($this->baseUrl() . '/embeddings', [
            // Embedding a batch of 64 chunks is not fast; the timeout has to
            // accommodate that or every large post fails on the first pass.
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey(),
                'Content-Type'  => 'application/json',
            ],
            'body' => (string) wp_json_encode([
                'model' => $this->model(),
                'input' => array_values($texts),
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Medora embedding request failed: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code !== 200 || ! is_array($body) || ! isset($body['data'])) {
            $message = is_array($body) ? (string) ($body['error']['message'] ?? '') : '';

            throw new RuntimeException(sprintf('Medora embedding API returned HTTP %d. %s', $code, $message));
        }

        $vectors = [];

        foreach ($body['data'] as $item) {
            $embedding = array_map('floatval', (array) ($item['embedding'] ?? []));

            // The API returns unit vectors, but normalising defensively keeps
            // the cosine-as-dot-product assumption true for every provider.
            $vectors[] = $this->normalize($embedding);
        }

        return $vectors;
    }

    /**
     * @param list<float> $vector
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));

        return $magnitude > 0.0
            ? array_map(static fn (float $v): float => $v / $magnitude, $vector)
            : $vector;
    }

    private function apiKey(): string
    {
        // An environment variable beats the database: it keeps the secret out
        // of backups and out of any options export.
        $fromEnv = (string) getenv('MEDORA_EMBEDDING_API_KEY');

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        if (defined('MEDORA_EMBEDDING_API_KEY')) {
            return (string) constant('MEDORA_EMBEDDING_API_KEY');
        }

        return $this->options->getString('embedding_api_key');
    }

    private function baseUrl(): string
    {
        $url = $this->options->getString('embedding_base_url', 'https://api.openai.com/v1');

        return rtrim($url, '/');
    }

    private function model(): string
    {
        return $this->options->getString('embedding_model', 'text-embedding-3-small');
    }
}
