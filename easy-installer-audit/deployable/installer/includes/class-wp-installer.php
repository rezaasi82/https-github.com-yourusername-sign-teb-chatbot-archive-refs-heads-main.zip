<?php
/**
 * نصب هسته وردپرس — نوشتن wp-config.php و اجرای wp_install()
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

function ezi_run_wp_install( array $post ): array {
	$db = $_SESSION['ezi_db'] ?? null;

	if ( ! $db ) {
		return ezi_json( false, 'اطلاعات دیتابیس یافت نشد. به مرحله قبل بازگردید.' );
	}

	$site_title = trim( (string) ( $post['site_title'] ?? 'سایت من' ) );
	$admin_user = trim( (string) ( $post['admin_user'] ?? 'admin' ) );
	$admin_pass = (string) ( $post['admin_pass'] ?? '' );
	$admin_email = trim( (string) ( $post['admin_email'] ?? '' ) );

	if ( ! $admin_user || ! $admin_pass || ! $admin_email ) {
		return ezi_json( false, 'تمام فیلدهای مدیر سایت را تکمیل کنید.' );
	}

	if ( ! filter_var( $admin_email, FILTER_VALIDATE_EMAIL ) ) {
		return ezi_json( false, 'ایمیل مدیر سایت معتبر نیست.' );
	}

	// ── ۱. نوشتن wp-config.php ──────────────────────────────────────────────

	$config_path = EZI_ROOT . '/wp-config.php';

	if ( ! file_exists( EZI_ROOT . '/wp-config-sample.php' ) ) {
		return ezi_json( false, 'فایل wp-config-sample.php یافت نشد. آیا وردپرس به‌درستی استخراج شده است؟' );
	}

	if ( ! file_exists( $config_path ) ) {
		$sample = file_get_contents( EZI_ROOT . '/wp-config-sample.php' );

		// مقادیر دیتابیس درون رشته‌های تک‌کوتیشن PHP در wp-config-sample.php
		// جایگزین می‌شوند. اگر رمز عبور یا نام دیتابیس شامل ' یا \ باشد (که
		// در MySQL کاملاً مجاز است)، بدون escape رشته PHP می‌شکند و باقی مقدار
		// به‌صورت کد PHP خام تزریق می‌شود — دقیقاً همان چیزی که خود وردپرس هم
		// در wp-admin/setup-config.php با addcslashes() از آن جلوگیری می‌کند.
		$esc = static fn( string $v ): string => addcslashes( $v, "\\'" );

		$replacements = [
			"database_name_here" => $esc( $db['name'] ),
			"username_here"      => $esc( $db['user'] ),
			"password_here"      => $esc( $db['pass'] ),
			"localhost"          => $esc( $db['host'] ),
			"wp_"                => $db['prefix'], // قبلاً در class-database.php با regex به [a-zA-Z0-9_]+ محدود شده
		];

		$config = strtr( $sample, $replacements );

		// جایگزینی کلیدهای امنیتی — هر ۸ کلید/سالت باید مقدار منحصربه‌فرد بگیرند
		$config = ezi_replace_secret_keys( $config );

		// فعال‌سازی زبان فارسی — اولویت با جایگزینی مستقیم خط WPLANG
		if ( str_contains( $config, "define( 'WPLANG', '' );" ) ) {
			$config = str_replace(
				"define( 'WPLANG', '' );",
				"define( 'WPLANG', 'fa_IR' );",
				$config
			);
		} elseif ( ! str_contains( $config, 'WPLANG' ) ) {
			// روش مقاوم: تزریق قبل از خطی که همیشه در wp-config وجود دارد
			// (به‌جای جستجوی متن کامنت که ممکن است بین نسخه‌ها تغییر کند)
			$anchor = "require_once ABSPATH . 'wp-settings.php';";
			if ( str_contains( $config, $anchor ) ) {
				$config = str_replace(
					$anchor,
					"define( 'WPLANG', 'fa_IR' );\n\n" . $anchor,
					$config
				);
			} else {
				// آخرین fallback: اضافه کردن به انتهای فایل قبل از تگ بسته PHP
				$config = preg_replace( '/\?>\s*$/', "define( 'WPLANG', 'fa_IR' );\n", $config, 1 ) ?? $config;
			}
		}

		file_put_contents( $config_path, $config );
	}

	// ── ۲. بارگذاری وردپرس و اجرای نصب ─────────────────────────────────────

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'WP_INSTALLING', true );
		require_once EZI_ROOT . '/wp-load.php';
	}

	if ( ! function_exists( 'wp_install' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	// ── بررسی حیاتی: اگر وردپرس قبلاً به‌طور کامل نصب شده (جدول‌ها و کاربر
	// ادمین از قبل وجود دارند)، اجرای دوباره wp_install() می‌تواند دیتای
	// موجود را خراب کند یا کاربر تکراری بسازد. در این حالت نصب را متوقف
	// و این مرحله را به‌عنوان «از قبل انجام شده» علامت می‌زنیم.
	global $wpdb;
	$tables_exist = false;
	if ( isset( $wpdb ) ) {
		$tables_exist = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}users'" );
	}

	if ( $tables_exist && function_exists( 'get_users' ) ) {
		$existing_admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
		if ( ! empty( $existing_admins ) ) {
			$_SESSION['ezi_admin_user'] = $existing_admins[0]->user_login;
			return ezi_json( true, 'وردپرس از قبل نصب و دارای حساب مدیر است — این مرحله نادیده گرفته شد.', [
				'already_installed' => true,
			] );
		}
	}

	$site_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https://' : 'http://' )
		. ezi_safe_host();

	update_option( 'siteurl', $site_url );
	update_option( 'home', $site_url );

	// ── محافظت حیاتی: غیرفعال‌سازی کامل ارسال ایمیل ───────────────────────────
	// wp_install() در پایان کار خودش تلاش می‌کند یک ایمیل خوش‌آمدگویی به
	// ادمین تازه‌ساخته‌شده بفرستد (از طریق wp_new_blog_notification).
	// روی بسیاری از هاست‌های اشتراکی، تابع PHP داخلی mail() کاملاً
	// غیرفعال یا حذف شده (برای مقابله با اسپم) — در این حالت PHPMailer
	// با خطای «Call to undefined function mail()» کاملاً Fatal می‌شود
	// و کل فرآیند نصب (نه فقط ارسال ایمیل) متوقف می‌شود.
	ezi_disable_wp_mail();

	$result = wp_install(
		$site_title,
		$admin_user,
		$admin_email,
		true, // public
		'',
		$admin_pass
	);

	if ( is_wp_error( $result ) ) {
		return ezi_json( false, 'نصب وردپرس با خطا مواجه شد: ' . $result->get_error_message() );
	}

	// فعال‌سازی پیشوند صحیح اگر دیتابیس قبلاً جدول داشت
	update_option( 'blogname', $site_title );

	// تنظیم ساختار پرمالینک خوانا و نوشتن قوانین rewrite در .htaccess
	// (در غیر این صورت لینک پست‌ها/صفحات پس از نصب 404 می‌شوند)
	$htaccess_path = EZI_ROOT . '/.htaccess';
	if ( file_exists( $htaccess_path ) && ! is_writable( $htaccess_path ) ) {
		@chmod( $htaccess_path, 0664 );
	}

	global $wp_rewrite;
	update_option( 'permalink_structure', '/%postname%/' );
	if ( isset( $wp_rewrite ) ) {
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
	}

	$_SESSION['ezi_admin_user'] = $admin_user;

	return ezi_json( true, 'وردپرس با موفقیت نصب شد.', [ 'site_url' => $site_url ] );
}

/**
 * دریافت ۸ کلید/سالت امنیتی از API رسمی وردپرس
 *
 * @return array<string,string> نگاشت نام کلید → خط کامل define(...)
 */
