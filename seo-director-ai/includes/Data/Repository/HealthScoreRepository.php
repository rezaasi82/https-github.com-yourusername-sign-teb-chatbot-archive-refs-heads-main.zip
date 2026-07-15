<?php
/**
 * Daily SEO health score history ({p}sda_health_scores).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class HealthScoreRepository extends BaseRepository {

	protected const TABLE = 'health_scores';

	/** @param array<string, mixed> $components */
	public function save( string $date, int $score, array $components ): void {
		$db = $this->db();
		$db->query(
			$db->prepare(
				"INSERT INTO {$this->table()} (site_id, date, score, components) VALUES (%d, %s, %d, %s)
				 ON DUPLICATE KEY UPDATE score = VALUES(score), components = VALUES(components)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$date,
				$score,
				(string) wp_json_encode( $components )
			)
		);
	}

	/** @return array{date: string, score: int, components: array<string, mixed>}|null */
	public function latest(): ?array {
		$db  = $this->db();
		$row = $db->get_row(
			$db->prepare(
				"SELECT date, score, components FROM {$this->table()} WHERE site_id = %d ORDER BY date DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id()
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$components = json_decode( (string) $row['components'], true );
		return array(
			'date'       => (string) $row['date'],
			'score'      => (int) $row['score'],
			'components' => is_array( $components ) ? $components : array(),
		);
	}

	/** @return array<int, array{date: string, score: int}> */
	public function history( int $days = 90 ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT date, score FROM {$this->table()} WHERE site_id = %d ORDER BY date DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$days
			),
			ARRAY_A
		);
		$rows = array_reverse( $rows ?: array() );
		return array_map( static fn( array $r ) => array( 'date' => (string) $r['date'], 'score' => (int) $r['score'] ), $rows );
	}
}
