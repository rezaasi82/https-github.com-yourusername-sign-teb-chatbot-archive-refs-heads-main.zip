<?php
/**
 * POST /sda/v1/content/meta — AI meta title/description for a page.
 * POST /sda/v1/content/gap — content gap analysis.
 * Both PRO-gated and rate-limited.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Content\ContentStrategist;
use SEODirector\Core\Capabilities;
use SEODirector\License\FeatureGate;
use SEODirector\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class ContentController extends AbstractController {

	public function __construct(
		private ContentStrategist $strategist,
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
	}

	public function meta( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$guard = $this->guard( 'content_meta' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->strategist->meta_for_page( strtoupper( (string) $request->get_param( 'hash' ) ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function gap(): \WP_REST_Response|\WP_Error {
		$guard = $this->guard( 'content_gap' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = $this->strategist->gap_analysis();

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	private function guard( string $action ): bool|\WP_Error {
		if ( ! $this->gate->allows( 'content_strategist' ) ) {
			return new \WP_Error( 'sda_pro', __( 'The Content Strategist requires a Pro license.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}
		if ( ! $this->strategist->is_available() ) {
			return new \WP_Error( 'sda_ai_off', __( 'AI features are not available.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}
		if ( ! $this->limiter->allow( $action, 20, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sda_rate', __( 'Too many requests. Please try again later.', 'seo-director-ai' ), [ 'status' => 429 ] );
		}

		return true;
	}
}
