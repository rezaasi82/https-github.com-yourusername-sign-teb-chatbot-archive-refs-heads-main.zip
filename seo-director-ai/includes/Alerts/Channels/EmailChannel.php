<?php
/**
 * Email alert channel: one digest per evaluation run, never one mail per
 * alert. Webhook/Slack/Telegram channels arrive with the PRO phase.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class EmailChannel {

	public function __construct( private Settings $settings ) {}

	/**
	 * @param array<int, array{rule: string, severity: string, message: string}> $alerts
	 */
	public function send_digest( array $alerts ): void {
		$to = (string) $this->settings->get( 'alert_email', '' );
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: alert count. */
			__( '[%1$s] SEO Director: %2$d new alert(s)', 'seo-director-ai' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			count( $alerts )
		);

		$severity_icons = [
			'critical' => '⛔',
			'high'     => '⚠️',
			'medium'   => '🔶',
			'low'      => 'ℹ️',
		];

		$lines = [];
		foreach ( $alerts as $alert ) {
			$lines[] = sprintf(
				'%s [%s] %s',
				$severity_icons[ $alert['severity'] ] ?? '',
				strtoupper( $alert['severity'] ),
				$alert['message']
			);
		}

		$lines[] = '';
		$lines[] = __( 'Review and act on alerts:', 'seo-director-ai' ) . ' ' . admin_url( 'admin.php?page=seo-director-ai#/alerts' );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
