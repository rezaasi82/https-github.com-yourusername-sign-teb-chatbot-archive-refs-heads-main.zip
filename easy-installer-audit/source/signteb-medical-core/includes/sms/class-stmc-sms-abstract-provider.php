<?php
/**
 * SignTeb Medical Core — Abstract SMS Provider
 *
 * منطق مشترک همهٔ درگاه‌ها: بررسی فعال بودن SMS، نرمال‌سازی شماره،
 * و helperهای ساخت پاسخ استاندارد. کلاینت‌های واقعی فقط send() و
 * is_configured() و متادیتای خود را پیاده می‌کنند.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

abstract class AbstractProvider implements ProviderInterface {

	/**
	 * آیا سوییچ اصلی SMS روشن است؟ (مستقل از کامل بودن اطلاعات درگاه)
	 */
	protected function sms_enabled(): bool {
		return '1' === get_option( 'stmc_sms_enabled', '0' );
	}

	/**
	 * نرمال‌سازی شماره به فرمت ۰۹xxxxxxxxx.
	 * ورودی‌های 98…، +98…، 9…، و 09… را می‌پذیرد؛ در غیر این صورت '' برمی‌گرداند.
	 */
	protected function normalize_phone( string $phone ): string {
		$phone = preg_replace( '/[^0-9]/', '', $phone ) ?? '';

		// +98 / 0098 → 0
		if ( str_starts_with( $phone, '0098' ) && 14 === strlen( $phone ) ) {
			$phone = '0' . substr( $phone, 4 );
		} elseif ( str_starts_with( $phone, '98' ) && 12 === strlen( $phone ) ) {
			$phone = '0' . substr( $phone, 2 );
		} elseif ( str_starts_with( $phone, '9' ) && 10 === strlen( $phone ) ) {
			// 9xxxxxxxxx بدون صفر ابتدایی
			$phone = '0' . $phone;
		}

		if ( ! str_starts_with( $phone, '09' ) || 11 !== strlen( $phone ) ) {
			return '';
		}

		return $phone;
	}

	/**
	 * پاسخ ناموفق استاندارد.
	 *
	 * @return array{success:bool, message:string, raw:string}
	 */
	protected function fail( string $message, string $raw = '' ): array {
		return [ 'success' => false, 'message' => $message, 'raw' => $raw ];
	}

	/**
	 * پاسخ موفق استاندارد.
	 *
	 * @return array{success:bool, message:string, raw:string}
	 */
	protected function ok( string $raw = '' ): array {
		return [ 'success' => true, 'message' => 'Sent', 'raw' => $raw ];
	}
}
