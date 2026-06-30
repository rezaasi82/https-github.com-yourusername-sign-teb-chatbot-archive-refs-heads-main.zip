<?php

namespace STMC_Chat\AI\Providers;

use STMC_Chat\AI\AIProviderInterface;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI Chat Completions provider — kept as an optional fallback backend.
 * Same contract as AnthropicProvider so AIManager can swap them transparently.
 */
class OpenAIProvider implements AIProviderInterface
{
    private const ENDPOINT      = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    public function __construct(private string $api_key)
    {
    }

    public function id(): string
    {
        return 'openai';
    }

    public function complete(string $system, array $messages, array $options = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'missing_api_key'];
        }

        $payload_messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            array_map(static fn($m) => ['role' => $m['role'], 'content' => $m['content']], $messages)
        );

        $response = wp_remote_post(
            self::ENDPOINT,
            [
                'timeout' => 22,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body'    => wp_json_encode([
                    'model'      => $options['model'] ?? self::DEFAULT_MODEL,
                    'max_tokens' => $options['max_tokens'] ?? 1024,
                    'messages'   => $payload_messages,
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || ! is_array($data)) {
            $msg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : 'http_' . $code;
            return ['ok' => false, 'error' => $msg];
        }

        $text   = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        $tokens = (int) ($data['usage']['total_tokens'] ?? 0);

        return ['ok' => true, 'content' => $text, 'tokens' => $tokens];
    }
}
