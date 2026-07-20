<?php
/**
 * Research API:
 *  GET  /research/keywords    — keyword discovery for a seed (Starter+)
 *  POST /research/cluster     — AI topic cluster plan (PRO)
 *  GET  /research/competitors — competitor overview from top GSC queries (PRO)
 *  GET  /research/serp        — full SERP for one query (PRO)
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\License\FeatureGate;
use SEODirector\Research\ClusterBuilder;
use SEODirector\Research\CompetitorAnalyzer;
use SEODirector\Research\KeywordResearcher;
use SEODirector\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class ResearchController extends AbstractController {

	public function __construct(
		private KeywordResearcher $researcher,
		private ClusterBuilder $clusters,
		private CompetitorAnalyzer $competitors,
		private FeatureGate $gate,
		private RateLimiter $limiter,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/research/keywords',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'keywords' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'seed' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'lang' => [ 'type' => 'string', 'default' => 'fa', 'enum' => [ 'fa', 'en' ] ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/research/cluster',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cluster' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'seed' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'lang' => [ 'type' => 'string', 'default' => 'fa', 'enum' => [ 'fa', 'en' ] ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/research/competitors',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'competitors_overview' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/research/serp',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'serp' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'query' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);
	}

	public function keywords( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard( 'keyword_research', 'research_keywords', 30 );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return rest_ensure_response(
			$this->researcher->research( (string) $request->get_param( 'seed' ), (string) $request->get_param( 'lang' ) )
		);
	}

	public function cluster( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard( 'topic_clusters', 'research_cluster', 10 );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		if ( ! $this->clusters->is_available() ) {
			return new \WP_Error( 'sda_ai_off', __( 'AI features are not available.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$result = $this->clusters->build( (string) $request->get_param( 'seed' ), (string) $request->get_param( 'lang' ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function competitors_overview(): \WP_REST_Response|\WP_Error {
		$guard = $this->guard( 'competitor_intel', 'research_competitors', 10 );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->competitors->overview();

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function serp( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard( 'competitor_intel', 'research_serp', 30 );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->competitors->query( (string) $request->get_param( 'query' ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	private function guard( string $feature, string $action, int $per_hour ): bool|\WP_Error {
		if ( ! $this->gate->allows( $feature ) ) {
			return new \WP_Error( 'sda_locked', __( 'Your plan does not include this feature.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}
		if ( ! $this->limiter->allow( $action, $per_hour, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sda_rate', __( 'Too many requests. Please try again later.', 'seo-director-ai' ), [ 'status' => 429 ] );
		}

		return true;
	}
}
