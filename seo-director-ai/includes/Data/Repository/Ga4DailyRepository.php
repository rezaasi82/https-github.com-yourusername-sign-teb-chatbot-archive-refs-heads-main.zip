<?php
/**
 * GA4 daily facts per channel × landing page ({p}sda_ga4_daily).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class Ga4DailyRepository extends BaseRepository {

	protected const TABLE = 'ga4_daily';

	/**
	 * @param array<int, array{date: string, channel: string, landing_path: string, sessions: int,
	 *                         total_users: int, engaged_sessions: int, engagement_rate: float,
	 *                         conversions: float, event_count: int}> $rows
	 */
	public function upsert_rows( int $property_id, array $rows ): int {
		$tuples = array();
		foreach ( $rows as $row ) {
			$tuples[] = array(
				$this->site_id(),
				$property_id,
				$row['date'],
				substr( $row['channel'], 0, 64 ),
				$this->bin_hash( $row['landing_path'] ),
				$row['landing_path'],
				(int) $row['sessions'],
				(int) $row['total_users'],
				(int) $row['engaged_sessions'],
				(float) $row['engagement_rate'],
				(float) $row['conversions'],
				(int) $row['event_count'],
			);
		}
		return $this->bulk_upsert(
			array(
				'site_id', 'property_id', 'date', 'channel', 'landing_hash', 'landing_path',
				'sessions', 'total_users', 'engaged_sessions', 'engagement_rate', 'conversions', 'event_count',
			),
			$tuples,
			array( 'sessions', 'total_users', 'engaged_sessions', 'engagement_rate', 'conversions', 'event_count' )
		);
	}

	/**
	 * Organic-channel daily series (sessions + conversions) for the dashboard.
	 *
	 * @return array<int, array{date: string, sessions: int, conversions: float}>
	 */
	public function organic_series( int $property_id, string $from, string $to ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT date, SUM(sessions) AS sessions, SUM(conversions) AS conversions
				 FROM {$this->table()}
				 WHERE property_id = %d AND channel = 'Organic Search' AND date BETWEEN %s AND %s
				 GROUP BY date ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$from,
				$to
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $r ) => array(
				'date'        => (string) $r['date'],
				'sessions'    => (int) $r['sessions'],
				'conversions' => (float) $r['conversions'],
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
