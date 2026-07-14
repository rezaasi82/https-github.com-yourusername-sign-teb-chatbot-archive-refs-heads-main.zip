<?php
/**
 * Google OAuth 2.0 authorization-code flow with PKCE.
 *
 * The admin supplies their own OAuth client (Client ID/Secret) during setup;
 * a vendor-verified shared client can replace it later without touching this
 * flow. Tokens live encrypted in the connections table; refresh happens
 * transparently in access_token().
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Integrations\Http\RetryingHttpClient;

defined( 'ABSPATH' ) || exit;

final class OAuthClient {

	public const SERVICE = 'google';

	public const SCOPES = [
		'https://www.googleapis.com/auth/webmasters.readonly',
		'https://www.googleapis.com/auth/analytics.readonly',
	];

	private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	public function __construct(
		private ConnectionsRepository $connections,
		private RetryingHttpClient $http,
	) {}

	/**
	 * The redirect URI Google must be configured with.
	 */
	public function redirect_uri(): string {
		return rest_url( 'sda/v1/connections/google/callback' );
	}

	/**
	 * Build the consent URL and stash state + PKCE verifier + client secret
	 * in a short-lived transient keyed by state.
	 */
	public function begin( string $client_id, string $client_secret ): string {
		$state    = wp_generate_password( 32, false );
		$verifier = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		set_transient(
			'sda_oauth_' . $state,
			[
				'verifier'      => $verifier,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'user_id'       => get_current_user_id(),
			],
			10 * MINUTE_IN_SECONDS
		);

		return add_query_arg(
			[
				'client_id'             => rawurlencode( $client_id ),
				'redirect_uri'          => rawurlencode( $this->redirect_uri() ),
				'response_type'         => 'code',
				'scope'                 => rawurlencode( implode( ' ', self::SCOPES ) ),
				'access_type'           => 'offline',
				'prompt'                => 'consent',
				'state'                 => $state,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
			],
			self::AUTH_URL
		);
	}

	/**
	 * Handle the callback: validate state, exchange the code, persist tokens.
	 *
	 * @return true|\WP_Error
	 */
	public function complete( string $state, string $code ): bool|\WP_Error {
		$stash = get_transient( 'sda_oauth_' . $state );
		delete_transient( 'sda_oauth_' . $state );

		if ( ! is_array( $stash ) ) {
			return new \WP_Error( 'sda_oauth_state', __( 'Authorization session expired or invalid. Please try connecting again.', 'seo-director-ai' ) );
		}

		$result = $this->http->post(
			self::TOKEN_URL,
			[
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => http_build_query(
					[
						'client_id'     => $stash['client_id'],
						'client_secret' => $stash['client_secret'],
						'code'          => $code,
						'code_verifier' => $stash['verifier'],
						'grant_type'    => 'authorization_code',
						'redirect_uri'  => $this->redirect_uri(),
					]
				),
			]
		);

		$data = $result->json();

		if ( ! $result->ok() || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return new \WP_Error(
				'sda_oauth_exchange',
				sprintf(
					/* translators: %s: error detail from Google. */
					__( 'Google rejected the authorization: %s', 'seo-director-ai' ),
					esc_html( (string) ( $data['error_description'] ?? $data['error'] ?? $result->error ?? 'unknown' ) )
				)
			);
		}

		$this->connections->save(
			self::SERVICE,
			[
				'client_id'     => (string) $stash['client_id'],
				'client_secret' => (string) $stash['client_secret'],
				'access_token'  => (string) $data['access_token'],
				'refresh_token' => (string) ( $data['refresh_token'] ?? '' ),
				'expires_at'    => time() + (int) ( $data['expires_in'] ?? 3600 ) - 60,
			],
			'',
			implode( ' ', self::SCOPES )
		);

		return true;
	}

	/**
	 * A valid access token, refreshing if expired. Null when disconnected
	 * or the refresh is rejected (connection flagged for re-auth).
	 */
	public function access_token(): ?string {
		$connection = $this->connections->get( self::SERVICE );
		if ( null === $connection || 'connected' !== $connection['status'] ) {
			return null;
		}

		$creds = $connection['credentials'];

		if ( ! empty( $creds['access_token'] ) && time() < (int) ( $creds['expires_at'] ?? 0 ) ) {
			return (string) $creds['access_token'];
		}

		if ( empty( $creds['refresh_token'] ) ) {
			$this->connections->set_status( self::SERVICE, 'expired' );
			return null;
		}

		$result = $this->http->post(
			self::TOKEN_URL,
			[
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => http_build_query(
					[
						'client_id'     => (string) $creds['client_id'],
						'client_secret' => (string) $creds['client_secret'],
						'refresh_token' => (string) $creds['refresh_token'],
						'grant_type'    => 'refresh_token',
					]
				),
			]
		);

		$data = $result->json();

		if ( ! $result->ok() || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			// invalid_grant = token revoked by the user; anything else may be transient.
			if ( is_array( $data ) && 'invalid_grant' === ( $data['error'] ?? '' ) ) {
				$this->connections->set_status( self::SERVICE, 'revoked' );
			}
			return null;
		}

		$creds['access_token'] = (string) $data['access_token'];
		$creds['expires_at']   = time() + (int) ( $data['expires_in'] ?? 3600 ) - 60;
		if ( ! empty( $data['refresh_token'] ) ) {
			$creds['refresh_token'] = (string) $data['refresh_token'];
		}

		$this->connections->save( self::SERVICE, $creds, $connection['account_label'], $connection['scopes'] );

		return $creds['access_token'];
	}
}
