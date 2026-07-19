<?php
/**
 * /sda/v1/license — activate, deactivate, status.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\License\FeatureGate;
use SEODirector\License\LicenseManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class LicenseController extends BaseController {

	public function __construct(
		private readonly LicenseManager $manager,
		private readonly FeatureGate $gate,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/license/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$this->ns(),
			'/license/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'license_key' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->ns(),
			'/license/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'deactivate' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	public function status(): WP_REST_Response {
		return new WP_REST_Response( $this->payload() );
	}

	public function activate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->rate_limit( 'license_activate', 10 ) ) {
			return new WP_Error( 'sda_rate_limited', __( 'Too many attempts — try again shortly.', 'seo-director-ai' ), array( 'status' => 429 ) );
		}
		$result = $this->manager->activate( (string) $request->get_param( 'license_key' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->gate->flush();
		return new WP_REST_Response( $this->payload() );
	}

	public function deactivate(): WP_REST_Response {
		$this->manager->deactivate();
		$this->gate->flush();
		return new WP_REST_Response( $this->payload() );
	}

	/** @return array<string, mixed> */
	private function payload(): array {
		return array(
			'license'      => $this->manager->current_state()->to_array(),
			'capabilities' => $this->gate->capabilities(),
		);
	}
}
