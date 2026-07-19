<?php
/**
 * SignTeb Fatty Liver Calculator — Dynamic SMS Gateway
 *
 * A provider-agnostic SMS layer that supports:
 *   • Switchable providers   (Kavenegar / SMS.ir / MeliPayamak / Custom webhook)
 *   • Pattern/Template SMS   (Body Pattern with {0}/{NAME} token replacement)
 *   • Per-recipient patterns  (OTP, Admin, and a dedicated Secretary line)
 *
 * Extend or override entirely with the `signteb_liver_send_sms` filter:
 *   add_filter( 'signteb_liver_send_sms', function ( $pre, $receptor, $pattern, $tokens, $text, $provider ) {
 *       // return true|false to short-circuit, or null to fall through.
 *       return $pre;
 *   }, 10, 6 );
 *
 * @package SignTeb_Liver
 */

defined( 'ABSPATH' ) || exit;

class SignTeb_Liver_SMS {

	/** @var string Active provider slug */
	private $provider;

	private function __construct() {
		$this->provider = get_option( 'signteb_liver_sms_provider', 'kavenegar' );
	}

	/** Singleton */
	public static function instance() {
		static $inst = null;
		if ( null === $inst ) {
			$inst = new self();
		}
		return $inst;
	}

	/** List of supported providers (slug => human label) */
	public static function providers() {
		return [
			'kavenegar'   => 'کاوه‌نگار (Kavenegar)',
			'smsir'       => 'اس‌ام‌اس دات آی‌آر (SMS.ir)',
			'melipayamak' => 'ملی‌پیامک (MeliPayamak)',
			'custom'      => 'وب‌هوک سفارشی (Custom Webhook)',
		];
	}

	/** Read a prefixed plugin option */
	private function opt( $key, $default = '' ) {
		return trim( (string) get_option( 'signteb_liver_sms_' . $key, $default ) );
	}

