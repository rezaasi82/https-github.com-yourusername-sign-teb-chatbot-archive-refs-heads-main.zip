<?php
/**
 * /sda/v1/connections — service connect/disconnect, OAuth kickoff, API keys, sync trigger.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Integrations\Google\OAuthClient;
use SEODirector\Jobs\Handlers\SyncGscJob;
use SEODirector\Jobs\Scheduler;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ConnectionsController extends BaseController {

	private const OAUTH_SERVICES = array( 'gsc', 'ga4' );
	private const KEY_SERVICES   = array( 'psi', 'openai', 'claude', 'gemini' );

	public function __construct(
		private readonly ConnectionsRepository $connections,
		private readonly OAuthClient $oauth,
		private readonly Scheduler $scheduler,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/connections',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$this->ns(),
			'/connections/(?P<service>[a-z0-9_]+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'connect' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'api_key' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'disconnect' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->ns(),
			'/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'trigger_sync' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	public function list(): WP_REST_Response {
		return new WP_REST_Response( array( 'connections' => $this->connections->list_statuses() ) );
	}

	/**
	 * OAuth services → returns an authorize_url the SPA redirects to.
	 * Key services → stores the submitted API key encrypted.
	 */
	public function connect( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service = (string) $request['service'];

		if ( in_array( $service, self::OAUTH_SERVICES, true ) ) {
			$scopes = 'gsc' === $service
				? array( OAuthClient::SCOPE_GSC )
				: array( OAuthClient::SCOPE_GA4 );
			$url    = $this->oauth->build_authorization_url( $scopes, $service );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			return new WP_REST_Response( array( 'authorize_url' => $url ) );
		}

		if ( in_array( $service, self::KEY_SERVICES, true ) ) {
			$api_key = (string) $request->get_param( 'api_key' );
			if ( '' === $api_key ) {
				return new WP_Error( 'sda_missing_key', __( 'An API key is required for this service.', 'seo-director-ai' ), array( 'status' => 400 ) );
			}
			$this->connections->store_api_key( $service, $api_key );
			return new WP_REST_Response( array( 'connected' => true ) );
		}

		return new WP_Error( 'sda_unknown_service', __( 'Unknown service.', 'seo-director-ai' ), array( 'status' => 400 ) );
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$this->connections->disconnect( (string) $request['service'] );
		return new WP_REST_Response( array( 'disconnected' => true ) );
	}

	public function trigger_sync(): WP_REST_Response|WP_Error {
		if ( ! $this->rate_limit( 'sync', 4 ) ) {
			return new WP_Error(
				'sda_rate_limited',
				__( 'A sync was triggered recently — background jobs are already running.', 'seo-director-ai' ),
				array( 'status' => 429 )
			);
		}
		$this->scheduler->enqueue( SyncGscJob::NAME );
		return new WP_REST_Response( array( 'queued' => true ), 202 );
	}
}
