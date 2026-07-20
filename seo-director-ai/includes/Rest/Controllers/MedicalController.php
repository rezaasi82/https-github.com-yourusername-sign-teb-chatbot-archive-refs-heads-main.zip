<?php
/**
 * Medical Pack API (Pro + medical_mode):
 *  GET  /medical/eeat?post_id=  — E-E-A-T trust score for a post
 *  GET  /medical/entities?post_id= — detected medical entities for a post
 *  POST /medical/schema         — build + save MedicalWebPage schema
 *  DELETE /medical/schema       — remove it
 *  GET  /medical/knowledge-graph — site-wide medical coverage + gaps
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\License\FeatureGate;
use SEODirector\Medical\EeatAnalyzer;
use SEODirector\Medical\KnowledgeGraph;
use SEODirector\Medical\MedicalEntityEngine;
use SEODirector\Medical\MedicalSchemaBuilder;

defined( 'ABSPATH' ) || exit;

final class MedicalController extends AbstractController {

	public function __construct(
		private EeatAnalyzer $eeat,
		private MedicalEntityEngine $entities,
		private MedicalSchemaBuilder $schema,
		private KnowledgeGraph $graph,
		private FeatureGate $gate,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/medical/eeat',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'eeat' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [ 'post_id' => [ 'type' => 'integer', 'required' => true ] ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/medical/entities',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'entities' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [ 'post_id' => [ 'type' => 'integer', 'required' => true ] ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/medical/schema',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'schema_save' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
					'args'                => [ 'post_id' => [ 'type' => 'integer', 'required' => true ] ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'schema_remove' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
					'args'                => [ 'post_id' => [ 'type' => 'integer', 'required' => true ] ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/medical/knowledge-graph',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'knowledge_graph' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [ 'force' => [ 'type' => 'boolean', 'default' => false ] ],
			]
		);
	}

	public function eeat( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->eeat->analyze( (int) $request->get_param( 'post_id' ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function entities( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return rest_ensure_response( [ 'entities' => $this->entities->detect_in_post( (int) $request->get_param( 'post_id' ) ) ] );
	}

	public function schema_save( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->schema->save( (int) $request->get_param( 'post_id' ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( array_merge( $result, [ 'saved' => true ] ) );
	}

	public function schema_remove( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$this->schema->remove( (int) $request->get_param( 'post_id' ) );

		return rest_ensure_response( [ 'removed' => true ] );
	}

	public function knowledge_graph( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return rest_ensure_response( $this->graph->build( (bool) $request->get_param( 'force' ) ) );
	}

	private function guard(): bool|\WP_Error {
		if ( ! $this->gate->allows( 'medical_pack' ) ) {
			return new \WP_Error( 'sda_locked', __( 'The Medical Pack requires a Pro license.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}

		return true;
	}
}
