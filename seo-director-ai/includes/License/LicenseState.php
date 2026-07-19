<?php
/**
 * Immutable snapshot of the current license, with the derived "effective edition"
 * that FeatureGate consults. During grace the paid edition still applies; once the
 * grace window closes it collapses to FREE (data is never touched).
 *
 * @package SEODirector
 */

namespace SEODirector\License;

defined( 'ABSPATH' ) || exit;

final class LicenseState {

	public const ACTIVE      = 'active';
	public const EXPIRED     = 'expired';
	public const GRACE       = 'grace';
	public const INVALID     = 'invalid';
	public const DEACTIVATED = 'deactivated';

	public function __construct(
		public readonly string $status,
		public readonly string $edition,
		public readonly ?string $expires_at,
		public readonly ?string $grace_ends_at = null,
	) {}

	/** No license row yet — the free tier. */
	public static function free(): self {
		return new self( self::DEACTIVATED, Edition::FREE, null, null );
	}

	/**
	 * Edition that actually governs features right now.
	 * active/grace → the licensed edition; anything else → free.
	 */
	public function effective_edition(): string {
		return in_array( $this->status, array( self::ACTIVE, self::GRACE ), true )
			? $this->edition
			: Edition::FREE;
	}

	public function is_grace(): bool {
		return self::GRACE === $this->status;
	}

	/** @return array<string, mixed> Serializable form for the REST/SPA layer. */
	public function to_array(): array {
		return array(
			'status'            => $this->status,
			'edition'           => $this->edition,
			'effective_edition' => $this->effective_edition(),
			'expires_at'        => $this->expires_at,
			'grace_ends_at'     => $this->grace_ends_at,
		);
	}
}
