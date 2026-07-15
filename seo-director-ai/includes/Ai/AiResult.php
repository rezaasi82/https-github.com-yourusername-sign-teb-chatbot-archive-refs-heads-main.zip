<?php
/**
 * Immutable AI response value object.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class AiResult {

	/**
	 * @param array<string, mixed>|null $payload      Parsed, schema-valid JSON, or null on failure.
	 * @param string                    $provider     Provider slug that answered.
	 * @param string                    $model        Model id used.
	 * @param int                       $tokens_used  Total tokens billed (input + output).
	 * @param string|null               $error        Error message when payload is null.
	 */
	public function __construct(
		public readonly ?array $payload,
		public readonly string $provider,
		public readonly string $model,
		public readonly int $tokens_used,
		public readonly ?string $error = null,
	) {}

	public function ok(): bool {
		return null !== $this->payload && null === $this->error;
	}

	public static function failure( string $provider, string $model, string $error ): self {
		return new self( null, $provider, $model, 0, $error );
	}
}
