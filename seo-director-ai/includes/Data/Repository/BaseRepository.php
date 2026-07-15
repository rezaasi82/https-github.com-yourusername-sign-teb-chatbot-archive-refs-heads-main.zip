<?php
/**
 * Shared repository plumbing: table naming whitelist, site scoping, batch upserts.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

use wpdb;

abstract class BaseRepository {

	/** Short table name without the sda_ prefix — must appear in TABLES. */
	protected const TABLE = '';

	/** Whitelist guarding against dynamic table interpolation. */
	private const TABLES = array(
		'connections',
		'properties',
		'gsc_daily_totals',
		'gsc_query_daily',
		'gsc_page_daily',
		'gsc_page_query_weekly',
		'gsc_dimension_daily',
		'ga4_daily',
		'psi_audits',
		'insights',
		'opportunities',
		'roadmap_tasks',
		'alerts',
		'health_scores',
		'reports',
		'job_state',
		'agency_sites',
		'license',
	);

	protected function db(): wpdb {
		global $wpdb;
		return $wpdb;
	}

	protected function table(): string {
		if ( ! in_array( static::TABLE, self::TABLES, true ) ) {
			wp_die( 'Invalid repository table.' ); // Programming error, not user input.
		}
		return $this->db()->prefix . 'sda_' . static::TABLE;
	}

	protected function site_id(): int {
		return get_current_blog_id();
	}

	/**
	 * md5 binary hash used for the *_hash BINARY(16) columns.
	 */
	protected function bin_hash( string $value ): string {
		return md5( $value, true );
	}

	/**
	 * Multi-row INSERT … ON DUPLICATE KEY UPDATE in 500-row batches.
	 *
	 * @param string[]                        $columns      Column names (whitelisted by caller).
	 * @param array<int, array<int, mixed>>   $rows         Row tuples matching $columns order.
	 * @param string[]                        $update_cols  Columns to overwrite on duplicate key.
	 */
	protected function bulk_upsert( array $columns, array $rows, array $update_cols ): int {
		if ( empty( $rows ) ) {
			return 0;
		}
		$db       = $this->db();
		$table    = $this->table();
		$col_sql  = '`' . implode( '`, `', array_map( static fn( $c ) => preg_replace( '/[^a-z0-9_]/', '', $c ), $columns ) ) . '`';
		$updates  = implode( ', ', array_map( static fn( $c ) => "`{$c}` = VALUES(`{$c}`)", array_map( static fn( $c ) => preg_replace( '/[^a-z0-9_]/', '', $c ), $update_cols ) ) );
		$affected = 0;

		foreach ( array_chunk( $rows, 500 ) as $chunk ) {
			$placeholders = array();
			$values       = array();
			foreach ( $chunk as $row ) {
				$row_ph = array();
				foreach ( $row as $value ) {
					$row_ph[] = is_int( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' );
					$values[] = $value;
				}
				$placeholders[] = '(' . implode( ', ', $row_ph ) . ')';
			}
			$sql = "INSERT INTO {$table} ({$col_sql}) VALUES " . implode( ', ', $placeholders )
				. " ON DUPLICATE KEY UPDATE {$updates}";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/columns whitelisted, values prepared.
			$result = $db->query( $db->prepare( $sql, $values ) );
			if ( false !== $result ) {
				$affected += (int) $result;
			}
		}
		return $affected;
	}
}
