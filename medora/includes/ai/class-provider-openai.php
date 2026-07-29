<?php
/**
 * OpenAI Chat Completions provider.
 *
 * @package Medora
 */

namespace Medora\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class ProviderOpenai extends \Medora\Ai\ProviderOpenaiCompatible
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
