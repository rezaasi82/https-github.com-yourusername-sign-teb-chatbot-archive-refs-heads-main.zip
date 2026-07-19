<?php
/**
 * /sda/v1/roadmap — kanban board, generation, task status changes.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\RoadmapTaskRepository;
use SEODirector\Roadmap\RoadmapGenerator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RoadmapController extends BaseController {

	public function __construct(
		private readonly RoadmapTaskRepository $tasks,
		private readonly RoadmapGenerator $generator,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/roadmap',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'board' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'scope' => array(
							'type'    => 'string',
							'default' => 'monthly',
							'enum'    => array( 'weekly', 'monthly', 'quarterly' ),
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'generate' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'scope' => array(
							'type'    => 'string',
							'default' => 'monthly',
							'enum'    => array( 'weekly', 'monthly' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->ns(),
			'/roadmap/tasks/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_task' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'status' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'todo', 'in_progress', 'done', 'dismissed' ),
					),
				),
			)
		);
	}

	public function board( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'board' => $this->tasks->board( (string) $request->get_param( 'scope' ) ) ) );
	}

	public function generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->rate_limit( 'roadmap_gen', 6 ) ) {
			return new WP_Error( 'sda_rate_limited', __( 'Roadmap was generated recently — refresh to see it.', 'seo-director-ai' ), array( 'status' => 429 ) );
		}
		$scope  = (string) $request->get_param( 'scope' );
		$result = $this->generator->generate( $scope );
		return new WP_REST_Response(
			array(
				'created' => $result['created'],
				'skipped' => $result['skipped'],
				'board'   => $this->tasks->board( $scope ),
			)
		);
	}

	public function update_task( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ok = $this->tasks->set_status( (int) $request['id'], (string) $request->get_param( 'status' ) );
		if ( ! $ok ) {
			return new WP_Error( 'sda_not_found', __( 'Task not found.', 'seo-director-ai' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'updated' => true ) );
	}
}
