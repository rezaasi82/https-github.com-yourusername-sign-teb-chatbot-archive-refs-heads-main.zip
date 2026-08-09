<?php
/**
 * OpenAI Chat Completions provider.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class ProviderOpenai extends \Pezhkam\Ai\ProviderOpenaiCompatible
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
