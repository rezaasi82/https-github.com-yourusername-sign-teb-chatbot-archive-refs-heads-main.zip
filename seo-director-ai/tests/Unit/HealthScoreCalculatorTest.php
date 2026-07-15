<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\HealthScore\HealthScoreCalculator;

final class HealthScoreCalculatorTest extends TestCase {

	private HealthScoreCalculator $calc;

	protected function setUp(): void {
		$this->calc = new HealthScoreCalculator( new ExpectedCtrCurve() );
	}

	public function test_empty_inputs_yield_zero_and_red(): void {
		$result = $this->calc->calculate( [] );

		$this->assertSame( 0, $result['score'] );
		$this->assertSame( 'red', $result['band'] );
	}

	public function test_weights_redistribute_over_available_components(): void {
		// Only ranking available; a strong position should score high despite
		// every other component being null.
		$result = $this->calc->calculate( [ 'avg_position' => 1.0 ] );

		$this->assertSame( 100, $result['components']['ranking']['score'] );
		$this->assertSame( 100, $result['score'] );
		$this->assertFalse( $result['components']['ctr']['available'] );
	}

	public function test_band_thresholds(): void {
		$green = $this->calc->calculate( [ 'avg_position' => 2.0 ] ); // ranking ~95
		$this->assertSame( 'green', $green['band'] );

		$mid = $this->calc->calculate( [ 'avg_position' => 11.5 ] ); // ranking ~45
		$this->assertContains( $mid['band'], [ 'yellow', 'red' ] );
	}

	public function test_cwv_component_weighted_by_status_mix(): void {
		$result = $this->calc->calculate(
			[ 'cwv_status_counts' => [ 'good' => 8, 'needs_improvement' => 2, 'poor' => 0 ] ]
		);

		$this->assertTrue( $result['components']['core_web_vitals']['available'] );
		$this->assertGreaterThan( 80, $result['components']['core_web_vitals']['score'] );
	}

	public function test_trend_score_midpoint_for_flat(): void {
		$result = $this->calc->calculate( [ 'clicks_weekly_slope' => 0.0 ] );

		$this->assertSame( 50, $result['components']['growth_trend']['score'] );
	}

	public function test_full_inputs_produce_transparent_breakdown(): void {
		$result = $this->calc->calculate(
			[
				'avg_position'          => 5.0,
				'ctr'                   => 0.05,
				'cwv_status_counts'     => [ 'good' => 10, 'needs_improvement' => 0, 'poor' => 0 ],
				'indexation_ratio'      => 0.95,
				'freshness_ratio'       => 0.6,
				'internal_link_ratio'   => 0.8,
				'clicks_weekly_slope'   => 0.05,
				'sessions_weekly_slope' => 0.03,
			]
		);

		$this->assertGreaterThan( 0, $result['score'] );
		$this->assertLessThanOrEqual( 100, $result['score'] );
		foreach ( $result['components'] as $component ) {
			$this->assertTrue( $component['available'] );
		}
	}
}
