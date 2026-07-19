<?php
/**
 * Evaluates all registered alert rules; the repository dedups via fingerprints.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\AlertsRepository;
use Throwable;

final class AlertEngine {

	/** @var AlertRuleInterface[] */
	private array $rules;

	/** @param AlertRuleInterface[] $rules */
	public function __construct(
		private readonly AlertsRepository $alerts,
		array $rules,
	) {
		/**
		 * Filter the registered alert rules.
		 *
		 * @param AlertRuleInterface[] $rules Rule instances.
		 */
		$this->rules = (array) apply_filters( 'sda_alert_rules', $rules );
	}

	public function evaluate_all(): void {
		foreach ( $this->rules as $rule ) {
			if ( ! $rule instanceof AlertRuleInterface ) {
				continue;
			}
			try {
				foreach ( $rule->evaluate() as $candidate ) {
					$this->alerts->raise(
						$rule->slug(),
						(string) $candidate['severity'],
						(string) $candidate['message'],
						(string) $candidate['fingerprint'],
						$candidate['entity_label'],
						(array) ( $candidate['data'] ?? array() )
					);
				}
			} catch ( Throwable $e ) {
				// One broken rule must never take down the evaluation pass.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'SDA alert rule ' . $rule->slug() . ' failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}
	}
}
