<?php
/**
 * Google Autocomplete client — the free keyword-discovery source. Uses the
 * public suggest endpoint the search box itself calls (no key needed).
 * Cached for 24h per (seed, language) since suggestions barely move.
 *
 * @package SEODirector
 */

namespace SEODirector\Research;

use SEODirector\Integrations\Http\RetryingHttpClient;

defined( 'ABSPATH' ) || exit;

final class AutocompleteClient {

	private const ENDPOINT  = 'https://suggestqueries.google.com/complete/search';
	private const CACHE_TTL = DAY_IN_SECONDS;

	public function __construct( private RetryingHttpClient $http ) {}

	/**
	 * Suggestions for one seed phrase.
	 *
	 * @param string $hl Interface language, e.g. 'fa' or 'en'.
	 * @return string[]
	 */
	public function suggest( string $seed, string $hl = 'fa' ): array {
		$seed = trim( $seed );
		if ( '' === $seed ) {
			return [];
		}

		$cache_key = 'sda_ac_' . md5( $seed . '|' . $hl );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = add_query_arg(
			[
				// The firefox client returns plain JSON: [seed, [suggestions]].
				'client' => 'firefox',
				'hl'     => $hl,
				'q'      => rawurlencode( $seed ),
			],
			self::ENDPOINT
		);

		$result = $this->http->get( $url, [ 'timeout' => 10 ] );
		$json   = $result->ok() ? json_decode( $result->body, true ) : null;

		$suggestions = [];
		if ( is_array( $json ) && isset( $json[1] ) && is_array( $json[1] ) ) {
			$suggestions = array_values( array_filter( array_map( 'strval', $json[1] ) ) );
		}

		// Cache misses briefly too, so a blocked host doesn't retry every view.
		set_transient( $cache_key, $suggestions, [] === $suggestions ? 15 * MINUTE_IN_SECONDS : self::CACHE_TTL );

		return $suggestions;
	}
}
