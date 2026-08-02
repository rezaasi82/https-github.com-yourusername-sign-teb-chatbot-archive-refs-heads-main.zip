<?php
/**
 * SignTeb Medical Core — SMS Client (SMS.ir)
 *
 * ارسال SMS از طریق REST API نسخهٔ ۱ سرویس SMS.ir.
 * احراز هویت با هدر X-API-KEY و بدنهٔ JSON؛ نیازمند شماره خط ارسال (lineNumber).
 *
 * Endpoint: https://api.sms.ir/v1/send/bulk
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

defined( 'ABSPATH' ) || exit;

final class SmsirClient extends AbstractProvider {

	private const API_URL = 'https://api.sms.ir/v1/send/bulk';

	private string $api_key;
	private string $sender_line;

	public function __construct() {
		$this->api_key     = (string) get_option( 'stmc_sms_api_key', '' );
		$this->sender_line = (string) get_option( 'stmc_sms_sender_line', '' );
	}

	public function id(): string {
		return 'smsir';
	}

	public function label(): string {
		return 'اس‌ام‌اس دات آی‌آر (SMS.ir)';
	}

	public function is_configured(): bool {
		return $this->sms_enabled() && '' !== $this->api_key && '' !== $this->sender_line;
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

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 15,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'X-API-KEY'    => $this->api_key,
			],
			'body'    => wp_json_encode( [
				'lineNumber'  => $this->sender_line,
				'messageText' => $text,
				'mobiles'     => [ $to ],
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return $this->fail( $response->get_error_message() );
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );

		// SMS.ir v1: status === 1 یعنی موفق.
		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;
		if ( 1 === $status ) {
			return $this->ok( $raw_body );
		}

		$msg = $data['message'] ?? ( 'Error status: ' . $status );
		return $this->fail( (string) $msg, $raw_body );
	}
}
