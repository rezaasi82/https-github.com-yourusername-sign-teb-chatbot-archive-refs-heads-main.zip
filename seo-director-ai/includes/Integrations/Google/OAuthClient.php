<?php
/**
 * Google OAuth 2.0 client: PKCE, state nonce, refresh, incremental scopes.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Options;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class OAuthClient {

	private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	public const SCOPE_GSC = 'https://www.googleapis.com/auth/webmasters.readonly';
	public const SCOPE_GA4 = 'https://www.googleapis.com/auth/analytics.readonly';

	public function __construct(
		private readonly RetryingHttpClient $http,
		private readonly TokenVault $vault,
		private readonly ConnectionsRepository $connections,
		private readonly Options $options,
	) {}

	/**
	 * Build the consent URL and stash the PKCE verifier + state server-side.
	 *
	 * @param string[] $scopes Requested scopes (incremental).
	 */
	public function build_authorization_url( array $scopes, string $service ): string|WP_Error {
		$client_id = (string) $this->options->get( 'google_client_id', '' );
		if ( '' === $client_id ) {
			return new WP_Error( 'sda_oauth_config', __( 'Google OAuth client is not configured yet.', 'seo-director-ai' ) );
		}

		$verifier  = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$state     = wp_generate_password( 32, false );

		set_transient(
			'sda_oauth_state_' . $state,
			array(
				'verifier' => $verifier,
				'service'  => $service,
				'user_id'  => get_current_user_id(),
			),
			10 * MINUTE_IN_SECONDS
		);

		return add_query_arg(
			array(
				'client_id'             => rawurlencode( $client_id ),
				'redirect_uri'          => rawurlencode( $this->redirect_uri() ),
				'response_type'         => 'code',
				'scope'                 => rawurlencode( implode( ' ', $scopes ) ),
				'access_type'           => 'offline',
				'prompt'                => 'consent',
				'include_granted_scopes' => 'true',
				'state'                 => $state,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
			),
			self::AUTH_URL
		);
	}

	/**
	 * Exchange the authorization code; persist encrypted tokens on the connection row.
	 */
	public function handle_callback( string $code, string $state ): true|WP_Error {
		$stash = get_transient( 'sda_oauth_state_' . $state );
		delete_transient( 'sda_oauth_state_' . $state );

		if ( ! is_array( $stash ) || get_current_user_id() !== (int) $stash['user_id'] ) {
			return new WP_Error( 'sda_oauth_state', __( 'OAuth state check failed — please retry the connection.', 'seo-director-ai' ) );
		}

		$response = $this->http->post_json(
			self::TOKEN_URL,
			array(
				'json' => false,
				'body' => array(
					'client_id'     => (string) $this->options->get( 'google_client_id', '' ),
					'code'          => $code,
					'code_verifier' => $stash['verifier'],
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $this->redirect_uri(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== $response['code'] || empty( $response['body']['access_token'] ) ) {
			return new WP_Error( 'sda_oauth_exchange', __( 'Google rejected the token exchange.', 'seo-director-ai' ) );
		}

		$tokens = array(
			'access_token'  => (string) $response['body']['access_token'],
			'refresh_token' => (string) ( $response['body']['refresh_token'] ?? '' ),
			'expires_at'    => time() + (int) ( $response['body']['expires_in'] ?? 3600 ),
		);

		$this->connections->upsert(
			(string) $stash['service'],
			$this->vault->seal( $tokens ),
			(string) ( $response['body']['scope'] ?? '' )
		);

		return true;
	}

	/**
	 * Return a valid access token for a service, refreshing when < 2 min remain.
	 */
	public function access_token( string $service ): string|WP_Error {
		$connection = $this->connections->get_by_service( $service );
		if ( ! $connection ) {
			return new WP_Error( 'sda_not_connected', __( 'Service is not connected.', 'seo-director-ai' ) );
		}

		$tokens = $this->vault->open( (string) $connection->credentials );
		if ( ! $tokens ) {
			$this->connections->mark_status( $service, 'error' );
			return new WP_Error( 'sda_vault', __( 'Stored credentials are unreadable — reconnect the service.', 'seo-director-ai' ) );
		}

		if ( (int) $tokens['expires_at'] - time() > 120 ) {
			return (string) $tokens['access_token'];
		}

		if ( empty( $tokens['refresh_token'] ) ) {
			$this->connections->mark_status( $service, 'expired' );
			return new WP_Error( 'sda_token_expired', __( 'Access expired and no refresh token exists — reconnect the service.', 'seo-director-ai' ) );
		}

		$response = $this->http->post_json(
			self::TOKEN_URL,
			array(
				'json' => false,
				'body' => array(
					'client_id'     => (string) $this->options->get( 'google_client_id', '' ),
					'refresh_token' => (string) $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== $response['code'] || empty( $response['body']['access_token'] ) ) {
			$this->connections->mark_status( $service, 'expired' );
			return new WP_Error( 'sda_refresh_failed', __( 'Token refresh failed — Google may have revoked access.', 'seo-director-ai' ) );
		}

		$tokens['access_token'] = (string) $response['body']['access_token'];
		$tokens['expires_at']   = time() + (int) ( $response['body']['expires_in'] ?? 3600 );
		$this->connections->update_credentials( $service, $this->vault->seal( $tokens ) );

		return $tokens['access_token'];
	}

	public function redirect_uri(): string {
		return admin_url( 'admin.php?page=seo-director-ai&sda_oauth=callback' );
	}
}
