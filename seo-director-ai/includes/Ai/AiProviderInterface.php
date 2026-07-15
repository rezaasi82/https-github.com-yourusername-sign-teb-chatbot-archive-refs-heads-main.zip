<?php
/**
 * Contract every AI provider adapter implements. Adapters translate a
 * PromptEnvelope into their provider's wire format, call the API, and return
 * the raw assistant text plus token usage — schema validation and caching
 * happen one layer up, so adapters stay thin and uniform.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

interface AiProviderInterface {

	/**
	 * Provider slug: openai | claude | gemini.
	 */
	public function slug(): string;

	/**
	 * Whether this provider is configured (has an API key).
	 */
	public function is_configured(): bool;

	/**
	 * Raw completion. Returns [text, tokens_used] or a WP_Error.
	 *
	 * @return array{text: string, tokens: int}|\WP_Error
	 */
	public function complete( PromptEnvelope $envelope ): array|\WP_Error;

	/**
	 * The model id this provider will use (for logging/attribution).
	 */
	public function model(): string;
}
