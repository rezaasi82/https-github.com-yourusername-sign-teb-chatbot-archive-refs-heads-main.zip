<?php
/**
 * نصب پکیج قالب و افزونه‌ها
 *
 * فایل‌های zip قالب/افزونه‌ها از قبل در پوشه installer/package/
 * قرار گرفته‌اند (همراه با این نصب‌کننده در یک zip واحد).
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

/**
 * نگاشت نام فایل zip ← اطلاعات نمایشی
 * (نام‌های فنی داخلی به کاربر نهایی نشان داده نمی‌شود)
 */
function ezi_package_manifest(): array {
	return [
		'theme' => [
			'zip'        => 'theme.zip',
			'label'      => 'قالب اصلی سایت',
			'type'       => 'theme',
			'extract_to' => 'wp-content/themes',
		],
		'plugin_1' => [
			'zip'        => 'plugin-1.zip',
			'label'      => 'موتور اصلی سایت',
			'type'       => 'plugin',
			'extract_to' => 'wp-content/plugins',
		],
		'plugin_2' => [
			'zip'        => 'plugin-2.zip',
			'label'      => 'ابزارهای صفحه‌ساز',
			'type'       => 'plugin',
			'extract_to' => 'wp-content/plugins',
		],
		'plugin_3' => [
			'zip'        => 'plugin-3.zip',
			'label'      => 'دستیار راه‌اندازی',
			'type'       => 'plugin',
			'extract_to' => 'wp-content/plugins',
		],
	];
}

/**
 * استخراج همه فایل‌های پکیج به مسیر صحیح در wp-content
 */
function ezi_extract_package(): array {
	$manifest    = ezi_package_manifest();
	$package_dir = EZI_ROOT . '/installer/package';
	$results     = [];
	$any_found   = false;

	foreach ( $manifest as $key => $item ) {
		$zip_path = $package_dir . '/' . $item['zip'];

		if ( ! file_exists( $zip_path ) ) {
			$results[] = [ 'key' => $key, 'label' => $item['label'], 'status' => 'skipped' ];
			continue;
		}

		$any_found = true;
		$dest      = EZI_ROOT . '/' . $item['extract_to'];

		if ( ! is_dir( $dest ) ) {
			mkdir( $dest, 0755, true );
		}

		$ok = ezi_unzip( $zip_path, $dest );

		$results[] = [
			'key'    => $key,
			'label'  => $item['label'],
			'status' => $ok ? 'ok' : 'failed',
		];
	}

	if ( ! $any_found ) {
		return ezi_json( false, 'هیچ فایل نصبی در پوشه installer/package یافت نشد.' );
	}

	$failed = array_filter( $results, fn( $r ) => 'failed' === $r['status'] );
	if ( ! empty( $failed ) ) {
		return ezi_json( false, 'استخراج برخی فایل‌ها ناموفق بود.', [ 'results' => $results ] );
	}

	return ezi_json( true, 'فایل‌های قالب و افزونه با موفقیت استخراج شدند.', [ 'results' => $results ] );
}

/**
 * فعال‌سازی قالب نصب‌شده
 */
