<?php
/**
 * Connection management: Google OAuth start/callback, PSI + AI API keys,
 * property discovery/selection, disconnect, and sync status.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Integrations\Google\OAuthClient;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Jobs\Handlers\DailySyncCoordinator;
use SEODirector\Jobs\Handlers\SyncGa4Job;
use SEODirector\Jobs\Handlers\SyncGscJob;

defined( 'ABSPATH' ) || exit;

final class ConnectionsController extends AbstractController {

	private const KEY_SERVICES = [ 'psi', 'openai', 'claude', 'gemini', 'gapgpt' ];

	public function __construct(
		private ConnectionsRepository $connections,
		private PropertiesRepository $properties,
		private OAuthClient $oauth,
		private SearchConsoleClient $gsc,
		private Analytics4Client $ga4,
		private JobStateRepository $job_state,
		private DailySyncCoordinator $coordinator,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/connections',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_state' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connections/google/start',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'start_google' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'client_id'     => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'client_secret' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		// Public GET: Google redirects the browser here. State transient is the auth.
		register_rest_route(
			self::REST_NAMESPACE,
			'/connections/google/callback',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'google_callback' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connections/key',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_key' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'service' => [ 'type' => 'string', 'required' => true, 'enum' => self::KEY_SERVICES ],
					'api_key' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connections/property',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'select_property' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'service'     => [ 'type' => 'string', 'required' => true, 'enum' => [ 'gsc', 'ga4' ] ],
					'property_id' => [ 'type' => 'integer', 'required' => true ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connections/(?P<service>[a-z0-9_]+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'disconnect' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
			]
		);
	}

	public function get_state(): \WP_REST_Response {
		$google = $this->connections->get( OAuthClient::SERVICE );

		$keys = [];
		foreach ( self::KEY_SERVICES as $service ) {
			$connection       = $this->connections->get( $service );
			$keys[ $service ] = null !== $connection && 'connected' === $connection['status'];
		}

		return rest_ensure_response(
			[
				'google'       => [
					'status'       => $google['status'] ?? 'disconnected',
					'redirect_uri' => $this->oauth->redirect_uri(),
				],
				'keys'         => $keys,
				'properties'   => $this->properties->list(),
				'sync'         => [
					'gsc' => $this->job_state->get( SyncGscJob::NAME ),
					'ga4' => $this->job_state->get( SyncGa4Job::NAME ),
				],
			]
		);
	}

	public function start_google( \WP_REST_Request $request ): \WP_REST_Response {
		$url = $this->oauth->begin(
			(string) $request->get_param( 'client_id' ),
			(string) $request->get_param( 'client_secret' )
		);

		return rest_ensure_response( [ 'authorize_url' => $url ] );
	}

	/**
	 * OAuth redirect target: completes the exchange, discovers properties,
	 * then bounces the browser back to the plugin's admin page.
	 */
	public function google_callback( \WP_REST_Request $request ): void {
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$error = sanitize_text_field( (string) $request->get_param( 'error' ) );

		$admin_url = admin_url( 'admin.php?page=seo-director-ai#/settings' );

		if ( '' !== $error || '' === $code ) {
			wp_safe_redirect( add_query_arg( 'sda_oauth', 'denied', $admin_url ) );
			exit;
		}

		$result = $this->oauth->complete( $state, $code );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'sda_oauth', 'failed', $admin_url ) );
			exit;
		}

		$this->discover_properties();

		wp_safe_redirect( add_query_arg( 'sda_oauth', 'connected', $admin_url ) );
		exit;
	}

	public function save_key( \WP_REST_Request $request ): \WP_REST_Response {
		$service = (string) $request->get_param( 'service' );
		$this->connections->save( $service, [ 'api_key' => (string) $request->get_param( 'api_key' ) ] );

		return $this->get_state();
	}

	public function select_property( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$service     = (string) $request->get_param( 'service' );
		$property_id = (int) $request->get_param( 'property_id' );

		if ( ! $this->properties->activate( $service, $property_id ) ) {
			return new \WP_Error( 'sda_property', __( 'Unknown property.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		// Selecting a property is the "go" signal: start the historical backfill.
		$this->coordinator->start_backfill( $service );

		return $this->get_state();
	}

	public function disconnect( \WP_REST_Request $request ): \WP_REST_Response {
		$service = sanitize_key( (string) $request->get_param( 'service' ) );

		$this->connections->delete( $service );

		if ( OAuthClient::SERVICE === $service ) {
			$this->properties->delete_for_service( 'gsc' );
			$this->properties->delete_for_service( 'ga4' );
		}

		return $this->get_state();
	}

	private function discover_properties(): void {
		$connection = $this->connections->get( OAuthClient::SERVICE );
		if ( null === $connection ) {
			return;
		}

		$sites = $this->gsc->list_sites();
		if ( ! is_wp_error( $sites ) ) {
			$this->properties->sync_candidates( $connection['id'], 'gsc', $sites );
		}

		$ga4_properties = $this->ga4->list_properties();
		if ( ! is_wp_error( $ga4_properties ) ) {
			$this->properties->sync_candidates( $connection['id'], 'ga4', $ga4_properties );
		}
	}
}
