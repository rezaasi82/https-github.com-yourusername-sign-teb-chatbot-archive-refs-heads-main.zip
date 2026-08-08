<?php

namespace SignTeb\VideoHub\Ai;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI backend abstraction. Swapping models or vendors never touches the
 * generators that build prompts.
 */
interface AiProviderInterface
{
    /**
     * @param string                                       $system   System prompt.
     * @param array<int,array{role:string,content:string}> $messages Conversation turns.
     * @param array{model?:string,max_tokens?:int,temperature?:float} $options
     *
     * @return array{ok:bool,content?:string,tokens?:int,error?:string}
     */
    public function complete(string $system, array $messages, array $options = []): array;

    public function id(): string;

    public function default_model(): string;
}
