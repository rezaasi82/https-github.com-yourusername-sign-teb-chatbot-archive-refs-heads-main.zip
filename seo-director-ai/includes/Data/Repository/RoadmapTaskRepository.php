<?php
/**
 * Roadmap tasks ({p}sda_roadmap_tasks).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class RoadmapTaskRepository extends BaseRepository {

	protected const TABLE = 'roadmap_tasks';

	/**
	 * Insert a task only if no open task already references the same opportunity
	 * (prevents duplicate roadmap entries on repeated generation).
	 *
	 * @param array<string, mixed> $task
	 * @return int Inserted task id, or 0 when skipped as a duplicate.
	 */
	public function insert_if_absent( array $task ): int {
		$db = $this->db();

		if ( ! empty( $task['opportunity_id'] ) ) {
			$exists = $db->get_var(
				$db->prepare(
					"SELECT id FROM {$this->table()} WHERE site_id = %d AND opportunity_id = %d AND status IN ('todo','in_progress') LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$this->site_id(),
					(int) $task['opportunity_id']
				)
			);
			if ( $exists ) {
				return 0;
			}
		}

		$now = current_time( 'mysql', true );
		$db->insert(
			$this->table(),
			array(
				'site_id'         => $this->site_id(),
				'roadmap_scope'   => (string) ( $task['roadmap_scope'] ?? 'monthly' ),
				'period_start'    => (string) ( $task['period_start'] ?? gmdate( 'Y-m-01' ) ),
				'title'           => (string) $task['title'],
				'description'     => $task['description'] ?? null,
				'category'        => (string) ( $task['category'] ?? 'content' ),
				'impact'          => (int) ( $task['impact'] ?? 5 ),
				'difficulty'      => (int) ( $task['difficulty'] ?? 5 ),
				'est_hours'       => $task['est_hours'] ?? null,
				'priority'        => (int) ( $task['priority'] ?? 0 ),
				'expected_result' => $task['expected_result'] ?? null,
				'status'          => 'todo',
				'opportunity_id'  => $task['opportunity_id'] ?? null,
				'insight_id'      => $task['insight_id'] ?? null,
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		return (int) $db->insert_id;
	}

	/**
	 * Tasks grouped by kanban column (status).
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function board( string $scope = 'monthly' ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT id, title, description, category, impact, difficulty, est_hours, priority,
						expected_result, status, opportunity_id, completed_at
				 FROM {$this->table()}
				 WHERE site_id = %d AND roadmap_scope = %s
				 ORDER BY priority DESC, impact DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$scope
			),
			ARRAY_A
		);

		$board = array( 'todo' => array(), 'in_progress' => array(), 'done' => array(), 'dismissed' => array() );
		foreach ( $rows ?: array() as $row ) {
			$row['id']         = (int) $row['id'];
			$row['impact']     = (int) $row['impact'];
			$row['difficulty'] = (int) $row['difficulty'];
			$row['priority']   = (int) $row['priority'];
			$status            = (string) $row['status'];
			if ( isset( $board[ $status ] ) ) {
				$board[ $status ][] = $row;
			}
		}
		return $board;
	}

	public function set_status( int $id, string $status ): bool {
		if ( ! in_array( $status, array( 'todo', 'in_progress', 'done', 'dismissed' ), true ) ) {
			return false;
		}
		$fields = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( 'done' === $status ) {
			$fields['completed_at'] = current_time( 'mysql', true );
		}
		$db = $this->db();
		return false !== $db->update(
			$this->table(),
			$fields,
			array(
				'id'      => $id,
				'site_id' => $this->site_id(),
			)
		);
	}
}
