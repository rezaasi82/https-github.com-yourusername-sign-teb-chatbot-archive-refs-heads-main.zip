<?php
/**
 * GET/POST /sda/v1/settings.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Ai\InsightService;
use SEODirector\Ai\TokenBudget;
use SEODirector\Core\Capabilities;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsController extends AbstractController {

	public function __construct(
		private Settings $settings,
		private TokenBudget $budget,
		private InsightService $insights,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
					'args'                => [
						'settings' => [
							'type'     => 'object',
							'required' => true,
						],
					],
				],
			]
		);
	}

	/** Never echoed back to the SPA; exposed only as a boolean "is set" flag. */
	private const SECRET_KEYS = [ 'agency_pair_key', 'license_shared_secret' ];

	public function get_settings(): \WP_REST_Response {
		$all         = $this->settings->all();
		$secrets_set = [];

		foreach ( self::SECRET_KEYS as $key ) {
			$secrets_set[ $key ] = '' !== (string) ( $all[ $key ] ?? '' );
			unset( $all[ $key ] );
		}

		return rest_ensure_response(
			[
				'settings'    => $all,
				'secrets_set' => $secrets_set,
				'ai'          => [
					'available' => $this->insights->is_available(),
					'meter'     => $this->budget->meter(),
				],
			]
		);
	}

	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$incoming = $request->get_param( 'settings' );
		$this->settings->update( is_array( $incoming ) ? $incoming : [] );

		// Re-read through the same redaction path so secrets never round-trip.
		return $this->get_settings();
	}
}
