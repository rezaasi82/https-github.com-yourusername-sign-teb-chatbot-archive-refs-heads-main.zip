<?php
/**
 * License row persistence ({p}sda_license). The key and the last verified
 * server payload are stored encrypted (payload is the cached grace source).
 *
 * @package SEODirector
 */

namespace SEODirector\License;

use SEODirector\Core\Schema;
use SEODirector\Integrations\Google\TokenVault;

defined( 'ABSPATH' ) || exit;

final class LicenseRepository {

	public function __construct( private TokenVault $vault ) {}

	/**
	 * @return array{license_key: string, edition: string, status: string, expires_at: string|null, last_check_at: string|null, server_payload: array<string, mixed>}|null
	 */
	public function get(): ?array {
		global $wpdb;

		$table = Schema::table( 'license' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT license_key, edition, status, expires_at, last_check_at, server_payload FROM {$table} WHERE site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$payload = null !== $row['server_payload'] ? $this->vault->decrypt( (string) $row['server_payload'] ) : null;

		return [
			'license_key'    => (string) ( $this->vault->decrypt( (string) $row['license_key'] ) ?? '' ),
			'edition'        => (string) $row['edition'],
			'status'         => (string) $row['status'],
			'expires_at'     => $row['expires_at'] ? (string) $row['expires_at'] : null,
			'last_check_at'  => $row['last_check_at'] ? (string) $row['last_check_at'] : null,
			'server_payload' => null !== $payload ? ( json_decode( $payload, true ) ?: [] ) : [],
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function save( array $data ): void {
		global $wpdb;

		$table   = Schema::table( 'license' );
		$now     = current_time( 'mysql', true );
		$existing = $this->get();

		$row = [
			'site_id'        => get_current_blog_id(),
			'license_key'    => $this->vault->encrypt( (string) ( $data['license_key'] ?? ( $existing['license_key'] ?? '' ) ) ),
			'edition'        => (string) ( $data['edition'] ?? ( $existing['edition'] ?? 'starter' ) ),
			'status'         => (string) ( $data['status'] ?? ( $existing['status'] ?? 'invalid' ) ),
			'domain_hash'    => hash( 'sha256', home_url() ),
			'expires_at'     => $data['expires_at'] ?? ( $existing['expires_at'] ?? null ),
			'last_check_at'  => $now,
			'server_payload' => isset( $data['server_payload'] ) ? $this->vault->encrypt( (string) wp_json_encode( $data['server_payload'] ) ) : null,
		];

		if ( null === $existing ) {
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return;
		}

		$wpdb->update( $table, $row, [ 'site_id' => get_current_blog_id() ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function delete(): void {
		global $wpdb;

		$wpdb->delete( Schema::table( 'license' ), [ 'site_id' => get_current_blog_id() ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
