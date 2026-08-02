<?php
/**
 * SignTeb Medical Core — SMS Client (کاوه‌نگار / Kavenegar)
 *
 * ارسال SMS از طریق REST API کاوه‌نگار. کلید API در مسیر URL قرار می‌گیرد.
 * شماره خط ارسال (sender) اختیاری است — اگر خالی باشد، خط پیش‌فرض حساب
 * استفاده می‌شود.
 *
 * Endpoint: https://api.kavenegar.com/v1/{API_KEY}/sms/send.json
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

final class KavenegarClient extends AbstractProvider {

	private string $api_key;
	private string $sender_line;

	public function __construct() {
		$this->api_key     = (string) get_option( 'stmc_sms_api_key', '' );
		$this->sender_line = (string) get_option( 'stmc_sms_sender_line', '' );
	}

	public function id(): string {
		return 'kavenegar';
	}

	public function label(): string {
		return 'کاوه‌نگار';
	}

	public function is_configured(): bool {
		return $this->sms_enabled() && '' !== $this->api_key;
	}

	/**
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

		$url = 'https://api.kavenegar.com/v1/' . rawurlencode( $this->api_key ) . '/sms/send.json';

		$body = [
			'receptor' => $to,
			'message'  => $text,
		];
		if ( '' !== $this->sender_line ) {
			$body['sender'] = $this->sender_line;
		}

		$response = wp_remote_post( $url, [
			'timeout' => 15,
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return $this->fail( $response->get_error_message() );
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );

		$status = isset( $data['return']['status'] ) ? (int) $data['return']['status'] : 0;
		if ( 200 === $status ) {
			return $this->ok( $raw_body );
		}

		$msg = $data['return']['message'] ?? ( 'Error status: ' . $status );
		return $this->fail( (string) $msg, $raw_body );
	}
}
