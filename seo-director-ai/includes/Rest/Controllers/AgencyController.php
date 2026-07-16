<?php
/**
 * Hub-side management of paired client sites.
 *
 *   GET    /sda/v1/agency/sites        — the sites grid.
 *   POST   /sda/v1/agency/sites/pair   — add a client; returns the one-time
 *                                        pairing key + ingest URL to paste into
 *                                        the client site (the key is never
 *                                        retrievable again).
 *   DELETE /sda/v1/agency/sites/{id}   — unpair a client.
 *
 * All routes require the manage_sda_clients capability and an Agency license.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Agency\SiteConnector;
use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\AgencySitesRepository;
use SEODirector\License\FeatureGate;

defined( 'ABSPATH' ) || exit;

final class AgencyController extends AbstractController {

	public function __construct(
		private AgencySitesRepository $sites,
		private FeatureGate $gate,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/agency/sites',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_sites' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE_CLIENTS ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/agency/sites/pair',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'pair' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE_CLIENTS ),
				'args'                => [
					'client_name' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'site_url'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'esc_url_raw' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/agency/sites/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'unpair' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE_CLIENTS ),
			]
		);
	}

	public function list_sites(): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return rest_ensure_response( [ 'items' => $this->sites->all() ] );
	}

	public function pair( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$site_url = (string) $request->get_param( 'site_url' );
		if ( '' === $site_url || ! wp_http_validate_url( $site_url ) ) {
			return new \WP_Error( 'sda_bad_url', __( 'A valid client site URL is required.', 'seo-director-ai' ), [ 'status' => 400 ] );
		}

		$pair_key = SiteConnector::generate_pair_key();
		$this->sites->create( (string) $request->get_param( 'client_name' ), $site_url, $pair_key );

		return rest_ensure_response(
			[
				'items'      => $this->sites->all(),
				// Shown exactly once — the hub only keeps an encrypted copy.
				'pair_key'   => $pair_key,
				'ingest_url' => rest_url( self::REST_NAMESPACE . '/hub/ingest' ),
				'hub_url'    => home_url(),
			]
		);
	}

	public function unpair( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$this->sites->delete( (int) $request->get_param( 'id' ) );

		return rest_ensure_response( [ 'items' => $this->sites->all() ] );
	}

	private function guard(): bool|\WP_Error {
		if ( ! $this->gate->allows( 'agency_hub' ) ) {
			return new \WP_Error( 'sda_agency', __( 'The agency hub requires an Agency license.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}

		return true;
	}
}
