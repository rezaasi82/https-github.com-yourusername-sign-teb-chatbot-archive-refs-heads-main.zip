<?php
/**
 * The HMAC handshake shared by both ends of a hub↔client pairing.
 *
 *   Client side:  sign( body, key ) → headers pushed with the snapshot.
 *   Hub side:     verify( body, key, timestamp, signature ) on ingest.
 *
 * Signatures cover "{timestamp}.{body}" so a captured request cannot be
 * replayed outside a short freshness window, and the constant-time compare
 * keeps verification free of timing leaks. This class is pure — no WP calls —
 * so it is unit-testable in isolation.
 *
 * @package SEODirector
 */

namespace SEODirector\Agency;

defined( 'ABSPATH' ) || exit;

final class SiteConnector {

	public const HEADER_SITE      = 'X-SDA-Site';
	public const HEADER_TIMESTAMP = 'X-SDA-Timestamp';
	public const HEADER_SIGNATURE = 'X-SDA-Signature';

	/** Requests older than this (seconds) are rejected as stale/replayed. */
	private const FRESHNESS_WINDOW = 300;

	/**
	 * Generate a cryptographically strong pairing key (hex).
	 */
	public static function generate_pair_key(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Compute the signature for a request body at a given time.
	 */
	public function sign( string $body, string $key, int $timestamp ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $key );
	}

	/**
	 * Verify an incoming signature against the shared key.
	 *
	 * @param int $now Current unix time (injected for testability).
	 */
	public function verify( string $body, string $key, int $timestamp, string $signature, int $now ): bool {
		if ( '' === $key || '' === $signature ) {
			return false;
		}

		// Reject stale or future-dated requests (clock-skew tolerant both ways).
		if ( abs( $now - $timestamp ) > self::FRESHNESS_WINDOW ) {
			return false;
		}

		$expected = $this->sign( $body, $key, $timestamp );

		return hash_equals( $expected, $signature );
	}
}
