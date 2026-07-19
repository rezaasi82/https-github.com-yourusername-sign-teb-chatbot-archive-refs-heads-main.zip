<?php
/**
 * Deactivation: unschedule jobs, keep all data.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'sda_daily_sync' );
		wp_clear_scheduled_hook( 'sda_hourly_alerts' );
		wp_clear_scheduled_hook( 'sda_weekly_pipeline' );
		wp_clear_scheduled_hook( 'sda_run_chunk' );
	}
}
