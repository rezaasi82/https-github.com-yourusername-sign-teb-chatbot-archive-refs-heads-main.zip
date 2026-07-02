<?php
/**
 * SignTeb Medical Core — Deactivator
 * پاکسازی rewrite rules و cron schedules هنگام غیرفعال‌سازی
 */
declare( strict_types=1 );
namespace STMC;
defined( 'ABSPATH' ) || exit;

final class Deactivator {
	public static function deactivate(): void {
		flush_rewrite_rules();

		$timestamp = wp_next_scheduled( 'stmc_sms_reminder_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'stmc_sms_reminder_cron' );
		}
	}
}
