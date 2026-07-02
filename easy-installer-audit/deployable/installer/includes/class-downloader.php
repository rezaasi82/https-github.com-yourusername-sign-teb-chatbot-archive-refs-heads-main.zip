<?php
/**
 * دانلود و آماده‌سازی هسته وردپرس
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

const EZI_WP_DOWNLOAD_URL = 'https://fa.wordpress.org/latest-fa_IR.zip';
const EZI_WP_DOWNLOAD_FALLBACK = 'https://wordpress.org/latest.zip';

function ezi_download_wordpress(): array {
	// جلوگیری از kill شدن این درخواست توسط محدودیت زمانی هاست
	@set_time_limit( 0 );

	// اگر وردپرس از قبل نصب شده (wp-config.php موجود است)، نیازی به دانلود نیست
	if ( file_exists( EZI_ROOT . '/wp-config.php' ) || file_exists( EZI_ROOT . '/wp-load.php' ) ) {
		return ezi_json( true, 'وردپرس از قبل نصب شده است.', [ 'already_installed' => true ] );
	}

	$tmp_zip = EZI_ROOT . '/installer/wp-core.zip';

	$result = ezi_download_file_verbose( EZI_WP_DOWNLOAD_URL, $tmp_zip );

	if ( ! $result['ok'] || ! file_exists( $tmp_zip ) || filesize( $tmp_zip ) < 1000 ) {
		// تلاش با نسخه انگلیسی در صورت شکست نسخه فارسی
		$result = ezi_download_file_verbose( EZI_WP_DOWNLOAD_FALLBACK, $tmp_zip );
	}

	if ( ! $result['ok'] || ! file_exists( $tmp_zip ) ) {
		$detail = $result['error'] ?: 'دلیل نامشخص';
		return ezi_json( false, "دانلود وردپرس ناموفق بود. جزئیات: {$detail}" );
	}

	// استخراج zip — داخل آن یک پوشه wordpress/ هست
	$extract_to = EZI_ROOT . '/installer/wp-temp';
	if ( ! ezi_unzip( $tmp_zip, $extract_to ) ) {
		return ezi_json( false, 'استخراج فایل وردپرس ناموفق بود.' );
	}

	$wp_source = $extract_to . '/wordpress';
	if ( ! is_dir( $wp_source ) ) {
		return ezi_json( false, 'ساختار فایل دانلود شده نامعتبر است.' );
	}

	// انتقال محتویات به روت دامنه
	$items = array_diff( scandir( $wp_source ) ?: [], [ '.', '..' ] );
	foreach ( $items as $item ) {
		$src = $wp_source . '/' . $item;
		$dst = EZI_ROOT . '/' . $item;

		if ( file_exists( $dst ) ) {
			continue; // اگر فایلی با همین نام از قبل هست (مثل install.php) رونویسی نکن
		}

		if ( is_dir( $src ) ) {
			ezi_rcopy( $src, $dst );
		} else {
			copy( $src, $dst );
		}
	}

	// پاکسازی فایل‌های موقت
	@unlink( $tmp_zip );
	ezi_rrmdir( $extract_to );

	return ezi_json( true, 'وردپرس با موفقیت دانلود و استخراج شد.' );
}
