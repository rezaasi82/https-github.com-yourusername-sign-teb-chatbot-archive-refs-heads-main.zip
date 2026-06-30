<?php
/**
 * SWC_AI_Provider_Interface — provider abstraction.
 *
 * Adding a new AI backend means adding one class that implements this
 * interface; nothing else in the plugin changes.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

interface SWC_AI_Provider_Interface
{
    /**
     * Generate a reply.
     *
     * @param string $message The latest user message.
     * @param array  $context {
     *     @type string                                       $system   Fully built system prompt.
     *     @type array<int,array{role:string,content:string}> $history  Prior conversation turns.
     *     @type string                                       $model    Model id to use.
     *     @type int                                          $max_tokens
     * }
     *
     * @return array{ok:bool,content?:string,tokens?:int,error?:string}
     */
    public function generate_reply(string $message, array $context = []): array;

    /**
     * Stable provider id, e.g. "anthropic" or "openai".
     */
    public function id(): string;
}
