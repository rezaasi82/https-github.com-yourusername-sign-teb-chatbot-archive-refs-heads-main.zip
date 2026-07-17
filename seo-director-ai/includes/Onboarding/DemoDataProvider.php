<?php
/**
 * A deterministic, synthetic dataset for the "not connected yet" experience.
 * Before a site has synced any real Search Console data, the dashboard would
 * be empty — the single biggest driver of onboarding-failure refunds. Demo
 * mode fills every panel with a plausible, clearly-labelled sample so buyers
 * see what the plugin does the moment it activates.
 *
 * Pure: no I/O, no WP data. The numbers are seeded so they are stable across
 * requests (no flicker) but look like a real 28-day trend.
 *
 * @package SEODirector
 */

namespace SEODirector\Onboarding;

defined( 'ABSPATH' ) || exit;

final class DemoDataProvider {

	/**
	 * The data sections of the overview payload, matching the real shape so the
	 * SPA renders identically.
	 *
	 * @return array<string, mixed>
	 */
	public function overview(): array {
		return [
			'health'        => [
				'score'      => 72,
				'band'       => 'yellow',
				'delta'      => 4,
				'components' => [
					'rankings'       => [ 'score' => 78, 'weight' => 0.25, 'available' => true ],
					'ctr'            => [ 'score' => 65, 'weight' => 0.2, 'available' => true ],
					'core_web_vitals' => [ 'score' => 70, 'weight' => 0.2, 'available' => true ],
					'trend'          => [ 'score' => 80, 'weight' => 0.2, 'available' => true ],
					'indexation'     => [ 'score' => null, 'weight' => 0.15, 'available' => false ],
				],
			],
			'traffic'       => [ 'series' => $this->series(), 'compare' => null ],
			'opportunities' => $this->opportunities(),
			'risks'         => $this->risks(),
			'summaries'     => [
				'weekly'  => 'Sample summary — organic clicks are up 12% week-over-week, led by three "striking distance" keywords now on page one. Connect Google Search Console to replace this with your site\'s real analysis.',
				'monthly' => null,
			],
		];
	}

	/**
	 * A 28-day organic-traffic series with weekly seasonality and a gentle
	 * upward trend. Deterministic (seeded by day index).
	 *
	 * @return array<int, array{date:string, clicks:int, impressions:int, ctr:float, position:float}>
	 */
	private function series(): array {
		$series = [];
		$start  = strtotime( '-29 days' );

		for ( $i = 0; $i < 28; $i++ ) {
			$ts       = $start + $i * DAY_IN_SECONDS;
			$dow      = (int) gmdate( 'N', $ts );
			$weekend  = ( $dow >= 6 ) ? 0.72 : 1.0;           // Quieter weekends.
			$trend    = 1 + ( $i / 28 ) * 0.18;                // +18% over the window.
			$wobble   = 0.9 + ( ( ( $i * 7 ) % 5 ) / 25 );     // Deterministic ±.
			$clicks   = (int) round( 120 * $weekend * $trend * $wobble );
			$impr     = (int) round( $clicks * ( 24 + ( $i % 4 ) ) ); // CTR ~3-4%.
			$position = round( 12.5 - ( $i / 28 ) * 2.5, 1 );  // Improving avg position.

			$series[] = [
				'date'        => gmdate( 'Y-m-d', $ts ),
				'clicks'      => $clicks,
				'impressions' => $impr,
				'ctr'         => $impr > 0 ? round( $clicks / $impr, 4 ) : 0.0,
				'position'    => $position,
			];
		}

		return $series;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function opportunities(): array {
		$now = gmdate( 'c' );

		return [
			[ 'id' => -1, 'detector' => 'striking_distance', 'entity_type' => 'query', 'label' => 'best project management software', 'secondary_label' => null, 'score' => 340.0, 'est_traffic_gain' => 210, 'difficulty' => 4, 'status' => 'open', 'data' => [ 'position' => 11.2 ], 'refreshed_at' => $now ],
			[ 'id' => -2, 'detector' => 'low_ctr', 'entity_type' => 'page', 'label' => '/blog/remote-team-guide/', 'secondary_label' => null, 'score' => 180.0, 'est_traffic_gain' => 130, 'difficulty' => 3, 'status' => 'open', 'data' => [ 'position' => 6.1 ], 'refreshed_at' => $now ],
			[ 'id' => -3, 'detector' => 'near_top', 'entity_type' => 'query', 'label' => 'kanban board template', 'secondary_label' => null, 'score' => 150.0, 'est_traffic_gain' => 95, 'difficulty' => 2, 'status' => 'open', 'data' => [ 'position' => 3.4 ], 'refreshed_at' => $now ],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function risks(): array {
		$now = gmdate( 'Y-m-d H:i:s' );

		return [
			[ 'id' => -1, 'rule' => 'traffic_drop', 'severity' => 'high', 'entity_label' => '/pricing/', 'message' => 'Sample alert — /pricing/ lost 28% of clicks over two weeks.', 'status' => 'active', 'raised_at' => $now, 'resolved_at' => null ],
			[ 'id' => -2, 'rule' => 'cwv_regression', 'severity' => 'medium', 'entity_label' => 'Mobile', 'message' => 'Sample alert — Core Web Vitals slipped to "needs improvement" on mobile.', 'status' => 'active', 'raised_at' => $now, 'resolved_at' => null ],
		];
	}
}
