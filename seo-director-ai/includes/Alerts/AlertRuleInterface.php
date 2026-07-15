<?php
/**
 * Contract for alert rules: evaluate local data, return alert candidates.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts;

defined( 'ABSPATH' ) || exit;

interface AlertRuleInterface {

	/** Rule slug stored on the alert row. */
	public function slug(): string;

	/**
	 * Evaluate the rule against local data.
	 *
	 * @return array<int, array{severity: string, message: string, fingerprint: string,
	 *                          entity_label: ?string, data: array<string, mixed>}>
	 */
	public function evaluate(): array;
}
