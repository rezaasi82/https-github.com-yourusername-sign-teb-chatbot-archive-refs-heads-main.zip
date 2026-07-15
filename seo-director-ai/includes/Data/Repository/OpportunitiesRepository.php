<?php
/**
 * Persists detector output ({p}sda_opportunities). A rescan upserts fresh
 * findings, refreshes still-valid ones, and marks vanished rows stale —
 * without touching user state (done/dismissed survive rescans).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class OpportunitiesRepository {

	/**
	 * @param array<int, array{entity_type: string, hash: string, label: string, secondary_label: string|null, score: float, est_traffic_gain: int, difficulty: int, data: array<string, mixed>}> $findings
	 */
	public function sync_detector( string $detector, array $findings ): void {
		global $wpdb;

		$table   = Schema::table( 'opportunities' );
		$site_id = get_current_blog_id();
		$now     = current_time( 'mysql', true );

		$fresh_hashes = [];

		foreach ( $findings as $finding ) {
			$fresh_hashes[] = strtoupper( $finding['hash'] );

			$sql = "INSERT INTO {$table}
					(site_id, detector, entity_type, entity_hash, entity_label, secondary_label, score, est_traffic_gain, difficulty, status, data, detected_at, refreshed_at)
				VALUES (%d, %s, %s, UNHEX(%s), %s, %s, %f, %d, %d, 'open', %s, %s, %s)
				ON DUPLICATE KEY UPDATE
					score = VALUES(score), est_traffic_gain = VALUES(est_traffic_gain),
					difficulty = VALUES(difficulty), data = VALUES(data), refreshed_at = VALUES(refreshed_at),
					status = IF(status = 'stale', 'open', status)";

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$sql,
					$site_id,
					$detector,
					$finding['entity_type'],
					$finding['hash'],
					$finding['label'],
					$finding['secondary_label'] ?? '',
					$finding['score'],
					$finding['est_traffic_gain'],
					$finding['difficulty'],
					wp_json_encode( $finding['data'] ),
					$now,
					$now
				)
			);
		}

		// Open findings this scan did not confirm go stale.
		if ( [] === $fresh_hashes ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'stale' WHERE site_id = %d AND detector = %s AND status = 'open'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$site_id,
					$detector
				)
			);
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $fresh_hashes ), 'UNHEX(%s)' ) );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"UPDATE {$table} SET status = 'stale' WHERE site_id = %d AND detector = %s AND status = 'open' AND entity_hash NOT IN ({$placeholders})",
				array_merge( [ $site_id, $detector ], $fresh_hashes )
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_open( int $limit = 50 ): array {
		global $wpdb;

		$table = Schema::table( 'opportunities' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, detector, entity_type, entity_label, secondary_label, score, est_traffic_gain, difficulty, status, data, refreshed_at
				FROM {$table} WHERE site_id = %d AND status IN ('open', 'in_roadmap') ORDER BY score DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static function ( array $r ) {
				$data = json_decode( (string) $r['data'], true );

				return [
					'id'               => (int) $r['id'],
					'detector'         => (string) $r['detector'],
					'entity_type'      => (string) $r['entity_type'],
					'label'            => (string) $r['entity_label'],
					'secondary_label'  => (string) $r['secondary_label'],
					'score'            => (float) $r['score'],
					'est_traffic_gain' => (int) $r['est_traffic_gain'],
					'difficulty'       => (int) $r['difficulty'],
					'status'           => (string) $r['status'],
					'data'             => is_array( $data ) ? $data : [],
					'refreshed_at'     => (string) $r['refreshed_at'],
				];
			},
			$rows ?: []
		);
	}

	public function set_status( int $id, string $status ): bool {
		global $wpdb;

		if ( ! in_array( $status, [ 'open', 'in_roadmap', 'done', 'dismissed' ], true ) ) {
			return false;
		}

		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'opportunities' ),
			[ 'status' => $status ],
			[
				'id'      => $id,
				'site_id' => get_current_blog_id(),
			]
		);
	}
}
