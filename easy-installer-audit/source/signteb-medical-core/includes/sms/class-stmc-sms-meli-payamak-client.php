<?php
/**
 * SignTeb Medical Core — SMS Client (ملی‌پیامک)
 *
 * کلاینت سبک برای ارسال SMS از طریق API ملی‌پیامک.
 * بدون وابستگی خارجی — فقط wp_remote_post.
 *
 * Endpoint: https://api.payamak-panel.com/post/Send.asmx/SendSimpleSMS2
 * احراز هویت: username / password + شماره خط ارسال.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

final class MeliPayamakClient extends AbstractProvider {

	private const API_URL = 'https://api.payamak-panel.com/post/Send.asmx/SendSimpleSMS2';

	private string $username;
	private string $password;
	private string $sender_line;

	public function __construct() {
		$this->username    = (string) get_option( 'stmc_sms_username', '' );
		$this->password    = (string) get_option( 'stmc_sms_password', '' );
		$this->sender_line = (string) get_option( 'stmc_sms_sender_line', '' );
	}

	public function id(): string {
		return 'melipayamak';
	}

	public function label(): string {
		return 'ملی‌پیامک';
	}

	public function is_configured(): bool {
		return $this->sms_enabled()
			&& '' !== $this->username
			&& '' !== $this->password
			&& '' !== $this->sender_line;
	}

	/**
	 * ارسال یک پیامک ساده.
	 *
	 * @return array{success:bool, message:string, raw:string}
	 */
	public function send( string $to, string $text ): array {
		if ( ! $this->is_configured() ) {
			return $this->fail( 'SMS not configured' );
		}

		$to = $this->normalize_phone( $to );
		if ( '' === $to ) {
			return $this->fail( 'Invalid phone number' );
		}

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 15,
			'body'    => [
				'username' => $this->username,
				'password' => $this->password,
				'to'       => $to,
				'from'     => $this->sender_line,
				'text'     => $text,
				'isflash'  => 'false',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $this->fail( $response->get_error_message() );
		}

		$raw_body = wp_remote_retrieve_body( $response );

		// خروجی ملی‌پیامک معمولاً یک عدد RecId است (یا کد خطا منفی/صفر).
		$recld   = trim( wp_strip_all_tags( $raw_body ) );
		$success = is_numeric( $recld ) && (float) $recld > 0;

		return $success
			? $this->ok( $raw_body )
			: $this->fail( 'Error code: ' . $recld, $raw_body );
	}
}
