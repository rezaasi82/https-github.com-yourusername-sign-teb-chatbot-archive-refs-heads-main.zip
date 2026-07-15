<?php
/**
 * /sda/v1/alerts — list and acknowledge/snooze/resolve.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\AlertsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class AlertsController extends BaseController {

	public function __construct( private readonly AlertsRepository $alerts ) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/alerts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$this->ns(),
			'/alerts/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'status'        => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'acknowledged', 'snoozed', 'resolved' ),
					),
					'snoozed_until' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function list(): WP_REST_Response {
		return new WP_REST_Response( array( 'alerts' => $this->alerts->list_active( 50 ) ) );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ok = $this->alerts->set_status(
			(int) $request['id'],
			(string) $request->get_param( 'status' ),
			$request->get_param( 'snoozed_until' ) ? (string) $request->get_param( 'snoozed_until' ) : null
		);
		if ( ! $ok ) {
			return new WP_Error( 'sda_not_found', __( 'Alert not found.', 'seo-director-ai' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'updated' => true ) );
	}
}
