<?php
/**
 * فعال‌سازی یک افزونه در یک پردازش PHP مجزا (CLI)
 *
 * این اسکریپت توسط ezi_safe_activate_plugin() از طریق proc_open/exec
 * اجرا می‌شود. هدف: اگر کد افزونه باعث PHP Fatal Error شود، این خطا
 * فقط همین پردازش جدا را می‌کشد — نه کل نصب‌کننده یا سایت اصلی را.
 *
 * استفاده (از CLI):
 *   php activate-one-plugin.php "plugin-folder/plugin-file.php"
 *
 * خروجی: همیشه یک خط JSON معتبر — حتی اگر چیزی Fatal شود (به لطف
 * register_shutdown_function در پایین همین فایل).
 */

// این اسکریپت باید فقط از طریق CLI اجرا شود، نه از طریق وب
if ( 'cli' !== PHP_SAPI ) {
	echo json_encode( [ 'ok' => false, 'error' => 'این اسکریپت فقط از طریق CLI قابل اجرا است.' ] );
	exit( 1 );
}

error_reporting( E_ALL );
ini_set( 'display_errors', '1' ); // به stdout/stderr می‌رود، نه به یک پاسخ HTTP

$relative_path = $argv[1] ?? '';

if ( ! $relative_path ) {
	echo json_encode( [ 'ok' => false, 'error' => 'مسیر افزونه مشخص نشده است.' ] );
	exit( 1 );
}

// مسیر روت وردپرس: این فایل در installer/cli/ است → دو پوشه بالاتر
define( 'EZI_WP_ROOT', dirname( __DIR__, 2 ) );

// تضمین خروجی JSON معتبر حتی در صورت بروز PHP Fatal Error
register_shutdown_function( static function () use ( $relative_path ): void {
	$error = error_get_last();
	$is_fatal = $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true );

	if ( $is_fatal ) {
		echo json_encode( [
			'ok'    => false,
			'error' => sprintf(
				'PHP Fatal Error در افزونه «%s»: %s (فایل: %s، خط: %d)',
				$relative_path,
				$error['message'],
				basename( (string) $error['file'] ),
				$error['line']
			),
		] );
	}
} );

if ( ! file_exists( EZI_WP_ROOT . '/wp-load.php' ) ) {
	echo json_encode( [ 'ok' => false, 'error' => 'فایل wp-load.php یافت نشد.' ] );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once EZI_WP_ROOT . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// محافظت در برابر هاست‌هایی که تابع mail() را غیرفعال کرده‌اند
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

$result = activate_plugin( $relative_path );

if ( is_wp_error( $result ) ) {
	echo json_encode( [ 'ok' => false, 'error' => $result->get_error_message() ] );
	exit( 0 );
}

echo json_encode( [ 'ok' => true, 'error' => '' ] );
