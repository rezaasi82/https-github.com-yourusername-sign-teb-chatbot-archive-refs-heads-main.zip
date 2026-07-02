<?php
/**
 * ابزار تشخیص خطا — Diagnostic Tool
 *
 * این فایل را موقتاً در همان پوشه‌ای که وردپرس نصب است آپلود کنید
 * (کنار wp-config.php) و در مرورگر به آدرس زیر بروید:
 *
 *     https://دامنه‌شما/diagnose.php
 *
 * این ابزار دقیقاً نشان می‌دهد کدام افزونه یا قالب باعث خطا شده است.
 * پس از پیدا کردن مشکل، این فایل را حذف کنید (حاوی اطلاعات حساس است).
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">';
echo '<title>تشخیص خطا</title>';
echo '<style>body{font-family:Tahoma,sans-serif;background:#0f172a;color:#e2e8f0;padding:2rem;line-height:1.8;}
h1{color:#fbbf24;} .ok{color:#4ade80;} .fail{color:#f87171;} .box{background:#1e293b;padding:1rem 1.5rem;border-radius:8px;margin:1rem 0;}
pre{background:#000;color:#0f0;padding:1rem;border-radius:8px;overflow:auto;direction:ltr;text-align:left;font-size:13px;}</style>';
echo '</head><body>';
echo '<h1>🔍 ابزار تشخیص خطا</h1>';

// ── مرحله ۱: بررسی بارگذاری وردپرس ──────────────────────────────────────────

echo '<div class="box"><strong>مرحله ۱: بارگذاری وردپرس</strong><br>';

if ( ! file_exists( __DIR__ . '/wp-load.php' ) ) {
	echo '<span class="fail">❌ فایل wp-load.php در این پوشه یافت نشد. این ابزار را کنار wp-config.php آپلود کنید.</span></div>';
	echo '</body></html>';
	exit;
}

try {
	define( 'WP_USE_THEMES', false );
	require __DIR__ . '/wp-load.php';
	echo '<span class="ok">✅ وردپرس با موفقیت بارگذاری شد (یعنی مشکل اصلی در خود core نیست)</span>';
} catch ( \Throwable $e ) {
	echo '<span class="fail">❌ خطا در بارگذاری وردپرس:</span>';
	echo '<pre>' . htmlspecialchars( $e->getMessage() . "\n in " . $e->getFile() . ' on line ' . $e->getLine() ) . '</pre>';
	echo '</div></body></html>';
	exit;
}
echo '</div>';

// ── کنترل دسترسی: این ابزار مسیر افزونه‌های فعال، ساختار فایل سرور و
// خطوط آخر debug.log را نمایش می‌دهد — اطلاعاتی که نباید در دسترس عموم
// باشد. اگر این فایل طبق راهنما پس از رفع مشکل حذف نشود، حداقل باید
// پشت یک بررسی دسترسی مدیر قرار بگیرد، نه اینکه برای هر بازدیدکننده‌ای
// باز باشد.
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	echo '<div class="box" style="border:1px solid #f87171;">';
	echo '<strong class="fail">⛔ دسترسی غیرمجاز</strong><br>';
	echo 'برای استفاده از این ابزار، ابتدا در یک تب دیگر وارد <code>/wp-admin/</code> شوید (با حساب مدیر سایت)، سپس این صفحه را رفرش کنید.';
	echo '</div></body></html>';
	exit;
}

// ── مرحله ۲: بررسی افزونه‌های فعال ───────────────────────────────────────────

echo '<div class="box"><strong>مرحله ۲: افزونه‌های فعال</strong><br>';

$active_plugins = get_option( 'active_plugins', [] );

if ( empty( $active_plugins ) ) {
	echo '<span class="fail">⚠️ هیچ افزونه فعالی یافت نشد.</span>';
} else {
	echo '<ul>';
	foreach ( $active_plugins as $plugin ) {
		$full_path = WP_PLUGIN_DIR . '/' . $plugin;
		$exists    = file_exists( $full_path );
		echo '<li>' . ( $exists ? '<span class="ok">✅</span>' : '<span class="fail">❌ فایل یافت نشد:</span>' ) . ' ' . htmlspecialchars( $plugin ) . '</li>';
	}
	echo '</ul>';
}
echo '</div>';

// ── مرحله ۳: تست جداگانه هر افزونه (lood مجدد فایل آن) ───────────────────────

echo '<div class="box"><strong>مرحله ۳: تست بارگذاری مجدد هر افزونه (برای یافتن خطای دقیق)</strong><br>';
echo '<p style="color:#94a3b8;font-size:13px;">توجه: این تست فقط می‌تواند خطاهای parse-time را پیدا کند. اگر افزونه از قبل بدون خطا require شده، نتیجه فقط نشان «از قبل بارگذاری شده» می‌دهد که یعنی مشکل آن جایی دیگر است.</p>';

foreach ( $active_plugins as $plugin ) {
	$full_path = WP_PLUGIN_DIR . '/' . $plugin;
	echo '<div style="margin-bottom:0.75rem;padding:0.5rem;background:#0f172a;border-radius:6px;">';
	echo '<strong>' . htmlspecialchars( $plugin ) . '</strong><br>';

	if ( ! file_exists( $full_path ) ) {
		echo '<span class="fail">فایل وجود ندارد</span>';
		echo '</div>';
		continue;
	}

	// بررسی سینتکس فایل با php -l (در صورت دسترسی به exec)
	if ( function_exists( 'exec' ) ) {
		$output = [];
		$return_code = 0;
		@exec( 'php -l ' . escapeshellarg( $full_path ) . ' 2>&1', $output, $return_code );
		$output_str = implode( "\n", $output );

		if ( 0 === $return_code ) {
			echo '<span class="ok">✅ بدون خطای سینتکسی</span>';
		} else {
			echo '<span class="fail">❌ خطای سینتکسی پیدا شد:</span><pre>' . htmlspecialchars( $output_str ) . '</pre>';
		}
	} else {
		echo '<span style="color:#94a3b8;">بررسی سینتکس ممکن نیست (exec غیرفعال است)</span>';
	}
	echo '</div>';
}
echo '</div>';

// ── مرحله ۴: بررسی قالب فعال ──────────────────────────────────────────────────

echo '<div class="box"><strong>مرحله ۴: قالب فعال</strong><br>';
$theme = wp_get_theme();
echo 'نام: ' . htmlspecialchars( $theme->get( 'Name' ) ?: '(نامشخص)' ) . '<br>';
echo 'مسیر: ' . htmlspecialchars( $theme->get_stylesheet_directory() ) . '<br>';

$functions_path = $theme->get_stylesheet_directory() . '/functions.php';
if ( file_exists( $functions_path ) ) {
	if ( function_exists( 'exec' ) ) {
		$output = [];
		$return_code = 0;
		@exec( 'php -l ' . escapeshellarg( $functions_path ) . ' 2>&1', $output, $return_code );
		if ( 0 === $return_code ) {
			echo '<span class="ok">✅ functions.php بدون خطای سینتکسی</span>';
		} else {
			echo '<span class="fail">❌ خطای سینتکسی در functions.php:</span><pre>' . htmlspecialchars( implode( "\n", $output ) ) . '</pre>';
		}
	}
} else {
	echo '<span class="fail">❌ functions.php یافت نشد</span>';
}
echo '</div>';

// ── مرحله ۵: نمایش آخرین خطاهای ثبت‌شده در سرور (در صورت دسترسی) ────────────

echo '<div class="box"><strong>مرحله ۵: لاگ خطاهای اخیر PHP</strong><br>';

$possible_logs = [
	WP_CONTENT_DIR . '/debug.log',
	ini_get( 'error_log' ),
	__DIR__ . '/error_log',
];

$found_log = false;
foreach ( $possible_logs as $log_path ) {
	if ( $log_path && file_exists( $log_path ) && is_readable( $log_path ) ) {
		$found_log = true;
		echo '<p>فایل لاگ یافت شد: <code>' . htmlspecialchars( $log_path ) . '</code></p>';
		$content = file_get_contents( $log_path );
		$lines   = array_slice( explode( "\n", (string) $content ), -40 ); // ۴۰ خط آخر
		echo '<pre>' . htmlspecialchars( implode( "\n", $lines ) ) . '</pre>';
		break;
	}
}

if ( ! $found_log ) {
	echo '<span style="color:#94a3b8;">هیچ فایل لاگ قابل‌دسترسی یافت نشد. برای فعال‌سازی لاگ، خطوط زیر را به wp-config.php (قبل از خط "stop editing") اضافه کنید:</span>';
	echo '<pre>define( \'WP_DEBUG\', true );
define( \'WP_DEBUG_LOG\', true );
define( \'WP_DEBUG_DISPLAY\', false );</pre>';
	echo '<p style="color:#94a3b8;font-size:13px;">سپس دوباره سایت را باز کنید تا خطا رخ دهد، و این صفحه را رفرش کنید تا لاگ نمایش داده شود.</p>';
}
echo '</div>';

echo '<div class="box" style="border:1px solid #fbbf24;"><strong>⚠️ یادآوری امنیتی:</strong><br>پس از پیدا کردن مشکل، این فایل (diagnose.php) را حذف کنید — حاوی اطلاعات ساختاری سایت شماست.</div>';

echo '</body></html>';
