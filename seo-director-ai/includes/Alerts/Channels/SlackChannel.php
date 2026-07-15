<?php
/**
 * Slack alert channel (PRO). Posts a formatted digest to an incoming-webhook
 * URL configured by the admin.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class SlackChannel implements AlertChannelInterface {

	public function __construct(
		private Settings $settings,
		private RetryingHttpClient $http,
	) {}

	public function slug(): string {
		return 'slack';
	}

	public function is_enabled(): bool {
		return '' !== $this->url();
	}

	public function requires_pro(): bool {
		return true;
	}

	public function send_digest( array $alerts ): void {
		$url = $this->url();
		if ( '' === $url || ! str_starts_with( $url, 'https://hooks.slack.com/' ) ) {
			return;
		}

		$icons = [ 'critical' => ':no_entry:', 'high' => ':warning:', 'medium' => ':large_orange_diamond:', 'low' => ':information_source:' ];
		$lines = [ sprintf( '*SEO Director — %d new alert(s)* on %s', count( $alerts ), home_url() ) ];
		foreach ( $alerts as $alert ) {
			$lines[] = sprintf( '%s %s', $icons[ $alert['severity'] ] ?? '', $alert['message'] );
		}

		$this->http->post(
			$url,
			[
				'timeout' => 15,
				'body'    => [ 'text' => implode( "\n", $lines ) ],
			]
		);
	}

	private function url(): string {
		return esc_url_raw( (string) $this->settings->get( 'alert_slack_url', '' ) );
	}
}
