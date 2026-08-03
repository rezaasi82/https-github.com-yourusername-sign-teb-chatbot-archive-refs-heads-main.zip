<?php

declare(strict_types=1);

namespace Medora\Authority\Llm;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What a provider returned.
 *
 * `$stopReason` is carried rather than collapsed into a boolean because the
 * three ways a completion can end badly need different handling: a refusal is
 * final, a truncation is a request that asked for too much, and an error is
 * retryable. Callers that treat "no usable text" as one case ship truncated
 * summaries.
 */
final class GenerationResult
{
    public const STOP_END_TURN  = 'end_turn';
    public const STOP_MAX       = 'max_tokens';
    public const STOP_REFUSAL   = 'refusal';

    /**
     * @param array<string, mixed>|null $structured Decoded JSON when the
     *                                              request carried a schema.
     * @param array<string, int>        $usage      Token counts, for the audit record.
     */
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly string $stopReason = self::STOP_END_TURN,
        public readonly ?array $structured = null,
        public readonly array $usage = [],
    ) {
    }

    /**
     * The model declined. Not an error and not retryable — whatever was asked
     * for is not going to be produced, so the caller must fall back.
     */
    public function refused(): bool
    {
        return $this->stopReason === self::STOP_REFUSAL;
    }

    /**
     * The response hit the token ceiling. Structured output is then almost
     * certainly invalid JSON and free text is cut mid-sentence; either way it
     * must not be published.
     */
    public function truncated(): bool
    {
        return $this->stopReason === self::STOP_MAX;
    }

    public function usable(): bool
    {
        return ! $this->refused() && ! $this->truncated() && trim($this->text) !== '';
    }
}