function ezi_fetch_secret_keys(): array {
	$remote = @file_get_contents( 'https://api.wordpress.org/secret-key/1.1/salt/' );
	$wanted = [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ];
	$result = [];

	if ( $remote && strlen( $remote ) > 200 ) {
		foreach ( explode( "\n", $remote ) as $line ) {
			$line = trim( $line );
			if ( ! str_starts_with( $line, 'define(' ) ) {
				continue;
			}
			// استخراج نام واقعی کلید از همان خط (مثل AUTH_KEY) — نه با فرض ترتیب
			if ( preg_match( "/define\\(\\s*'([A-Z_]+)'/", $line, $m ) && in_array( $m[1], $wanted, true ) ) {
				$result[ $m[1] ] = $line;
			}
		}
	}

	// fallback محلی برای هر کلیدی که از API دریافت نشد (یا کلاً API در دسترس نبود)
	foreach ( $wanted as $key ) {
		if ( ! isset( $result[ $key ] ) ) {
			$value         = addslashes( ezi_random_password( 64 ) );
			$result[ $key ] = "define( '{$key}', '{$value}' );";
		}
	}

	return $result;
}

/**
 * جایگزینی هر یک از ۸ خط define() کلید امنیتی در محتوای wp-config
 * با مقدار واقعی — هر کلید به‌صورت مجزا و دقیق (نه فقط AUTH_KEY)
 */
function ezi_replace_secret_keys( string $config ): string {
	$key_lines = ezi_fetch_secret_keys();

	foreach ( $key_lines as $key => $new_line ) {
		// جستجوی خط define برای این کلید مشخص (با هر مقدار placeholder که باشد)
		// و جایگزینی دقیق همان یک خط
		$pattern = '/define\(\s*\'' . preg_quote( $key, '/' ) . '\'\s*,.*?\);/s';
		$config  = preg_replace( $pattern, $new_line, $config, 1 ) ?? $config;
	}

	return $config;
}
