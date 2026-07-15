<?php
/**
 * Roadmap task persistence ({p}sda_roadmap_tasks). Tasks carry provenance
 * (opportunity_id / insight_id) and a completion feedback loop.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class TaskRepository {

	/**
	 * Insert a generated task unless an open task already exists for the same
	 * opportunity in this roadmap period (avoids duplicates on regeneration).
	 *
	 * @param array<string, mixed> $task
	 */
	public function insert_unique( array $task ): void {
		global $wpdb;

		$table = Schema::table( 'roadmap_tasks' );

		if ( ! empty( $task['opportunity_id'] ) ) {
			$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE site_id = %d AND opportunity_id = %d AND status IN ('todo','in_progress')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					get_current_blog_id(),
					(int) $task['opportunity_id']
				)
			);
			if ( $exists ) {
				return;
			}
		}

		$now = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			[
				'site_id'         => get_current_blog_id(),
				'roadmap_scope'   => (string) ( $task['scope'] ?? 'monthly' ),
				'period_start'    => (string) ( $task['period_start'] ?? gmdate( 'Y-m-01' ) ),
				'title'           => (string) $task['title'],
				'description'     => (string) ( $task['description'] ?? '' ),
				'category'        => (string) ( $task['category'] ?? 'content' ),
				'impact'          => (int) ( $task['impact'] ?? 5 ),
				'difficulty'      => (int) ( $task['difficulty'] ?? 5 ),
				'est_hours'       => $task['est_hours'] ?? null,
				'priority'        => (int) ( $task['priority'] ?? 50 ),
				'expected_result' => (string) ( $task['expected_result'] ?? '' ),
				'status'          => 'todo',
				'opportunity_id'  => $task['opportunity_id'] ?? null,
				'insight_id'      => $task['insight_id'] ?? null,
				'created_at'      => $now,
				'updated_at'      => $now,
			]
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list( string $scope = 'monthly' ): array {
		global $wpdb;

		$table = Schema::table( 'roadmap_tasks' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, roadmap_scope, title, description, category, impact, difficulty, est_hours, priority, expected_result, status, measured_result
				FROM {$table} WHERE site_id = %d AND roadmap_scope = %s ORDER BY FIELD(status,'in_progress','todo','done','dismissed'), priority DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$scope
			),
			ARRAY_A
		);

		return array_map(
			static function ( array $r ) {
				$measured = json_decode( (string) $r['measured_result'], true );

				return [
					'id'              => (int) $r['id'],
					'scope'           => (string) $r['roadmap_scope'],
					'title'           => (string) $r['title'],
					'description'     => (string) $r['description'],
					'category'        => (string) $r['category'],
					'impact'          => (int) $r['impact'],
					'difficulty'      => (int) $r['difficulty'],
					'est_hours'       => null !== $r['est_hours'] ? (float) $r['est_hours'] : null,
					'priority'        => (int) $r['priority'],
					'expected_result' => (string) $r['expected_result'],
					'status'          => (string) $r['status'],
					'measured_result' => is_array( $measured ) ? $measured : null,
				];
			},
			$rows ?: []
		);
	}

	public function set_status( int $id, string $status, ?int $owner = null ): bool {
		global $wpdb;

		if ( ! in_array( $status, [ 'todo', 'in_progress', 'done', 'dismissed' ], true ) ) {
			return false;
		}

		$data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		];
		if ( 'done' === $status ) {
			$data['completed_at'] = current_time( 'mysql', true );
		}
		if ( null !== $owner ) {
			$data['owner_user_id'] = $owner;
		}

		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'roadmap_tasks' ),
			$data,
			[
				'id'      => $id,
				'site_id' => get_current_blog_id(),
			]
		);
	}
}
