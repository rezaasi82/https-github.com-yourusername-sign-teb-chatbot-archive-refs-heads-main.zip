<?php
/**
 * SWC_Provider_GapGPT — GapGPT provider (OpenAI-compatible gateway).
 *
 * GapGPT (api.gapgpt.app) is an Iran-accessible gateway that exposes an
 * OpenAI-compatible Chat Completions API and can serve both GPT and Claude
 * models. It is a good default for Iranian hosts where api.openai.com /
 * api.anthropic.com are often blocked or sanctioned. Same contract as the
 * other providers so SWC_AI_Manager can swap it transparently.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Provider_GapGPT implements SWC_AI_Provider_Interface
{
    private const ENDPOINT      = 'https://api.gapgpt.app/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function id(): string
    {
        return 'gapgpt';
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
                'timeout' => 22, // stay under the 30s host ceiling
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body'    => wp_json_encode([
                    'model'       => $context['model'] ?? self::DEFAULT_MODEL,
                    'max_tokens'  => (int) ($context['max_tokens'] ?? 1024),
                    'temperature' => (float) ($context['temperature'] ?? 0.8),
                    'messages'    => array_values($payload_messages),
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
