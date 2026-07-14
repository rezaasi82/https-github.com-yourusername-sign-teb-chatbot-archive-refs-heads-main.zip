<?php
/**
 * Writes/reads Search Console fact tables. All writes are batched
 * multi-row INSERT … ON DUPLICATE KEY UPDATE so re-syncs (the 3-day
 * restatement window) are idempotent.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class GscRepository {

	private const BATCH = 500;

	/**
	 * @param array<int, array{date: string, clicks: int, impressions: int, ctr: float, position: float}> $rows
	 */
	public function upsert_daily_totals( int $property_id, array $rows ): void {
		global $wpdb;

		$table   = Schema::table( 'gsc_daily_totals' );
		$site_id = get_current_blog_id();

		foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
			$values       = [];
			$placeholders = [];
			foreach ( $chunk as $row ) {
				$placeholders[] = '(%d, %d, %s, %d, %d, %f, %f)';
				array_push( $values, $site_id, $property_id, $row['date'], $row['clicks'], $row['impressions'], $row['ctr'], $row['position'] );
			}

			$sql = "INSERT INTO {$table} (site_id, property_id, date, clicks, impressions, ctr, position) VALUES "
				. implode( ', ', $placeholders )
				. ' ON DUPLICATE KEY UPDATE clicks = VALUES(clicks), impressions = VALUES(impressions), ctr = VALUES(ctr), position = VALUES(position)';

			$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Upsert per-entity daily rows (queries or pages).
	 *
	 * @param 'query'|'page'                                                                                                 $entity
	 * @param array<int, array{date: string, hash_hex: string, label: string, clicks: int, impressions: int, ctr: float, position: float}> $rows
	 */
	public function upsert_entity_daily( string $entity, int $property_id, array $rows ): void {
		global $wpdb;

		$table     = Schema::table( "gsc_{$entity}_daily" );
		$hash_col  = "{$entity}_hash";
		$label_col = 'query' === $entity ? 'query' : 'page_path';
		$site_id   = get_current_blog_id();

		foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
			$values       = [];
			$placeholders = [];
			foreach ( $chunk as $row ) {
				$placeholders[] = '(%d, %d, %s, UNHEX(%s), %s, %d, %d, %f, %f)';
				array_push( $values, $site_id, $property_id, $row['date'], $row['hash_hex'], $row['label'], $row['clicks'], $row['impressions'], $row['ctr'], $row['position'] );
			}

			$sql = "INSERT INTO {$table} (site_id, property_id, date, {$hash_col}, `{$label_col}`, clicks, impressions, ctr, position) VALUES "
				. implode( ', ', $placeholders )
				. ' ON DUPLICATE KEY UPDATE clicks = VALUES(clicks), impressions = VALUES(impressions), ctr = VALUES(ctr), position = VALUES(position)';

			$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * @param array<int, array{date: string, dim_type: string, dim_value: string, clicks: int, impressions: int, ctr: float, position: float}> $rows
	 */
	public function upsert_dimension_daily( int $property_id, array $rows ): void {
		global $wpdb;

		$table   = Schema::table( 'gsc_dimension_daily' );
		$site_id = get_current_blog_id();

		foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
			$values       = [];
			$placeholders = [];
			foreach ( $chunk as $row ) {
				$placeholders[] = '(%d, %d, %s, %s, %s, %d, %d, %f, %f)';
				array_push( $values, $site_id, $property_id, $row['date'], $row['dim_type'], $row['dim_value'], $row['clicks'], $row['impressions'], $row['ctr'], $row['position'] );
			}

			$sql = "INSERT INTO {$table} (site_id, property_id, date, dim_type, dim_value, clicks, impressions, ctr, position) VALUES "
				. implode( ', ', $placeholders )
				. ' ON DUPLICATE KEY UPDATE clicks = VALUES(clicks), impressions = VALUES(impressions), ctr = VALUES(ctr), position = VALUES(position)';

			$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Site-level daily series for the overview chart.
	 *
	 * @return array<int, array{date: string, clicks: int, impressions: int, ctr: float, position: float}>
	 */
	public function daily_totals_series( int $property_id, string $from, string $to ): array {
		global $wpdb;

		$table = Schema::table( 'gsc_daily_totals' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT date, clicks, impressions, ctr, position FROM {$table} WHERE property_id = %d AND date BETWEEN %s AND %s ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$from,
				$to
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $r ) => [
				'date'        => (string) $r['date'],
				'clicks'      => (int) $r['clicks'],
				'impressions' => (int) $r['impressions'],
				'ctr'         => round( (float) $r['ctr'], 4 ),
				'position'    => round( (float) $r['position'], 2 ),
			],
			$rows ?: []
		);
	}

	/**
	 * Most recent date present in daily totals, or null when empty.
	 */
	public function latest_date( int $property_id ): ?string {
		global $wpdb;

		$table = Schema::table( 'gsc_daily_totals' );
		$date  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT MAX(date) FROM {$table} WHERE property_id = %d", $property_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $date ?: null;
	}

	/**
	 * Top page paths by clicks over a recent window (PSI audit targets).
	 *
	 * @return string[]
	 */
	public function top_pages( int $property_id, string $from, string $to, int $limit = 20 ): array {
		global $wpdb;

		$table = Schema::table( 'gsc_page_daily' );

		return array_map(
			'strval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT page_path FROM {$table} WHERE property_id = %d AND date BETWEEN %s AND %s GROUP BY page_hash, page_path ORDER BY SUM(clicks) DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$property_id,
					$from,
					$to,
					$limit
				)
			)
		);
	}
}
