<?php

declare(strict_types=1);

namespace Medora\Authority\Llm;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A text-generation backend.
 *
 * Deliberately narrow: one synchronous completion, no streaming, no tool use,
 * no conversation state. Everything Medora generates is a short, bounded,
 * single-turn transformation of content the site already published — a
 * chat loop would be scope this product does not have.
 *
 * Implementations must not retry internally. Failures are thrown; the job
 * queue owns back-off, and a provider that quietly retries three times inside
 * a cron request turns a rate limit into a request timeout.
 */
interface LlmProviderInterface
{
    /** Stable identifier stored in settings. */
    public function id(): string;

    /** Human-readable name for the settings screen. */
    public function label(): string;

    /** True when the provider has everything it needs to be called. */
    public function isAvailable(): bool;

    /** The model this provider will use, for display and audit records. */
    public function model(): string;

    /**
     * @throws \RuntimeException On transport failure, an API error, or a
     *                           response that cannot be parsed.
     */
    public function complete(GenerationRequest $request): GenerationResult;
}
