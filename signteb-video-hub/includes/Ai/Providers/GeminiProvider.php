<?php

namespace SignTeb\VideoHub\Ai\Providers;

use SignTeb\VideoHub\Ai\AiProviderInterface;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Helpers\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Google Gemini via the Generative Language API.
 *
 * Its request shape differs from the OpenAI one in three ways that are easy to
 * get wrong: the system prompt is its own top-level field rather than a first
 * message, the assistant role is called `model`, and every turn's text lives
 * in a `parts` array instead of a plain string.
 *
 * The base URL is configurable for the same reason it is on the OpenAI
 * provider: `generativelanguage.googleapis.com` is not reachable from many
 * Iranian hosts, and a compatible relay is the only way through.
 */
class GeminiProvider implements AiProviderInterface
{
    private const DEFAULT_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    private const TIMEOUT      = 45;

    public function __construct(
        private readonly string $api_key,
        private readonly string $base_url = ''
    ) {
    }

    public function id(): string
    {
        return 'gemini';
    }

    /**
     * Google retires model aliases on its own schedule, so this is a starting
     * point rather than a guarantee — the settings screen has a model field
     * precisely so an operator can move on without waiting for a release.
     */
    public function default_model(): string
    {
        return 'gemini-2.5-flash';
    }

    public function complete(string $system, array $messages, array $options = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'کلید API تنظیم نشده است.'];
        }

        $model = (string) ($options['model'] ?? '');
        $model = $model !== '' ? $model : $this->default_model();
        $base  = $this->base_url !== '' ? rtrim($this->base_url, '/') : self::DEFAULT_BASE;

        $endpoint = sprintf('%s/models/%s:generateContent', $base, rawurlencode($model));

        $response = wp_remote_post($endpoint, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                // Header rather than ?key=, so the secret stays out of access
                // logs and error reports along the way.
                'x-goog-api-key' => $this->api_key,
            ],
            'body'    => Json::encode(self::build_payload($system, $messages, $options)),
        ]);

        if (is_wp_error($response)) {
            Logger::error('ai', $response->get_error_message(), ['provider' => 'gemini']);
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = Json::decode((string) wp_remote_retrieve_body($response));

        if ($code !== 200) {
            $message = (string) Json::dig($body, 'error.message', sprintf('پاسخ %d از سرویس هوش مصنوعی.', $code));
            Logger::error('ai', $message, ['provider' => 'gemini', 'code' => $code, 'model' => $model]);
            return ['ok' => false, 'error' => $message];
        }

        // A safety filter returns 200 with no candidate at all. Reporting that
        // as "empty response" would send an operator hunting for a network
        // fault that does not exist.
        $blocked = (string) Json::dig($body, 'promptFeedback.blockReason', '');
        if ($blocked !== '') {
            return [
                'ok'    => false,
                'error' => sprintf('درخواست توسط فیلتر ایمنی گوگل رد شد (%s).', $blocked),
            ];
        }

        $text = self::extract_text($body);
        if (trim($text) === '') {
            $finish = (string) Json::dig($body, 'candidates.0.finishReason', '');

            return [
                'ok'    => false,
                'error' => $finish !== '' && $finish !== 'STOP'
                    ? sprintf('پاسخ ناتمام از مدل (%s).', $finish)
                    : 'پاسخ خالی از مدل دریافت شد.',
            ];
        }

        return [
            'ok'      => true,
            'content' => $text,
            'tokens'  => (int) Json::dig($body, 'usageMetadata.candidatesTokenCount', 0),
        ];
    }

    /**
     * @param array<int,array{role:string,content:string}>            $messages
     * @param array{max_tokens?:int,temperature?:float,model?:string} $options
     *
     * @return array<string,mixed>
     */
    public static function build_payload(string $system, array $messages, array $options = []): array
    {
        $contents = [];
        foreach ($messages as $message) {
            $text = (string) ($message['content'] ?? '');
            if (trim($text) === '') {
                continue;
            }

            $contents[] = [
                // Gemini names the assistant turn `model`; anything else is a
                // 400, so map rather than pass through.
                'role'  => ($message['role'] ?? 'user') === 'user' ? 'user' : 'model',
                'parts' => [['text' => $text]],
            ];
        }

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'maxOutputTokens' => (int) ($options['max_tokens'] ?? 2048),
            ],
        ];

        if (trim($system) !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        if (isset($options['temperature'])) {
            $payload['generationConfig']['temperature'] = (float) $options['temperature'];
        }

        return $payload;
    }

    /**
     * A reply can arrive split across several parts; taking only the first
     * silently truncates it.
     *
     * @param array<mixed> $body
     */
    public static function extract_text(array $body): string
    {
        $parts = Json::dig($body, 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return '';
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        return implode('', $chunks);
    }
}
