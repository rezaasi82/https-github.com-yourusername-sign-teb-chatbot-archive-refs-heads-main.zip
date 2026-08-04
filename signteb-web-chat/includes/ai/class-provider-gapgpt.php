<?php
/**
 * GapGPT provider — an OpenAI-compatible gateway reachable from inside Iran,
 * where the OpenAI and Anthropic endpoints are frequently blocked. Serves both
 * GPT and Claude models through the same protocol.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class ProviderGapgpt extends \SignTeb\WebChat\Ai\ProviderOpenaiCompatible
{
    public function id(): string
    {
        return 'gapgpt';
    }

    protected function endpoint(): string
    {
        return 'https://api.gapgpt.app/v1/chat/completions';
    }

    protected function default_model(): string
    {
        return 'gpt-4o-mini';
    }
}
