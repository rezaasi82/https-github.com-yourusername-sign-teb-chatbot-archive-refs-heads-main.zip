<?php
/**
 * Extended Google integrations: Trends interest-over-time, Business Profile
 * accounts/locations/metrics, and Google Ads campaign performance. Each
 * endpoint degrades to a clear error when its prerequisite (OAuth scope,
 * developer token) is missing rather than failing opaquely.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Integrations\Google\BusinessProfileClient;
use SEODirector\Integrations\Google\GoogleAdsClient;
use SEODirector\Integrations\Google\TrendsClient;

defined( 'ABSPATH' ) || exit;

final class IntegrationsController extends AbstractController {

	public function __construct(
		private TrendsClient $trends,
		private BusinessProfileClient $gbp,
		private GoogleAdsClient $gads,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/trends/interest',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'trends_interest' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
				'args'                => [
					'keyword' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'geo'     => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => static fn( $v ) => preg_replace( '/[^A-Z\-]/', '', strtoupper( (string) $v ) ) ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gbp/accounts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'gbp_accounts' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gbp/locations',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'gbp_locations' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'account' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gbp/metrics',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'gbp_metrics' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
				'args'                => [
					'location' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gads/campaigns',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'gads_campaigns' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
			]
		);
	}

	public function trends_interest( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->trends->interest_over_time(
			(string) $request['keyword'],
			(string) $request['geo']
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function gbp_accounts(): \WP_REST_Response|\WP_Error {
		$result = $this->gbp->accounts();

		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'accounts' => $result ] );
	}

	public function gbp_locations( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->gbp->locations( (string) $request['account'] );

		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'locations' => $result ] );
	}

	public function gbp_metrics( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->gbp->daily_metrics(
			(string) $request['location'],
			gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			gmdate( 'Y-m-d' )
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'metrics' => $result ] );
	}

	public function gads_campaigns(): \WP_REST_Response|\WP_Error {
		$result = $this->gads->campaign_performance();

		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'campaigns' => $result, 'configured' => $this->gads->is_configured() ] );
	}
}
