<?php

namespace SignTeb\VideoHub\Ai\Providers;

use SignTeb\VideoHub\Ai\AiProviderInterface;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Helpers\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Anthropic Messages API.
 */
class AnthropicProvider implements AiProviderInterface
{
    private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const TIMEOUT     = 90;

    public function __construct(
        private readonly string $api_key,
        private readonly string $base_url = ''
    ) {
    }

    public function id(): string
    {
        return 'anthropic';
    }

    public function default_model(): string
    {
        return 'claude-sonnet-5';
    }

    public function complete(string $system, array $messages, array $options = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'کلید API تنظیم نشده است.'];
        }

        $endpoint = $this->base_url !== ''
            ? rtrim($this->base_url, '/') . '/v1/messages'
            : self::ENDPOINT;

        $payload = [
            'model'      => $options['model'] ?? $this->default_model(),
            'max_tokens' => (int) ($options['max_tokens'] ?? 2048),
            'system'     => $system,
            'messages'   => array_values($messages),
        ];
        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => self::API_VERSION,
            ],
            'body'    => Json::encode($payload),
        ]);

        if (is_wp_error($response)) {
            Logger::error('ai', $response->get_error_message(), ['provider' => 'anthropic']);
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = Json::decode((string) wp_remote_retrieve_body($response));

        if ($code !== 200) {
            $message = (string) Json::dig($body, 'error.message', sprintf('پاسخ %d از Anthropic.', $code));
            Logger::error('ai', $message, ['provider' => 'anthropic', 'code' => $code]);
            return ['ok' => false, 'error' => $message];
        }

        $text = '';
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        if (trim($text) === '') {
            return ['ok' => false, 'error' => 'پاسخ خالی از مدل دریافت شد.'];
        }

        return [
            'ok'      => true,
            'content' => $text,
            'tokens'  => (int) Json::dig($body, 'usage.output_tokens', 0),
        ];
    }
}
