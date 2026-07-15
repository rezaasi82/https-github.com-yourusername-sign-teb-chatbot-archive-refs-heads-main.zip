<?php
/**
 * GET /sda/v1/alerts — list by status.
 * PATCH /sda/v1/alerts/{id} — acknowledge / snooze / resolve.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\AlertsRepository;

defined( 'ABSPATH' ) || exit;

final class AlertsController extends AbstractController {

	public function __construct( private AlertsRepository $alerts ) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/alerts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
				'args'                => [
					'status' => [ 'type' => 'string', 'default' => 'active', 'enum' => [ 'active', 'acknowledged', 'snoozed', 'resolved' ] ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/alerts/(?P<id>\d+)',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'status' => [ 'type' => 'string', 'required' => true, 'enum' => [ 'acknowledged', 'snoozed', 'resolved' ] ],
				],
			]
		);
	}

	public function list( \WP_REST_Request $request ): \WP_REST_Response {
		$status = (string) $request->get_param( 'status' );

		return rest_ensure_response(
			[
				'items'  => $this->alerts->list( $status ),
				'counts' => $this->alerts->active_counts(),
			]
		);
	}

	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$updated = $this->alerts->set_status(
			(int) $request->get_param( 'id' ),
			(string) $request->get_param( 'status' )
		);

		if ( ! $updated ) {
			return new \WP_Error( 'sda_alert', __( 'Unknown alert.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response(
			[
				'items'  => $this->alerts->list( 'active' ),
				'counts' => $this->alerts->active_counts(),
			]
		);
	}
}
