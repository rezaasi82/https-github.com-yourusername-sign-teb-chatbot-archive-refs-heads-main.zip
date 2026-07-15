<?php
/**
 * Alert persistence with fingerprint dedup and auto-resolve
 * ({p}sda_alerts). One active row per condition fingerprint.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class AlertsRepository {

	/**
	 * Raise an alert. Returns true when a NEW alert row was created
	 * (deduped repeats return false so channels don't re-notify).
	 *
	 * @param array<string, mixed> $data Evidence payload.
	 */
	public function raise( string $rule, string $severity, string $message, string $fingerprint_hex, ?string $entity_label = null, array $data = [] ): bool {
		global $wpdb;

		$table = Schema::table( 'alerts' );

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE site_id = %d AND fingerprint = UNHEX(%s) AND status IN ('active', 'acknowledged', 'snoozed')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$fingerprint_hex
			)
		);

		if ( $existing ) {
			return false;
		}

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"INSERT INTO {$table} (site_id, rule, severity, entity_label, message, fingerprint, status, data, raised_at)
				VALUES (%d, %s, %s, %s, %s, UNHEX(%s), 'active', %s, %s)",
				get_current_blog_id(),
				$rule,
				$severity,
				$entity_label ?? '',
				$message,
				$fingerprint_hex,
				wp_json_encode( $data ),
				current_time( 'mysql', true )
			)
		);

		return true;
	}

	/**
	 * Auto-resolve alerts of a rule whose fingerprints are no longer firing.
	 *
	 * @param string[] $active_fingerprints_hex Fingerprints the rule currently reports.
	 */
	public function resolve_missing( string $rule, array $active_fingerprints_hex ): void {
		global $wpdb;

		$table = Schema::table( 'alerts' );
		$now   = current_time( 'mysql', true );

		if ( [] === $active_fingerprints_hex ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'resolved', resolved_at = %s WHERE site_id = %d AND rule = %s AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$now,
					get_current_blog_id(),
					$rule
				)
			);
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $active_fingerprints_hex ), 'UNHEX(%s)' ) );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"UPDATE {$table} SET status = 'resolved', resolved_at = %s WHERE site_id = %d AND rule = %s AND status = 'active' AND fingerprint NOT IN ({$placeholders})",
				array_merge( [ $now, get_current_blog_id(), $rule ], $active_fingerprints_hex )
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list( string $status = 'active', int $limit = 100 ): array {
		global $wpdb;

		$table = Schema::table( 'alerts' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, rule, severity, entity_label, message, status, raised_at, resolved_at
				FROM {$table} WHERE site_id = %d AND status = %s
				ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low'), raised_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$status,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $r ) => [
				'id'           => (int) $r['id'],
				'rule'         => (string) $r['rule'],
				'severity'     => (string) $r['severity'],
				'entity_label' => (string) $r['entity_label'],
				'message'      => (string) $r['message'],
				'status'       => (string) $r['status'],
				'raised_at'    => (string) $r['raised_at'],
				'resolved_at'  => $r['resolved_at'] ? (string) $r['resolved_at'] : null,
			],
			$rows ?: []
		);
	}

	public function set_status( int $id, string $status ): bool {
		global $wpdb;

		if ( ! in_array( $status, [ 'acknowledged', 'snoozed', 'resolved' ], true ) ) {
			return false;
		}

		$update = [ 'status' => $status ];
		if ( 'resolved' === $status ) {
			$update['resolved_at'] = current_time( 'mysql', true );
		}
		if ( 'snoozed' === $status ) {
			$update['snoozed_until'] = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) );
		}

		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'alerts' ),
			$update,
			[
				'id'      => $id,
				'site_id' => get_current_blog_id(),
			]
		);
	}

	/**
	 * @return array<string, int> severity => active count.
	 */
	public function active_counts(): array {
		global $wpdb;

		$table = Schema::table( 'alerts' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT severity, COUNT(*) AS total FROM {$table} WHERE site_id = %d AND status = 'active' GROUP BY severity", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			),
			ARRAY_A
		);

		$counts = [ 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0 ];
		foreach ( $rows ?: [] as $row ) {
			$counts[ (string) $row['severity'] ] = (int) $row['total'];
		}

		return $counts;
	}
}
