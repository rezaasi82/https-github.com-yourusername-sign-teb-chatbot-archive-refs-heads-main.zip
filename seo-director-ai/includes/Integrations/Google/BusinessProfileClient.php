<?php
/**
 * Google Business Profile client (local SEO). Uses the shared Google OAuth
 * connection (business.manage scope) to list the account's locations and pull
 * daily performance metrics from the Business Profile Performance API —
 * how often the listing appeared in Search/Maps and what users did.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

use SEODirector\Integrations\Http\RetryingHttpClient;

defined( 'ABSPATH' ) || exit;

final class BusinessProfileClient {

	private const ACCOUNTS_BASE    = 'https://mybusinessaccountmanagement.googleapis.com/v1';
	private const INFO_BASE        = 'https://mybusinessbusinessinformation.googleapis.com/v1';
	private const PERFORMANCE_BASE = 'https://businessprofileperformance.googleapis.com/v1';

	/** Daily metrics pulled for the dashboard. */
	private const METRICS = [
		'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH',
		'BUSINESS_IMPRESSIONS_MOBILE_SEARCH',
		'BUSINESS_IMPRESSIONS_DESKTOP_MAPS',
		'BUSINESS_IMPRESSIONS_MOBILE_MAPS',
		'WEBSITE_CLICKS',
		'CALL_CLICKS',
		'BUSINESS_DIRECTION_REQUESTS',
	];

	public function __construct(
		private OAuthClient $oauth,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
	) {}

	/**
	 * Accounts visible to the connected Google user.
	 *
	 * @return array<int, array{name: string, account_name: string}>|\WP_Error
	 */
	public function accounts(): array|\WP_Error {
		$data = $this->call( self::ACCOUNTS_BASE . '/accounts' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array_map(
			static fn( array $account ) => [
				'name'         => (string) ( $account['name'] ?? '' ),
				'account_name' => (string) ( $account['accountName'] ?? '' ),
			],
			(array) ( $data['accounts'] ?? [] )
		);
	}

	/**
	 * Locations (listings) under an account.
	 *
	 * @param string $account Account resource name, e.g. "accounts/123".
	 * @return array<int, array{name: string, title: string}>|\WP_Error
	 */
	public function locations( string $account ): array|\WP_Error {
		$data = $this->call(
			self::INFO_BASE . '/' . rawurlencode( $account ) . '/locations?readMask=name,title&pageSize=100'
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array_map(
			static fn( array $location ) => [
				'name'  => (string) ( $location['name'] ?? '' ),
				'title' => (string) ( $location['title'] ?? '' ),
			],
			(array) ( $data['locations'] ?? [] )
		);
	}

	/**
	 * Daily time series for the standard metric set over a date range.
	 *
	 * @param string $location Location resource name, e.g. "locations/456".
	 * @param string $start    Y-m-d.
	 * @param string $end      Y-m-d.
	 * @return array<string, array<int, array{date: string, value: int}>>|\WP_Error Metric => series.
	 */
	public function daily_metrics( string $location, string $start, string $end ): array|\WP_Error {
		[ $sy, $sm, $sd ] = array_map( 'intval', explode( '-', $start ) );
		[ $ey, $em, $ed ] = array_map( 'intval', explode( '-', $end ) );

		$query = [
			'dailyRange.start_date.year'  => $sy,
			'dailyRange.start_date.month' => $sm,
			'dailyRange.start_date.day'   => $sd,
			'dailyRange.end_date.year'    => $ey,
			'dailyRange.end_date.month'   => $em,
			'dailyRange.end_date.day'     => $ed,
		];
		foreach ( self::METRICS as $metric ) {
			$query['dailyMetrics'][] = $metric;
		}

		$url  = self::PERFORMANCE_BASE . '/' . rawurlencode( $location ) . ':fetchMultiDailyMetricsTimeSeries?' . http_build_query( $query );
		$data = $this->call( $url );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$series = [];
		foreach ( (array) ( $data['multiDailyMetricTimeSeries'] ?? [] ) as $group ) {
			foreach ( (array) ( $group['dailyMetricTimeSeries'] ?? [] ) as $metric_series ) {
				$metric = (string) ( $metric_series['dailyMetric'] ?? '' );
				$points = [];
				foreach ( (array) ( $metric_series['timeSeries']['datedValues'] ?? [] ) as $point ) {
					$date     = $point['date'] ?? [];
					$points[] = [
						'date'  => sprintf( '%04d-%02d-%02d', (int) ( $date['year'] ?? 0 ), (int) ( $date['month'] ?? 0 ), (int) ( $date['day'] ?? 0 ) ),
						'value' => (int) ( $point['value'] ?? 0 ),
					];
				}
				$series[ $metric ] = $points;
			}
		}

		return $series;
	}

	/**
	 * @return array<mixed>|\WP_Error
	 */
	private function call( string $url ): array|\WP_Error {
		if ( ! $this->quota->allow( 'gbp' ) ) {
			return new \WP_Error( 'sda_quota', 'Business Profile API budget exhausted or circuit breaker open.' );
		}

		$token = $this->oauth->access_token();
		if ( null === $token ) {
			return new \WP_Error( 'sda_auth', 'Google connection is not available (expired or revoked).' );
		}

		$result = $this->http->get( $url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ], 'timeout' => 30 ] );
		$this->quota->record( 'gbp' );

		if ( 429 === $result->status || $result->status >= 500 ) {
			$this->quota->trip( 'gbp' );
		}

		$data = $result->json();
		if ( ! $result->ok() || ! is_array( $data ) ) {
			return new \WP_Error( 'sda_gbp_api', (string) ( $data['error']['message'] ?? $result->error ?? ( 'HTTP ' . $result->status ) ) );
		}

		return $data;
	}
}
