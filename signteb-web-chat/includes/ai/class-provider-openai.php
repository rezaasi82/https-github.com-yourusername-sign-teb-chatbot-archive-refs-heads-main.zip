<?php
/**
 * OpenAI Chat Completions provider.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Provider_OpenAI extends SWC_OpenAI_Compatible_Provider
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
