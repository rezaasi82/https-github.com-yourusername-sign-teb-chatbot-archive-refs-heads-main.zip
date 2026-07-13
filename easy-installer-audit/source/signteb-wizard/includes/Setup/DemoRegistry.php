<?php
/**
 * SignTeb Setup Wizard — Demo Registry (کشف دموها از فایل)
 *
 * هر دمو یک پوشه در `demos/<slug>/` با فایل `demo.php` است که یک آرایه‌ی تعریف
 * برمی‌گرداند. افزودن دموی جدید = افزودن یک پوشه، بدون تغییر در کد (مقیاس‌پذیر و
 * مناسب محصول تجاری).
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard\Setup;

defined( 'ABSPATH' ) || exit;

final class DemoRegistry {

	/** @var array<string,array>|null کش تعریف‌ها */
	private static ?array $cache = null;

	/** پوشه‌ی ریشه‌ی دموها. */
	private function demos_dir(): string {
		return STWIZ_DIR . 'demos/';
	}

	/**
	 * همه‌ی تعریف‌های دمو، کلیددار با id.
	 *
	 * @return array<string,array>
	 */
	public function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$out = [];
		$dir = $this->demos_dir();

		foreach ( glob( $dir . '*/demo.php' ) ?: [] as $file ) {
			$def = $this->load_file( $file );
			if ( is_array( $def ) && ! empty( $def['id'] ) ) {
				$out[ (string) $def['id'] ] = $def;
			}
		}

		// مرتب‌سازی بر اساس order سپس عنوان، برای نمایش پایدار.
		uasort( $out, static function ( $a, $b ) {
			return ( $a['order'] ?? 100 ) <=> ( $b['order'] ?? 100 );
		} );

		self::$cache = $out;
		return $out;
	}

	public function get( string $id ): ?array {
		return $this->all()[ $id ] ?? null;
	}

	public function has( string $id ): bool {
		return isset( $this->all()[ $id ] );
	}

	/** @return string[] */
	public function ids(): array {
		return array_keys( $this->all() );
	}

	/**
	 * بارگذاری امن یک فایل تعریف. هر خطای فایل نباید کل ویزارد را بشکند.
	 */
	private function load_file( string $file ): mixed {
		if ( ! is_file( $file ) ) {
			return null;
		}
		try {
			return require $file;
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
