<?php
/**
 * /sda/v1/properties — discover properties from connected Google accounts
 * and select the active one per service.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Integrations\Google\SearchConsoleClient;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PropertiesController extends BaseController {

	public function __construct(
		private readonly PropertiesRepository $properties,
		private readonly ConnectionsRepository $connections,
		private readonly SearchConsoleClient $gsc,
		private readonly Analytics4Client $ga4,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/properties',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'service' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'gsc', 'ga4' ),
					),
				),
			)
		);

		register_rest_route(
			$this->ns(),
			'/properties/(?P<id>\d+)/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'service' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'gsc', 'ga4' ),
					),
				),
			)
		);
	}

	/**
	 * Discovery is an explicit admin action, so a live Google call here is fine
	 * (the "no live calls" rule protects dashboard reads, not setup flows).
	 */
	public function list( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service    = (string) $request->get_param( 'service' );
		$connection = $this->connections->get_by_service( $service );
		if ( ! $connection ) {
			return new WP_Error( 'sda_not_connected', __( 'Connect the service first.', 'seo-director-ai' ), array( 'status' => 409 ) );
		}

		if ( 'gsc' === $service ) {
			$discovered = $this->gsc->list_properties();
			if ( is_wp_error( $discovered ) ) {
				return $discovered;
			}
			foreach ( $discovered as $entry ) {
				$this->properties->register( (int) $connection->id, 'gsc', (string) $entry['siteUrl'], (string) $entry['siteUrl'] );
			}
		} else {
			$discovered = $this->ga4->list_properties();
			if ( is_wp_error( $discovered ) ) {
				return $discovered;
			}
			foreach ( $discovered as $entry ) {
				$this->properties->register( (int) $connection->id, 'ga4', (string) $entry['property'], (string) $entry['displayName'] );
			}
		}

		return new WP_REST_Response( array( 'properties' => $this->properties->list_for_service( $service ) ) );
	}

	public function activate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ok = $this->properties->activate( (int) $request['id'], (string) $request->get_param( 'service' ) );
		if ( ! $ok ) {
			return new WP_Error( 'sda_not_found', __( 'Property not found.', 'seo-director-ai' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'activated' => true ) );
	}
}
