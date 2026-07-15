<?php
/**
 * Canonical URL form used to join GSC ↔ GA4 ↔ WP content.
 * Stored form: path + normalized query, no scheme/host, no fragment.
 *
 * @package SEODirector
 */

namespace SEODirector\Data;

defined( 'ABSPATH' ) || exit;

final class UrlCanonicalizer {

	/** Tracking params stripped before storage. */
	private const STRIP_PARAMS = array(
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
		'gclid', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid', 'ref', '_ga',
	);

	public function canonicalize( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			return '/';
		}

		$path = $parts['path'] ?? '/';
		$path = '/' . ltrim( $path, '/' );
		// Normalize trailing slash except for root.
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path ) . '/';
		}
		$path = strtolower( rawurldecode( $path ) );

		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $params );
			foreach ( self::STRIP_PARAMS as $strip ) {
				unset( $params[ $strip ] );
			}
			if ( ! empty( $params ) ) {
				ksort( $params );
				$query = '?' . http_build_query( $params );
			}
		}

		return substr( $path . $query, 0, 750 );
	}

	/**
	 * Rebuild an absolute URL from a stored canonical path.
	 */
	public function to_absolute( string $canonical_path ): string {
		return home_url( $canonical_path );
	}
}
