<?php
/**
 * GA4 Data API (runReport) + Admin API (property listing) client.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class Analytics4Client {

	private const DATA_API  = 'https://analyticsdata.googleapis.com/v1beta';
	private const ADMIN_API = 'https://analyticsadmin.googleapis.com/v1beta';

	public function __construct(
		private readonly RetryingHttpClient $http,
		private readonly OAuthClient $oauth,
		private readonly QuotaManager $quota,
	) {}

	/**
	 * List GA4 properties the connected account can read (via account summaries).
	 *
	 * @return array<int, array{property: string, displayName: string}>|WP_Error
	 */
	public function list_properties(): array|WP_Error {
		$response = $this->call( 'GET', self::ADMIN_API . '/accountSummaries?pageSize=200' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$properties = array();
		foreach ( (array) ( $response['accountSummaries'] ?? array() ) as $account ) {
			foreach ( (array) ( $account['propertySummaries'] ?? array() ) as $summary ) {
				$properties[] = array(
					'property'    => (string) ( $summary['property'] ?? '' ),   // "properties/123456".
					'displayName' => (string) ( $summary['displayName'] ?? '' ),
				);
			}
		}
		return $properties;
	}

	/**
	 * Daily channel × landing-page report for a date range.
	 *
	 * @param string $property GA4 property resource name ("properties/123456").
	 * @return array<int, array<string, mixed>>|WP_Error Normalized rows.
	 */
	public function daily_report( string $property, string $date_from, string $date_to ): array|WP_Error {
		$response = $this->call(
			'POST',
			self::DATA_API . '/' . rawurlencode( $property ) . ':runReport',
			array(
				'dateRanges' => array(
					array(
						'startDate' => $date_from,
						'endDate'   => $date_to,
					),
				),
				'dimensions' => array(
					array( 'name' => 'date' ),
					array( 'name' => 'sessionDefaultChannelGroup' ),
					array( 'name' => 'landingPagePlusQueryString' ),
				),
				'metrics'    => array(
					array( 'name' => 'sessions' ),
					array( 'name' => 'totalUsers' ),
					array( 'name' => 'engagedSessions' ),
					array( 'name' => 'engagementRate' ),
					array( 'name' => 'conversions' ),
					array( 'name' => 'eventCount' ),
				),
				'limit'      => '100000',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rows = array();
		foreach ( (array) ( $response['rows'] ?? array() ) as $row ) {
			$dims    = array_map( static fn( $d ) => (string) ( $d['value'] ?? '' ), (array) ( $row['dimensionValues'] ?? array() ) );
			$metrics = array_map( static fn( $m ) => (string) ( $m['value'] ?? '0' ), (array) ( $row['metricValues'] ?? array() ) );
			if ( count( $dims ) < 3 || count( $metrics ) < 6 ) {
				continue;
			}
			$rows[] = array(
				// GA4 returns dates as YYYYMMDD.
				'date'             => substr( $dims[0], 0, 4 ) . '-' . substr( $dims[0], 4, 2 ) . '-' . substr( $dims[0], 6, 2 ),
				'channel'          => $dims[1],
				'landing_path'     => $dims[2],
				'sessions'         => (int) $metrics[0],
				'total_users'      => (int) $metrics[1],
				'engaged_sessions' => (int) $metrics[2],
				'engagement_rate'  => (float) $metrics[3],
				'conversions'      => (float) $metrics[4],
				'event_count'      => (int) $metrics[5],
			);
		}
		return $rows;
	}

	/** @param array<string, mixed>|null $body @return array<string, mixed>|WP_Error */
	private function call( string $method, string $url, ?array $body = null ): array|WP_Error {
		if ( ! $this->quota->can_request( 'ga4' ) ) {
			return new WP_Error( 'sda_quota', __( 'GA4 daily API budget reached; sync resumes tomorrow.', 'seo-director-ai' ) );
		}

		$token = $this->oauth->access_token( 'ga4' );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ) );
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = $this->http->request_json( $method, $url, $args );
		$this->quota->record_request( 'ga4' );

		if ( is_wp_error( $response ) ) {
			$this->quota->trip_breaker( 'ga4' );
			return $response;
		}
		if ( 403 === $response['code'] || 429 === $response['code'] ) {
			$this->quota->trip_breaker( 'ga4' );
		}
		if ( $response['code'] >= 400 ) {
			$message = (string) ( $response['body']['error']['message'] ?? __( 'GA4 API error.', 'seo-director-ai' ) );
			return new WP_Error( 'sda_ga4_' . $response['code'], $message, array( 'status' => $response['code'] ) );
		}

		return $response['body'];
	}
}
