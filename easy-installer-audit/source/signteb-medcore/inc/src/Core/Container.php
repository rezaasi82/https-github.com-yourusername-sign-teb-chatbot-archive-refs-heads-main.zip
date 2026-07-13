<?php
/**
 * SignTeb MedCore — Lightweight Service Container (DI)
 *
 * یک Container کوچک و بدون وابستگی برای Dependency Injection. هدف: تزریق و
 * مدیریت چرخه‌ی حیات سرویس‌ها بدون آوردن یک فریم‌ورک سنگین. کافی برای نیاز یک
 * قالب: bind (نمونه‌ی تازه در هر بار)، singleton (نمونه‌ی مشترک)، و make.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Core;

defined( 'ABSPATH' ) || exit;

final class Container {

	/** @var array<string,callable> کارخانه‌های ثبت‌شده */
	private array $bindings = [];

	/** @var array<string,bool> کدام binding به‌صورت singleton است */
	private array $shared = [];

	/** @var array<string,mixed> نمونه‌های ساخته‌شده‌ی singleton */
	private array $instances = [];

	/**
	 * ثبت یک سرویس با نمونه‌ی تازه در هر make.
	 *
	 * @param string   $id      شناسه (معمولاً نام کلاس).
	 * @param callable $factory تابعی که سرویس را می‌سازد و برمی‌گرداند.
	 */
	public function bind( string $id, callable $factory ): void {
		$this->bindings[ $id ] = $factory;
		$this->shared[ $id ]   = false;
		unset( $this->instances[ $id ] );
	}

	/**
	 * ثبت یک سرویس اشتراکی (singleton) — فقط یک‌بار ساخته می‌شود.
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->bindings[ $id ] = $factory;
		$this->shared[ $id ]   = true;
		unset( $this->instances[ $id ] );
	}

	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] );
	}

	/**
	 * ساخت/دریافت یک سرویس.
	 *
	 * @throws \RuntimeException اگر سرویس ثبت نشده باشد.
	 */
	public function make( string $id ): mixed {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->bindings[ $id ] ) ) {
			throw new \RuntimeException( sprintf( 'Service "%s" is not registered in the container.', $id ) );
		}

		$object = ( $this->bindings[ $id ] )( $this );

		if ( ! empty( $this->shared[ $id ] ) ) {
			$this->instances[ $id ] = $object;
		}

		return $object;
	}
}
