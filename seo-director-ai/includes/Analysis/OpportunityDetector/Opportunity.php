<?php
/**
 * Immutable opportunity value object produced by detectors.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

defined( 'ABSPATH' ) || exit;

final class Opportunity {

	/**
	 * @param 'page'|'query'|'pair'   $entity_type
	 * @param array<string, mixed>    $data Detector-specific evidence.
	 */
	public function __construct(
		public readonly string $entity_type,
		public readonly string $entity_label,
		public readonly ?string $secondary_label,
		public readonly float $score,
		public readonly ?int $est_traffic_gain,
		public readonly int $difficulty,
		public readonly array $data = array(),
	) {}
}
