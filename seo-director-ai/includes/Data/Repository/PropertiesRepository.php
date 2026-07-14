<?php
/**
 * CRUD for {p}sda_properties (GSC properties / GA4 properties).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class PropertiesRepository {

	/**
	 * @return array<int, array{id: int, service: string, external_id: string, display_name: string, is_active: bool}>
	 */
	public function list( ?string $service = null ): array {
		global $wpdb;

		$table = Schema::table( 'properties' );

		if ( null !== $service ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id, service, external_id, display_name, is_active FROM {$table} WHERE site_id = %d AND service = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					get_current_blog_id(),
					$service
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id, service, external_id, display_name, is_active FROM {$table} WHERE site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					get_current_blog_id()
				),
				ARRAY_A
			);
		}

		return array_map(
			static fn( array $r ) => [
				'id'           => (int) $r['id'],
				'service'      => (string) $r['service'],
				'external_id'  => (string) $r['external_id'],
				'display_name' => (string) $r['display_name'],
				'is_active'    => (bool) $r['is_active'],
			],
			$rows ?: []
		);
	}

	/**
	 * The single active property for a service, or null.
	 *
	 * @return array{id: int, external_id: string, display_name: string}|null
	 */
	public function active( string $service ): ?array {
		foreach ( $this->list( $service ) as $property ) {
			if ( $property['is_active'] ) {
				return $property;
			}
		}

		return null;
	}

	/**
	 * Replace the candidate list for a service (called after OAuth discovery).
	 *
	 * @param array<int, array{external_id: string, display_name: string}> $properties Discovered properties.
	 */
	public function sync_candidates( int $connection_id, string $service, array $properties ): void {
		global $wpdb;

		$table    = Schema::table( 'properties' );
		$existing = $this->list( $service );
		$keep     = array_column( $properties, 'external_id' );

		foreach ( $existing as $row ) {
			if ( ! in_array( $row['external_id'], $keep, true ) ) {
				$wpdb->delete( $table, [ 'id' => $row['id'] ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}

		$known = array_column( $existing, 'external_id' );

		foreach ( $properties as $property ) {
			if ( in_array( $property['external_id'], $known, true ) ) {
				continue;
			}
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				[
					'site_id'       => get_current_blog_id(),
					'connection_id' => $connection_id,
					'service'       => $service,
					'external_id'   => $property['external_id'],
					'display_name'  => $property['display_name'],
					'is_active'     => 0,
				]
			);
		}
	}

	/**
	 * Mark one property active for a service (deactivating the rest).
	 */
	public function activate( string $service, int $property_id ): bool {
		global $wpdb;

		$table = Schema::table( 'properties' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$table} SET is_active = 0 WHERE site_id = %d AND service = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$service
			)
		);

		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			[ 'is_active' => 1 ],
			[
				'id'      => $property_id,
				'site_id' => get_current_blog_id(),
				'service' => $service,
			]
		);
	}

	public function delete_for_service( string $service ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'properties' ),
			[
				'site_id' => get_current_blog_id(),
				'service' => $service,
			]
		);
	}
}
