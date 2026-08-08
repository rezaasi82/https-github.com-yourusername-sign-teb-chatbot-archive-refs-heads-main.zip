<?php
/**
 * Google Gemini provider (Generative Language API).
 *
 * Gemini speaks neither the OpenAI nor the Anthropic protocol, so this is a
 * standalone class rather than a subclass: turns live under "contents", the
 * assistant role is called "model", the system prompt is its own top-level
 * field, and generation settings sit in "generationConfig".
 *
 * @package Medora
 */

namespace Medora\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class ProviderGemini implements \Medora\Ai\AiProviderInterface
{
    private const ENDPOINT      = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const DEFAULT_MODEL = 'gemini-2.5-flash';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function id(): string
    {
        return 'gemini';
    }

    public function generate_reply(string $message, array $context = []): array
    {
        if ($this->api_key === '') {
            return ['ok' => false, 'error' => 'missing_api_key'];
        }

        $model = (string) ($context['model'] ?? self::DEFAULT_MODEL);
        $body  = [
            'contents'         => $this->build_contents($message, $context['history'] ?? []),
            'generationConfig' => [
                'temperature'     => (float) ($context['temperature'] ?? 0.8),
                'maxOutputTokens' => (int) ($context['max_tokens'] ?? 1024),
            ],
        ];

        $system = trim((string) ($context['system'] ?? ''));
        if ($system !== '') {
            $body['system_instruction'] = ['parts' => [['text' => $system]]];
        }

        $response = wp_remote_post(
            sprintf(self::ENDPOINT, rawurlencode($model)),
            [
                'timeout' => 22, // stay under the 30s host ceiling
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'x-goog-api-key' => $this->api_key,
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
        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        // A safety block returns 200 with no parts; say so rather than
        // handing an empty bubble to the visitor.
        if (trim($text) === '') {
            $reason = (string) ($data['candidates'][0]['finishReason'] ?? $data['promptFeedback']['blockReason'] ?? 'empty_response');
            return ['ok' => false, 'error' => 'gemini_' . strtolower($reason)];
        }

        return [
            'ok'      => true,
            'content' => trim($text),
            'tokens'  => (int) ($data['usageMetadata']['totalTokenCount'] ?? 0),
        ];
    }

    /**
     * Gemini calls the assistant role "model" and wraps each turn's text in a
     * parts array. History arrives already normalised to start with a user
     * turn and alternate, so it only needs translating.
     *
     * @param array<int,array{role:string,content:string}> $history
     * @return array<int,array{role:string,parts:array<int,array{text:string}>}>
     */
    private function build_contents(string $message, array $history): array
    {
        $contents = [];
        $last     = null;

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $text = (string) ($turn['content'] ?? '');
            $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            $last       = ['role' => $role, 'text' => $text];
        }

        // Ensure the latest user message is the final turn.
        if ($last === null || $last['role'] !== 'user' || $last['text'] !== $message) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
        }

        return $contents;
    }
}
