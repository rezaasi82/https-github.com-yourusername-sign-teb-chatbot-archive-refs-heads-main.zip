<?php
/**
 * SignTeb Setup Wizard — Environment Check (مرحله ۱)
 *
 * بررسی پیش‌نیازهای سرور برای راه‌اندازی بی‌نقص: نسخه‌ی PHP، حافظه، حجم آپلود،
 * زمان اجرا، و افزونه‌های لازم PHP. منطق خالص و قابل‌تست (بدون I/O وردپرس در
 * هسته‌ی محاسبات؛ فقط خواندن ini/نسخه).
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard\Setup;

defined( 'ABSPATH' ) || exit;

final class EnvironmentCheck {

	private const MIN_PHP        = '8.1';
	private const MIN_WP         = '6.4';
	private const MIN_MEMORY_MB  = 128;
	private const MIN_UPLOAD_MB  = 16;
	private const MIN_EXEC_SEC   = 30;
	private const REQUIRED_EXT   = [ 'mbstring', 'json', 'gd', 'curl', 'dom' ];

	/**
	 * اجرای همه‌ی بررسی‌ها.
	 *
	 * @return array{checks: array<int,array>, pass: bool}
	 */
	public function run(): array {
		$checks = [];

		// PHP version
		$php_ok  = version_compare( PHP_VERSION, self::MIN_PHP, '>=' );
		$checks[] = $this->row( 'php', 'نسخه PHP', PHP_VERSION, $php_ok, true, 'حداقل ' . self::MIN_PHP );

		// WordPress version
		$wp_ver  = get_bloginfo( 'version' );
		$wp_ok   = version_compare( $wp_ver, self::MIN_WP, '>=' );
		$checks[] = $this->row( 'wp', 'نسخه وردپرس', $wp_ver, $wp_ok, true, 'حداقل ' . self::MIN_WP );

		// Memory limit
		$mem_bytes = $this->parse_size( (string) ini_get( 'memory_limit' ) );
		$mem_ok    = -1 === $mem_bytes || $mem_bytes >= self::MIN_MEMORY_MB * 1024 * 1024;
		$checks[]  = $this->row( 'memory', 'حافظه PHP', ini_get( 'memory_limit' ), $mem_ok, false, 'پیشنهاد ' . self::MIN_MEMORY_MB . 'M' );

		// Upload max filesize
		$up_bytes = $this->parse_size( (string) ini_get( 'upload_max_filesize' ) );
		$up_ok    = $up_bytes >= self::MIN_UPLOAD_MB * 1024 * 1024;
		$checks[] = $this->row( 'upload', 'حداکثر حجم آپلود', ini_get( 'upload_max_filesize' ), $up_ok, false, 'پیشنهاد ' . self::MIN_UPLOAD_MB . 'M' );

		// Max execution time
		$exec     = (int) ini_get( 'max_execution_time' );
		$exec_ok  = 0 === $exec || $exec >= self::MIN_EXEC_SEC;
		$checks[] = $this->row( 'exec', 'حداکثر زمان اجرا', 0 === $exec ? 'نامحدود' : $exec . 's', $exec_ok, false, 'پیشنهاد ' . self::MIN_EXEC_SEC . 's+' );

		// Required extensions
		foreach ( self::REQUIRED_EXT as $ext ) {
			$loaded   = extension_loaded( $ext );
			$checks[] = $this->row( 'ext_' . $ext, 'افزونه PHP: ' . $ext, $loaded ? 'فعال' : 'غیرفعال', $loaded, in_array( $ext, [ 'mbstring', 'json' ], true ), '' );
		}

		// نتیجه‌ی کلی: فقط شکستِ موارد fatal مانع ادامه است.
		$pass = true;
		foreach ( $checks as $c ) {
			if ( $c['fatal'] && ! $c['pass'] ) {
				$pass = false;
			}
		}

		return [ 'checks' => $checks, 'pass' => $pass ];
	}

	private function row( string $id, string $label, $value, bool $pass, bool $fatal, string $hint ): array {
		return compact( 'id', 'label', 'value', 'pass', 'fatal', 'hint' );
	}

	/** تبدیل مقدار ini مثل «128M» به بایت. */
	public function parse_size( string $size ): int {
		$size = trim( $size );
		if ( '-1' === $size ) {
			return -1;
		}
		$unit  = strtolower( substr( $size, -1 ) );
		$value = (int) $size;
		return match ( $unit ) {
			'g'     => $value * 1024 * 1024 * 1024,
			'm'     => $value * 1024 * 1024,
			'k'     => $value * 1024,
			default => $value,
		};
	}
}
