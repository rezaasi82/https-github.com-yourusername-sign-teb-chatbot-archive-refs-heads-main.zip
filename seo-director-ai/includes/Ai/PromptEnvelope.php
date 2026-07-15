<?php
/**
 * Versioned prompt + structured evidence handed to an AI provider.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class PromptEnvelope {

	/**
	 * @param array<string, mixed> $evidence      Structured JSON evidence packet.
	 * @param array<string, mixed> $output_schema JSON-schema the provider must satisfy.
	 */
	public function __construct(
		public readonly string $prompt_id,
		public readonly string $prompt_version,
		public readonly string $system_prompt,
		public readonly string $user_prompt,
		public readonly array $evidence,
		public readonly array $output_schema,
		public readonly string $language = 'en',
		public readonly int $max_tokens = 1500,
	) {}

	/**
	 * Cache key: identical prompt version + evidence never re-spends tokens.
	 */
	public function evidence_hash(): string {
		return md5( $this->prompt_version . '|' . wp_json_encode( $this->evidence ) . '|' . $this->language, true );
	}

	/**
	 * Full user message: prompt text + serialized evidence + schema instruction.
	 */
	public function render_user_message(): string {
		return $this->user_prompt
			. "\n\n## Evidence (JSON)\n" . wp_json_encode( $this->evidence )
			. "\n\n## Output\nRespond with ONLY a JSON object matching this JSON Schema — no markdown, no prose:\n"
			. wp_json_encode( $this->output_schema );
	}
}
