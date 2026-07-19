<?php
/**
 * Site-level exact daily totals ({p}sda_gsc_daily_totals) — the anchor series.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class GscDailyTotalsRepository extends BaseRepository {

	protected const TABLE = 'gsc_daily_totals';

	/**
	 * @param array<int, array{date: string, clicks: int, impressions: int, ctr: float, position: float}> $rows
	 */
	public function upsert_rows( int $property_id, array $rows ): int {
		$tuples = array();
		foreach ( $rows as $row ) {
			$tuples[] = array(
				$this->site_id(),
				$property_id,
				$row['date'],
				(int) $row['clicks'],
				(int) $row['impressions'],
				(float) $row['ctr'],
				(float) $row['position'],
			);
		}
		return $this->bulk_upsert(
			array( 'site_id', 'property_id', 'date', 'clicks', 'impressions', 'ctr', 'position' ),
			$tuples,
			array( 'clicks', 'impressions', 'ctr', 'position' )
		);
	}

	/**
	 * Ordered series for a date range.
	 *
	 * @return array<int, array{date: string, clicks: int, impressions: int, ctr: float, position: float}>
	 */
	public function series( int $property_id, string $from, string $to ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT date, clicks, impressions, ctr, position FROM {$this->table()}
				 WHERE property_id = %d AND date BETWEEN %s AND %s ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$from,
				$to
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $r ) => array(
				'date'        => (string) $r['date'],
				'clicks'      => (int) $r['clicks'],
				'impressions' => (int) $r['impressions'],
				'ctr'         => (float) $r['ctr'],
				'position'    => (float) $r['position'],
			),
			$rows ?: array()
		);
	}

	public function latest_date( int $property_id ): ?string {
		$db = $this->db();
		return $db->get_var(
			$db->prepare(
				"SELECT MAX(date) FROM {$this->table()} WHERE property_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id
			)
		) ?: null;
	}
}
