<?php
/**
 * SignTeb MedCore — Logger & Debug
 *
 * سیستم لاگ سبک و بی‌خطر برای ثبت خطاهای قالب بدون وابستگی به هیچ ماژول دیگر.
 * این فایل باید اولین ماژول بارگذاری‌شده باشد تا بقیه‌ی بوت‌استرپ بتوانند از آن
 * برای گزارش خطا استفاده کنند.
 *
 * حالت دیباگ: با ثابت MEDCORE_DEBUG یا فیلتر `medcore_debug` کنترل می‌شود؛ اگر
 * فعال نباشد، فقط از error_log استاندارد PHP استفاده می‌شود (بی‌صدا برای کاربر).
 *
 * @package SignTeb_MedCore
 */

defined( 'ABSPATH' ) || exit;

final class MedCore_Logger {

	/** @var string|null مسیر فایل لاگ اختصاصی قالب (کش‌شده) */
	private static ?string $log_file = null;

	/**
	 * آیا حالت دیباگ قالب فعال است؟
	 *
	 * اولویت: ثابت MEDCORE_DEBUG → فیلتر medcore_debug → WP_DEBUG
	 */
	public static function is_debug(): bool {
		if ( defined( 'MEDCORE_DEBUG' ) ) {
			return (bool) MEDCORE_DEBUG;
		}
		$wp_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		return (bool) apply_filters( 'medcore_debug', $wp_debug );
	}

	/**
	 * ثبت یک پیام در لاگ.
	 *
	 * @param string $message متن پیام.
	 * @param string $level   یکی از: error | warning | info.
	 * @param array  $context داده‌ی اضافی برای عیب‌یابی.
	 */
	public static function log( string $message, string $level = 'error', array $context = [] ): void {
		$line = sprintf(
			'[SignTeb MedCore][%s] %s%s',
			strtoupper( $level ),
			$message,
			$context ? ' ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : ''
		);

		// همیشه در error_log استاندارد PHP ثبت می‌شود (بی‌صدا).
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// در حالت دیباگ، در یک فایل اختصاصی و محافظت‌شده هم ثبت می‌شود.
		if ( self::is_debug() ) {
			self::write_to_file( $line );
		}
	}

	/**
	 * نوشتن امن در فایل لاگ اختصاصی داخل uploads (با محافظت .htaccess).
	 */
	private static function write_to_file( string $line ): void {
		$file = self::log_file();
		if ( ! $file ) {
			return;
		}
		// @ برای جلوگیری از هرگونه warning در صورت مشکل مجوز — لاگ نباید خودش خطا بسازد.
		@file_put_contents( $file, gmdate( 'Y-m-d H:i:s' ) . ' ' . $line . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore
	}

	/**
	 * مسیر فایل لاگ اختصاصی؛ در اولین فراخوانی پوشه و محافظ آن ساخته می‌شود.
	 */
	private static function log_file(): ?string {
		if ( null !== self::$log_file ) {
			return self::$log_file ?: null;
		}

		if ( ! function_exists( 'wp_upload_dir' ) ) {
			self::$log_file = '';
			return null;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			self::$log_file = '';
			return null;
		}

		$dir = $uploads['basedir'] . '/signteb-logs';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// جلوگیری از دسترسی مستقیم به لاگ‌ها از طریق مرورگر.
			@file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
			@file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore
		}

		self::$log_file = $dir . '/medcore-' . gmdate( 'Y-m' ) . '.log';
		return self::$log_file;
	}
}
