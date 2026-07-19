<?php
/**
 * Bale (بله) alert channel (PRO). The Iranian Bale messenger exposes a
 * Telegram-compatible Bot API at tapi.bale.ai, so the digest payload matches
 * TelegramChannel — only the host and settings keys differ. Reachable from
 * Iranian hosts without a VPN, which Telegram's API often is not.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class BaleChannel implements AlertChannelInterface {

	public function __construct(
		private Settings $settings,
		private RetryingHttpClient $http,
	) {}

	public function slug(): string {
		return 'bale';
	}

	public function is_enabled(): bool {
		return '' !== (string) $this->settings->get( 'alert_bale_token', '' )
			&& '' !== (string) $this->settings->get( 'alert_bale_chat', '' );
	}

	public function requires_pro(): bool {
		return true;
	}

	public function send_digest( array $alerts ): void {
		$token = (string) $this->settings->get( 'alert_bale_token', '' );
		$chat  = (string) $this->settings->get( 'alert_bale_chat', '' );
		if ( '' === $token || '' === $chat ) {
			return;
		}

		$icons = [ 'critical' => '⛔', 'high' => '⚠️', 'medium' => '🔶', 'low' => 'ℹ️' ];
		$lines = [ sprintf( 'SEO Director — %d new alert(s)', count( $alerts ) ) ];
		foreach ( $alerts as $alert ) {
			$lines[] = sprintf( '%s %s', $icons[ $alert['severity'] ] ?? '', $alert['message'] );
		}

		$this->http->post(
			'https://tapi.bale.ai/bot' . rawurlencode( $token ) . '/sendMessage',
			[
				'timeout' => 15,
				'body'    => [
					'chat_id' => $chat,
					'text'    => implode( "\n", $lines ),
				],
			]
		);
	}
}
