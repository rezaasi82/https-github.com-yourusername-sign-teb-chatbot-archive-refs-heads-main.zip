<?php
/**
 * Computes the enforcement state from expiry + server status. Pure logic:
 *
 *   active                                  → 'active'
 *   expired ≤ 14 days ago (grace)           → 'grace'   (everything still works)
 *   expired > 14 days ago (full lock)       → 'expired' (PRO pauses, data kept)
 *   server unreachable past outage tolerance→ 'expired'
 *
 * @package SEODirector
 */

namespace SEODirector\License;

defined( 'ABSPATH' ) || exit;

final class GracePeriodHandler {

	private const GRACE_DAYS = 14;

	/**
	 * @param string      $server_status active|expired|invalid|deactivated
	 * @param string|null $expires_at    MySQL datetime (UTC) or null.
	 * @param bool        $outage        Cached payload is stale past tolerance.
	 */
	public function resolve( string $server_status, ?string $expires_at, bool $outage ): string {
		if ( 'deactivated' === $server_status || 'invalid' === $server_status ) {
			return 'expired';
		}

		if ( $outage ) {
			return 'expired';
		}

		if ( null === $expires_at ) {
			return 'active' === $server_status ? 'active' : 'expired';
		}

		$expiry = strtotime( $expires_at );
		if ( false === $expiry ) {
			return 'active';
		}

		if ( time() <= $expiry ) {
			return 'active';
		}

		$days_expired = (int) floor( ( time() - $expiry ) / DAY_IN_SECONDS );

		return $days_expired <= self::GRACE_DAYS ? 'grace' : 'expired';
	}

	/**
	 * Days until expiry (negative when past). Null when no expiry.
	 */
	public function days_left( ?string $expires_at ): ?int {
		if ( null === $expires_at ) {
			return null;
		}

		$expiry = strtotime( $expires_at );
		if ( false === $expiry ) {
			return null;
		}

		return (int) ceil( ( $expiry - time() ) / DAY_IN_SECONDS );
	}
}
