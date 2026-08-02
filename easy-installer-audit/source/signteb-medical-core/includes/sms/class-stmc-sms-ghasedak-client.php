<?php
/**
 * SignTeb Medical Core — SMS Client (قاصدک / Ghasedak)
 *
 * ارسال SMS از طریق REST API نسخهٔ ۲ سرویس قاصدک.
 * احراز هویت با هدر apikey؛ شماره خط ارسال (linenumber) اختیاری است.
 *
 * Endpoint: https://api.ghasedak.me/v2/sms/send/simple
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

final class GhasedakClient extends AbstractProvider {

	private const API_URL = 'https://api.ghasedak.me/v2/sms/send/simple';

	private string $api_key;
	private string $sender_line;

	public function __construct() {
		$this->api_key     = (string) get_option( 'stmc_sms_api_key', '' );
		$this->sender_line = (string) get_option( 'stmc_sms_sender_line', '' );
	}

	public function id(): string {
		return 'ghasedak';
	}

	public function label(): string {
		return 'قاصدک';
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

		$body = [
			'message'  => $text,
			'receptor' => $to,
		];
		if ( '' !== $this->sender_line ) {
			$body['linenumber'] = $this->sender_line;
		}

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 15,
			'headers' => [
				'apikey'       => $this->api_key,
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Accept'       => 'application/json',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return $this->fail( $response->get_error_message() );
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );

		// قاصدک v2: result.code === 200 یعنی موفق.
		$code = isset( $data['result']['code'] ) ? (int) $data['result']['code'] : 0;
		if ( 200 === $code ) {
			return $this->ok( $raw_body );
		}

		$msg = $data['result']['message'] ?? ( 'Error code: ' . $code );
		return $this->fail( (string) $msg, $raw_body );
	}
}
