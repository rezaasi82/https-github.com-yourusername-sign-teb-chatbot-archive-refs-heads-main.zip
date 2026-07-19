<?php
/**
 * Reads weekly/monthly query & page rollups for Winners/Losers and comparisons.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class GscRollupRepository extends BaseRepository {

	// Concrete table chosen per-call; TABLE stays empty and is not used directly.
	protected const TABLE = 'gsc_query_weekly';

	/**
	 * Two comparable periods of per-entity rollup rows, keyed by entity label,
	 * so a caller can diff "recent" vs. "previous" without touching raw daily data.
	 *
	 * @param 'query'|'page'     $dim         Dimension family.
	 * @param 'weekly'|'monthly' $grain       Rollup grain.
	 * @param string             $recent      Recent bucket date (week_start / month_start).
	 * @param string             $previous    Previous bucket date.
	 * @return array{recent: array<string, array<string, mixed>>, previous: array<string, array<string, mixed>>}
	 */
	public function comparison( string $dim, string $grain, int $property_id, string $recent, string $previous ): array {
		$table    = $this->rollup_table( $dim, $grain );
		$label    = 'query' === $dim ? 'query' : 'page_path';
		$date_col = 'weekly' === $grain ? 'week_start' : 'month_start';
		$db       = $this->db();

		$fetch = function ( string $bucket ) use ( $db, $table, $label, $date_col ): array {
			$rows = $db->get_results(
				$db->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers whitelisted.
					"SELECT {$label} AS label, clicks, impressions, ctr, position, best_position
					 FROM {$table} WHERE property_id = %d AND {$date_col} = %s",
					$property_id,
					$bucket
				),
				ARRAY_A
			);
			$keyed = array();
			foreach ( $rows ?: array() as $row ) {
				$keyed[ (string) $row['label'] ] = array(
					'label'         => (string) $row['label'],
					'clicks'        => (int) $row['clicks'],
					'impressions'   => (int) $row['impressions'],
					'ctr'           => (float) $row['ctr'],
					'position'      => (float) $row['position'],
					'best_position' => (float) $row['best_position'],
				);
			}
			return $keyed;
		};

		return array(
			'recent'   => $fetch( $recent ),
			'previous' => $fetch( $previous ),
		);
	}

	/**
	 * Most recent bucket start present for a rollup, and the one before it.
	 *
	 * @return array{recent: ?string, previous: ?string}
	 */
	public function latest_buckets( string $dim, string $grain, int $property_id ): array {
		$table    = $this->rollup_table( $dim, $grain );
		$date_col = 'weekly' === $grain ? 'week_start' : 'month_start';
		$db       = $this->db();

		$dates = $db->get_col(
			$db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers whitelisted.
				"SELECT DISTINCT {$date_col} FROM {$table} WHERE property_id = %d ORDER BY {$date_col} DESC LIMIT 2",
				$property_id
			)
		);

		return array(
			'recent'   => $dates[0] ?? null,
			'previous' => $dates[1] ?? null,
		);
	}

	/**
	 * Resolve a validated rollup table name (guards the whitelist explicitly since
	 * this repository fans out across four tables rather than the single TABLE).
	 */
	private function rollup_table( string $dim, string $grain ): string {
		$dim   = in_array( $dim, array( 'query', 'page' ), true ) ? $dim : 'query';
		$grain = in_array( $grain, array( 'weekly', 'monthly' ), true ) ? $grain : 'weekly';
		return $this->db()->prefix . "sda_gsc_{$dim}_{$grain}";
	}
}
