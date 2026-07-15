<?php
/**
 * Alerts with dedup fingerprints ({p}sda_alerts).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class AlertsRepository extends BaseRepository {

	protected const TABLE = 'alerts';

	/**
	 * Raise an alert; the (site, fingerprint, status) unique key makes re-raises no-ops
	 * while an identical alert is still active.
	 *
	 * @param array<string, mixed> $data
	 */
	public function raise( string $rule, string $severity, string $message, string $fingerprint_source, ?string $entity_label = null, array $data = array() ): void {
		$db = $this->db();
		$db->query(
			$db->prepare(
				"INSERT IGNORE INTO {$this->table()}
					(site_id, rule, severity, entity_label, message, fingerprint, status, data, raised_at)
				 VALUES (%d, %s, %s, %s, %s, %s, 'active', %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$rule,
				$severity,
				$entity_label,
				$message,
				$this->bin_hash( $rule . '|' . $fingerprint_source ),
				(string) wp_json_encode( $data ),
				current_time( 'mysql', true )
			)
		);

		if ( $db->rows_affected > 0 ) {
			/**
			 * Fires when a new alert is raised (post-dedup).
			 *
			 * @param string $rule     Rule slug.
			 * @param string $severity critical|high|medium|low.
			 * @param string $message  Human-readable message.
			 */
			do_action( 'sda_alert_raised', $rule, $severity, $message );
		}
	}

	/** @return array<int, array<string, mixed>> */
	public function list_active( int $limit = 50 ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT id, rule, severity, entity_label, message, status, raised_at
				 FROM {$this->table()}
				 WHERE site_id = %d AND status = 'active'
				 ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low'), raised_at DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$limit
			),
			ARRAY_A
		);
		return array_map(
			static function ( array $r ): array {
				$r['id'] = (int) $r['id'];
				return $r;
			},
			$rows ?: array()
		);
	}

	public function set_status( int $id, string $status, ?string $snoozed_until = null ): bool {
		if ( ! in_array( $status, array( 'active', 'acknowledged', 'snoozed', 'resolved' ), true ) ) {
			return false;
		}
		$fields = array( 'status' => $status );
		if ( 'snoozed' === $status ) {
			$fields['snoozed_until'] = $snoozed_until;
		}
		if ( 'resolved' === $status ) {
			$fields['resolved_at'] = current_time( 'mysql', true );
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
