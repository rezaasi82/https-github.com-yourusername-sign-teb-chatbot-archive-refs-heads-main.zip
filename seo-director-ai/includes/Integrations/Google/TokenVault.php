<?php
/**
 * Encrypted secret storage. libsodium secretbox preferred, openssl AES-256-GCM fallback.
 * Key derived from SECURE_AUTH_KEY + per-install salt (stored separately from ciphertext rows).
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

use RuntimeException;

final class TokenVault {

	private const SALT_OPTION = 'sda_vault_salt';

	/**
	 * Encrypt an arbitrary payload to a versioned, base64 armored blob.
	 *
	 * @param array<string, mixed> $payload Secret material (tokens, api keys).
	 */
	public function seal( array $payload ): string {
		$plain = (string) wp_json_encode( $payload );
		$key   = $this->derive_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $key );
			return 'v1s:' . base64_encode( $nonce . $cipher );
		}

		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			throw new RuntimeException( 'Encryption failed.' );
		}
		return 'v1o:' . base64_encode( $iv . $tag . $cipher );
	}

	/**
	 * Decrypt a sealed blob back to its payload.
	 *
	 * @return array<string, mixed>|null Null on tamper/corruption — callers treat as revoked.
	 */
	public function open( string $blob ): ?array {
		$key = $this->derive_key();

		if ( str_starts_with( $blob, 'v1s:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$data = base64_decode( substr( $blob, 4 ), true );
			if ( false === $data || strlen( $data ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return null;
			}
			$nonce  = substr( $data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
			return false === $plain ? null : $this->decode( $plain );
		}

		if ( str_starts_with( $blob, 'v1o:' ) ) {
			$data = base64_decode( substr( $blob, 4 ), true );
			if ( false === $data || strlen( $data ) <= 28 ) {
				return null;
			}
			$iv     = substr( $data, 0, 12 );
			$tag    = substr( $data, 12, 16 );
			$cipher = substr( $data, 28 );
			$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return false === $plain ? null : $this->decode( $plain );
		}

		return null;
	}

	/**
	 * Display-safe masked representation (last 4 chars only).
	 */
	public static function mask( string $secret ): string {
		$len = strlen( $secret );
		return $len <= 4 ? str_repeat( '•', $len ) : str_repeat( '•', 8 ) . substr( $secret, -4 );
	}

	/** @return array<string, mixed>|null */
	private function decode( string $plain ): ?array {
		$decoded = json_decode( $plain, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	private function derive_key(): string {
		$salt = (string) get_option( self::SALT_OPTION, '' );
		if ( '' === $salt ) {
			$salt = base64_encode( random_bytes( 32 ) );
			add_option( self::SALT_OPTION, $salt, '', false );
		}
		$secret = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : wp_salt( 'secure_auth' );
		return hash_hkdf( 'sha256', $secret, 32, 'sda-token-vault', (string) base64_decode( $salt, true ) );
	}
}
