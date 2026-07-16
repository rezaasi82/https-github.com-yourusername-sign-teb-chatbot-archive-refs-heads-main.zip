<?php
/**
 * Runs all alert rules: raises new alerts (deduped by fingerprint),
 * auto-resolves cleared conditions, and dispatches new alerts to channels.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts;

use SEODirector\Alerts\Channels\AlertChannelInterface;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\License\FeatureGate;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class AlertEngine {

	/**
	 * @param AlertRuleInterface[]    $rules
	 * @param AlertChannelInterface[] $channels
	 */
	public function __construct(
		private array $rules,
		private AlertsRepository $alerts,
		private array $channels,
		private FeatureGate $gate,
		private EscalationPolicy $escalation,
		private Settings $settings,
	) {}

	public function evaluate(): void {
		/**
		 * Filters the alert rules to evaluate (extension point).
		 *
		 * @param AlertRuleInterface[] $rules
		 */
		$rules = apply_filters( 'sda_alert_rules', $this->rules );

		$new_alerts = [];

		foreach ( $rules as $rule ) {
			$conditions = $rule->evaluate();

			foreach ( $conditions as $condition ) {
				$is_new = $this->alerts->raise(
					$rule->slug(),
					$condition['severity'],
					$condition['message'],
					$condition['fingerprint_hex'],
					$condition['entity_label'],
					$condition['data']
				);

				if ( $is_new ) {
					$new_alerts[] = [
						'rule'     => $rule->slug(),
						'severity' => $condition['severity'],
						'message'  => $condition['message'],
					];

					/**
					 * Fires when a new alert is raised.
					 *
					 * @param string               $rule      Rule slug.
					 * @param array<string, mixed> $condition Alert payload.
					 */
					do_action( 'sda_alert_raised', $rule->slug(), $condition );
				}
			}

			$this->alerts->resolve_missing(
				$rule->slug(),
				array_column( $conditions, 'fingerprint_hex' )
			);
		}

		if ( [] !== $new_alerts ) {
			$this->dispatch( $new_alerts );
		}

		$this->escalate();
	}

	/**
	 * SLA escalation (Enterprise): re-notify for critical/high alerts that have
	 * stayed open past the configured threshold, exactly once each.
	 */
	public function escalate(): void {
		if ( ! $this->gate->allows( 'sla_alerting' ) ) {
			return;
		}

		$threshold = (int) $this->settings->get( 'sla_escalation_hours', 24 );
		$breaches  = $this->escalation->breaches( $this->alerts->active_escalatable(), $threshold, time() );

		if ( [] === $breaches ) {
			return;
		}

		$digest = array_map(
			static fn( array $a ) => [
				'rule'     => 'sla_breach',
				'severity' => (string) $a['severity'],
				'message'  => sprintf(
					/* translators: 1: hours open, 2: alert message. */
					__( 'SLA breach (open %1$d h): %2$s', 'seo-director-ai' ),
					$threshold,
					(string) $a['message']
				),
			],
			$breaches
		);

		$this->dispatch( $digest );
		$this->alerts->mark_escalated( array_column( $breaches, 'id' ) );

		/**
		 * Fires after alerts are escalated past SLA.
		 *
		 * @param array<int, array<string, mixed>> $breaches Escalated alert rows.
		 */
		do_action( 'sda_alerts_escalated', $breaches );
	}

	/**
	 * Send the digest to every enabled channel, gating PRO channels.
	 *
	 * @param array<int, array{rule: string, severity: string, message: string}> $alerts
	 */
	private function dispatch( array $alerts ): void {
		$pro_ok = $this->gate->allows( 'alert_channels' );

		/**
		 * Filters the alert channels (extension point).
		 *
		 * @param AlertChannelInterface[] $channels
		 */
		$channels = apply_filters( 'sda_alert_channels', $this->channels );

		foreach ( $channels as $channel ) {
			if ( ! $channel->is_enabled() ) {
				continue;
			}
			if ( $channel->requires_pro() && ! $pro_ok ) {
				continue;
			}
			$channel->send_digest( $alerts );
		}
	}
}
