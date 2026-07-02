<?php
/**
 * توابع کمکی نصب‌کننده
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

/**
 * صفحه قفل‌شده — وقتی نصب قبلاً تکمیل شده
 */
function ezi_render_locked_screen(): void {
	?>
	<!DOCTYPE html>
	<html lang="fa" dir="rtl">
	<head>
		<meta charset="UTF-8">
		<title>نصب قبلاً انجام شده</title>
		<style><?php include EZI_DIR . '/assets/style.css'; ?></style>
	</head>
	<body class="ezi-body">
		<div class="ezi-wrap ezi-wrap--center">
			<div class="ezi-card" style="max-width:520px;text-align:center;">
				<div class="ezi-lock-icon">🔒</div>
				<h1>نصب قبلاً تکمیل شده است</h1>
				<p class="ezi-muted">
					سایت شما با موفقیت نصب شده است. برای امنیت بیشتر،
					این نصب‌کننده غیرفعال شده است.
				</p>
				<div class="ezi-notice ezi-notice--warning">
					برای حذف کامل، فایل <code>install.php</code> و پوشه
					<code>installer/</code> را از سرور حذف کنید.
				</div>
				<a href="/wp-admin/" class="ezi-btn ezi-btn--primary">ورود به پیشخوان وردپرس</a>
				<a href="/" class="ezi-btn ezi-btn--ghost">مشاهده سایت</a>
			</div>
		</div>
	</body>
	</html>
	<?php
}

/**
 * اجرای امن دستورات shell (اگر مجاز باشد) — برای دانلود/استخراج فایل بزرگ
 */
function ezi_shell_available(): bool {
	if ( ! function_exists( 'exec' ) ) {
		return false;
	}
	$disabled = explode( ',', (string) ini_get( 'disable_functions' ) );
	return ! in_array( 'exec', array_map( 'trim', $disabled ), true );
}

/**
 * فرمت حجم فایل به فارسی
 */
function ezi_format_bytes( int $bytes ): string {
	$units = [ 'بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت' ];
	$i     = 0;
	while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
		$bytes /= 1024;
		$i++;
	}
	return round( $bytes, 1 ) . ' ' . $units[ $i ];
}

/**
 * حذف بازگشتی یک پوشه
 */
function ezi_rrmdir( string $dir ): bool {
	if ( ! is_dir( $dir ) ) {
		return false;
	}
	$items = array_diff( scandir( $dir ) ?: [], [ '.', '..' ] );
	foreach ( $items as $item ) {
		$path = $dir . '/' . $item;
		is_dir( $path ) ? ezi_rrmdir( $path ) : unlink( $path );
	}
	return rmdir( $dir );
}

/**
 * کپی بازگشتی یک پوشه
 */
function ezi_rcopy( string $src, string $dst ): bool {
	if ( ! is_dir( $src ) ) {
		return false;
	}
	if ( ! is_dir( $dst ) ) {
		mkdir( $dst, 0755, true );
	}
	$items = array_diff( scandir( $src ) ?: [], [ '.', '..' ] );
	foreach ( $items as $item ) {
		$s = $src . '/' . $item;
		$d = $dst . '/' . $item;
		if ( is_dir( $s ) ) {
			ezi_rcopy( $s, $d );
		} else {
			copy( $s, $d );
		}
	}
	return true;
}

/**
 * استخراج یک فایل zip به یک مسیر
 */
function ezi_unzip( string $zip_path, string $dest_dir ): bool {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return false;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return false;
	}
	if ( ! is_dir( $dest_dir ) ) {
		mkdir( $dest_dir, 0755, true );
	}
	$result = $zip->extractTo( $dest_dir );
	$zip->close();
	return $result;
}

/**
 * دانلود یک فایل با cURL یا fopen (هرکدام در دسترس بود)
 *
 * @return array{ok: bool, error: string}
 */