	/**
	 * Render a Body Pattern: replace {0}/{1}… (positional) and {NAME} (named)
	 * tokens with their values.
	 *
	 * @param string $pattern Pattern text, e.g. "کد تأیید شما {0} می‌باشد".
	 * @param array  $tokens  Associative or indexed token map.
	 */
	public static function render( $pattern, array $tokens ) {
		$vals = array_values( $tokens );
		return preg_replace_callback(
			'/\{([A-Za-z0-9_]+)\}/u',
			function ( $m ) use ( $tokens, $vals ) {
				$k = $m[1];
				if ( is_numeric( $k ) ) {
					return isset( $vals[ (int) $k ] ) ? $vals[ (int) $k ] : '';
				}
				return isset( $tokens[ $k ] ) ? $tokens[ $k ] : '';
			},
			(string) $pattern
		);
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * PUBLIC API
	 * ───────────────────────────────────────────────────────────────────── */

	/** Send a one-time verification code. */
	public function send_otp( $receptor, $code ) {
		$pattern = $this->opt( 'otp_pattern', $this->opt( 'otp_template' ) ); // back-compat
		$text    = $this->opt( 'otp_text', 'کد تأیید شما {0} می‌باشد. لطفاً آن را در اختیار دیگران قرار ندهید' );
		return $this->dispatch( $receptor, $pattern, [ 'CODE' => $code ], $text );
	}

	/**
	 * Notify the clinic about a new lead. Sends to the admin line AND the
	 * secretary line — each with its OWN pattern/template & body text.
	 *
	 * @param array $tokens [ 'NAME'=>…, 'MOBILE'=>…, 'GRADE'=>…, 'TYPE'=>… ]
	 * @return bool True if at least one message was accepted.
	 */
	public function notify_lead( array $tokens ) {
		$sent = false;

		$admin = preg_replace( '/[^0-9]/', '', $this->opt( 'admin_mobile' ) );
		if ( $admin ) {
			$sent = $this->dispatch(
				$admin,
				$this->opt( 'admin_pattern' ),
				$tokens,
				$this->opt( 'admin_text', 'درخواست مشاوره جدید. نام: {NAME} - موبایل: {MOBILE} - گرید: {GRADE}' )
			) || $sent;
		}

		$secretary = preg_replace( '/[^0-9]/', '', $this->opt( 'secretary_mobile' ) );
		if ( $secretary ) {
			$sent = $this->dispatch(
				$secretary,
				$this->opt( 'secretary_pattern' ),
				$tokens,
				$this->opt( 'secretary_text', 'بیمار جدید جهت پیگیری. نام: {NAME} - موبایل: {MOBILE} - گرید: {GRADE}' )
			) || $sent;
		}

		return $sent;
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * CORE DISPATCHER
	 * ───────────────────────────────────────────────────────────────────── */

	/**
	 * @param string $receptor     Destination mobile (09…).
	 * @param string $pattern      Registered pattern/template code or id (provider-specific). Empty → plain text.
	 * @param array  $tokens       Token map for the pattern / body text.
	 * @param string $text_pattern Fallback Body Pattern text (used when no pattern code, or for custom/plain).
	 */
	private function dispatch( $receptor, $pattern, array $tokens, $text_pattern ) {
		// Full override hook — return non-null bool to short-circuit.
		$pre = apply_filters( 'signteb_liver_send_sms', null, $receptor, $pattern, $tokens, $text_pattern, $this->provider );
		if ( null !== $pre ) {
			return (bool) $pre;
		}
		if ( ! $receptor ) {
			return false;
		}

		switch ( $this->provider ) {
			case 'smsir':
				return $this->via_smsir( $receptor, $pattern, $tokens, $text_pattern );
			case 'melipayamak':
				return $this->via_melipayamak( $receptor, $pattern, $tokens, $text_pattern );
			case 'custom':
				return $this->via_custom( $receptor, $pattern, $tokens, $text_pattern );
			case 'kavenegar':
			default:
				return $this->via_kavenegar( $receptor, $pattern, $tokens, $text_pattern );
		}
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * PROVIDER: KAVENEGAR
	 * ───────────────────────────────────────────────────────────────────── */

	private function via_kavenegar( $receptor, $pattern, array $tokens, $text_pattern ) {
		$api = $this->opt( 'api_key' );
		if ( ! $api ) {
			return false;
		}

		if ( $pattern ) {
			// verify/lookup — Kavenegar tokens cannot contain spaces/newlines.
			$body = [ 'receptor' => $receptor, 'template' => $pattern ];
			$keys = [ 'token', 'token2', 'token3', 'token10', 'token20' ];
			$i    = 0;
			foreach ( array_values( $tokens ) as $val ) {
				if ( ! isset( $keys[ $i ] ) ) {
					break;
				}
				$body[ $keys[ $i ] ] = str_replace( [ ' ', "\n", "\r" ], [ '‌', '؛', '' ], $val );
				$i++;
			}
			$url = 'https://api.kavenegar.com/v1/' . rawurlencode( $api ) . '/verify/lookup.json';
			return $this->kavenegar_ok( wp_remote_post( $url, [ 'timeout' => 20, 'body' => $body ] ) );
		}

		$url = 'https://api.kavenegar.com/v1/' . rawurlencode( $api ) . '/sms/send.json';
		return $this->kavenegar_ok( wp_remote_post( $url, [
			'timeout' => 20,
			'body'    => array_filter( [
				'receptor' => $receptor,
				'sender'   => $this->opt( 'sender' ),
				'message'  => self::render( $text_pattern, $tokens ),
			] ),
		] ) );
	}

	private function kavenegar_ok( $resp ) {
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$body   = json_decode( wp_remote_retrieve_body( $resp ), true );
		$status = isset( $body['return']['status'] ) ? (int) $body['return']['status'] : 0;
		return ( 200 === $status );
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * PROVIDER: SMS.ir  (v1 — header X-API-KEY)
	 * ───────────────────────────────────────────────────────────────────── */

	private function via_smsir( $receptor, $pattern, array $tokens, $text_pattern ) {
		$api = $this->opt( 'api_key' );
		if ( ! $api ) {
			return false;
		}

		if ( $pattern ) {
			// /v1/send/verify — templateId + named parameters.
			$params = [];
			foreach ( $tokens as $name => $value ) {
				$params[] = [ 'name' => (string) $name, 'value' => (string) $value ];
			}
			$resp = wp_remote_post( 'https://api.sms.ir/v1/send/verify', [
				'timeout' => 20,
				'headers' => [ 'X-API-KEY' => $api, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
				'body'    => wp_json_encode( [
					'mobile'     => $receptor,
					'templateId' => (int) $pattern,
					'parameters' => $params,
				] ),
			] );
			return $this->smsir_ok( $resp );
		}

		// /v1/send/bulk — plain text.
		$resp = wp_remote_post( 'https://api.sms.ir/v1/send/bulk', [
			'timeout' => 20,
			'headers' => [ 'X-API-KEY' => $api, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
			'body'    => wp_json_encode( [
				'lineNumber'  => $this->opt( 'sender' ),
				'messageText' => self::render( $text_pattern, $tokens ),
				'mobiles'     => [ $receptor ],
			] ),
		] );
		return $this->smsir_ok( $resp );
	}

	private function smsir_ok( $resp ) {
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		$st   = isset( $body['status'] ) ? (int) $body['status'] : 0;
		return ( 200 === $code && 1 === $st );
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * PROVIDER: MELIPAYAMAK
	 * ───────────────────────────────────────────────────────────────────── */

	private function via_melipayamak( $receptor, $pattern, array $tokens, $text_pattern ) {
		$user = $this->opt( 'api_key' );    // username
		$pass = $this->opt( 'api_secret' ); // password
		if ( ! $user || ! $pass ) {
			return false;
		}

		if ( $pattern ) {
			// Shared/pattern endpoint — args are semicolon-joined in template order.
			$args = implode( ';', array_map(
				function ( $v ) { return str_replace( ';', '،', $v ); },
				array_values( $tokens )
			) );
			$resp = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', [
				'timeout' => 20,
				'body'    => [
					'username' => $user,
					'password' => $pass,
					'text'     => $args,
					'to'       => $receptor,
					'bodyId'   => (int) $pattern,
				],
			] );
			return $this->melipayamak_ok( $resp );
		}

		$resp = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
			'timeout' => 20,
			'body'    => [
				'username' => $user,
				'password' => $pass,
				'from'     => $this->opt( 'sender' ),
				'to'       => $receptor,
				'text'     => self::render( $text_pattern, $tokens ),
			],
		] );
		return $this->melipayamak_ok( $resp );
	}

	private function melipayamak_ok( $resp ) {
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		// RetStatus === 1 (pattern API) or a long numeric Value (plain) signals success.
		if ( isset( $body['RetStatus'] ) ) {
			return ( 1 === (int) $body['RetStatus'] );
		}
		if ( isset( $body['Value'] ) ) {
			return ( strlen( (string) $body['Value'] ) > 5 );
		}
		return false;
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * PROVIDER: CUSTOM WEBHOOK
	 * ───────────────────────────────────────────────────────────────────── */

	private function via_custom( $receptor, $pattern, array $tokens, $text_pattern ) {
		$url = $this->opt( 'custom_url' );
		if ( ! $url ) {
			return false;
		}

		$message = self::render( $text_pattern, $tokens );

		// Placeholders the admin can use in the URL/body template.
		$repl = [
			'{api_key}' => rawurlencode( $this->opt( 'api_key' ) ),
			'{sender}'  => rawurlencode( $this->opt( 'sender' ) ),
			'{to}'      => rawurlencode( $receptor ),
			'{pattern}' => rawurlencode( $pattern ),
			'{message}' => rawurlencode( $message ),
		];
		$url = strtr( $url, $repl );

		$method   = strtoupper( $this->opt( 'custom_method', 'POST' ) ) === 'GET' ? 'GET' : 'POST';
		$body_tpl = $this->opt( 'custom_body' );

		$args = [ 'timeout' => 20 ];
		if ( 'POST' === $method && $body_tpl ) {
			// Raw (non-encoded) replacements for the JSON body template.
			$args['headers'] = [ 'Content-Type' => 'application/json' ];
			$args['body']    = strtr( $body_tpl, [
				'{api_key}' => $this->opt( 'api_key' ),
				'{sender}'  => $this->opt( 'sender' ),
				'{to}'      => $receptor,
				'{pattern}' => $pattern,
				'{message}' => $message,
			] );
		}

		$resp = ( 'GET' === $method )
			? wp_remote_get( $url, $args )
			: wp_remote_post( $url, $args );

		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		return ( $code >= 200 && $code < 300 );
	}
}
