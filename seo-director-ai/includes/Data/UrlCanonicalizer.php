<?php
/**
 * Canonicalizes URLs from GSC (absolute), GA4 (path+query), and WordPress
 * into one comparable form: lowercase-host-stripped path, trailing slash
 * normalized, tracking params removed, sorted remaining query params.
 * The canonical path plus its binary md5 hash are what fact tables store.
 *
 * @package SEODirector
 */

namespace SEODirector\Data;

defined( 'ABSPATH' ) || exit;

final class UrlCanonicalizer {

	private const TRACKING_PARAMS = [
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
		'gclid', 'fbclid', 'msclkid', 'yclid', 'mc_cid', 'mc_eid', '_ga', '_gl', 'srsltid',
	];

	/**
	 * Canonical path (+ significant query), max 750 chars.
	 */
	public function canonicalize( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '/';
		}

		// GA4 landing pages arrive host-less ("/path?x=1"); GSC pages arrive absolute.
		$parts = wp_parse_url( str_starts_with( $url, '/' ) ? 'https://placeholder.invalid' . $url : $url );
		if ( false === $parts ) {
			return '/';
		}

		$path = (string) ( $parts['path'] ?? '/' );
		$path = rawurldecode( $path );

		// Normalize trailing slash (root stays "/", everything else stripped).
		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
		}
		if ( '' === $path ) {
			$path = '/';
		}

		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $params );
			foreach ( self::TRACKING_PARAMS as $tracking ) {
				unset( $params[ $tracking ] );
			}
			if ( [] !== $params ) {
				ksort( $params );
				$query = '?' . http_build_query( $params );
			}
		}

		return mb_substr( $path . $query, 0, 750 );
	}

	/**
	 * Binary md5 of the canonical form — the hash used in unique keys.
	 */
	public function hash( string $canonical ): string {
		return md5( $canonical, true );
	}

	/**
	 * Hex md5 for use with SQL UNHEX() placeholders.
	 */
	public function hash_hex( string $canonical ): string {
		return md5( $canonical );
	}
}
