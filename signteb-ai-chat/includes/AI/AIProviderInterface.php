<?php

namespace STMC_Chat\AI;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Provider abstraction so the AI backend (Anthropic today, OpenAI fallback)
 * can be swapped without touching the rest of the plugin.
 */
interface AIProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param string                                         $system   Fully built system prompt.
     * @param array<int,array{role:string,content:string}>   $messages Conversation turns (user/assistant).
     * @param array{model?:string,max_tokens?:int}           $options
     *
     * @return array{ok:bool,content?:string,tokens?:int,error?:string}
     */
    public function complete(string $system, array $messages, array $options = []): array;

    public function id(): string;
}
