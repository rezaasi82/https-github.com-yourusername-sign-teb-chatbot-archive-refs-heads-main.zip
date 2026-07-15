<?php
/**
 * Email alert channel: one digest per evaluation run, never one mail per
 * alert. Included in every edition.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class EmailChannel implements AlertChannelInterface {

	public function __construct( private Settings $settings ) {}

	public function slug(): string {
		return 'email';
	}

	public function is_enabled(): bool {
		return '' !== (string) $this->settings->get( 'alert_email', '' );
	}

	public function requires_pro(): bool {
		return false;
	}

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

		$icons = [ 'critical' => '⛔', 'high' => '⚠️', 'medium' => '🔶', 'low' => 'ℹ️' ];
		$lines = [];
		foreach ( $alerts as $alert ) {
			$lines[] = sprintf( '%s [%s] %s', $icons[ $alert['severity'] ] ?? '', strtoupper( $alert['severity'] ), $alert['message'] );
		}
		$lines[] = '';
		$lines[] = __( 'Review and act on alerts:', 'seo-director-ai' ) . ' ' . admin_url( 'admin.php?page=seo-director-ai#/alerts' );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
