<?php
/**
 * OpenAI Chat Completions provider.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class ProviderOpenai extends \SignTeb\WebChat\Ai\ProviderOpenaiCompatible
{
    public function id(): string
    {
        return 'openai';
    }

    protected function endpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function default_model(): string
    {
        return 'gpt-4o-mini';
    }
}
