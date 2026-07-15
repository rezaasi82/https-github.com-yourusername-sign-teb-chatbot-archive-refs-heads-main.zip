<?php
/**
 * Detected opportunities ({p}sda_opportunities).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

use SEODirector\Analysis\OpportunityDetector\Opportunity;

final class OpportunitiesRepository extends BaseRepository {

	protected const TABLE = 'opportunities';

	/**
	 * Upsert detector results; refreshed_at bumps on every scan so stale rows can be aged out.
	 *
	 * @param Opportunity[] $opportunities
	 */
	public function sync_detector_results( string $detector, array $opportunities ): void {
		$db  = $this->db();
		$now = current_time( 'mysql', true );

		foreach ( $opportunities as $opp ) {
			$db->query(
				$db->prepare(
					"INSERT INTO {$this->table()}
						(site_id, detector, entity_type, entity_hash, entity_label, secondary_label,
						 score, est_traffic_gain, difficulty, status, data, detected_at, refreshed_at)
					 VALUES (%d, %s, %s, %s, %s, %s, %f, %d, %d, 'open', %s, %s, %s)
					 ON DUPLICATE KEY UPDATE score = VALUES(score), est_traffic_gain = VALUES(est_traffic_gain),
						secondary_label = VALUES(secondary_label), data = VALUES(data), refreshed_at = VALUES(refreshed_at),
						status = IF(status = 'stale', 'open', status)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$this->site_id(),
					$detector,
					$opp->entity_type,
					$this->bin_hash( $opp->entity_label ),
					$opp->entity_label,
					$opp->secondary_label,
					$opp->score,
					$opp->est_traffic_gain,
					$opp->difficulty,
					(string) wp_json_encode( $opp->data ),
					$now,
					$now
				)
			);
		}

		// Anything this detector no longer reports goes stale (unless the user already acted on it).
		$db->query(
			$db->prepare(
				"UPDATE {$this->table()} SET status = 'stale'
				 WHERE site_id = %d AND detector = %s AND status = 'open' AND refreshed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$detector,
				$now
			)
		);
	}

	/** @return array<int, array<string, mixed>> */
	public function list_open( int $limit = 50 ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT id, detector, entity_type, entity_label, secondary_label, score,
						est_traffic_gain, difficulty, status, data, detected_at
				 FROM {$this->table()}
				 WHERE site_id = %d AND status IN ('open', 'in_roadmap')
				 ORDER BY score DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$limit
			),
			ARRAY_A
		);
		return array_map(
			static function ( array $r ): array {
				$r['id']               = (int) $r['id'];
				$r['score']            = (float) $r['score'];
				$r['est_traffic_gain'] = null === $r['est_traffic_gain'] ? null : (int) $r['est_traffic_gain'];
				$r['difficulty']       = (int) $r['difficulty'];
				$data                  = json_decode( (string) $r['data'], true );
				$r['data']             = is_array( $data ) ? $data : array();
				return $r;
			},
			$rows ?: array()
		);
	}

	public function set_status( int $id, string $status ): bool {
		$allowed = array( 'open', 'in_roadmap', 'done', 'dismissed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		$db = $this->db();
		return false !== $db->update(
			$this->table(),
			array( 'status' => $status ),
			array(
				'id'      => $id,
				'site_id' => $this->site_id(),
			)
		);
	}
}
