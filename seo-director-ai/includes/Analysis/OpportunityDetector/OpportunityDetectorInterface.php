<?php
/**
 * Contract for opportunity detectors. Detectors are pure: they receive
 * aggregate entity rows and return scored opportunity records; persistence
 * happens in the analysis job.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

interface OpportunityDetectorInterface {

	/**
	 * Detector slug stored in the opportunities table.
	 */
	public function slug(): string;

	/**
	 * @param MoverRow[] $rows Entity aggregates for the current period.
	 * @return array<int, array{
	 *   entity_type: string, hash: string, label: string, secondary_label: string|null,
	 *   score: float, est_traffic_gain: int, difficulty: int, data: array<string, mixed>
	 * }>
	 */
	public function detect( array $rows ): array;
}
