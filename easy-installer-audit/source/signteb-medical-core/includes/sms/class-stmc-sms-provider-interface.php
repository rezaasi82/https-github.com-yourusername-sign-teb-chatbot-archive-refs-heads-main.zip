<?php
/**
 * SignTeb Medical Core — SMS Provider Interface
 *
 * قرارداد مشترک همهٔ درگاه‌های پیامک. افزودن پنل جدید فقط با ساختن یک
 * کلاس تازه که این interface را implement کند انجام می‌شود — بدون تغییر
 * در Notifier یا تنظیمات ادمین.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

interface ProviderInterface {

	/**
	 * شناسهٔ یکتای درگاه (برای ذخیره در option). مثال: 'melipayamak'.
	 */
	public function id(): string;

	/**
	 * برچسب قابل نمایش به کاربر. مثال: 'ملی‌پیامک'.
	 */
	public function label(): string;

	/**
	 * آیا درگاه فعال و اطلاعات آن کامل است؟
	 */
	public function is_configured(): bool;

	/**
	 * ارسال یک پیامک ساده.
	 *
	 * @return array{success:bool, message:string, raw:string}
	 */
	public function send( string $to, string $text ): array;
}
