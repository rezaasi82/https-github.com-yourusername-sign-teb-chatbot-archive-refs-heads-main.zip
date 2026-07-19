<?php
/**
 * Shared REST controller plumbing: capability checks, namespace.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Capabilities;
use SEODirector\Rest\RestServiceProvider;

abstract class BaseController {

	abstract public function register(): void;

	protected function ns(): string {
		return RestServiceProvider::NAMESPACE;
	}

	/**
	 * Cookie-authenticated SPA requests already pass the X-WP-Nonce check in core;
	 * these callbacks add the capability layer.
	 */
	public function can_view(): bool {
		return current_user_can( Capabilities::VIEW_REPORTS ) || current_user_can( Capabilities::MANAGE );
	}

	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE );
	}

	/**
	 * Sliding-window rate limit for expensive endpoints (per user).
	 */
	protected function rate_limit( string $bucket, int $max_per_hour ): bool {
		$key   = 'sda_rl_' . $bucket . '_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= $max_per_hour ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}
}
