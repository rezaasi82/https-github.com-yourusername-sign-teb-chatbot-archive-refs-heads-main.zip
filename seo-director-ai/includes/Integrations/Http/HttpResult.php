<?php
/**
 * Immutable HTTP response value object.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Http;

defined( 'ABSPATH' ) || exit;

final class HttpResult {

	public function __construct(
		public readonly int $status,
		public readonly string $body,
		public readonly ?string $error,
	) {}

	public function ok(): bool {
		return null === $this->error && $this->status >= 200 && $this->status < 300;
	}

	/**
	 * Decoded JSON body, or null when the body is not valid JSON.
	 *
	 * @return array<mixed>|null
	 */
	public function json(): ?array {
		$decoded = json_decode( $this->body, true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
