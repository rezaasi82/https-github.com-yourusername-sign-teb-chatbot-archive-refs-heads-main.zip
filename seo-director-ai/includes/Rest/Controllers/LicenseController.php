<?php
/**
 * GET /sda/v1/license/status · POST /activate · POST /deactivate.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\License\FeatureGate;
use SEODirector\License\LicenseManager;
use SEODirector\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class LicenseController extends AbstractController {

	public function __construct(
		private LicenseManager $license,
		private FeatureGate $gate,
		private RateLimiter $limiter,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/license/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'status' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/license/activate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'activate' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'license_key' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/license/deactivate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'deactivate' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);
	}

	public function status(): \WP_REST_Response {
		return rest_ensure_response(
			[
				'license'  => $this->license->status(),
				'features' => $this->gate->snapshot(),
				'edition'  => $this->gate->effective_edition(),
			]
		);
	}

	public function activate( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->limiter->allow( 'license_activate', 10, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sda_rate', __( 'Too many attempts. Please try again later.', 'seo-director-ai' ), [ 'status' => 429 ] );
		}

		$result = $this->license->activate( (string) $request->get_param( 'license_key' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->status();
	}

	public function deactivate(): \WP_REST_Response|\WP_Error {
		$result = $this->license->deactivate();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->status();
	}
}
