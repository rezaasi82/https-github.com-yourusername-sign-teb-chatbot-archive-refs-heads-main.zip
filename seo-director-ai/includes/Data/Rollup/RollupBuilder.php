<?php
/**
 * Builds weekly/monthly rollups from GSC daily fact tables via
 * INSERT … SELECT aggregation. Position is impression-weighted;
 * best_position is the best (lowest) daily average in the bucket.
 * Weeks start on Monday.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Rollup;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class RollupBuilder {

	/**
	 * Rebuild all four rollup tables for the buckets overlapping [$from, $to].
	 */
	public function rebuild_range( int $property_id, string $from, string $to ): void {
		foreach ( [ 'query', 'page' ] as $entity ) {
			$this->rollup( $entity, 'weekly', $property_id, $from, $to );
			$this->rollup( $entity, 'monthly', $property_id, $from, $to );
		}
	}

	/**
	 * @param 'query'|'page'     $entity
	 * @param 'weekly'|'monthly' $grain
	 */
	private function rollup( string $entity, string $grain, int $property_id, string $from, string $to ): void {
		global $wpdb;

		$daily     = Schema::table( "gsc_{$entity}_daily" );
		$target    = Schema::table( "gsc_{$entity}_{$grain}" );
		$hash_col  = "{$entity}_hash";
		$label_col = 'query' === $entity ? '`query`' : 'page_path';
		$date_col  = 'weekly' === $grain ? 'week_start' : 'month_start';

		// Widen the range to full bucket boundaries so partial buckets recompute correctly.
		$bucket = 'weekly' === $grain
			? 'DATE_SUB(date, INTERVAL WEEKDAY(date) DAY)'
			: "DATE_FORMAT(date, '%%Y-%%m-01')";

		$sql = "INSERT INTO {$target} (site_id, property_id, {$date_col}, {$hash_col}, {$label_col}, clicks, impressions, ctr, position, best_position)
			SELECT site_id, property_id, {$bucket} AS bucket, {$hash_col},
				SUBSTRING_INDEX(GROUP_CONCAT({$label_col} SEPARATOR 0x1F), 0x1F, 1),
				SUM(clicks), SUM(impressions),
				COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0), 0),
				COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0),
				MIN(position)
			FROM {$daily}
			WHERE property_id = %d
				AND date >= " . ( 'weekly' === $grain ? 'DATE_SUB(%s, INTERVAL WEEKDAY(%s) DAY)' : "DATE_FORMAT(%s, '%%Y-%%m-01')" ) . '
				AND date <= %s
			GROUP BY site_id, property_id, bucket, ' . $hash_col . '
			ON DUPLICATE KEY UPDATE
				clicks = VALUES(clicks), impressions = VALUES(impressions),
				ctr = VALUES(ctr), position = VALUES(position), best_position = VALUES(best_position)';

		$params = 'weekly' === $grain
			? [ $property_id, $from, $from, $to ]
			: [ $property_id, $from, $to ];

		$wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}
}
