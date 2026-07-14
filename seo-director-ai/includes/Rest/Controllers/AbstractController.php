<?php
/**
 * Shared REST controller behavior: namespace, capability-based permission
 * callbacks, and uniform error responses.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

abstract class AbstractController {

	public const REST_NAMESPACE = 'sda/v1';

	abstract public function register_routes(): void;

	/**
	 * Permission callback factory for a capability. Cookie-authenticated SPA
	 * requests are additionally covered by core's rest nonce handling.
	 *
	 * @return callable(): (bool|\WP_Error)
	 */
	protected function require_cap( string $capability ): callable {
		return static function () use ( $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}

			return new \WP_Error(
				'sda_forbidden',
				__( 'You are not allowed to access this resource.', 'seo-director-ai' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		};
	}
}
