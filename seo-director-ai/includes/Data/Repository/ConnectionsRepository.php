<?php
/**
 * CRUD for {p}sda_connections. Credentials are encrypted via TokenVault
 * before they reach this repository's write methods' storage.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;
use SEODirector\Integrations\Google\TokenVault;

defined( 'ABSPATH' ) || exit;

final class ConnectionsRepository {

	public function __construct( private TokenVault $vault ) {}

	/**
	 * @return array{id: int, service: string, status: string, account_label: string, credentials: array<string, mixed>, scopes: string}|null
	 */
	public function get( string $service ): ?array {
		global $wpdb;

		$table = Schema::table( 'connections' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, service, status, account_label, credentials, scopes FROM {$table} WHERE site_id = %d AND service = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$service
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$plain = $this->vault->decrypt( (string) $row['credentials'] );
		$creds = null !== $plain ? json_decode( $plain, true ) : null;

		return [
			'id'            => (int) $row['id'],
			'service'       => (string) $row['service'],
			'status'        => is_array( $creds ) ? (string) $row['status'] : 'error',
			'account_label' => (string) $row['account_label'],
			'credentials'   => is_array( $creds ) ? $creds : [],
			'scopes'        => (string) $row['scopes'],
		];
	}

	/**
	 * @return string[] Connected service slugs.
	 */
	public function connected_services(): array {
		global $wpdb;

		$table = Schema::table( 'connections' );

		return array_map(
			'strval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT service FROM {$table} WHERE site_id = %d AND status = 'connected'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					get_current_blog_id()
				)
			)
		);
	}

	/**
	 * Insert or replace a connection.
	 *
	 * @param array<string, mixed> $credentials Plain credentials; encrypted here.
	 */
	public function save( string $service, array $credentials, string $account_label = '', string $scopes = '' ): int {
		global $wpdb;

		$table     = Schema::table( 'connections' );
		$encrypted = $this->vault->encrypt( (string) wp_json_encode( $credentials ) );
		$now       = current_time( 'mysql', true );
		$existing  = $this->get( $service );

		$data = [
			'site_id'       => get_current_blog_id(),
			'service'       => $service,
			'status'        => 'connected',
			'account_label' => $account_label,
			'credentials'   => $encrypted,
			'scopes'        => $scopes,
			'updated_at'    => $now,
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => $existing['id'] ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $existing['id'];
		}

		$data['created_at'] = $now;
		$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (int) $wpdb->insert_id;
	}

	public function set_status( string $service, string $status ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'connections' ),
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			],
			[
				'site_id' => get_current_blog_id(),
				'service' => $service,
			]
		);
	}

	public function delete( string $service ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'connections' ),
			[
				'site_id' => get_current_blog_id(),
				'service' => $service,
			]
		);
	}
}
