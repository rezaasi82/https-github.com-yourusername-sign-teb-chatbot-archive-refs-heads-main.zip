<?php
/**
 * Google Ads client (paid/organic overlap). Uses the shared Google OAuth
 * connection (adwords scope) plus an admin-supplied developer token and
 * customer id — Google Ads refuses API calls without a developer token, so
 * the integration stays dormant until both settings are filled in.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class GoogleAdsClient {

	private const BASE = 'https://googleads.googleapis.com/v18';

	public function __construct(
		private OAuthClient $oauth,
		private Settings $settings,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
	) {}

	/** Both the developer token and customer id are configured. */
	public function is_configured(): bool {
		return '' !== (string) $this->settings->get( 'gads_developer_token', '' )
			&& '' !== $this->customer_id();
	}

	/**
	 * Campaign performance over the last N days.
	 *
	 * @return array<int, array{campaign: string, clicks: int, impressions: int, cost_micros: int, conversions: float}>|\WP_Error
	 */
	public function campaign_performance( int $days = 28 ): array|\WP_Error {
		$rows = $this->search(
			'SELECT campaign.name, metrics.clicks, metrics.impressions, metrics.cost_micros, metrics.conversions '
			. 'FROM campaign WHERE segments.date DURING LAST_' . ( $days > 7 ? '30' : '7' ) . '_DAYS '
			. 'AND campaign.status = ENABLED'
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		return array_map(
			static fn( array $row ) => [
				'campaign'    => (string) ( $row['campaign']['name'] ?? '' ),
				'clicks'      => (int) ( $row['metrics']['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['metrics']['impressions'] ?? 0 ),
				'cost_micros' => (int) ( $row['metrics']['costMicros'] ?? 0 ),
				'conversions' => (float) ( $row['metrics']['conversions'] ?? 0 ),
			],
			$rows
		);
	}

	/**
	 * Search terms that triggered paid ads — useful to cross-reference with
	 * organic queries for cannibalization between paid and organic.
	 *
	 * @return array<int, array{term: string, clicks: int, impressions: int}>|\WP_Error
	 */
	public function paid_search_terms( int $limit = 100 ): array|\WP_Error {
		$rows = $this->search(
			'SELECT search_term_view.search_term, metrics.clicks, metrics.impressions '
			. 'FROM search_term_view WHERE segments.date DURING LAST_30_DAYS '
			. 'ORDER BY metrics.impressions DESC LIMIT ' . max( 1, min( 1000, $limit ) )
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		return array_map(
			static fn( array $row ) => [
				'term'        => (string) ( $row['searchTermView']['searchTerm'] ?? '' ),
				'clicks'      => (int) ( $row['metrics']['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['metrics']['impressions'] ?? 0 ),
			],
			$rows
		);
	}

	/**
	 * Run a GAQL query via searchStream and flatten result batches.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function search( string $gaql ): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'sda_gads_config', 'Google Ads developer token and customer id are not configured.' );
		}

		if ( ! $this->quota->allow( 'gads' ) ) {
			return new \WP_Error( 'sda_quota', 'Google Ads API budget exhausted or circuit breaker open.' );
		}

		$token = $this->oauth->access_token();
		if ( null === $token ) {
			return new \WP_Error( 'sda_auth', 'Google connection is not available (expired or revoked).' );
		}

		$result = $this->http->post(
			self::BASE . '/customers/' . rawurlencode( $this->customer_id() ) . '/googleAds:searchStream',
			[
				'timeout' => 30,
				'headers' => [
					'Authorization'   => 'Bearer ' . $token,
					'developer-token' => (string) $this->settings->get( 'gads_developer_token', '' ),
					'Content-Type'    => 'application/json',
				],
				'body'    => wp_json_encode( [ 'query' => $gaql ] ),
			]
		);
		$this->quota->record( 'gads' );

		if ( 429 === $result->status || $result->status >= 500 ) {
			$this->quota->trip( 'gads' );
		}

		$data = $result->json();
		if ( ! $result->ok() || ! is_array( $data ) ) {
			return new \WP_Error( 'sda_gads_api', (string) ( $data[0]['error']['message'] ?? $data['error']['message'] ?? $result->error ?? ( 'HTTP ' . $result->status ) ) );
		}

		// searchStream returns an array of batches, each with a results list.
		$rows = [];
		foreach ( $data as $batch ) {
			foreach ( (array) ( $batch['results'] ?? [] ) as $row ) {
				$rows[] = (array) $row;
			}
		}

		return $rows;
	}

	private function customer_id(): string {
		// Dashes are a display convention (123-456-7890); the API wants digits.
		return str_replace( '-', '', (string) $this->settings->get( 'gads_customer_id', '' ) );
	}
}
