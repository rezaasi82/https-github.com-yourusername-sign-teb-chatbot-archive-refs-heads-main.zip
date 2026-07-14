<?php
/**
 * SignTeb Setup Wizard — Uninstall
 *
 * وردپرس این فایل را هنگام «حذف افزونه» (نه غیرفعال‌سازی) اجرا می‌کند.
 *
 * سیاست امن: اینجا فقط داده‌های *خودِ افزونه* (آپشن‌های وضعیت و transientها)
 * پاک می‌شوند تا حذف افزونه باعث ناپدیدشدن ناگهانی محتوای سایت نشود. حذف کاملِ
 * محتوای دمو فقط از طریق «صفحه‌ی تأیید حذف داده‌های دمو» در خودِ ویزارد انجام
 * می‌شود (تصمیم صریح کاربر).
 *
 * @package SignTeb_Wizard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$stwiz_options = [
	'stwiz_setup_state', 'stwiz_completed_steps', 'stwiz_demo_installed',
	'stwiz_setup_complete', 'stwiz_selected_demo',
	'stwiz_step_welcome', 'stwiz_step_brand', 'stwiz_step_clinic',
	'stwiz_step_contact', 'stwiz_step_demo',
];

foreach ( $stwiz_options as $stwiz_opt ) {
	delete_option( $stwiz_opt );
}

delete_transient( 'stwiz_redirect' );

// پاک‌کردن transientهای باقی‌مانده‌ی افزونه (در صورت وجود).
global $wpdb;
if ( isset( $wpdb ) ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_stwiz_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_stwiz_' ) . '%'
		)
	);
}
