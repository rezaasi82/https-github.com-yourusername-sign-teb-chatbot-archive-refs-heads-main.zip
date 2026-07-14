<?php
/**
 * Prunes raw daily rows past the retention horizon (rollups keep the
 * long-term history). Runs inside the weekly pipeline in bounded deletes
 * so it never locks big tables for long.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Retention;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class RetentionPolicy {

	private const DAILY_RETENTION_MONTHS = 16;
	private const PSI_RETENTION_MONTHS   = 24;
	private const DELETE_BATCH           = 5000;

	public function prune(): void {
		$daily_cutoff = gmdate( 'Y-m-d', strtotime( '-' . self::DAILY_RETENTION_MONTHS . ' months' ) );
		$psi_cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::PSI_RETENTION_MONTHS . ' months' ) );

		foreach ( [ 'gsc_query_daily', 'gsc_page_daily', 'ga4_daily' ] as $table ) {
			$this->bounded_delete( Schema::table( $table ), 'date', $daily_cutoff );
		}

		$this->bounded_delete( Schema::table( 'psi_audits' ), 'audited_at', $psi_cutoff );
	}

	private function bounded_delete( string $table, string $column, string $cutoff ): void {
		global $wpdb;

		do {
			$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE {$column} < %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$cutoff,
					self::DELETE_BATCH
				)
			);
		} while ( self::DELETE_BATCH === $deleted );
	}
}
