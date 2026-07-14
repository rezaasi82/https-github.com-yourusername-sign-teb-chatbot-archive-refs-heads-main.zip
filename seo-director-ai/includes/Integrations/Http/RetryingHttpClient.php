<?php
/**
 * wp_remote_* wrapper with retries, exponential backoff + jitter, and a
 * uniform result shape. All outbound HTTP in the plugin goes through here
 * so timeout policy, UA, and error normalization live in one place.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Http;

defined( 'ABSPATH' ) || exit;

final class RetryingHttpClient {

	private const MAX_ATTEMPTS   = 4;
	private const BASE_DELAY_MS  = 500;
	private const RETRYABLE_CODES = [ 408, 425, 429, 500, 502, 503, 504 ];

	/**
	 * Perform a JSON request.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     Absolute URL.
	 * @param array<string, mixed> $args    Optional: headers (array), body (array|string), timeout (int), retries (int).
	 * @return HttpResult
	 */
	public function request( string $method, string $url, array $args = [] ): HttpResult {
		$attempts = max( 1, min( self::MAX_ATTEMPTS, (int) ( $args['retries'] ?? self::MAX_ATTEMPTS ) ) );

		$request = [
			'method'  => strtoupper( $method ),
			'timeout' => (int) ( $args['timeout'] ?? 20 ),
			'headers' => array_merge(
				[
					'Accept'     => 'application/json',
					'User-Agent' => 'SEODirectorAI/' . SDA_VERSION . '; ' . home_url( '/' ),
				],
				(array) ( $args['headers'] ?? [] )
			),
		];

		if ( isset( $args['body'] ) ) {
			if ( is_array( $args['body'] ) ) {
				$request['headers']['Content-Type'] = 'application/json; charset=utf-8';
				$request['body'] = wp_json_encode( $args['body'] );
			} else {
				$request['body'] = $args['body'];
			}
		}

		$last_error = null;

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			$response = wp_remote_request( $url, $request );

			if ( is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );
				$body = (string) wp_remote_retrieve_body( $response );

				if ( ! in_array( $code, self::RETRYABLE_CODES, true ) ) {
					return new HttpResult( $code, $body, null );
				}

				$last_error = sprintf( 'HTTP %d', $code );

				// Honor Retry-After when the API provides one (e.g. 429).
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				if ( is_numeric( $retry_after ) && $attempt < $attempts ) {
					$this->sleep_ms( min( 30000, (int) $retry_after * 1000 ) );
					continue;
				}
			}

			if ( $attempt < $attempts ) {
				$delay = self::BASE_DELAY_MS * ( 2 ** ( $attempt - 1 ) );
				$this->sleep_ms( $delay + wp_rand( 0, (int) ( $delay / 2 ) ) );
			}
		}

		return new HttpResult( 0, '', $last_error ?? 'Request failed.' );
	}

	public function get( string $url, array $args = [] ): HttpResult {
		return $this->request( 'GET', $url, $args );
	}

	public function post( string $url, array $args = [] ): HttpResult {
		return $this->request( 'POST', $url, $args );
	}

	private function sleep_ms( int $ms ): void {
		usleep( $ms * 1000 );
	}
}
