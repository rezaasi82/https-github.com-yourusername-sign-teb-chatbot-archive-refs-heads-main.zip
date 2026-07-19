<?php
/**
 * Top-N page rows per day ({p}sda_gsc_page_daily).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class GscPageDailyRepository extends BaseRepository {

	protected const TABLE = 'gsc_page_daily';

	/**
	 * @param array<int, array{page_path: string, clicks: int, impressions: int, ctr: float, position: float}> $rows
	 */
	public function upsert_rows( int $property_id, string $date, array $rows ): int {
		$tuples = array();
		foreach ( $rows as $row ) {
			$tuples[] = array(
				$this->site_id(),
				$property_id,
				$date,
				$this->bin_hash( $row['page_path'] ),
				$row['page_path'],
				(int) $row['clicks'],
				(int) $row['impressions'],
				(float) $row['ctr'],
				(float) $row['position'],
			);
		}
		return $this->bulk_upsert(
			array( 'site_id', 'property_id', 'date', 'page_hash', 'page_path', 'clicks', 'impressions', 'ctr', 'position' ),
			$tuples,
			array( 'clicks', 'impressions', 'ctr', 'position' )
		);
	}

	/**
	 * Top pages by clicks over a window.
	 *
	 * @return array<int, array{page_path: string, clicks: int, impressions: int, ctr: float, position: float}>
	 */
	public function top_pages( int $property_id, string $from, string $to, int $limit = 25 ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT page_path,
						SUM(clicks) AS clicks,
						SUM(impressions) AS impressions,
						IF(SUM(impressions) = 0, 0, SUM(clicks) / SUM(impressions)) AS ctr,
						SUM(position * impressions) / NULLIF(SUM(impressions), 0) AS position
				 FROM {$this->table()}
				 WHERE property_id = %d AND date BETWEEN %s AND %s
				 GROUP BY page_hash, page_path
				 ORDER BY clicks DESC
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
				'page_path'   => (string) $r['page_path'],
				'clicks'      => (int) $r['clicks'],
				'impressions' => (int) $r['impressions'],
				'ctr'         => (float) $r['ctr'],
				'position'    => (float) $r['position'],
			),
			$rows ?: array()
		);
	}
}