function ezi_download_file_verbose( string $url, string $dest_path ): array {
	// جلوگیری از kill شدن PHP توسط max_execution_time هاست در میانه دانلود
	@set_time_limit( 0 );
	if ( function_exists( 'ini_set' ) ) {
		@ini_set( 'max_execution_time', '0' );
	}

	if ( function_exists( 'curl_init' ) ) {
		$fp = fopen( $dest_path, 'w' );
		if ( ! $fp ) {
			return [ 'ok' => false, 'error' => 'امکان ساخت فایل موقت در سرور وجود ندارد (مشکل دسترسی نوشتن).' ];
		}

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_FILE, $fp );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 5 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );  // اتصال اولیه — اگر سرور در دسترس نیست زود خبر بده
		curl_setopt( $ch, CURLOPT_TIMEOUT, 90 );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Easy-Installer)' );

		// برخی هاست‌های اشتراکی CA bundle قدیمی/ناقص دارند که باعث
		// شکست بی‌صدای SSL handshake می‌شود. ابتدا با verify کامل تلاش
		// می‌کنیم؛ در صورت خطای مشخصاً SSL، یک بار با verify غیرفعال
		// تلاش مجدد می‌کنیم (فقط برای این دانلود عمومی و امن از wordpress.org).
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );

		$ok        = curl_exec( $ch );
		$curl_err  = curl_error( $ch );
		$curl_errno = curl_errno( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		// تلاش دوم در صورت خطای مرتبط با SSL (errno 35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91)
		$ssl_related = in_array( $curl_errno, [ 35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91 ], true );

		if ( ( ! $ok || $curl_errno ) && $ssl_related ) {
			ftruncate( $fp, 0 );
			rewind( $fp );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
			$ok        = curl_exec( $ch );
			$curl_err  = curl_error( $ch );
			$curl_errno = curl_errno( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		}

		curl_close( $ch );
		fclose( $fp );

		if ( ! $ok || $curl_errno || $http_code >= 400 ) {
			@unlink( $dest_path );
			$detail = $curl_err ?: ( $http_code ? "کد HTTP: {$http_code}" : 'نامشخص' );
			return [ 'ok' => false, 'error' => "خطای دانلود (cURL): {$detail}" ];
		}

		return [ 'ok' => true, 'error' => '' ];
	}

	if ( ini_get( 'allow_url_fopen' ) ) {
		$context = stream_context_create( [
			'http'  => [ 'timeout' => 90, 'user_agent' => 'Mozilla/5.0 (Easy-Installer)' ],
			'ssl'   => [ 'verify_peer' => true, 'verify_peer_name' => true ],
		] );

		$content = @file_get_contents( $url, false, $context );

		if ( false === $content ) {
			// تلاش دوم بدون verify (برای هاست‌های با CA bundle ناقص)
			$context2 = stream_context_create( [
				'http' => [ 'timeout' => 90, 'user_agent' => 'Mozilla/5.0 (Easy-Installer)' ],
				'ssl'  => [ 'verify_peer' => false, 'verify_peer_name' => false ],
			] );
			$content = @file_get_contents( $url, false, $context2 );
		}

		if ( false === $content ) {
			$err = error_get_last();
			return [ 'ok' => false, 'error' => 'خطای دانلود (fopen): ' . ( $err['message'] ?? 'نامشخص — احتمالاً allow_url_fopen محدود شده است' ) ];
		}

		if ( false === file_put_contents( $dest_path, $content ) ) {
			return [ 'ok' => false, 'error' => 'امکان ذخیره فایل دانلود شده در سرور وجود ندارد.' ];
		}

		return [ 'ok' => true, 'error' => '' ];
	}

	return [ 'ok' => false, 'error' => 'نه cURL و نه allow_url_fopen در این سرور فعال نیستند — دانلود خودکار ممکن نیست.' ];
}

/**
 * نسخه ساده (بدون جزئیات خطا) — برای حفظ سازگاری با کدهای قبلی
 */
function ezi_download_file( string $url, string $dest_path ): bool {
	return ezi_download_file_verbose( $url, $dest_path )['ok'];
}

/**
 * غیرفعال‌سازی کامل ارسال ایمیل وردپرس برای کل فرآیند نصب
 *
 * روی بسیاری از هاست‌های اشتراکی (به‌خصوص هاست‌های ایرانی)، تابع PHP
 * داخلی mail() کاملاً غیرفعال یا حذف شده است. اگر wp_install() یا
 * activate_plugin() تلاش کنند ایمیلی بفرستند (مثلاً ایمیل خوش‌آمدگویی
 * یا اعلان فعال‌سازی)، PHPMailer با خطای «Call to undefined function
 * mail()» به‌صورت Fatal از کار می‌افتد و کل فرآیند نصب متوقف می‌شود —
 * نه فقط ارسال آن ایمیل.
 *
 * این تابع باید بعد از لود وردپرس (یعنی بعد از require کردن wp-load.php)
 * و قبل از هر عملیاتی که می‌تواند ایمیل بفرستد صدا زده شود.
 */
function ezi_disable_wp_mail(): void {
	if ( function_exists( 'add_filter' ) ) {
		add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
	}
}

/**
 * تولید پسورد تصادفی امن
 */
function ezi_random_password( int $length = 16 ): string {
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%';
	$pass  = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$pass .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $pass;
}

/**
 * استخراج امن Host از درخواست فعلی
 *
 * $_SERVER['HTTP_HOST'] مستقیماً از هدر HTTP ارسالی توسط کلاینت خوانده
 * می‌شود و می‌تواند توسط هر کسی جعل شود (Host Header Injection). این مقدار
 * مستقیماً در آدرس siteurl/home سایت (و لینک‌های صفحه پایان نصب) استفاده
 * می‌شود، پس قبل از استفاده باید در قالب یک نام میزبان معتبر باشد؛ در غیر
 * این صورت به SERVER_NAME (که توسط پیکربندی خود وب‌سرور تعیین می‌شود، نه
 * کلاینت) بازمی‌گردیم.
 */
function ezi_safe_host(): string {
	$host = (string) ( $_SERVER['HTTP_HOST'] ?? '' );

	if ( $host && preg_match( '/^[a-zA-Z0-9.\-]+(:\d+)?$/', $host ) ) {
		return $host;
	}

	return (string) ( $_SERVER['SERVER_NAME'] ?? 'localhost' );
}

/**
 * خروجی JSON برای پاسخ‌های AJAX
 */
function ezi_json( bool $success, string $message = '', array $extra = [] ): array {
	return array_merge( [ 'success' => $success, 'message' => $message ], $extra );
}

/**
 * ذخیره/خوانش پیشرفت در فایل موقت (چون عملیات نصب چند مرحله AJAX دارد)
 */
function ezi_state_path(): string {
	return EZI_ROOT . '/installer/.state.json';
}

function ezi_save_state( array $data ): void {
	$existing = ezi_load_state();
	$merged   = array_merge( $existing, $data );
	file_put_contents( ezi_state_path(), json_encode( $merged, JSON_UNESCAPED_UNICODE ) );
}

function ezi_load_state(): array {
	$path = ezi_state_path();
	if ( ! file_exists( $path ) ) {
		return [];
	}
	$content = file_get_contents( $path );
	$data    = json_decode( (string) $content, true );
	return is_array( $data ) ? $data : [];
}
