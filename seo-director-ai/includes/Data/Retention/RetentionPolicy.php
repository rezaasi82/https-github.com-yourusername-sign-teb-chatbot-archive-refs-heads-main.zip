<?php
/**
 * Prunes raw daily rows past the retention horizon (rollups keep the history).
 * Deletes in bounded batches to stay friendly to shared hosts.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Retention;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Options;

final class RetentionPolicy {

	private const BATCH = 5000;

	/** Tables subject to daily-grain pruning. */
	private const PRUNABLE = array( 'gsc_query_daily', 'gsc_page_daily', 'gsc_dimension_daily', 'ga4_daily' );

	public function __construct( private readonly Options $options ) {}

	/**
	 * Run one pruning pass. Returns rows deleted (0 = nothing left to prune).
	 */
	public function prune(): int {
		global $wpdb;

		$months = (int) $this->options->get( 'retention_months', 16 );
		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );
		$total  = 0;

		foreach ( self::PRUNABLE as $short ) {
			$table = $wpdb->prefix . 'sda_' . $short;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from internal whitelist.
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE date < %s LIMIT %d", $cutoff, self::BATCH ) );
			if ( is_int( $deleted ) ) {
				$total += $deleted;
			}
		}

		// PSI audits: 24-month horizon per DB doc.
		$psi_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-24 months' ) );
		$psi_table  = $wpdb->prefix . 'sda_psi_audits';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$psi_table} WHERE audited_at < %s LIMIT %d", $psi_cutoff, self::BATCH ) );
		if ( is_int( $deleted ) ) {
			$total += $deleted;
		}

		return $total;
	}
}
