<?php
/**
 * Activation routine: create tables, capabilities, defaults, schedules.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			$site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single_site();
				restore_current_blog();
			}
			return;
		}

		self::activate_single_site();
	}

	private static function activate_single_site(): void {
		self::create_tables();
		Capabilities::add();

		add_option( 'sda_db_version', SDA_DB_VERSION );
		add_option( 'sda_installed_at', time() );
		add_option( 'sda_settings', self::default_settings() );

		// Flag consumed on the next admin page load to redirect into the setup wizard.
		add_option( 'sda_activation_redirect', 1 );
	}

	public static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::tables() as $ddl ) {
			dbDelta( $ddl );
		}

		update_option( 'sda_db_version', SDA_DB_VERSION );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return [
			'ai_provider'          => '',           // '' | openai | claude | gemini
			'ai_auto_explain'      => true,
			'ai_monthly_token_cap' => 500000,
			'gsc_query_rows_cap'   => 5000,
			'gsc_page_rows_cap'    => 2000,
			'report_day'           => 'saturday',
			'report_time'          => '07:00',
			'alert_email'          => get_option( 'admin_email' ),
			'delete_data_on_uninstall' => false,
		];
	}
}
