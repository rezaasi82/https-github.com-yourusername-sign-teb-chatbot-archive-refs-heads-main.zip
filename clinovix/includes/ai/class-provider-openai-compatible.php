<?php
/**
 * Base class for providers that speak the OpenAI Chat Completions protocol.
 *
 * Concrete providers only declare their id, endpoint and default model; the
 * request/response handling lives here so it is written once.
 *
 * @package Clinovix
 */

namespace Clinovix\Ai;

if (! defined('ABSPATH')) {
    exit;
}

abstract class ProviderOpenaiCompatible implements \Clinovix\Ai\AiProviderInterface
{
    protected string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    abstract protected function endpoint(): string;

    abstract protected function default_model(): string;

    public function generate_reply(string $message, array $context = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'missing_api_key'];
        }

        $response = wp_remote_post(
            $this->endpoint(),
            [
                'timeout' => 22,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body'    => wp_json_encode([
                    'model'       => $context['model'] ?? $this->default_model(),
                    'max_tokens'  => (int) ($context['max_tokens'] ?? 1024),
                    'temperature' => (float) ($context['temperature'] ?? 0.8),
                    'messages'    => $this->build_messages($message, $context),
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

        return [
            'ok'      => true,
            'content' => trim((string) ($data['choices'][0]['message']['content'] ?? '')),
            'tokens'  => (int) ($data['usage']['total_tokens'] ?? 0),
        ];
    }

    /**
     * @return array<int,array{role:string,content:string}>
     */
    private function build_messages(string $message, array $context): array
    {
        $messages = [];
        $system   = (string) ($context['system'] ?? '');
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        foreach (($context['history'] ?? []) as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $last = end($messages);
        if (! $last || $last['role'] !== 'user' || $last['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        return array_values($messages);
    }
}
