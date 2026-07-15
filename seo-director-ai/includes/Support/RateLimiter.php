<?php
/**
 * Transient-backed fixed-window rate limiter for expensive endpoints
 * (AI explanations, rescans, license calls). Keyed per user + action.
 *
 * @package SEODirector
 */

namespace SEODirector\Support;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {

	/**
	 * Consume one unit; returns false when the window budget is exhausted.
	 */
	public function allow( string $action, int $limit, int $window_seconds ): bool {
		$key   = 'sda_rl_' . $action . '_' . get_current_user_id();
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window_seconds );

		return true;
	}
}
