<?php
/**
 * SignTeb License Manager — WordPress SDK
 *
 * Reusable drop-in licensing client for ALL SignTeb plugins (MEDORA AI, SignBot,
 * SignTeb SEO Dashboard, QRCODR, …). One class, zero dependencies.
 *
 * Usage (in your plugin bootstrap):
 *
 *     require_once __DIR__ . '/sdk/class-signteb-sdk.php';
 *
 *     $slm = new SignTeb_SDK( array(
 *         'product'        => 'medora-ai',              // product slug in SLM
 *         'signing_secret' => 'per-product-public-hmac',// response-verification key
 *         'plugin_file'    => __FILE__,                 // enables auto-updates
 *         'version'        => MEDORA_VERSION,
 *     ) );
 *
 *     $slm->activate_license( $key );   // from your settings page
 *     if ( $slm->is_valid() ) { ... }   // gate premium features
 *
 * @package SignTeb\SDK
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SignTeb_SDK' ) ) {
	return;
}

class SignTeb_SDK {

	const SDK_VERSION   = '1.0.0';
	const API_BASE      = 'https://license.signteb.com/api/v1/plugin';
	const CACHE_TTL     = 12 * HOUR_IN_SECONDS; // revalidate twice a day
	const OFFLINE_GRACE = 3 * DAY_IN_SECONDS;   // trust last good validation this long if SLM is unreachable

	/** @var array{product:string,signing_secret:string,plugin_file:string,version:string,api_base?:string} */
	private $config;

	/** @var string Option key prefix, unique per product. */
	private $prefix;

	public function __construct( array $config ) {
		$this->config = $config;
		$this->prefix = 'signteb_' . str_replace( '-', '_', $config['product'] );

		if ( ! empty( $config['plugin_file'] ) ) {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		}

		// Twice-daily background heartbeat keeps validation fresh without blocking page loads.
		add_action( $this->prefix . '_heartbeat', array( $this, 'validate_license' ) );
		if ( ! wp_next_scheduled( $this->prefix . '_heartbeat' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', $this->prefix . '_heartbeat' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Public API                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Activate a license key for this site.
	 *
	 * @return true|WP_Error
	 */
	public function activate_license( $license_key ) {
		$license_key = strtoupper( trim( (string) $license_key ) );

		$response = $this->request( 'POST', '/activate', array(
			'license_key' => $license_key,
			'domain'      => $this->domain(),
			'product'     => $this->config['product'],
			'site_url'    => home_url(),
			'sdk_version' => self::SDK_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'fingerprint' => $this->fingerprint(),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		update_option( $this->prefix . '_key', $license_key, false );
		$this->store_validation( $response );

		return true;
	}

	/**
	 * Validate against the server and refresh the local cache.
	 * Falls back to the cached result inside the offline-grace window,
	 * so a licensing-server outage never hard-locks a customer site.
	 *
	 * @return array|WP_Error validation payload
	 */
	public function validate_license() {
		$key = get_option( $this->prefix . '_key' );

		if ( ! $key ) {
			return new WP_Error( 'no_license', __( 'No license key saved.', 'signteb' ) );
		}

		$response = $this->request( 'POST', '/validate', array(
			'license_key' => $key,
			'domain'      => $this->domain(),
			'product'     => $this->config['product'],
		) );

		if ( is_wp_error( $response ) ) {
			$cached = get_option( $this->prefix . '_validation' );
			if ( $cached && ( time() - $cached['checked_at'] ) < self::OFFLINE_GRACE ) {
				return $cached['data']; // server unreachable — honour last good answer
			}

			return $response;
		}

		$this->store_validation( $response );

		return $response;
	}

	/**
	 * Deactivate this site's activation (frees the slot for another domain).
	 *
	 * @return true|WP_Error
	 */
	public function deactivate_license() {
		$key = get_option( $this->prefix . '_key' );

		if ( $key ) {
			$this->request( 'POST', '/deactivate', array(
				'license_key' => $key,
				'domain'      => $this->domain(),
				'product'     => $this->config['product'],
			) );
		}

		delete_option( $this->prefix . '_key' );
		delete_option( $this->prefix . '_validation' );
		wp_clear_scheduled_hook( $this->prefix . '_heartbeat' );

		return true;
	}

	/**
	 * Cheap cached check for feature gating. Never performs a network call.
	 */
	public function is_valid() {
		$cached = get_option( $this->prefix . '_validation' );

		if ( ! $cached ) {
			return false;
		}

		// Stale beyond TTL + offline grace → treat as invalid until revalidated.
		if ( ( time() - $cached['checked_at'] ) > ( self::CACHE_TTL + self::OFFLINE_GRACE ) ) {
			return false;
		}

		return ! empty( $cached['data']['valid'] );
	}

	/** True while the license is in its renewal grace window (show a notice, keep working). */
	public function in_grace() {
		$cached = get_option( $this->prefix . '_validation' );

		return $cached && ! empty( $cached['data']['grace'] );
	}

	/**
	 * Query SLM for an update manifest. Used by inject_update(); callable directly.
	 *
	 * @return array|WP_Error
	 */
	public function check_updates() {
		$key = get_option( $this->prefix . '_key' );

		if ( ! $key ) {
			return new WP_Error( 'no_license', __( 'No license key saved.', 'signteb' ) );
		}

		return $this->request( 'GET', '/check-update', array(
			'license_key' => $key,
			'domain'      => $this->domain(),
			'product'     => $this->config['product'],
			'version'     => $this->config['version'],
		) );
	}

	/**
	 * Report anonymous usage metrics (opt-in, filterable).
	 *
	 * @param array $data e.g. array( 'chats' => 42 )
	 * @return true|WP_Error
	 */
	public function send_usage_data( array $data ) {
		if ( ! apply_filters( $this->prefix . '_allow_usage_data', true ) ) {
			return true;
		}

		$key = get_option( $this->prefix . '_key' );

		if ( ! $key ) {
			return new WP_Error( 'no_license', __( 'No license key saved.', 'signteb' ) );
		}

		$result = $this->request( 'POST', '/usage', array(
			'license_key' => $key,
			'domain'      => $this->domain(),
			'product'     => $this->config['product'],
			'data'        => $data,
		) );

		return is_wp_error( $result ) ? $result : true;
	}

	/* ------------------------------------------------------------------ */
	/* WordPress auto-update bridge                                        */
	/* ------------------------------------------------------------------ */

	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$manifest = get_transient( $this->prefix . '_update' );

		if ( false === $manifest ) {
			$manifest = $this->check_updates();
			set_transient( $this->prefix . '_update', is_wp_error( $manifest ) ? array() : $manifest, self::CACHE_TTL );
		}

		if ( empty( $manifest['update_available'] ) ) {
			return $transient;
		}

		$basename = plugin_basename( $this->config['plugin_file'] );

		$transient->response[ $basename ] = (object) array(
			'slug'         => dirname( $basename ),
			'plugin'       => $basename,
			'new_version'  => $manifest['version'],
			'package'      => $manifest['download_url'],
			'requires_php' => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'requires'     => isset( $manifest['requires_wp'] ) ? $manifest['requires_wp'] : '',
		);

		return $transient;
	}

	/* ------------------------------------------------------------------ */
	/* Internals                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Signed HTTP call to SLM. Verifies the response HMAC before returning data —
	 * a spoofed license server fails verification and is treated as an error.
	 *
	 * @return array|WP_Error the verified `data` payload
	 */
	private function request( $method, $path, array $body ) {
		$base = ! empty( $this->config['api_base'] ) ? $this->config['api_base'] : self::API_BASE;
		$url  = $base . $path;
		$args = array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		);

		if ( 'GET' === $method ) {
			$url = add_query_arg( array_map( 'rawurlencode', $body ), $url );
			$response = wp_remote_get( $url, $args );
		} else {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
			$response                        = wp_remote_post( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$error   = isset( $json['error'] ) ? $json['error'] : 'http_' . $code;
			$message = isset( $json['message'] ) ? $json['message'] : __( 'License server error.', 'signteb' );

			return new WP_Error( $error, $message );
		}

		if ( ! isset( $json['data'], $json['timestamp'], $json['signature'] ) ) {
			return new WP_Error( 'malformed_response', __( 'Unexpected license server response.', 'signteb' ) );
		}

		$expected = hash_hmac(
			'sha256',
			wp_json_encode( $json['data'], JSON_UNESCAPED_SLASHES ) . '|' . $json['timestamp'],
			$this->config['signing_secret']
		);

		if ( ! hash_equals( $expected, $json['signature'] ) ) {
			return new WP_Error( 'bad_signature', __( 'License server response failed verification.', 'signteb' ) );
		}

		return $json['data'];
	}

	private function store_validation( array $data ) {
		update_option( $this->prefix . '_validation', array(
			'data'       => $data,
			'checked_at' => time(),
		), false );
	}

	private function domain() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return $host ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';
	}

	private function fingerprint() {
		return hash( 'sha256', implode( '|', array(
			ABSPATH,
			defined( 'DB_NAME' ) ? DB_NAME : '',
			wp_parse_url( home_url(), PHP_URL_HOST ),
		) ) );
	}
}
