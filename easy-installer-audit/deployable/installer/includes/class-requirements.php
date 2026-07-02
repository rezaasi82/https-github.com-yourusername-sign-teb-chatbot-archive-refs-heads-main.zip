<?php
/**
 * بررسی پیش‌نیازهای سرور
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

/**
 * @return array{checks: array, all_pass: bool}
 */
function ezi_check_requirements(): array {
	$checks = [];

	// PHP Version
	$php_ok = version_compare( PHP_VERSION, '8.1', '>=' );
	$checks[] = [
		'label'   => 'نسخه PHP',
		'value'   => PHP_VERSION,
		'pass'    => $php_ok,
		'detail'  => $php_ok ? 'مناسب است' : 'حداقل نسخه ۸.۱ نیاز است',
		'fatal'   => true,
	];

	// Extensions
	$extensions = [
		'mysqli'   => 'اتصال به دیتابیس MySQL',
		'zip'      => 'استخراج فایل‌های نصبی',
		'curl'     => 'دانلود فایل‌ها (یا allow_url_fopen)',
		'mbstring' => 'پردازش متن فارسی/عربی',
		'gd'       => 'پردازش تصاویر',
	];

	foreach ( $extensions as $ext => $desc ) {
		$loaded = extension_loaded( $ext );
		// curl یا allow_url_fopen — هرکدام کافی است
		if ( 'curl' === $ext && ! $loaded ) {
			$loaded = (bool) ini_get( 'allow_url_fopen' );
		}
		$checks[] = [
			'label'  => 'افزونه PHP: ' . $ext,
			'value'  => $loaded ? 'فعال' : 'غیرفعال',
			'pass'   => $loaded,
			'detail' => $desc,
			'fatal'  => in_array( $ext, [ 'mysqli', 'zip' ], true ),
		];
	}

	// Memory limit
	$memory_limit = ini_get( 'memory_limit' );
	$memory_bytes = ezi_parse_size( (string) $memory_limit );
	$memory_ok    = -1 === $memory_bytes || $memory_bytes >= 128 * 1024 * 1024;
	$checks[] = [
		'label'  => 'حافظه مجاز PHP (memory_limit)',
		'value'  => $memory_limit,
		'pass'   => $memory_ok,
		'detail' => $memory_ok ? 'کافی است' : 'حداقل 128M پیشنهاد می‌شود',
		'fatal'  => false,
	];

	// Writable root
	$writable = is_writable( EZI_ROOT );
	$checks[] = [
		'label'  => 'مجوز نوشتن در پوشه اصلی',
		'value'  => $writable ? 'دارد' : 'ندارد',
		'pass'   => $writable,
		'detail' => $writable ? 'سرور می‌تواند فایل بسازد' : 'دسترسی پوشه را روی 755 تنظیم کنید',
		'fatal'  => true,
	];

	// max_execution_time
	$max_exec = (int) ini_get( 'max_execution_time' );
	$set_time_limit_works = function_exists( 'set_time_limit' )
		&& ! in_array( 'set_time_limit', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true );
	$exec_ok  = 0 === $max_exec || $max_exec >= 60 || $set_time_limit_works;
	$checks[] = [
		'label'  => 'حداکثر زمان اجرا (max_execution_time)',
		'value'  => 0 === $max_exec ? 'نامحدود' : $max_exec . ' ثانیه',
		'pass'   => $exec_ok,
		'detail' => $exec_ok
			? ( $set_time_limit_works ? 'کافی است (قابل تمدید توسط نصب‌کننده)' : 'کافی است' )
			: 'ممکن است دانلود وردپرس با timeout مواجه شود — set_time_limit نیز در دسترس نیست',
		'fatal'  => false,
	];

	$all_pass = true;
	foreach ( $checks as $check ) {
		if ( $check['fatal'] && ! $check['pass'] ) {
			$all_pass = false;
		}
	}

	return [ 'checks' => $checks, 'all_pass' => $all_pass ];
}

function ezi_parse_size( string $size ): int {
	if ( '-1' === trim( $size ) ) {
		return -1;
	}
	$unit  = strtolower( substr( $size, -1 ) );
	$value = (int) $size;

	return match ( $unit ) {
		'g' => $value * 1024 * 1024 * 1024,
		'm' => $value * 1024 * 1024,
		'k' => $value * 1024,
		default => $value,
	};
}
