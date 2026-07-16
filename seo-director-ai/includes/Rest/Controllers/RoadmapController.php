<?php
/**
 * GET /sda/v1/roadmap — list tasks by scope.
 * POST /sda/v1/roadmap/generate — regenerate from current opportunities.
 * PATCH /sda/v1/roadmap/tasks/{id} — status transitions.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\TaskRepository;
use SEODirector\Integrations\TaskSync\TaskSyncDispatcher;
use SEODirector\Roadmap\RoadmapGenerator;

defined( 'ABSPATH' ) || exit;

final class RoadmapController extends AbstractController {

	public function __construct(
		private TaskRepository $tasks,
		private RoadmapGenerator $generator,
		private TaskSyncDispatcher $task_sync,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/roadmap',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
				'args'                => [
					'scope' => [ 'type' => 'string', 'default' => 'monthly', 'enum' => [ 'weekly', 'monthly', 'quarterly' ] ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/roadmap/generate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'scope' => [ 'type' => 'string', 'default' => 'monthly', 'enum' => [ 'weekly', 'monthly', 'quarterly' ] ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/roadmap/sync',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'scope' => [ 'type' => 'string', 'default' => 'monthly', 'enum' => [ 'weekly', 'monthly', 'quarterly' ] ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/roadmap/tasks/(?P<id>\d+)',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_task' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'status' => [ 'type' => 'string', 'required' => true, 'enum' => [ 'todo', 'in_progress', 'done', 'dismissed' ] ],
				],
			]
		);
	}

	public function list( \WP_REST_Request $request ): \WP_REST_Response {
		$scope = (string) $request->get_param( 'scope' );

		return rest_ensure_response( [ 'items' => $this->tasks->list( $scope ), 'scope' => $scope ] );
	}

	public function generate( \WP_REST_Request $request ): \WP_REST_Response {
		$scope = (string) $request->get_param( 'scope' );
		$this->generator->generate( $scope );

		return rest_ensure_response( [ 'items' => $this->tasks->list( $scope ), 'scope' => $scope ] );
	}

	public function sync( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->task_sync->push_scope( (string) $request->get_param( 'scope' ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function update_task( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$updated = $this->tasks->set_status(
			(int) $request->get_param( 'id' ),
			(string) $request->get_param( 'status' )
		);

		if ( ! $updated ) {
			return new \WP_Error( 'sda_task', __( 'Unknown task.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$scope = (string) ( $request->get_param( 'scope' ) ?: 'monthly' );

		return rest_ensure_response( [ 'items' => $this->tasks->list( $scope ), 'scope' => $scope ] );
	}
}
