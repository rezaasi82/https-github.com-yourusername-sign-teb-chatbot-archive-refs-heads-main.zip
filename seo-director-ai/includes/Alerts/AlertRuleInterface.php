<?php
/**
 * Contract for alert rules. A rule reports every condition currently firing;
 * the engine handles dedup (fingerprints) and auto-resolve (conditions that
 * stopped firing).
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts;

defined( 'ABSPATH' ) || exit;

interface AlertRuleInterface {

	public function slug(): string;

	/**
	 * @return array<int, array{severity: string, message: string, fingerprint_hex: string, entity_label: string|null, data: array<string, mixed>}>
	 */
	public function evaluate(): array;
}
