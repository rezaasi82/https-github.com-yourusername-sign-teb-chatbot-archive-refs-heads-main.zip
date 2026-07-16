<?php
/**
 * SLA escalation decision (Enterprise). Given the active alerts and an SLA
 * threshold, decides which ones have been open too long and must be escalated
 * a second time. Pure — no I/O, no WP calls — so it is unit-testable and the
 * AlertEngine owns all persistence and dispatch.
 *
 * Only the two highest severities carry an SLA; medium/low never escalate. An
 * alert escalates at most once (tracked by escalated_at on the row).
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts;

defined( 'ABSPATH' ) || exit;

final class EscalationPolicy {

	private const ESCALATABLE_SEVERITIES = [ 'critical', 'high' ];

	/**
	 * @param array<int, array{id:int, severity:string, raised_at:string, escalated_at:?string}> $alerts
	 * @param int $threshold_hours Hours an alert may stay open before escalation.
	 * @param int $now             Current unix time (injected for testability).
	 * @return array<int, array{id:int, severity:string, raised_at:string, escalated_at:?string}> Breaching alerts.
	 */
	public function breaches( array $alerts, int $threshold_hours, int $now ): array {
		$threshold_seconds = max( 1, $threshold_hours ) * 3600;
		$breaches          = [];

		foreach ( $alerts as $alert ) {
			if ( ! in_array( $alert['severity'] ?? '', self::ESCALATABLE_SEVERITIES, true ) ) {
				continue;
			}
			if ( ! empty( $alert['escalated_at'] ) ) {
				continue; // Already escalated once.
			}

			$raised = strtotime( (string) ( $alert['raised_at'] ?? '' ) . ' UTC' );
			if ( false === $raised ) {
				continue;
			}

			if ( $now - $raised >= $threshold_seconds ) {
				$breaches[] = $alert;
			}
		}

		return $breaches;
	}
}
