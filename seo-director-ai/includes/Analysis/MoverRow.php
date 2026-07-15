<?php
/**
 * Input value object for Growth/Decline detectors: one entity's aggregates
 * for the current and previous comparison periods.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class MoverRow {

	public function __construct(
		public readonly string $label,
		public readonly string $hash_hex,
		public readonly int $cur_clicks,
		public readonly int $cur_impressions,
		public readonly float $cur_position,
		public readonly float $cur_ctr,
		public readonly int $prev_clicks,
		public readonly int $prev_impressions,
		public readonly float $prev_position,
		public readonly float $prev_ctr,
	) {}
}
