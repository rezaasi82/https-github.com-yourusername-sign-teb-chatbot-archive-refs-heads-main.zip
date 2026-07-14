<?php
/**
 * Google Search Console API client (Search Analytics + Sites).
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

use SEODirector\Integrations\Http\RetryingHttpClient;

defined( 'ABSPATH' ) || exit;

final class SearchConsoleClient {

	private const BASE = 'https://www.googleapis.com/webmasters/v3';

	public function __construct(
		private OAuthClient $oauth,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
	) {}

	/**
	 * Sites the connected account can read.
	 *
	 * @return array<int, array{external_id: string, display_name: string}>|\WP_Error
	 */
	public function list_sites(): array|\WP_Error {
		$data = $this->call( 'GET', '/sites' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$sites = [];
		foreach ( (array) ( $data['siteEntry'] ?? [] ) as $entry ) {
			if ( in_array( $entry['permissionLevel'] ?? '', [ 'siteOwner', 'siteFullUser', 'siteRestrictedUser' ], true ) ) {
				$sites[] = [
					'external_id'  => (string) $entry['siteUrl'],
					'display_name' => (string) $entry['siteUrl'],
				];
			}
		}

		return $sites;
	}

	/**
	 * Search Analytics query.
	 *
	 * @param string   $site_url   GSC property URI (sc-domain:… or URL-prefix).
	 * @param string   $start      Y-m-d.
	 * @param string   $end        Y-m-d.
	 * @param string[] $dimensions e.g. ['date'] or ['query'] or ['page','query'].
	 * @param int      $row_limit  1–25000.
	 * @param int      $start_row  Pagination offset.
	 * @return array<int, array{keys: string[], clicks: float, impressions: float, ctr: float, position: float}>|\WP_Error
	 */
	public function query( string $site_url, string $start, string $end, array $dimensions, int $row_limit = 5000, int $start_row = 0 ): array|\WP_Error {
		$data = $this->call(
			'POST',
			'/sites/' . rawurlencode( $site_url ) . '/searchAnalytics/query',
			[
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => $dimensions,
				'rowLimit'   => min( 25000, max( 1, $row_limit ) ),
				'startRow'   => max( 0, $start_row ),
				'dataState'  => 'final',
			]
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array_map(
			static fn( array $row ) => [
				'keys'        => array_map( 'strval', (array) ( $row['keys'] ?? [] ) ),
				'clicks'      => (float) ( $row['clicks'] ?? 0 ),
				'impressions' => (float) ( $row['impressions'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			],
			(array) ( $data['rows'] ?? [] )
		);
	}

	/**
	 * @param array<string, mixed>|null $body JSON body for POST.
	 * @return array<mixed>|\WP_Error
	 */
	private function call( string $method, string $path, ?array $body = null ): array|\WP_Error {
		if ( ! $this->quota->allow( 'gsc' ) ) {
			return new \WP_Error( 'sda_quota', 'GSC API budget exhausted or circuit breaker open.' );
		}

		$token = $this->oauth->access_token();
		if ( null === $token ) {
			return new \WP_Error( 'sda_auth', 'Google connection is not available (expired or revoked).' );
		}

		$args = [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ];
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$result = $this->http->request( $method, self::BASE . $path, $args );
		$this->quota->record( 'gsc' );

		if ( 429 === $result->status || $result->status >= 500 ) {
			$this->quota->trip( 'gsc' );
		}

		if ( ! $result->ok() ) {
			$json = $result->json();
			return new \WP_Error(
				'sda_gsc_api',
				(string) ( $json['error']['message'] ?? $result->error ?? ( 'HTTP ' . $result->status ) ),
				[ 'status' => $result->status ]
			);
		}

		return $result->json() ?? [];
	}
}
