<?php

namespace STMC_Chat\AI\Providers;

use STMC_Chat\AI\AIProviderInterface;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Anthropic Claude Messages API provider.
 *
 * Uses wp_remote_post with a short timeout (host max_execution_time is ~30s)
 * and full error handling — never crashes the request, returns a structured
 * result the caller can fall back on.
 */
class AnthropicProvider implements AIProviderInterface
{
    private const ENDPOINT      = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION   = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-opus-4-8';

    public function __construct(private string $api_key)
    {
    }

    public function id(): string
    {
        return 'anthropic';
    }

    public function complete(string $system, array $messages, array $options = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'missing_api_key'];
        }

        $body = [
            'model'      => $options['model'] ?? self::DEFAULT_MODEL,
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'system'     => $system,
            'messages'   => array_map(
                static fn($m) => ['role' => $m['role'], 'content' => $m['content']],
                $messages
            ),
        ];

        $response = wp_remote_post(
            self::ENDPOINT,
            [
                'timeout' => 22, // stay under the 30s host ceiling
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => self::API_VERSION,
                ],
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || ! is_array($data)) {
            $msg = is_array($data) && isset($data['error']['message'])
                ? $data['error']['message']
                : 'http_' . $code;
            return ['ok' => false, 'error' => $msg];
        }

        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        $tokens = (int) (($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0));

        return ['ok' => true, 'content' => trim($text), 'tokens' => $tokens];
    }
}
