<?php
/**
 * Email delivery for newly raised alerts.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

defined( 'ABSPATH' ) || exit;

final class EmailChannel {

	public function register(): void {
		add_action( 'sda_alert_raised', array( $this, 'send' ), 10, 3 );
	}

	public function send( string $rule, string $severity, string $message ): void {
		// Only interrupt inboxes for the serious ones; the rest live in the dashboard.
		if ( ! in_array( $severity, array( 'critical', 'high' ), true ) ) {
			return;
		}

		$to      = (string) get_option( 'admin_email' );
		$subject = sprintf(
			/* translators: 1: severity, 2: site name */
			__( '[%1$s] SEO alert on %2$s', 'seo-director-ai' ),
			strtoupper( $severity ),
			wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES )
		);
		$body = $message . "\n\n"
			. __( 'Open the SEO Director dashboard for details:', 'seo-director-ai' ) . "\n"
			. admin_url( 'admin.php?page=seo-director-ai' );

		wp_mail( $to, $subject, $body );
	}
}