function ezi_activate_theme(): array {
	if ( ! defined( 'ABSPATH' ) ) {
		require_once EZI_ROOT . '/wp-load.php';
	}
	require_once ABSPATH . 'wp-admin/includes/theme.php';

	// محافظت در برابر هاست‌هایی که تابع mail() را غیرفعال کرده‌اند —
	// برخی قالب‌ها در هوک after_switch_theme تلاش به ارسال ایمیل می‌کنند
	ezi_disable_wp_mail();

	$themes_dir = EZI_ROOT . '/wp-content/themes';
	$installed  = array_diff( scandir( $themes_dir ) ?: [], [ '.', '..', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo' ] );

	if ( empty( $installed ) ) {
		return ezi_json( false, 'هیچ قالبی برای فعال‌سازی یافت نشد.' );
	}

	$theme_slug = reset( $installed );

	// محافظت در برابر هر PHP Fatal Error احتمالی در زمان فعال‌سازی قالب
	// (مثلاً اگر functions.php قالب به کلاس/تابعی ناموجود رجوع دهد)
	try {
		switch_theme( $theme_slug );
	} catch ( \Throwable $e ) {
		return ezi_json( false, sprintf(
			'خطای PHP در فعال‌سازی قالب: %s (فایل: %s، خط: %d)',
			$e->getMessage(),
			basename( $e->getFile() ),
			$e->getLine()
		) );
	}

	return ezi_json( true, 'قالب با موفقیت فعال شد.', [ 'theme' => $theme_slug ] );
}

/**
 * فعال‌سازی افزونه‌های نصب‌شده — به ترتیب صحیح وابستگی
 *
 * مهم: ترتیب الفبایی نام پوشه‌ها قابل اعتماد نیست (مثلاً پوشه‌ای که
 * با "b" شروع می‌شود قبل از پوشه‌ای با "m" می‌آید، در حالی که ممکن
 * است افزونه دوم پیش‌نیاز اولی باشد). به همین دلیل یک ترتیب صریح
 * و قطعی تعریف می‌کنیم: ابتدا افزونه‌های شناخته‌شده طبق این ترتیب،
 * سپس هر افزونه ناشناس باقی‌مانده (اگر کاربر چیز دیگری اضافه کرده باشد).
 */
function ezi_plugin_activation_order(): array {
	return [
		'signteb-medical-core', // هسته اصلی — باید همیشه اول فعال شود
		'signteb-blocks',       // به کلاس‌ها/Taxonomy‌های هسته نیاز دارد
		'signteb-wizard',       // دستیار راه‌اندازی — آخرین مورد
	];
}

function ezi_activate_plugins(): array {
	if ( ! defined( 'ABSPATH' ) ) {
		require_once EZI_ROOT . '/wp-load.php';
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	// محافظت در برابر هاست‌هایی که تابع mail() را غیرفعال کرده‌اند —
	// برخی افزونه‌ها (مثل Akismet) در زمان فعال‌سازی ایمیل می‌فرستند
	ezi_disable_wp_mail();

	$plugins_dir = EZI_ROOT . '/wp-content/plugins';
	$all_folders = array_values( array_diff( scandir( $plugins_dir ) ?: [], [ '.', '..' ] ) );

	// مرتب‌سازی بر اساس ترتیب وابستگی صریح، نه الفبا
	$priority   = ezi_plugin_activation_order();
	$known      = array_values( array_intersect( $priority, $all_folders ) );
	$unknown    = array_values( array_diff( $all_folders, $priority ) );
	natsort( $unknown ); // فقط برای پوشه‌های ناشناس، الفبا قابل قبول است
	$folders = array_merge( $known, $unknown );

	$activated = [];
	$failed    = [];
	$failed_details = [];

	foreach ( $folders as $folder ) {
		$plugin_dir_path = $plugins_dir . '/' . $folder;
		if ( ! is_dir( $plugin_dir_path ) ) {
			continue;
		}

		// یافتن فایل اصلی پلاگین (فایلی با Plugin Name در هدر)
		$main_file = ezi_find_plugin_main_file( $plugin_dir_path, $folder );

		if ( ! $main_file ) {
			$failed[] = $folder;
			$failed_details[] = "{$folder}: فایل اصلی افزونه (با هدر Plugin Name) یافت نشد";
			continue;
		}

		$relative = $folder . '/' . basename( $main_file );

		// فعال‌سازی هر افزونه را به‌صورت مجزا محافظت می‌کنیم تا اگر یکی
		// با PHP Fatal Error مواجه شد، کل فرآیند نصب متوقف نشود و
		// بتوانیم خطای دقیق را به کاربر گزارش کنیم.
		$result = ezi_safe_activate_plugin( $relative );

		if ( true === $result ) {
			$activated[] = $folder;
		} else {
			$failed[] = $folder;
			$failed_details[] = "{$folder}: " . $result;
		}
	}

	if ( empty( $activated ) ) {
		return ezi_json( false, 'هیچ افزونه‌ای فعال نشد.', [ 'failed' => $failed, 'details' => $failed_details ] );
	}

	if ( ! empty( $failed ) ) {
		return ezi_json( true, count( $activated ) . ' افزونه فعال شد، اما ' . count( $failed ) . ' افزونه با خطا مواجه شد: ' . implode( ' | ', $failed_details ), [
			'activated' => $activated,
			'failed'    => $failed,
		] );
	}

	return ezi_json( true, count( $activated ) . ' افزونه با موفقیت فعال شد.', [
		'activated' => $activated,
		'failed'    => $failed,
	] );
}

/**
 * فعال‌سازی یک افزونه به‌صورت ایزوله در یک پردازش PHP جدا (CLI subprocess
 * در صورت امکان)، تا اگر کد آن افزونه باعث PHP Fatal Error شود، این خطا
 * در همان پردازش جدا گزارش و capture شود — نه در پردازش اصلی نصب‌کننده
 * که می‌تواند باعث "خطای مهم" یا "صفحه سفید" در کل سایت شود.
 *
 * اگر اجرای subprocess ممکن نباشد (محدودیت هاست)، با activate_plugin()
 * مستقیم ادامه می‌دهیم — که برای افزونه‌های سالم کاملاً کافی است.
 *
 * @return true|string نتیجه true یا متن دقیق خطا (شامل فایل و خط در صورت امکان)
 */
function ezi_safe_activate_plugin( string $relative_path ): true|string {
	if ( ezi_shell_available() && function_exists( 'proc_open' ) ) {
		$php_binary = PHP_BINARY ?: 'php';
		$script     = EZI_DIR . '/cli/activate-one-plugin.php';

		if ( file_exists( $script ) ) {
			$cmd = sprintf(
				'%s %s %s 2>&1',
				escapeshellarg( $php_binary ),
				escapeshellarg( $script ),
				escapeshellarg( $relative_path )
			);

			$output = [];
			$exit_code = 0;
			@exec( $cmd, $output, $exit_code );
			$output_str = implode( "\n", $output );

			$decoded = json_decode( $output_str, true );
			if ( is_array( $decoded ) ) {
				return true === $decoded['ok'] ? true : (string) $decoded['error'];
			}

			// خروجی subprocess قابل پارس نبود — یعنی احتمالاً یک Fatal Error
			// خام PHP چاپ شده. این خودش بهترین سرنخ ممکن برای عیب‌یابی است.
			if ( $output_str ) {
				return 'PHP Fatal Error در فرآیند مجزا: ' . mb_substr( $output_str, 0, 500 );
			}
		}
	}

	// Fallback: فعال‌سازی مستقیم در همین پردازش. در PHP 7+ بسیاری از خطاهای
	// «فاتال» (مثل Class not found) در واقع از نوع \Error هستند که با
	// try/catch قابل گرفتن‌اند — این یعنی حتی بدون ایزوله‌سازی subprocess،
	// یک افزونه‌ی خراب نمی‌تواند کل فرآیند فعال‌سازی بقیه افزونه‌ها را متوقف کند.
	try {
		$result = activate_plugin( $relative_path );

		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		return true;
	} catch ( \Throwable $e ) {
		return sprintf(
			'خطای PHP در زمان فعال‌سازی: %s (فایل: %s، خط: %d)',
			$e->getMessage(),
			basename( $e->getFile() ),
			$e->getLine()
		);
	}
}

function ezi_find_plugin_main_file( string $dir, string $folder ): ?string {
	$php_files = glob( $dir . '/*.php' );

	foreach ( $php_files ?: [] as $file ) {
		$content = file_get_contents( $file, false, null, 0, 2000 );
		if ( $content && preg_match( '/Plugin\s*Name\s*:/i', $content ) ) {
			return $file;
		}
	}

	return null;
}

/**
 * پاکسازی نهایی — حذف نصب‌کننده و قفل کردن دسترسی دوباره
 */
function ezi_run_cleanup(): array {
	// ذخیره یک قفل تا اجرای دوباره نصب‌کننده ممکن نباشد
	file_put_contents( EZI_ROOT . '/.installed', date( 'Y-m-d H:i:s' ) );

	// حذف فایل‌های موقت پکیج (zip‌های خام دیگر لازم نیستند)
	$package_dir = EZI_ROOT . '/installer/package';
	if ( is_dir( $package_dir ) ) {
		$files = glob( $package_dir . '/*.zip' ) ?: [];
		foreach ( $files as $f ) {
			@unlink( $f );
		}
	}

	@unlink( ezi_state_path() );

	return ezi_json( true, 'پاکسازی انجام شد.' );
}
