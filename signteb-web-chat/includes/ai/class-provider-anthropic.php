<?php
/**
 * SWC_Provider_Anthropic — Anthropic Claude Messages API provider.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Uses wp_remote_post with a short timeout (host max_execution_time is ~30s)
 * and full error handling — it never crashes the request and always returns a
 * structured result the caller can fall back on.
 */
class SWC_Provider_Anthropic implements SWC_AI_Provider_Interface
{
    private const ENDPOINT      = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION   = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function id(): string
    {
        return 'anthropic';
    }

    public function generate_reply(string $message, array $context = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'missing_api_key'];
        }

        $messages   = $this->build_messages($message, $context['history'] ?? []);
        $body = [
            'model'      => $context['model'] ?? self::DEFAULT_MODEL,
            'max_tokens' => (int) ($context['max_tokens'] ?? 1024),
            'system'     => (string) ($context['system'] ?? ''),
            'messages'   => $messages,
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
            $msg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : 'http_' . $code;
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

    /**
     * @param array<int,array{role:string,content:string}> $history
     * @return array<int,array{role:string,content:string}>
     */
    private function build_messages(string $message, array $history): array
    {
        $messages = array_map(
            static fn($m) => ['role' => $m['role'], 'content' => $m['content']],
            $history
        );

        // Ensure the latest user message is the final turn.
        $last = end($messages);
        if (! $last || $last['role'] !== 'user' || $last['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        return array_values($messages);
    }
}
