<?php
/**
 * Versioned database migrations. Each step upgrades from (n-1) to n.
 * dbDelta against the canonical schema handles additive changes; destructive
 * or data migrations get an explicit step method.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Upgrader {

	public function maybe_upgrade(): void {
		$installed = (int) get_option( 'sda_db_version', 0 );
		$target    = (int) SDA_DB_VERSION;

		if ( $installed >= $target ) {
			return;
		}

		// Cheap cross-request lock so two admin hits don't migrate concurrently.
		if ( ! add_option( 'sda_upgrading', time(), '', false ) ) {
			$started = (int) get_option( 'sda_upgrading' );
			if ( $started > time() - 5 * MINUTE_IN_SECONDS ) {
				return;
			}
			update_option( 'sda_upgrading', time(), false );
		}

		try {
			for ( $step = $installed + 1; $step <= $target; $step++ ) {
				$method = 'upgrade_to_' . $step;
				if ( method_exists( $this, $method ) ) {
					$this->{$method}();
				}
				update_option( 'sda_db_version', $step );
			}
		} finally {
			delete_option( 'sda_upgrading' );
		}
	}

	private function upgrade_to_1(): void {
		Activator::create_tables();
	}

	/**
	 * Adds the agency_sites.pair_key_cipher column (Agency module). dbDelta
	 * against the canonical schema applies the additive change idempotently.
	 */
	private function upgrade_to_2(): void {
		Activator::create_tables();
		Capabilities::add(); // Ensure manage_sda_clients exists on upgrade.
	}
}
