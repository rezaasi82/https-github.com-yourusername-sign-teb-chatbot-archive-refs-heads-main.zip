<?php

namespace SignTeb\VideoHub\Ai\Providers;

use SignTeb\VideoHub\Ai\AiProviderInterface;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Helpers\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI-compatible chat completions.
 *
 * The base URL is configurable so the same class serves OpenAI and any
 * compatible relay (a necessity for sites that cannot reach api.openai.com
 * directly).
 */
class OpenAiProvider implements AiProviderInterface
{
    private const DEFAULT_BASE = 'https://api.openai.com/v1';
    private const TIMEOUT      = 45;

    public function __construct(
        private readonly string $api_key,
        private readonly string $base_url = ''
    ) {
    }

    public function id(): string
    {
        return 'openai';
    }

    public function default_model(): string
    {
        return 'gpt-4o-mini';
    }

    public function complete(string $system, array $messages, array $options = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'کلید API تنظیم نشده است.'];
        }

        $base     = $this->base_url !== '' ? rtrim($this->base_url, '/') : self::DEFAULT_BASE;
        $endpoint = $base . '/chat/completions';

        $payload = [
            'model'      => $options['model'] ?? $this->default_model(),
            'max_tokens' => (int) ($options['max_tokens'] ?? 2048),
            'messages'   => array_merge(
                [['role' => 'system', 'content' => $system]],
                array_values($messages)
            ),
        ];
        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body'    => Json::encode($payload),
        ]);

        if (is_wp_error($response)) {
            Logger::error('ai', $response->get_error_message(), ['provider' => 'openai']);
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = Json::decode((string) wp_remote_retrieve_body($response));

        if ($code !== 200) {
            $message = (string) Json::dig($body, 'error.message', sprintf('پاسخ %d از سرویس هوش مصنوعی.', $code));
            Logger::error('ai', $message, ['provider' => 'openai', 'code' => $code]);
            return ['ok' => false, 'error' => $message];
        }

        $text = (string) Json::dig($body, 'choices.0.message.content', '');
        if (trim($text) === '') {
            return ['ok' => false, 'error' => 'پاسخ خالی از مدل دریافت شد.'];
        }

        return [
            'ok'      => true,
            'content' => $text,
            'tokens'  => (int) Json::dig($body, 'usage.completion_tokens', 0),
        ];
    }
}
