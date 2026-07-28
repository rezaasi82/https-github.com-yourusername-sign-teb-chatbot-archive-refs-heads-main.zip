<?php

namespace SignTeb\VideoHub\Ai\Providers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * GapGPT — an Iran-accessible gateway exposing an OpenAI-compatible Chat
 * Completions API, able to serve both GPT and Claude models.
 *
 * This matters operationally, not just commercially: on most Iranian hosts
 * `api.openai.com` and `api.anthropic.com` are unreachable, so the direct
 * providers fail with a connection error no amount of configuration fixes.
 *
 * The wire protocol is identical to OpenAI's, so this only pins the base URL
 * and the identity — the request/response handling is inherited rather than
 * duplicated.
 */
class GapGptProvider extends OpenAiProvider
{
    public const BASE_URL = 'https://api.gapgpt.app/v1';

    /**
     * @param string $base_url Optional override; falls back to GapGPT's own.
     */
    public function __construct(string $api_key, string $base_url = '')
    {
        parent::__construct($api_key, $base_url !== '' ? $base_url : self::BASE_URL);
    }

    public function id(): string
    {
        return 'gapgpt';
    }

    public function default_model(): string
    {
        return 'gpt-4o-mini';
    }
}
