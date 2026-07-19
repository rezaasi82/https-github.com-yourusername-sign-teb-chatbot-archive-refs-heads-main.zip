<?php
/**
 * Google Trends client. Trends has no official API; this uses the public
 * endpoints the trends.google.com frontend calls, which need no key. Two-step
 * flow for interest-over-time: /explore issues short-lived widget tokens,
 * then /multiline returns the series for that token. Responses are JSON with
 * an anti-JSON-hijacking prefix ()]}' or similar) that must be stripped.
 *
 * Being unofficial, the endpoint can rate-limit (429) aggressively — results
 * are cached for 6 hours and failures for 15 minutes so the dashboard never
 * hammers it.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

use SEODirector\Integrations\Http\RetryingHttpClient;

defined( 'ABSPATH' ) || exit;

final class TrendsClient {

	private const EXPLORE_URL   = 'https://trends.google.com/trends/api/explore';
	private const MULTILINE_URL = 'https://trends.google.com/trends/api/widgetdata/multiline';

	public function __construct( private RetryingHttpClient $http ) {}

	/**
	 * Interest-over-time (0–100) for a keyword over the last 12 months.
	 *
	 * @param string $keyword Search term.
	 * @param string $geo     ISO country code ('' = worldwide, 'IR' = Iran).
	 * @return array{keyword: string, geo: string, points: array<int, array{date: string, value: int}>}|\WP_Error
	 */
	public function interest_over_time( string $keyword, string $geo = '' ): array|\WP_Error {
		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return new \WP_Error( 'sda_trends_input', 'Keyword is required.' );
		}

		$cache_key = 'sda_trends_' . md5( $keyword . '|' . $geo );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( 'error' === $cached ) {
			return new \WP_Error( 'sda_trends_cooldown', 'Google Trends is rate-limiting; try again in a few minutes.' );
		}

		$widget = $this->explore_widget( $keyword, $geo );
		if ( is_wp_error( $widget ) ) {
			set_transient( $cache_key, 'error', 15 * MINUTE_IN_SECONDS );
			return $widget;
		}

		$series = $this->multiline( $widget );
		if ( is_wp_error( $series ) ) {
			set_transient( $cache_key, 'error', 15 * MINUTE_IN_SECONDS );
			return $series;
		}

		$payload = [
			'keyword' => $keyword,
			'geo'     => $geo,
			'points'  => $series,
		];
		set_transient( $cache_key, $payload, 6 * HOUR_IN_SECONDS );

		return $payload;
	}

	/**
	 * Step 1: get the TIMESERIES widget (token + request blob) for a keyword.
	 *
	 * @return array{token: string, request: array<string, mixed>}|\WP_Error
	 */
	private function explore_widget( string $keyword, string $geo ): array|\WP_Error {
		$req = [
			'comparisonItem' => [ [ 'keyword' => $keyword, 'geo' => $geo, 'time' => 'today 12-m' ] ],
			'category'       => 0,
			'property'       => '',
		];

		$url  = self::EXPLORE_URL . '?' . http_build_query( [ 'hl' => 'en-US', 'tz' => '-210', 'req' => wp_json_encode( $req ) ] );
		$data = $this->fetch_json( $url );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		foreach ( (array) ( $data['widgets'] ?? [] ) as $widget ) {
			if ( 'TIMESERIES' === ( $widget['id'] ?? '' ) ) {
				return [
					'token'   => (string) ( $widget['token'] ?? '' ),
					'request' => (array) ( $widget['request'] ?? [] ),
				];
			}
		}

		return new \WP_Error( 'sda_trends_api', 'Trends explore response had no TIMESERIES widget.' );
	}

	/**
	 * Step 2: fetch the actual series with the widget token.
	 *
	 * @param array{token: string, request: array<string, mixed>} $widget Widget from explore.
	 * @return array<int, array{date: string, value: int}>|\WP_Error
	 */
	private function multiline( array $widget ): array|\WP_Error {
		$url  = self::MULTILINE_URL . '?' . http_build_query(
			[
				'hl'    => 'en-US',
				'tz'    => '-210',
				'req'   => wp_json_encode( $widget['request'] ),
				'token' => $widget['token'],
			]
		);
		$data = $this->fetch_json( $url );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$points = [];
		foreach ( (array) ( $data['default']['timelineData'] ?? [] ) as $point ) {
			$points[] = [
				'date'  => gmdate( 'Y-m-d', (int) ( $point['time'] ?? 0 ) ),
				'value' => (int) ( $point['value'][0] ?? 0 ),
			];
		}

		return $points;
	}

	/**
	 * GET a Trends endpoint and strip the anti-hijacking prefix before decoding.
	 *
	 * @return array<mixed>|\WP_Error
	 */
	private function fetch_json( string $url ): array|\WP_Error {
		$result = $this->http->get(
			$url,
			[
				'timeout' => 20,
				// Trends rejects requests without a browsery user agent.
				'headers' => [ 'User-Agent' => 'Mozilla/5.0 (compatible; SEODirector/' . SDA_VERSION . ')' ],
			]
		);

		if ( ! $result->ok() ) {
			return new \WP_Error( 'sda_trends_api', $result->error ?? ( 'HTTP ' . $result->status ) );
		}

		// Body starts with something like ")]}'\n" — cut everything before the first { .
		$body  = (string) $result->body;
		$brace = strpos( $body, '{' );
		$data  = false === $brace ? null : json_decode( substr( $body, $brace ), true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'sda_trends_api', 'Unexpected Trends response format.' );
		}

		return $data;
	}
}
