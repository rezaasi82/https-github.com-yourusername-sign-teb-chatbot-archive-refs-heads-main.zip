<?php
/**
 * Google Analytics 4 Data API + Admin API client.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

use SEODirector\Integrations\Http\RetryingHttpClient;

defined( 'ABSPATH' ) || exit;

final class Analytics4Client {

	private const DATA_BASE  = 'https://analyticsdata.googleapis.com/v1beta';
	private const ADMIN_BASE = 'https://analyticsadmin.googleapis.com/v1beta';

	public function __construct(
		private OAuthClient $oauth,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
	) {}

	/**
	 * GA4 properties across all accessible accounts.
	 *
	 * @return array<int, array{external_id: string, display_name: string}>|\WP_Error
	 */
	public function list_properties(): array|\WP_Error {
		$summaries = $this->call( 'GET', self::ADMIN_BASE . '/accountSummaries?pageSize=200' );
		if ( is_wp_error( $summaries ) ) {
			return $summaries;
		}

		$properties = [];
		foreach ( (array) ( $summaries['accountSummaries'] ?? [] ) as $account ) {
			foreach ( (array) ( $account['propertySummaries'] ?? [] ) as $property ) {
				$properties[] = [
					'external_id'  => (string) $property['property'], // "properties/123456"
					'display_name' => (string) ( $property['displayName'] ?? $property['property'] ),
				];
			}
		}

		return $properties;
	}

	/**
	 * runReport: daily landing-page metrics by channel group.
	 *
	 * @return array<int, array{date: string, channel: string, landing: string, sessions: int, total_users: int, engaged_sessions: int, engagement_rate: float, conversions: float, event_count: int}>|\WP_Error
	 */
	public function daily_report( string $property, string $start, string $end, int $offset = 0, int $limit = 10000 ): array|\WP_Error {
		$data = $this->call(
			'POST',
			self::DATA_BASE . '/' . $property . ':runReport',
			[
				'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
				'dimensions' => [
					[ 'name' => 'date' ],
					[ 'name' => 'sessionDefaultChannelGroup' ],
					[ 'name' => 'landingPagePlusQueryString' ],
				],
				'metrics'    => [
					[ 'name' => 'sessions' ],
					[ 'name' => 'totalUsers' ],
					[ 'name' => 'engagedSessions' ],
					[ 'name' => 'engagementRate' ],
					[ 'name' => 'keyEvents' ],
					[ 'name' => 'eventCount' ],
				],
				'limit'      => $limit,
				'offset'     => $offset,
			]
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$rows = [];
		foreach ( (array) ( $data['rows'] ?? [] ) as $row ) {
			$dims    = array_map( static fn( $d ) => (string) ( $d['value'] ?? '' ), (array) ( $row['dimensionValues'] ?? [] ) );
			$metrics = array_map( static fn( $m ) => (float) ( $m['value'] ?? 0 ), (array) ( $row['metricValues'] ?? [] ) );

			$rows[] = [
				'date'             => substr( $dims[0] ?? '', 0, 4 ) . '-' . substr( $dims[0] ?? '', 4, 2 ) . '-' . substr( $dims[0] ?? '', 6, 2 ),
				'channel'          => $dims[1] ?? '',
				'landing'          => $dims[2] ?? '',
				'sessions'         => (int) ( $metrics[0] ?? 0 ),
				'total_users'      => (int) ( $metrics[1] ?? 0 ),
				'engaged_sessions' => (int) ( $metrics[2] ?? 0 ),
				'engagement_rate'  => (float) ( $metrics[3] ?? 0 ),
				'conversions'      => (float) ( $metrics[4] ?? 0 ),
				'event_count'      => (int) ( $metrics[5] ?? 0 ),
			];
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed>|null $body JSON body for POST.
	 * @return array<mixed>|\WP_Error
	 */
	private function call( string $method, string $url, ?array $body = null ): array|\WP_Error {
		if ( ! $this->quota->allow( 'ga4' ) ) {
			return new \WP_Error( 'sda_quota', 'GA4 API budget exhausted or circuit breaker open.' );
		}

		$token = $this->oauth->access_token();
		if ( null === $token ) {
			return new \WP_Error( 'sda_auth', 'Google connection is not available (expired or revoked).' );
		}

		$args = [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ];
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$result = $this->http->request( $method, $url, $args );
		$this->quota->record( 'ga4' );

		if ( 429 === $result->status || $result->status >= 500 ) {
			$this->quota->trip( 'ga4' );
		}

		if ( ! $result->ok() ) {
			$json = $result->json();
			return new \WP_Error(
				'sda_ga4_api',
				(string) ( $json['error']['message'] ?? $result->error ?? ( 'HTTP ' . $result->status ) ),
				[ 'status' => $result->status ]
			);
		}

		return $result->json() ?? [];
	}
}
