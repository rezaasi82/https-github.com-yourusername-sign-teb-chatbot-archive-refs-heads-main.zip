<?php
/**
 * SignTeb Medical Core — SMS Provider Factory
 *
 * نقطهٔ واحد ساخت درگاه پیامک بر اساس option انتخاب‌شده توسط ادمین.
 * افزودن درگاه جدید فقط با اضافه‌کردن یک ردیف به map و ساخت کلاس کلاینت آن.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

final class ProviderFactory {

	public const DEFAULT_PROVIDER = 'melipayamak';

	/**
	 * فهرست درگاه‌های موجود: id => label.
	 *
	 * @return array<string, string>
	 */
	public static function list(): array {
		$map = [];
		foreach ( self::classes() as $id => $class ) {
			/** @var ProviderInterface $probe */
			$probe      = new $class();
			$map[ $id ] = $probe->label();
		}

		/**
		 * فیلتر برای افزودن/تغییر فهرست درگاه‌های پیامک.
		 *
		 * @param array<string, string> $map
		 */
		return apply_filters( 'stmc_sms_providers', $map );
	}

	/**
	 * ساخت یک درگاه با id مشخص. اگر id ناشناخته باشد، درگاه پیش‌فرض ساخته می‌شود.
	 */
	public static function make( string $id ): ProviderInterface {
		$classes = self::classes();
		$class   = $classes[ $id ] ?? $classes[ self::DEFAULT_PROVIDER ];

		return new $class();
	}

	/**
	 * درگاه فعال فعلی (طبق option ذخیره‌شده).
	 */
	public static function current(): ProviderInterface {
		$id = (string) get_option( 'stmc_sms_provider', self::DEFAULT_PROVIDER );
		return self::make( $id );
	}

	/**
	 * نگاشت id => نام کلاس کلاینت.
	 *
	 * @return array<string, class-string<ProviderInterface>>
	 */
	private static function classes(): array {
		return [
			'melipayamak' => MeliPayamakClient::class,
			'kavenegar'   => KavenegarClient::class,
			'smsir'       => SmsirClient::class,
			'ghasedak'    => GhasedakClient::class,
		];
	}
}
