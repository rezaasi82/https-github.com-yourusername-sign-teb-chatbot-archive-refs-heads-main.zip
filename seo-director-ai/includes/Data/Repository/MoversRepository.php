<?php
/**
 * Two-window entity aggregates (current vs previous period) for the
 * Growth/Decline detectors and opportunity scans, via conditional
 * aggregation over the daily fact tables.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Analysis\MoverRow;
use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class MoversRepository {

	/**
	 * @param 'query'|'page' $entity
	 * @return MoverRow[]
	 */
	public function period_aggregates(
		string $entity,
		int $property_id,
		string $cur_from,
		string $cur_to,
		string $prev_from,
		string $prev_to,
		int $limit = 500
	): array {
		global $wpdb;

		$table     = Schema::table( "gsc_{$entity}_daily" );
		$hash_col  = "{$entity}_hash";
		$label_col = 'query' === $entity ? '`query`' : 'page_path';

		// Impression-weighted position per window; MAX(label) picks the stored label.
		$sql = "SELECT
				HEX({$hash_col}) AS hash_hex,
				MAX({$label_col}) AS label,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks END), 0) AS cur_clicks,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions END), 0) AS cur_impressions,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN position * impressions END) / NULLIF(SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions END), 0), 0) AS cur_position,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks END) / NULLIF(SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions END), 0), 0) AS cur_ctr,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks END), 0) AS prev_clicks,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions END), 0) AS prev_impressions,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN position * impressions END) / NULLIF(SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions END), 0), 0) AS prev_position,
				COALESCE(SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks END) / NULLIF(SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions END), 0), 0) AS prev_ctr
			FROM {$table}
			WHERE property_id = %d AND date BETWEEN %s AND %s
			GROUP BY {$hash_col}
			ORDER BY ABS(cur_clicks - prev_clicks) DESC, cur_impressions DESC
			LIMIT %d";

		$cur  = [ $cur_from, $cur_to ];
		$prev = [ $prev_from, $prev_to ];

		$params = array_merge(
			$cur,          // cur_clicks
			$cur,          // cur_impressions
			$cur, $cur,    // cur_position (numerator + denominator)
			$cur, $cur,    // cur_ctr
			$prev,         // prev_clicks
			$prev,         // prev_impressions
			$prev, $prev,  // prev_position
			$prev, $prev,  // prev_ctr
			[ $property_id, $prev_from, $cur_to, $limit ]
		);

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		return array_map(
			static fn( array $r ) => new MoverRow(
				(string) $r['label'],
				strtolower( (string) $r['hash_hex'] ),
				(int) $r['cur_clicks'],
				(int) $r['cur_impressions'],
				(float) $r['cur_position'],
				(float) $r['cur_ctr'],
				(int) $r['prev_clicks'],
				(int) $r['prev_impressions'],
				(float) $r['prev_position'],
				(float) $r['prev_ctr']
			),
			$rows ?: []
		);
	}
}
