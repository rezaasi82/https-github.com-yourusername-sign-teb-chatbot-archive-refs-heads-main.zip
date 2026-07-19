<?php
/**
 * Top-N query rows per day ({p}sda_gsc_query_daily).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class GscQueryDailyRepository extends BaseRepository {

	protected const TABLE = 'gsc_query_daily';

	/**
	 * @param array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float}> $rows
	 */
	public function upsert_rows( int $property_id, string $date, array $rows ): int {
		$tuples = array();
		foreach ( $rows as $row ) {
			$tuples[] = array(
				$this->site_id(),
				$property_id,
				$date,
				$this->bin_hash( $row['query'] ),
				$row['query'],
				(int) $row['clicks'],
				(int) $row['impressions'],
				(float) $row['ctr'],
				(float) $row['position'],
			);
		}
		return $this->bulk_upsert(
			array( 'site_id', 'property_id', 'date', 'query_hash', 'query', 'clicks', 'impressions', 'ctr', 'position' ),
			$tuples,
			array( 'clicks', 'impressions', 'ctr', 'position' )
		);
	}

	/**
	 * Aggregated per-query stats over a window — feeds the opportunity detectors.
	 *
	 * @return array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float}>
	 */
	public function aggregate_window( int $property_id, string $from, string $to, int $limit = 2000 ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT query,
						SUM(clicks) AS clicks,
						SUM(impressions) AS impressions,
						IF(SUM(impressions) = 0, 0, SUM(clicks) / SUM(impressions)) AS ctr,
						SUM(position * impressions) / NULLIF(SUM(impressions), 0) AS position
				 FROM {$this->table()}
				 WHERE property_id = %d AND date BETWEEN %s AND %s
				 GROUP BY query_hash, query
				 ORDER BY impressions DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $r ) => array(
				'query'       => (string) $r['query'],
				'clicks'      => (int) $r['clicks'],
				'impressions' => (int) $r['impressions'],
				'ctr'         => (float) $r['ctr'],
				'position'    => (float) $r['position'],
			),
			$rows ?: array()
		);
	}
}
