<?php

declare(strict_types=1);

namespace Medora\Authority\Llm;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One completion request.
 *
 * `$source` is not sent as-is — it is the text the response will be checked
 * against by {@see Grounding}. Carrying it on the request keeps the prompt and
 * the material it must stay faithful to in one object, so a caller cannot
 * accidentally verify against a different document than it prompted with.
 */
final class GenerationRequest
{
    /**
     * @param array<string, mixed>|null $schema JSON Schema the response must
     *                                          satisfy, or null for free text.
     */
    public function __construct(
        public readonly string $system,
        public readonly string $prompt,
        public readonly string $source = '',
        public readonly ?array $schema = null,
        public readonly int $maxTokens = 8000,
        public readonly string $purpose = 'generic',
    ) {
    }

    public function expectsJson(): bool
    {
        return $this->schema !== null;
    }
}
