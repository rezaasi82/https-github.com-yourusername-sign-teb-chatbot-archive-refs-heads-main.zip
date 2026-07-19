<?php
/**
 * Builds weekly/monthly rollups from daily GSC facts via single INSERT…SELECT
 * statements — no PHP-side row shuffling. Rollups keep history after daily pruning.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Rollup;

defined( 'ABSPATH' ) || exit;

final class RollupBuilder {

	/**
	 * Rebuild rollups for a trailing window (default 60 days covers restatements).
	 */
	public function build( int $property_id, int $window_days = 60 ): void {
		$from = gmdate( 'Y-m-d', strtotime( "-{$window_days} days" ) );

		$this->rollup( 'query', 'weekly', $property_id, $from );
		$this->rollup( 'query', 'monthly', $property_id, $from );
		$this->rollup( 'page', 'weekly', $property_id, $from );
		$this->rollup( 'page', 'monthly', $property_id, $from );
	}

	/**
	 * @param 'query'|'page'     $dim   Dimension family.
	 * @param 'weekly'|'monthly' $grain Rollup grain.
	 */
	private function rollup( string $dim, string $grain, int $property_id, string $from ): void {
		global $wpdb;

		$source   = $wpdb->prefix . "sda_gsc_{$dim}_daily";
		$target   = $wpdb->prefix . "sda_gsc_{$dim}_{$grain}";
		$hash_col = 'query' === $dim ? 'query_hash' : 'page_hash';
		$label    = 'query' === $dim ? 'query' : 'page_path';
		$date_col = 'weekly' === $grain ? 'week_start' : 'month_start';

		// Monday-aligned week start / first of month, both computed in SQL.
		$bucket = 'weekly' === $grain
			? 'DATE_SUB(date, INTERVAL WEEKDAY(date) DAY)'
			: "DATE_FORMAT(date, '%%Y-%%m-01')";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers from internal whitelisted sets.
		$sql = "INSERT INTO {$target} (site_id, property_id, {$date_col}, {$hash_col}, {$label},
					clicks, impressions, ctr, position, best_position)
				SELECT site_id, property_id, {$bucket} AS bucket, {$hash_col}, MAX({$label}),
					SUM(clicks), SUM(impressions),
					IF(SUM(impressions) = 0, 0, SUM(clicks) / SUM(impressions)),
					COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0),
					MIN(position)
				FROM {$source}
				WHERE property_id = %d AND date >= %s
				GROUP BY site_id, property_id, bucket, {$hash_col}
				ON DUPLICATE KEY UPDATE
					clicks = VALUES(clicks), impressions = VALUES(impressions), ctr = VALUES(ctr),
					position = VALUES(position), best_position = VALUES(best_position)";

		$wpdb->query( $wpdb->prepare( $sql, $property_id, $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
