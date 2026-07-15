<?php
/**
 * Immutable request to the AI layer: a versioned prompt template plus the
 * structured evidence packet, site context, target language, and the JSON
 * schema the response must satisfy. Numbers live in the evidence; the model
 * only produces text fields.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class PromptEnvelope {

	/**
	 * @param string               $prompt_version Prompt template id (e.g. "root_cause.v1").
	 * @param string               $system         System instruction (role + rules + language).
	 * @param array<string, mixed> $evidence       Structured evidence packet (the AI's only inputs).
	 * @param array<string, mixed> $schema         JSON schema the response must satisfy.
	 * @param string               $lang           Target output language (en, fa, ar).
	 * @param int                  $max_tokens     Output cap.
	 */
	public function __construct(
		public readonly string $prompt_version,
		public readonly string $system,
		public readonly array $evidence,
		public readonly array $schema,
		public readonly string $lang = 'en',
		public readonly int $max_tokens = 1500,
	) {}

	/**
	 * The user-turn content: evidence as pretty JSON plus an explicit
	 * "respond only with JSON matching this schema" instruction.
	 */
	public function user_message(): string {
		return "Evidence (JSON):\n"
			. wp_json_encode( $this->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
			. "\n\nRespond with a single JSON object matching this schema. Do not include any prose outside the JSON:\n"
			. wp_json_encode( $this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Stable hash of everything that determines the output — the cache key.
	 */
	public function cache_hash(): string {
		return md5(
			$this->prompt_version . '|' . $this->lang . '|'
			. wp_json_encode( $this->evidence ) . '|' . wp_json_encode( $this->schema ),
			true
		);
	}
}
