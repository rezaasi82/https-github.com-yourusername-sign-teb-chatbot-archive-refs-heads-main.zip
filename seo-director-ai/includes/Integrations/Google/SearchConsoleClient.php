<?php
/**
 * Google Search Console Search Analytics client.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class SearchConsoleClient {

	private const API_BASE = 'https://www.googleapis.com/webmasters/v3';

	public function __construct(
		private readonly RetryingHttpClient $http,
		private readonly OAuthClient $oauth,
		private readonly QuotaManager $quota,
	) {}

	/**
	 * List GSC properties the connected account can read.
	 *
	 * @return array<int, array{siteUrl: string, permissionLevel: string}>|WP_Error
	 */
	public function list_properties(): array|WP_Error {
		$response = $this->call( 'GET', '/sites' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array_values( (array) ( $response['siteEntry'] ?? array() ) );
	}

	/**
	 * Query the Search Analytics endpoint.
	 *
	 * @param string               $property   GSC property URI (sc-domain:example.com or URL-prefix).
	 * @param array<string, mixed> $query      searchanalytics/query request body.
	 * @return array<int, array<string, mixed>>|WP_Error Rows.
	 */
	public function search_analytics( string $property, array $query ): array|WP_Error {
		$path     = '/sites/' . rawurlencode( $property ) . '/searchAnalytics/query';
		$response = $this->call( 'POST', $path, $query );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array_values( (array) ( $response['rows'] ?? array() ) );
	}

	/**
	 * Convenience: exact daily totals for a date range (the anchor series — never sampled).
	 */
	public function daily_totals( string $property, string $date_from, string $date_to ): array|WP_Error {
		return $this->search_analytics(
			$property,
			array(
				'startDate'  => $date_from,
				'endDate'    => $date_to,
				'dimensions' => array( 'date' ),
				'rowLimit'   => 5000,
				'dataState'  => 'final',
			)
		);
	}

	/**
	 * Top-N rows for one day and one dimension (query|page|country|device).
	 */
	public function top_rows( string $property, string $date, string $dimension, int $limit ): array|WP_Error {
		return $this->search_analytics(
			$property,
			array(
				'startDate'  => $date,
				'endDate'    => $date,
				'dimensions' => array( $dimension ),
				'rowLimit'   => min( 25000, $limit ),
				'dataState'  => 'final',
			)
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private function call( string $method, string $path, ?array $body = null ): array|WP_Error {
		if ( ! $this->quota->can_request( 'gsc' ) ) {
			return new WP_Error( 'sda_quota', __( 'Search Console daily API budget reached; sync resumes tomorrow.', 'seo-director-ai' ) );
		}

		$token = $this->oauth->access_token( 'gsc' );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ) );
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = $this->http->request_json( $method, self::API_BASE . $path, $args );
		$this->quota->record_request( 'gsc' );

		if ( is_wp_error( $response ) ) {
			$this->quota->trip_breaker( 'gsc' );
			return $response;
		}
		if ( 403 === $response['code'] || 429 === $response['code'] ) {
			$this->quota->trip_breaker( 'gsc' );
		}
		if ( $response['code'] >= 400 ) {
			$message = (string) ( $response['body']['error']['message'] ?? __( 'Search Console API error.', 'seo-director-ai' ) );
			return new WP_Error( 'sda_gsc_' . $response['code'], $message, array( 'status' => $response['code'] ) );
		}

		return $response['body'];
	}
}
