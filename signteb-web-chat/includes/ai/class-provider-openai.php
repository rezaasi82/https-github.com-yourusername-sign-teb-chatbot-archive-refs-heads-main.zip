<?php
/**
 * SWC_Provider_OpenAI — OpenAI Chat Completions provider.
 *
 * Same contract as SWC_Provider_Anthropic so SWC_AI_Manager can swap them
 * transparently based on the admin's choice.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Provider_OpenAI implements SWC_AI_Provider_Interface
{
    private const ENDPOINT      = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function id(): string
    {
        return 'openai';
    }

    public function generate_reply(string $message, array $context = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'missing_api_key'];
        }

        $payload_messages = [];
        $system           = (string) ($context['system'] ?? '');
        if ($system !== '') {
            $payload_messages[] = ['role' => 'system', 'content' => $system];
        }
        foreach (($context['history'] ?? []) as $m) {
            $payload_messages[] = ['role' => $m['role'], 'content' => $m['content']];
        }
        $last = end($payload_messages);
        if (! $last || $last['role'] !== 'user' || $last['content'] !== $message) {
            $payload_messages[] = ['role' => 'user', 'content' => $message];
        }

        $response = wp_remote_post(
            self::ENDPOINT,
            [
                'timeout' => 22,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body'    => wp_json_encode([
                    'model'      => $context['model'] ?? self::DEFAULT_MODEL,
                    'max_tokens' => (int) ($context['max_tokens'] ?? 1024),
                    'messages'   => array_values($payload_messages),
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
