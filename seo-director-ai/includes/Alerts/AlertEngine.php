<?php
/**
 * Runs all alert rules: raises new alerts (deduped by fingerprint),
 * auto-resolves cleared conditions, and dispatches new alerts to channels.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts;

use SEODirector\Alerts\Channels\EmailChannel;
use SEODirector\Data\Repository\AlertsRepository;

defined( 'ABSPATH' ) || exit;

final class AlertEngine {

	/**
	 * @param AlertRuleInterface[] $rules
	 */
	public function __construct(
		private array $rules,
		private AlertsRepository $alerts,
		private EmailChannel $email,
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
			$this->email->send_digest( $new_alerts );
		}
	}
}
