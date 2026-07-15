<?php
/**
 * Generic webhook alert channel (PRO). Posts a JSON digest to an admin-set
 * URL. The URL is SSRF-validated (must be a public HTTPS host, never a
 * private/loopback range) before any request.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class WebhookChannel implements AlertChannelInterface {

	public function __construct(
		private Settings $settings,
		private RetryingHttpClient $http,
	) {}

	public function slug(): string {
		return 'webhook';
	}

	public function is_enabled(): bool {
		return '' !== $this->url();
	}

	public function requires_pro(): bool {
		return true;
	}

	public function send_digest( array $alerts ): void {
		$url = $this->url();
		if ( '' === $url || ! $this->is_safe_url( $url ) ) {
			return;
		}

		$this->http->post(
			$url,
			[
				'timeout' => 15,
				'body'    => [
					'site'   => home_url(),
					'count'  => count( $alerts ),
					'alerts' => $alerts,
				],
			]
		);
	}

	private function url(): string {
		return esc_url_raw( (string) $this->settings->get( 'alert_webhook_url', '' ) );
	}

	/**
	 * Block SSRF: HTTPS only, valid public host, no private/loopback IPs.
	 */
	private function is_safe_url( string $url ): bool {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$ip = gethostbyname( $host );
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false && filter_var( $host, FILTER_VALIDATE_IP ) === false ) {
			// gethostbyname returns the host unchanged on failure; if it resolved to
			// a private range, reject.
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
				return false;
			}
		}

		return str_starts_with( $url, 'https://' );
	}
}
