<?php
/**
 * wp_remote_* wrapper with bounded retries, exponential backoff + jitter.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Http;

defined( 'ABSPATH' ) || exit;

use WP_Error;

final class RetryingHttpClient {

	private const MAX_ATTEMPTS   = 3;
	private const BASE_DELAY_MS  = 500;
	private const RETRYABLE_CODES = array( 429, 500, 502, 503, 504 );

	/**
	 * Perform a JSON request; retries transient failures.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $url    Target URL.
	 * @param array<string, mixed> $args   wp_remote_request args ('headers', 'body', 'timeout').
	 * @return array{code: int, body: array<string, mixed>, raw: string}|WP_Error
	 */
	public function request_json( string $method, string $url, array $args = array() ): array|WP_Error {
		$args = array_merge(
			array(
				'timeout'   => 20,
				'sslverify' => true,
			),
			$args,
			array( 'method' => strtoupper( $method ) )
		);

		if ( isset( $args['body'] ) && is_array( $args['body'] ) && ( $args['json'] ?? true ) ) {
			$args['body']                            = wp_json_encode( $args['body'] );
			$args['headers']['Content-Type']         = 'application/json';
		}
		unset( $args['json'] );

		$last_error = new WP_Error( 'sda_http_failed', __( 'HTTP request failed.', 'seo-director-ai' ) );

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );
				$raw  = (string) wp_remote_retrieve_body( $response );

				if ( ! in_array( $code, self::RETRYABLE_CODES, true ) ) {
					$decoded = json_decode( $raw, true );
					return array(
						'code' => $code,
						'body' => is_array( $decoded ) ? $decoded : array(),
						'raw'  => $raw,
					);
				}
				$last_error = new WP_Error(
					'sda_http_' . $code,
					sprintf( /* translators: %d: HTTP status code */ __( 'Upstream returned HTTP %d.', 'seo-director-ai' ), $code ),
					array( 'status' => $code )
				);
			}

			if ( $attempt < self::MAX_ATTEMPTS ) {
				$delay_ms = self::BASE_DELAY_MS * ( 2 ** ( $attempt - 1 ) ) + wp_rand( 0, 250 );
				usleep( $delay_ms * 1000 );
			}
		}

		return $last_error;
	}

	/** @param array<string, mixed> $args */
	public function get_json( string $url, array $args = array() ): array|WP_Error {
		return $this->request_json( 'GET', $url, $args );
	}

	/** @param array<string, mixed> $args */
	public function post_json( string $url, array $args = array() ): array|WP_Error {
		return $this->request_json( 'POST', $url, $args );
	}
}
