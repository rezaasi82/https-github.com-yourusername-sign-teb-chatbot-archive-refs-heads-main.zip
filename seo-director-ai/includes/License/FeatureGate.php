<?php
/**
 * The single place the rest of the plugin asks "is this feature unlocked?".
 * Resolves the effective edition (grace-aware) once per request and caches it.
 *
 * @package SEODirector
 */

namespace SEODirector\License;

defined( 'ABSPATH' ) || exit;

final class FeatureGate {

	private ?string $effective_edition = null;

	public function __construct( private readonly LicenseManager $manager ) {}

	/**
	 * Is a feature available under the current (effective) edition?
	 */
	public function can( string $feature ): bool {
		$edition = $this->edition();
		$allowed = Edition::includes( $edition, $feature );

		/**
		 * Filter a feature-gate decision (e.g. dev overrides, bundled promos).
		 *
		 * @param bool   $allowed Whether the feature is unlocked.
		 * @param string $feature Feature slug.
		 * @param string $edition Effective edition.
		 */
		return (bool) apply_filters( 'sda_feature_can', $allowed, $feature, $edition );
	}

	/**
	 * Effective edition, memoized for the request.
	 */
	public function edition(): string {
		if ( null === $this->effective_edition ) {
			$this->effective_edition = $this->manager->current_state()->effective_edition();
		}
		return $this->effective_edition;
	}

	/**
	 * A capability map for the SPA so it can hide/lock UI without extra round-trips.
	 *
	 * @return array<string, bool>
	 */
	public function capabilities(): array {
		$caps = array();
		foreach ( Edition::gated_features() as $feature ) {
			$caps[ $feature ] = $this->can( $feature );
		}
		return $caps;
	}

	/** Reset the memoized edition (after activation changes state mid-request). */
	public function flush(): void {
		$this->effective_edition = null;
	}
}
