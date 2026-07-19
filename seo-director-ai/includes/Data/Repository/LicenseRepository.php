<?php
/**
 * License row storage ({p}sda_license). The key is sealed via TokenVault before storage.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

use SEODirector\Integrations\Google\TokenVault;

final class LicenseRepository extends BaseRepository {

	protected const TABLE = 'license';

	public function __construct( private readonly TokenVault $vault ) {}

	public function get(): ?object {
		$db = $this->db();
		return $db->get_row(
			$db->prepare(
				"SELECT * FROM {$this->table()} WHERE site_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id()
			)
		) ?: null;
	}

	/**
	 * Return the decrypted license key, or null when absent/unreadable.
	 */
	public function get_key(): ?string {
		$row = $this->get();
		if ( ! $row ) {
			return null;
		}
		$payload = $this->vault->open( (string) $row->license_key );
		return isset( $payload['key'] ) ? (string) $payload['key'] : null;
	}

	/**
	 * Persist activation/refresh state.
	 *
	 * @param array<string, mixed> $server_payload Last HMAC-verified server response (grace source).
	 */
	public function save( string $license_key, string $edition, string $status, ?string $expires_at, array $server_payload = array() ): void {
		$db  = $this->db();
		$now = current_time( 'mysql', true );
		$db->query(
			$db->prepare(
				"INSERT INTO {$this->table()}
					(site_id, license_key, edition, status, domain_hash, expires_at, last_check_at, server_payload)
				 VALUES (%d, %s, %s, %s, %s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE license_key = VALUES(license_key), edition = VALUES(edition),
					status = VALUES(status), expires_at = VALUES(expires_at), last_check_at = VALUES(last_check_at),
					server_payload = VALUES(server_payload)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$this->vault->seal( array( 'key' => $license_key ) ),
				$edition,
				$status,
				self::domain_hash(),
				$expires_at,
				$now,
				(string) wp_json_encode( $server_payload )
			)
		);
	}

	public function update_status( string $status ): void {
		$db = $this->db();
		$db->update(
			$this->table(),
			array(
				'status'        => $status,
				'last_check_at' => current_time( 'mysql', true ),
			),
			array( 'site_id' => $this->site_id() )
		);
	}

	public function clear(): void {
		$db = $this->db();
		$db->delete( $this->table(), array( 'site_id' => $this->site_id() ) );
	}

	/** sha256(home_url) — privacy: the server never sees a raw domain. */
	public static function domain_hash(): string {
		return hash( 'sha256', untrailingslashit( home_url() ) );
	}
}
