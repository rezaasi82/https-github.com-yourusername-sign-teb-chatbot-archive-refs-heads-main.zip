<?php
/**
 * SignTeb Medical Core — SMS Client (ملی‌پیامک)
 *
 * کلاینت سبک برای ارسال SMS از طریق API ملی‌پیامک.
 * بدون وابستگی خارجی — فقط wp_remote_post.
 *
 * Endpoint: https://api.payamak-panel.com/post/Send.asmx/SendSimpleSMS2
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

final class MeliPayamakClient {

	private const API_URL = 'https://api.payamak-panel.com/post/Send.asmx/SendSimpleSMS2';

	private string $username;
	private string $password;
	private string $sender_line;

	public function __construct() {
		$this->username    = (string) get_option( 'stmc_sms_username', '' );
		$this->password    = (string) get_option( 'stmc_sms_password', '' );
		$this->sender_line = (string) get_option( 'stmc_sms_sender_line', '' );
	}

	/**
	 * آیا تنظیمات SMS کامل و فعال است؟
	 */
	public function is_configured(): bool {
		return '1' === get_option( 'stmc_sms_enabled', '0' )
			&& $this->username && $this->password && $this->sender_line;
	}

	/**
	 * ارسال یک پیامک ساده
	 *
	 * @return array{success:bool, message:string, raw:string}
	 */
	public function send( string $to, string $text ): array {
		if ( ! $this->is_configured() ) {
			return [ 'success' => false, 'message' => 'SMS not configured', 'raw' => '' ];
		}

		$to = $this->normalize_phone( $to );
		if ( ! $to ) {
			return [ 'success' => false, 'message' => 'Invalid phone number', 'raw' => '' ];
		}

		$body = [
			'username' => $this->username,
			'password' => $this->password,
			'to'       => $to,
			'from'     => $this->sender_line,
			'text'     => $text,
			'isflash'  => 'false',
		];

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 15,
			'body'    => $body, // x_www_form_urlencoded به صورت پیش‌فرض در wp_remote_post
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
				'raw'     => '',
			];
		}

		$raw_body = wp_remote_retrieve_body( $response );

		// خروجی ملی‌پیامک معمولاً یک عدد Recld است (یا کد خطا منفی)
		$recld = trim( wp_strip_all_tags( $raw_body ) );
		$success = is_numeric( $recld ) && (float) $recld > 0;

		return [
			'success' => $success,
			'message' => $success ? 'Sent' : ( 'Error code: ' . $recld ),
			'raw'     => $raw_body,
		];
	}

	/**
	 * نرمال‌سازی شماره به فرمت ۰۹xxxxxxxxx که ملی‌پیامک انتظار دارد
	 */
	private function normalize_phone( string $phone ): string {
		$phone = preg_replace( '/[^0-9]/', '', $phone ) ?? '';

		if ( str_starts_with( $phone, '98' ) && 12 === strlen( $phone ) ) {
			$phone = '0' . substr( $phone, 2 );
		}

		if ( ! str_starts_with( $phone, '09' ) || 11 !== strlen( $phone ) ) {
			return '';
		}

		return $phone;
	}
}
