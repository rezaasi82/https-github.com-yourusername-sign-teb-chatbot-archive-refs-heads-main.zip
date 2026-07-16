<?php
/**
 * Persists the hub's roster of paired client sites ({p}sda_agency_sites).
 * The raw pairing secret is never stored in the clear: pair_key_hash is a
 * lookup fingerprint and pair_key_cipher holds the TokenVault-encrypted key
 * so the hub can recompute HMAC signatures on incoming snapshots.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;
use SEODirector\Integrations\Google\TokenVault;

defined( 'ABSPATH' ) || exit;

final class AgencySitesRepository {

	public function __construct( private TokenVault $vault ) {}

	/**
	 * Insert a pending client and persist its (encrypted) pairing secret.
	 *
	 * @return int New row id.
	 */
	public function create( string $client_name, string $site_url, string $pair_key ): int {
		global $wpdb;

		$table = Schema::table( 'agency_sites' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"INSERT INTO {$table} (site_id, client_name, site_url, pair_key_hash, pair_key_cipher, status, created_at)
				VALUES (%d, %s, %s, %s, %s, 'pending', %s)
				ON DUPLICATE KEY UPDATE
					client_name = VALUES(client_name),
					pair_key_hash = VALUES(pair_key_hash),
					pair_key_cipher = VALUES(pair_key_cipher),
					status = 'pending'",
				get_current_blog_id(),
				$client_name,
				$this->normalize_url( $site_url ),
				hash( 'sha256', $pair_key ),
				$this->vault->encrypt( $pair_key ),
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;

		$table = Schema::table( 'agency_sites' );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, client_name, site_url, status, last_seen_at, snapshot, created_at FROM {$table} WHERE site_id = %d ORDER BY client_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			),
			ARRAY_A
		);

		return array_map( [ $this, 'hydrate' ], $rows ?: [] );
	}

	/**
	 * Look up a client by its site URL, returning the decrypted pairing key
	 * for signature verification. Null when unknown.
	 *
	 * @return array{id: int, pair_key: string, status: string}|null
	 */
	public function find_secret_by_url( string $site_url ): ?array {
		global $wpdb;

		$table = Schema::table( 'agency_sites' );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, pair_key_cipher, status FROM {$table} WHERE site_id = %d AND site_url = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$this->normalize_url( $site_url )
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		$key = $this->vault->decrypt( (string) $row['pair_key_cipher'] );
		if ( null === $key ) {
			return null;
		}

		return [ 'id' => (int) $row['id'], 'pair_key' => $key, 'status' => (string) $row['status'] ];
	}

	/**
	 * Record a fresh snapshot from a client and mark it active.
	 *
	 * @param array<string, mixed> $snapshot
	 */
	public function record_snapshot( int $id, array $snapshot ): void {
		global $wpdb;

		$table = Schema::table( 'agency_sites' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"UPDATE {$table} SET snapshot = %s, last_seen_at = %s, status = 'active' WHERE id = %d AND site_id = %d",
				wp_json_encode( $snapshot ),
				gmdate( 'Y-m-d H:i:s' ),
				$id,
				get_current_blog_id()
			)
		);
	}

	public function delete( int $id ): void {
		global $wpdb;

		$table = Schema::table( 'agency_sites' );

		$wpdb->delete( $table, [ 'id' => $id, 'site_id' => get_current_blog_id() ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$snapshot = null;
		if ( ! empty( $row['snapshot'] ) ) {
			$decoded  = json_decode( (string) $row['snapshot'], true );
			$snapshot = is_array( $decoded ) ? $decoded : null;
		}

		return [
			'id'           => (int) $row['id'],
			'client_name'  => (string) $row['client_name'],
			'site_url'     => (string) $row['site_url'],
			'status'       => (string) $row['status'],
			'last_seen_at' => $row['last_seen_at'],
			'snapshot'     => $snapshot,
			'created_at'   => (string) $row['created_at'],
		];
	}

	private function normalize_url( string $url ): string {
		return untrailingslashit( strtolower( esc_url_raw( $url ) ) );
	}
}
