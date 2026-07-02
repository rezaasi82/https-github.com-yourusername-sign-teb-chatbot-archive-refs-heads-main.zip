<?php
/**
 * Bootstrap — مدیریت مراحل نصب
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

// کوکی جلسه فقط HTTP (غیرقابل‌دسترس از جاوااسکریپت) و محدود به همان سایت —
// این جلسه اطلاعات حساس (رمز عبور دیتابیس) را تا پایان نصب نگه می‌دارد.
session_set_cookie_params( [
	'lifetime' => 0,
	'path'     => '/',
	'secure'   => isset( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'],
	'httponly' => true,
	'samesite' => 'Lax',
] );
session_start();

require EZI_DIR . '/includes/helpers.php';
require EZI_DIR . '/includes/class-requirements.php';
require EZI_DIR . '/includes/class-downloader.php';
require EZI_DIR . '/includes/class-database.php';
require EZI_DIR . '/includes/class-wp-installer.php';
require EZI_DIR . '/includes/class-package-installer.php';

// ── قفل امنیتی پس از اتمام نصب ───────────────────────────────────────────────
// نکته امنیتی: پارامتر ?force که پیش‌تر این قفل را دور می‌زد حذف شد — وجود
// چنین bypass ای بدون هیچ احراز هویتی هدف اصلی این قفل (جلوگیری از اجرای
// دوباره نصب توسط هرکسی که آدرس را حدس بزند) را کاملاً بی‌اثر می‌کرد.
$lock_file = EZI_ROOT . '/.installed';

if ( file_exists( $lock_file ) ) {
	ezi_render_locked_screen();
	exit;
}

// ── تعیین مرحله جاری ──────────────────────────────────────────────────────────

$step = $_GET['step'] ?? ( $_SESSION['ezi_step'] ?? 'welcome' );
$step = preg_replace( '/[^a-z_]/', '', (string) $step );

$valid_steps = [ 'welcome', 'requirements', 'database', 'install_wp', 'site_info', 'install_package', 'finish' ];

if ( ! in_array( $step, $valid_steps, true ) ) {
	$step = 'welcome';
}

// ── AJAX endpoints (برای مراحل progress-bar دار) ──────────────────────────────

if ( isset( $_GET['action'] ) ) {
	header( 'Content-Type: application/json; charset=utf-8' );
	$action = preg_replace( '/[^a-z_]/', '', (string) $_GET['action'] );

	// جلوگیری از خراب شدن خروجی JSON توسط هشدارها/اخطارهای احتمالی
	// توابع هسته وردپرس (مثل wp_install) که گاهی مستقیماً echo می‌کنند.
	// تمام خروجی ناخواسته در طول اجرای عملیات capture و دور ریخته می‌شود؛
	// فقط JSON نهایی که خودمان آگاهانه echo می‌کنیم به مرورگر می‌رسد.
	ob_start();

	// تضمین خروجی JSON معتبر حتی در صورت بروز PHP Fatal Error میانه‌ی کار
	// (که در حالت عادی اجرای اسکریپت را بدون هیچ پاسخی متوقف می‌کند)
	register_shutdown_function( static function (): void {
		$error = error_get_last();
		$is_fatal = $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true );

		if ( $is_fatal ) {
			// پاک‌کردن هر خروجی ناقص قبلی و چاپ یک JSON معتبر به‌جای آن
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			header( 'Content-Type: application/json; charset=utf-8' );
			echo json_encode( [
				'success' => false,
				'message' => 'خطای داخلی سرور (PHP Fatal Error): ' . $error['message'] .
					' — در فایل ' . basename( (string) $error['file'] ) . ' خط ' . $error['line'],
			] );
		}
	} );

	$result_json = '';

	switch ( $action ) {
		case 'check_requirements':
			$result_json = json_encode( ezi_check_requirements() );
			break;

		case 'test_database':
			$result_json = json_encode( ezi_test_database_connection( $_POST ) );
			break;

		case 'download_wp':
			$result_json = json_encode( ezi_download_wordpress() );
			break;

		case 'install_wp':
			$result_json = json_encode( ezi_run_wp_install( $_POST ) );
			break;

		case 'extract_package':
			$result_json = json_encode( ezi_extract_package() );
			break;

		case 'activate_theme':
			$result_json = json_encode( ezi_activate_theme() );
			break;

		case 'activate_plugins':
			$result_json = json_encode( ezi_activate_plugins() );
			break;

		case 'cleanup':
			$result_json = json_encode( ezi_run_cleanup() );
			break;

		default:
			$result_json = json_encode( [ 'success' => false, 'message' => 'عملیات نامعتبر است.' ] );
	}

	// دور ریختن هر خروجی ناخواسته (warning/notice/echo از وردپرس) که در
	// طول اجرای عملیات بالا تولید شده — فقط JSON نهایی باقی می‌ماند
	$stray_output = ob_get_clean();

	if ( $stray_output && defined( 'EZI_DEBUG' ) && EZI_DEBUG ) {
		// در حالت دیباگ، خروجی ناخواسته را به‌عنوان کمک عیب‌یابی ضمیمه می‌کنیم
		$decoded = json_decode( (string) $result_json, true );
		if ( is_array( $decoded ) ) {
			$decoded['_debug_stray_output'] = mb_substr( $stray_output, 0, 2000 );
			$result_json = json_encode( $decoded );
		}
	}

	echo $result_json;
	exit;
}

// ── رندر صفحه HTML بر اساس مرحله ──────────────────────────────────────────────

$_SESSION['ezi_step'] = $step;

require EZI_DIR . '/includes/render.php';
ezi_render_page( $step );
