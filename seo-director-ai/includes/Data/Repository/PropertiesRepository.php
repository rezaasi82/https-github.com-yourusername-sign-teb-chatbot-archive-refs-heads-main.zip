<?php
/**
 * GSC/GA4 property records ({p}sda_properties).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class PropertiesRepository extends BaseRepository {

	protected const TABLE = 'properties';

	/** @return array<int, array{id: int, service: string, external_id: string, display_name: string, is_active: bool}> */
	public function list_for_service( string $service ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT id, service, external_id, display_name, is_active FROM {$this->table()}
				 WHERE site_id = %d AND service = %s ORDER BY display_name", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$service
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $r ) => array(
				'id'           => (int) $r['id'],
				'service'      => (string) $r['service'],
				'external_id'  => (string) $r['external_id'],
				'display_name' => (string) $r['display_name'],
				'is_active'    => (bool) $r['is_active'],
			),
			$rows ?: array()
		);
	}

	public function active_property( string $service ): ?object {
		$db = $this->db();
		return $db->get_row(
			$db->prepare(
				"SELECT * FROM {$this->table()} WHERE site_id = %d AND service = %s AND is_active = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$service
			)
		) ?: null;
	}

	/**
	 * Register a discovered property (inactive by default; the admin picks one).
	 */
	public function register( int $connection_id, string $service, string $external_id, string $display_name ): int {
		$db = $this->db();
		$db->query(
			$db->prepare(
				"INSERT INTO {$this->table()} (site_id, connection_id, service, external_id, display_name, is_active)
				 VALUES (%d, %d, %s, %s, %s, 0)
				 ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), connection_id = VALUES(connection_id)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$connection_id,
				$service,
				$external_id,
				$display_name
			)
		);
		$row = $db->get_var(
			$db->prepare(
				"SELECT id FROM {$this->table()} WHERE site_id = %d AND service = %s AND external_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$service,
				$external_id
			)
		);
		return (int) $row;
	}

	/**
	 * Make one property active per service (single-property model in v1).
	 */
	public function activate( int $property_id, string $service ): bool {
		$db = $this->db();
		$db->query(
			$db->prepare(
				"UPDATE {$this->table()} SET is_active = 0 WHERE site_id = %d AND service = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$service
			)
		);
		$updated = $db->update(
			$this->table(),
			array( 'is_active' => 1 ),
			array(
				'id'      => $property_id,
				'site_id' => $this->site_id(),
				'service' => $service,
			)
		);
		return (bool) $updated;
	}
}
