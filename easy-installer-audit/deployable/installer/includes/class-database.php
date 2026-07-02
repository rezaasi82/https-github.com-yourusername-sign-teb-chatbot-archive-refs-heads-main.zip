<?php
/**
 * بررسی اتصال دیتابیس
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

function ezi_test_database_connection( array $post ): array {
	$host   = trim( (string) ( $post['db_host'] ?? 'localhost' ) );
	$name   = trim( (string) ( $post['db_name'] ?? '' ) );
	$user   = trim( (string) ( $post['db_user'] ?? '' ) );
	$pass   = (string) ( $post['db_pass'] ?? '' );
	$prefix = trim( (string) ( $post['db_prefix'] ?? 'wp_' ) );

	if ( ! $name || ! $user ) {
		return ezi_json( false, 'نام دیتابیس و نام کاربری را وارد کنید.' );
	}

	if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $prefix ) ) {
		return ezi_json( false, 'پیشوند جدول باید فقط شامل حروف انگلیسی، عدد، و _ باشد.' );
	}

	if ( ! extension_loaded( 'mysqli' ) ) {
		return ezi_json( false, 'افزونه mysqli در سرور فعال نیست.' );
	}

	mysqli_report( MYSQLI_REPORT_OFF );
	$conn = @mysqli_connect( $host, $user, $pass );

	if ( ! $conn ) {
		return ezi_json( false, 'اتصال به MySQL ناموفق بود. میزبان/نام‌کاربری/رمز را بررسی کنید: ' . mysqli_connect_error() );
	}

	$db_select = @mysqli_select_db( $conn, $name );

	if ( ! $db_select ) {
		// تلاش برای ساخت دیتابیس در صورت عدم وجود
		$created = @mysqli_query( $conn, "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
		if ( ! $created ) {
			mysqli_close( $conn );
			return ezi_json( false, 'دیتابیس یافت نشد و امکان ساخت آن نیز نبود. لطفاً از cPanel دیتابیس را از قبل بسازید.' );
		}
	}

	mysqli_close( $conn );

	// ذخیره موقت اطلاعات برای مرحله بعد (نصب وردپرس)
	$_SESSION['ezi_db'] = compact( 'host', 'name', 'user', 'pass', 'prefix' );

	return ezi_json( true, 'اتصال به دیتابیس با موفقیت برقرار شد.' );
}
