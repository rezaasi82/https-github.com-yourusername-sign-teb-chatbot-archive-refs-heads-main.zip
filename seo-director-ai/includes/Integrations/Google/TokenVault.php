<?php
/**
 * Encrypted at-rest storage for OAuth tokens and API keys.
 *
 * Uses libsodium XChaCha20-Poly1305 secretbox with a key derived from the
 * site's SECURE_AUTH_KEY plus a random per-install salt stored separately
 * from the ciphertext rows. Falls back to AES-256-GCM via OpenSSL when
 * sodium is unavailable (format-tagged so either can decrypt its own output).
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

final class TokenVault {

	private const SALT_OPTION = 'sda_vault_salt';
	private const FORMAT_SODIUM  = 's1';
	private const FORMAT_OPENSSL = 'o1';

	/**
	 * Encrypt a secret for storage.
	 */
	public function encrypt( string $plaintext ): string {
		$key = $this->key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );

			return self::FORMAT_SODIUM . ':' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			throw new \RuntimeException( 'SEO Director AI: encryption failed.' );
		}

		return self::FORMAT_OPENSSL . ':' . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored secret. Returns null on tampering/corruption instead of throwing,
	 * so callers can treat it as "connection needs re-auth".
	 */
	public function decrypt( string $stored ): ?string {
		$parts = explode( ':', $stored, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		[ $format, $encoded ] = $parts;
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return null;
		}

		$key = $this->key();

		if ( self::FORMAT_SODIUM === $format && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			if ( strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return null;
			}
			$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

			return false === $plain ? null : $plain;
		}

		if ( self::FORMAT_OPENSSL === $format ) {
			if ( strlen( $raw ) <= 28 ) {
				return null;
			}
			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );
			$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			return false === $plain ? null : $plain;
		}

		return null;
	}

	/**
	 * 32-byte key derived from WP auth secret + per-install random salt.
	 */
	private function key(): string {
		$salt = get_option( self::SALT_OPTION );
		if ( ! is_string( $salt ) || '' === $salt ) {
			$salt = bin2hex( random_bytes( 16 ) );
			add_option( self::SALT_OPTION, $salt, '', false );
			$salt = get_option( self::SALT_OPTION, $salt ); // Re-read: another request may have won the race.
		}

		$secret = defined( 'SECURE_AUTH_KEY' ) && '' !== SECURE_AUTH_KEY ? SECURE_AUTH_KEY : wp_salt( 'secure_auth' );

		return hash_hkdf( 'sha256', $secret, 32, 'sda-token-vault', $salt );
	}
}
