<?php
/**
 * /sda/v1/opportunities — list, rescan, status changes.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Jobs\Handlers\RunAnalysisJob;
use SEODirector\Jobs\Scheduler;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class OpportunitiesController extends BaseController {

	public function __construct(
		private readonly OpportunitiesRepository $opportunities,
		private readonly Scheduler $scheduler,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/opportunities',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'limit' => array(
							'type'              => 'integer',
							'default'           => 50,
							'minimum'           => 1,
							'maximum'           => 200,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rescan' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->ns(),
			'/opportunities/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'status' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'open', 'in_roadmap', 'done', 'dismissed' ),
					),
				),
			)
		);
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array( 'opportunities' => $this->opportunities->list_open( (int) $request->get_param( 'limit' ) ) )
		);
	}

	public function rescan(): WP_REST_Response|WP_Error {
		if ( ! $this->rate_limit( 'rescan', 6 ) ) {
			return new WP_Error(
				'sda_rate_limited',
				__( 'Too many rescans — try again later.', 'seo-director-ai' ),
				array( 'status' => 429 )
			);
		}
		$this->scheduler->enqueue( RunAnalysisJob::NAME );
		return new WP_REST_Response( array( 'queued' => true ), 202 );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ok = $this->opportunities->set_status( (int) $request['id'], (string) $request->get_param( 'status' ) );
		if ( ! $ok ) {
			return new WP_Error( 'sda_not_found', __( 'Opportunity not found.', 'seo-director-ai' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'updated' => true ) );
	}
}
