<?php
/**
 * SignTeb MedCore — PSR-4 Autoloader
 *
 * لودر سبک و مستقل برای فضای‌نام SignTeb\MedCore\. این فایل خودش نمی‌تواند
 * autoload شود (چون خودِ لودر است) و باید مستقیماً require شود؛ بقیه‌ی
 * کلاس‌های namespace‌دار پس از register شدن این لودر، خودکار بارگذاری می‌شوند.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	private string $prefix;
	private string $base_dir;
	private int $prefix_len;

	/**
	 * @param string $prefix   پیشوند فضای‌نام، مثل 'SignTeb\MedCore\'.
	 * @param string $base_dir پوشه‌ی ریشه‌ی PSR-4 (با اسلش انتهایی).
	 */
	public function __construct( string $prefix, string $base_dir ) {
		$this->prefix     = ltrim( $prefix, '\\' );
		$this->prefix_len = strlen( $this->prefix );
		$this->base_dir   = rtrim( $base_dir, '/\\' ) . '/';
	}

	public function register(): void {
		spl_autoload_register( [ $this, 'load' ] );
	}

	/**
	 * تبدیل نام کامل کلاس به مسیر فایل و بارگذاری آن.
	 */
	public function load( string $class ): void {
		$class = ltrim( $class, '\\' );

		// فقط کلاس‌های متعلق به این namespace را مدیریت می‌کنیم.
		if ( 0 !== strncmp( $this->prefix, $class, $this->prefix_len ) ) {
			return;
		}

		$relative = substr( $class, $this->prefix_len );
		$relative = str_replace( '\\', '/', $relative );
		$file     = $this->base_dir . $relative . '.php';

		// جلوگیری از path traversal — فایل باید واقعاً زیر base_dir باشد.
		$real_base = realpath( $this->base_dir );
		$real_file = realpath( $file );

		if ( false === $real_file || false === $real_base || 0 !== strncmp( $real_base, $real_file, strlen( $real_base ) ) ) {
			return;
		}

		require $real_file;
	}
}
