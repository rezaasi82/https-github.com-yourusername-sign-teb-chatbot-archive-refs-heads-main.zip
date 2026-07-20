<?php
/**
 * Thin SerpApi client for the research module (PRO). Unlike the Enterprise
 * SerpApiProvider (root-cause enrichment), this one only needs the API key
 * to be configured — the caller handles feature gating. Returns the raw
 * pieces research needs: organic results, People-Also-Ask questions, and
 * related searches. Results are cached for 24h per query; SERP data for a
 * keyword doesn't change meaningfully faster, and SerpApi calls cost money.
 *
 * @package SEODirector
 */

namespace SEODirector\Research;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class SerpClient {

	private const ENDPOINT  = 'https://serpapi.com/search.json';
	private const CACHE_TTL = DAY_IN_SECONDS;

	public function __construct(
		private Settings $settings,
		private RetryingHttpClient $http,
	) {}

	public function is_configured(): bool {
		return '' !== (string) $this->settings->get( 'serp_api_key', '' );
	}

	/**
	 * @return array{organic: array<int, array{position: int, title: string, link: string, domain: string}>, questions: string[], related: string[]}|null Null when unconfigured or the request failed.
	 */
	public function search( string $query, string $gl = '', string $hl = '' ): ?array {
		if ( ! $this->is_configured() || '' === trim( $query ) ) {
			return null;
		}

		$cache_key = 'sda_serp_' . md5( $query . '|' . $gl . '|' . $hl );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$args = [
			'engine'  => 'google',
			// add_query_arg does not encode values; Persian queries need it.
			'q'       => rawurlencode( $query ),
			'api_key' => (string) $this->settings->get( 'serp_api_key', '' ),
			'num'     => 10,
		];
		if ( '' !== $gl ) {
			$args['gl'] = $gl;
		}
		if ( '' !== $hl ) {
			$args['hl'] = $hl;
		}

		$result = $this->http->get( add_query_arg( $args, self::ENDPOINT ), [ 'timeout' => 20 ] );
		$json   = $result->ok() ? $result->json() : null;
		if ( null === $json ) {
			return null;
		}

		$organic = [];
		foreach ( (array) ( $json['organic_results'] ?? [] ) as $row ) {
			$link = (string) ( $row['link'] ?? '' );
			$host = '' !== $link ? wp_parse_url( $link, PHP_URL_HOST ) : '';
			$organic[] = [
				'position' => (int) ( $row['position'] ?? 0 ),
				'title'    => (string) ( $row['title'] ?? '' ),
				'link'     => $link,
				'domain'   => is_string( $host ) ? preg_replace( '/^www\./', '', $host ) : '',
			];
		}

		$questions = array_values(
			array_filter(
				array_map(
					static fn( $q ) => (string) ( $q['question'] ?? '' ),
					(array) ( $json['related_questions'] ?? [] )
				)
			)
		);

		$related = array_values(
			array_filter(
				array_map(
					static fn( $r ) => (string) ( $r['query'] ?? '' ),
					(array) ( $json['related_searches'] ?? [] )
				)
			)
		);

		$payload = [ 'organic' => $organic, 'questions' => $questions, 'related' => $related ];
		set_transient( $cache_key, $payload, self::CACHE_TTL );

		return $payload;
	}
}
