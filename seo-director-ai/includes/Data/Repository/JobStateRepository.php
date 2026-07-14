<?php
/**
 * Resumable job cursors and run log ({p}sda_job_state).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class JobStateRepository {

	/**
	 * @return array{cursor: array<mixed>, status: string, fail_count: int}|null
	 */
	public function get( string $job ): ?array {
		global $wpdb;

		$table = Schema::table( 'job_state' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT job_cursor, status, fail_count FROM {$table} WHERE site_id = %d AND job = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$job
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$cursor = json_decode( (string) $row['job_cursor'], true );

		return [
			'cursor'     => is_array( $cursor ) ? $cursor : [],
			'status'     => (string) $row['status'],
			'fail_count' => (int) $row['fail_count'],
		];
	}

	/**
	 * @param array<mixed>|null $cursor Null clears the cursor (job finished).
	 */
	public function put( string $job, ?array $cursor, string $status, ?string $error = null ): void {
		global $wpdb;

		$table = Schema::table( 'job_state' );
		$data  = [
			'site_id'     => get_current_blog_id(),
			'job'         => $job,
			'job_cursor'  => null === $cursor ? null : wp_json_encode( $cursor ),
			'status'      => $status,
			'last_run_at' => current_time( 'mysql', true ),
			'last_error'  => $error,
		];

		$existing = $this->get( $job );

		if ( null === $existing ) {
			$data['fail_count'] = null === $error ? 0 : 1;
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return;
		}

		$data['fail_count'] = null === $error ? 0 : $existing['fail_count'] + 1;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			$data,
			[
				'site_id' => get_current_blog_id(),
				'job'     => $job,
			]
		);
	}
}
