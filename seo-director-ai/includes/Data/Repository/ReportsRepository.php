<?php
/**
 * Report record persistence ({p}sda_reports).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class ReportsRepository {

	/**
	 * @param array<string, string> $files format => absolute path.
	 */
	public function insert( string $type, string $from, string $to, array $files, string $status = 'sent' ): int {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'reports' ),
			[
				'site_id'      => get_current_blog_id(),
				'type'         => $type,
				'period_start' => $from,
				'period_end'   => $to,
				'formats'      => implode( ',', array_keys( $files ) ),
				'storage'      => (string) wp_json_encode( $files ),
				'status'       => $status,
				'created_at'   => current_time( 'mysql', true ),
				'sent_at'      => 'sent' === $status ? current_time( 'mysql', true ) : null,
			]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list( int $limit = 30 ): array {
		global $wpdb;

		$table = Schema::table( 'reports' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, type, period_start, period_end, formats, status, created_at FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $r ) => [
				'id'           => (int) $r['id'],
				'type'         => (string) $r['type'],
				'period_start' => (string) $r['period_start'],
				'period_end'   => (string) $r['period_end'],
				'formats'      => array_filter( explode( ',', (string) $r['formats'] ) ),
				'status'       => (string) $r['status'],
				'created_at'   => (string) $r['created_at'],
			],
			$rows ?: []
		);
	}

	/**
	 * @return array{format_path: array<string, string>}|null
	 */
	public function files( int $id ): ?array {
		global $wpdb;

		$table   = Schema::table( 'reports' );
		$storage = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT storage FROM {$table} WHERE id = %d AND site_id = %d", $id, get_current_blog_id() ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $storage ) {
			return null;
		}

		$decoded = json_decode( (string) $storage, true );

		return [ 'format_path' => is_array( $decoded ) ? $decoded : [] ];
	}
}
