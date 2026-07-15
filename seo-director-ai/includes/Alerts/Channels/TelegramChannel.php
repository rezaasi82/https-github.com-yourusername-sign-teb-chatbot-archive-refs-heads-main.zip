<?php
/**
 * Telegram alert channel (PRO). Sends a digest via the Bot API using an
 * admin-set bot token and chat id.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class TelegramChannel implements AlertChannelInterface {

	public function __construct(
		private Settings $settings,
		private RetryingHttpClient $http,
	) {}

	public function slug(): string {
		return 'telegram';
	}

	public function is_enabled(): bool {
		return '' !== (string) $this->settings->get( 'alert_telegram_token', '' )
			&& '' !== (string) $this->settings->get( 'alert_telegram_chat', '' );
	}

	public function requires_pro(): bool {
		return true;
	}

	public function send_digest( array $alerts ): void {
		$token = (string) $this->settings->get( 'alert_telegram_token', '' );
		$chat  = (string) $this->settings->get( 'alert_telegram_chat', '' );
		if ( '' === $token || '' === $chat ) {
			return;
		}

		$icons = [ 'critical' => '⛔', 'high' => '⚠️', 'medium' => '🔶', 'low' => 'ℹ️' ];
		$lines = [ sprintf( 'SEO Director — %d new alert(s)', count( $alerts ) ) ];
		foreach ( $alerts as $alert ) {
			$lines[] = sprintf( '%s %s', $icons[ $alert['severity'] ] ?? '', $alert['message'] );
		}

		$this->http->post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
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
