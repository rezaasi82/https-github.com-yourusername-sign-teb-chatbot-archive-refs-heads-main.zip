<?php
/**
 * Versioned DB migrations keyed on the sda_db_version option.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Upgrader {

	/**
	 * Ordered migration steps: version => callable.
	 * Each step must be idempotent; dbDelta re-run covers additive schema changes.
	 *
	 * @var array<string, callable(): void>
	 */
	private array $steps = array();

	public function maybe_upgrade(): void {
		$installed = (string) get_option( 'sda_db_version', '' );
		if ( SDA_DB_VERSION === $installed ) {
			return;
		}

		// Fresh install (activation hook missed, e.g. after manual FTP upload).
		if ( '' === $installed ) {
			Activator::create_tables();
			Capabilities::add_caps();
			update_option( 'sda_db_version', SDA_DB_VERSION );
			return;
		}

		foreach ( $this->steps as $version => $step ) {
			if ( version_compare( $installed, $version, '<' ) ) {
				$step();
			}
		}

		// Additive schema drift is always safe to re-apply.
		Activator::create_tables();
		update_option( 'sda_db_version', SDA_DB_VERSION );
	}
}
