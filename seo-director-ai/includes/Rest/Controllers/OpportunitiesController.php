<?php
/**
 * GET /sda/v1/opportunities — list open opportunities.
 * POST /sda/v1/opportunities/rescan — queue a fresh analysis pass.
 * PATCH /sda/v1/opportunities/{id} — status transitions (dismiss, done…).
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Jobs\Handlers\RunAnalysisJob;
use SEODirector\Jobs\Scheduler;

defined( 'ABSPATH' ) || exit;

final class OpportunitiesController extends AbstractController {

	public function __construct( private OpportunitiesRepository $opportunities ) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/opportunities',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response( [ 'items' => $this->opportunities->list_open() ] ),
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/opportunities/rescan',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rescan' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/opportunities/(?P<id>\d+)',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'status' => [ 'type' => 'string', 'required' => true, 'enum' => [ 'open', 'in_roadmap', 'done', 'dismissed' ] ],
				],
			]
		);
	}

	public function rescan(): \WP_REST_Response {
		Scheduler::enqueue_next_chunk( RunAnalysisJob::NAME );

		return rest_ensure_response( [ 'queued' => true ] );
	}

	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$updated = $this->opportunities->set_status(
			(int) $request->get_param( 'id' ),
			(string) $request->get_param( 'status' )
		);

		if ( ! $updated ) {
			return new \WP_Error( 'sda_opportunity', __( 'Unknown opportunity.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( [ 'items' => $this->opportunities->list_open() ] );
	}
}
