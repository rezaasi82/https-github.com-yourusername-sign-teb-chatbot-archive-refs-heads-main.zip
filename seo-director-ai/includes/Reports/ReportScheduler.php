<?php
/**
 * The "every Saturday" automation (PRO): on the configured day, generate the
 * weekly report and email it to stakeholders. Fires from the daily schedule
 * and self-gates on the configured day/dedup so it runs once per week.
 *
 * @package SEODirector
 */

namespace SEODirector\Reports;

use SEODirector\License\FeatureGate;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class ReportScheduler {

	public function __construct(
		private ReportGenerator $generator,
		private Settings $settings,
		private FeatureGate $gate,
	) {}

	/**
	 * Called daily; runs the pipeline only on the configured report day, once.
	 */
	public function maybe_run(): void {
		if ( ! $this->gate->allows( 'reports_schedule' ) ) {
			return;
		}

		$day = strtolower( (string) $this->settings->get( 'report_day', 'saturday' ) );
		if ( strtolower( gmdate( 'l' ) ) !== $day ) {
			return;
		}

		$marker = 'sda_weekly_report_' . gmdate( 'oW' );
		if ( get_transient( $marker ) ) {
			return; // Already ran this ISO week.
		}
		set_transient( $marker, 1, WEEK_IN_SECONDS );

		$formats = $this->gate->allows( 'reports_all_formats' ) ? [ 'html', 'csv' ] : [ 'html' ];
		$result  = $this->generator->generate( 'weekly', $formats, 'sent' );

		$this->email_stakeholders( $result['files'] );
	}

	/**
	 * @param array<string, string> $files
	 */
	private function email_stakeholders( array $files ): void {
		$to = (string) $this->settings->get( 'alert_email', get_option( 'admin_email' ) );
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your weekly SEO report', 'seo-director-ai' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body = __( 'Your weekly SEO Director report is ready.', 'seo-director-ai' ) . "\n\n"
			. __( 'Open the dashboard:', 'seo-director-ai' ) . ' ' . admin_url( 'admin.php?page=seo-director-ai#/reports' );

		$attachments = array_values( array_filter( $files, 'is_readable' ) );

		wp_mail( $to, $subject, $body, [], $attachments );
	}
}
