<?php
/**
 * Contract every AI provider adapter implements.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

use WP_Error;

interface AiProviderInterface {

	/** Provider slug used in settings and connection rows. */
	public function slug(): string;

	/**
	 * Complete a prompt. Implementations return raw text; the router parses,
	 * validates against the envelope's schema, and retries once on schema failure.
	 *
	 * @return array{text: string, tokens: int}|WP_Error
	 */
	public function complete( PromptEnvelope $envelope, string $model, string $api_key ): array|WP_Error;
}
