<?php
/**
 * Normalized AI completion result.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class AiResult {

	/** @param array<string, mixed> $payload Schema-validated JSON payload. */
	public function __construct(
		public readonly array $payload,
		public readonly string $provider,
		public readonly string $model,
		public readonly int $tokens_used,
	) {}
}
