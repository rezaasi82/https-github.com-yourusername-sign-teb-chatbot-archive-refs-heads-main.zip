<?php
/**
 * Resumable job cursors + run log ({p}sda_job_state).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class JobStateRepository extends BaseRepository {

	protected const TABLE = 'job_state';

	/** @return array{status: string, cursor: array<string, mixed>, fail_count: int} */
	public function get( string $job ): array {
		$db  = $this->db();
		$row = $db->get_row(
			$db->prepare(
				"SELECT status, cursor_data, fail_count FROM {$this->table()} WHERE site_id = %d AND job = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$job
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return array( 'status' => 'idle', 'cursor' => array(), 'fail_count' => 0 );
		}
		$cursor = json_decode( (string) $row['cursor_data'], true );
		return array(
			'status'     => (string) $row['status'],
			'cursor'     => is_array( $cursor ) ? $cursor : array(),
			'fail_count' => (int) $row['fail_count'],
		);
	}

	/** @param array<string, mixed> $cursor */
	public function save( string $job, string $status, array $cursor = array(), ?string $error = null ): void {
		$db  = $this->db();
		$now = current_time( 'mysql', true );
		$db->query(
			$db->prepare(
				"INSERT INTO {$this->table()} (site_id, job, cursor_data, status, last_run_at, last_error, fail_count)
				 VALUES (%d, %s, %s, %s, %s, %s, %d)
				 ON DUPLICATE KEY UPDATE cursor_data = VALUES(cursor_data), status = VALUES(status),
					last_run_at = VALUES(last_run_at), last_error = VALUES(last_error),
					fail_count = IF(VALUES(status) = 'failed', fail_count + 1, 0)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$job,
				(string) wp_json_encode( $cursor ),
				$status,
				$now,
				$error,
				'failed' === $status ? 1 : 0
			)
		);
	}

	/** @return array<int, array<string, mixed>> */
	public function all(): array {
		$db = $this->db();
		return $db->get_results(
			$db->prepare(
				"SELECT job, status, last_run_at, last_error, fail_count FROM {$this->table()} WHERE site_id = %d ORDER BY job", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id()
			),
			ARRAY_A
		) ?: array();
	}
}
