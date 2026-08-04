<?php
/**
 * Content tools API:
 *  POST /content/meta    — AI meta title/description (PRO)
 *  POST /content/gap     — content gap analysis (PRO)
 *  POST /content/brief   — AI SEO brief for a keyword (PRO)
 *  GET  /content/posts   — post picker for the tools below
 *  GET  /content/links   — internal-link suggestions (Starter+)
 *  GET  /content/audit   — on-page audit of published content (Starter+)
 *  GET/POST/DELETE /content/schema — JSON-LD preview / save / remove (Starter+)
 *  POST /content/score   — optimization score for post × keyword (PRO)
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Content\BriefGenerator;
use SEODirector\Content\ContentStrategist;
use SEODirector\Content\InternalLinkSuggester;
use SEODirector\Content\OnPageAuditor;
use SEODirector\Content\OptimizationScorer;
use SEODirector\Content\SchemaGenerator;
use SEODirector\Content\ZombiePageDetector;
use SEODirector\Core\Capabilities;
use SEODirector\License\FeatureGate;
use SEODirector\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class ContentController extends AbstractController {

	public function __construct(
		private ContentStrategist $strategist,
		private BriefGenerator $briefs,
		private InternalLinkSuggester $links,
		private SchemaGenerator $schema,
		private OnPageAuditor $auditor,
		private OptimizationScorer $scorer,
		private ZombiePageDetector $zombies,
		private FeatureGate $gate,
		private RateLimiter $limiter,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/content/meta',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'meta' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'hash' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/gap',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'gap' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/brief',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'brief' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'keyword' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/posts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'posts' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/links',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'link_suggestions' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'post_id' => [ 'type' => 'integer', 'default' => 0 ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/audit',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'audit' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/schema',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'schema_preview' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
					'args'                => [
						'post_id' => [ 'type' => 'integer', 'required' => true ],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'schema_save' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
					'args'                => [
						'post_id' => [ 'type' => 'integer', 'required' => true ],
						'types'   => [ 'type' => 'array', 'required' => true, 'items' => [ 'type' => 'string', 'enum' => SchemaGenerator::TYPES ] ],
					],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'schema_remove' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
					'args'                => [
						'post_id' => [ 'type' => 'integer', 'required' => true ],
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/zombies',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'zombies' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'days'  => [ 'type' => 'integer', 'default' => 90 ],
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content/score',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'score' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'post_id'  => [ 'type' => 'integer', 'required' => true ],
					'keyword'  => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'entities' => [ 'type' => 'boolean', 'default' => false ],
				],
			]
		);
	}

	public function meta( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_ai( 'content_meta' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->strategist->meta_for_page( strtoupper( (string) $request->get_param( 'hash' ) ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function gap(): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_ai( 'content_gap' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->strategist->gap_analysis();

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function brief( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_ai( 'content_brief' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->briefs->brief( (string) $request->get_param( 'keyword' ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function posts(): \WP_REST_Response {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		return rest_ensure_response(
			[
				'posts' => array_map(
					static fn( \WP_Post $p ) => [
						'id'    => $p->ID,
						'title' => (string) get_the_title( $p ),
						'url'   => (string) get_permalink( $p ),
					],
					$posts
				),
			]
		);
	}

	public function link_suggestions( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_feature( 'core_detectors' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 ) {
			$result = $this->links->for_post( $post_id );

			return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'mode' => 'post', 'items' => [ $result ] ] );
		}

		return rest_ensure_response( [ 'mode' => 'sitewide', 'items' => $this->links->sitewide() ] );
	}

	public function audit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_feature( 'core_detectors' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return rest_ensure_response( $this->auditor->audit( (bool) $request->get_param( 'force' ) ) );
	}

	public function schema_preview( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_feature( 'core_detectors' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->schema->build( (int) $request->get_param( 'post_id' ), SchemaGenerator::TYPES );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function schema_save( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_feature( 'core_detectors' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$types  = array_map( 'strval', (array) $request->get_param( 'types' ) );
		$result = $this->schema->save( (int) $request->get_param( 'post_id' ), $types );

		return is_wp_error( $result ) ? $result : rest_ensure_response( array_merge( $result, [ 'saved' => true ] ) );
	}

	public function schema_remove( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_feature( 'core_detectors' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$this->schema->remove( (int) $request->get_param( 'post_id' ) );

		return rest_ensure_response( [ 'removed' => true ] );
	}

	public function zombies( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_feature( 'core_detectors' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return rest_ensure_response(
			$this->zombies->detect( (int) $request->get_param( 'days' ), (bool) $request->get_param( 'force' ) )
		);
	}

	public function score( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard_feature( 'content_strategist' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		if ( ! $this->limiter->allow( 'content_score', 60, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sda_rate', __( 'Too many requests. Please try again later.', 'seo-director-ai' ), [ 'status' => 429 ] );
		}

		$result = $this->scorer->score(
			(int) $request->get_param( 'post_id' ),
			(string) $request->get_param( 'keyword' ),
			(bool) $request->get_param( 'entities' )
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** AI-backed PRO endpoints: license + AI availability + rate limit. */
	private function guard_ai( string $action ): bool|\WP_Error {
		$feature = $this->guard_feature( 'content_strategist' );
		if ( is_wp_error( $feature ) ) {
			return $feature;
		}
		if ( ! $this->strategist->is_available() ) {
			return new \WP_Error( 'sda_ai_off', __( 'AI features are not available.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}
		if ( ! $this->limiter->allow( $action, 20, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sda_rate', __( 'Too many requests. Please try again later.', 'seo-director-ai' ), [ 'status' => 429 ] );
		}

		return true;
	}

	private function guard_feature( string $feature ): bool|\WP_Error {
		if ( ! $this->gate->allows( $feature ) ) {
			return new \WP_Error( 'sda_locked', __( 'Your plan does not include this feature.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}

		return true;
	}
}
