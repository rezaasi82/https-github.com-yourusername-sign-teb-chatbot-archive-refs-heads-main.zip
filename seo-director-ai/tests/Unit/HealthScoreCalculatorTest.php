<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\HealthScore\HealthScoreCalculator;

final class HealthScoreCalculatorTest extends TestCase {

	private HealthScoreCalculator $calculator;

	protected function setUp(): void {
		$this->calculator = new HealthScoreCalculator();
	}

	public function test_healthy_growing_site_scores_high(): void {
		$result = $this->calculator->calculate(
			array(
				'clicks_wow'      => 20.0,
				'clicks_mom'      => 35.0,
				'impressions_wow' => 15.0,
				'ctr'             => 0.09,
				'position'        => 2.5,
				'days_since_sync' => 1,
			)
		);
		$this->assertGreaterThanOrEqual( 80, $result['score'] );
		$this->assertLessThanOrEqual( 100, $result['score'] );
	}

	public function test_declining_stale_site_scores_low(): void {
		$result = $this->calculator->calculate(
			array(
				'clicks_wow'      => -45.0,
				'clicks_mom'      => -40.0,
				'impressions_wow' => -30.0,
				'ctr'             => 0.002,
				'position'        => 45.0,
				'days_since_sync' => 20,
			)
		);
		$this->assertLessThan( 40, $result['score'] );
	}

	public function test_score_is_deterministic(): void {
		$inputs = array(
			'clicks_wow'      => 5.0,
			'clicks_mom'      => -3.0,
			'impressions_wow' => 2.0,
			'ctr'             => 0.03,
			'position'        => 8.0,
			'days_since_sync' => 2,
		);
		$this->assertSame( $this->calculator->calculate( $inputs ), $this->calculator->calculate( $inputs ) );
	}

	public function test_unknown_trends_read_neutral(): void {
		$result = $this->calculator->calculate(
			array(
				'clicks_wow'      => null,
				'clicks_mom'      => null,
				'impressions_wow' => null,
				'ctr'             => 0.0,
				'position'        => 0.0,
				'days_since_sync' => 1,
			)
		);
		$this->assertGreaterThan( 40, $result['score'] );
		$this->assertLessThan( 80, $result['score'] );
		$this->assertArrayHasKey( 'traffic_trend', $result['components'] );
	}

	public function test_score_bounded_0_100(): void {
		$result = $this->calculator->calculate(
			array(
				'clicks_wow'      => 500.0,
				'clicks_mom'      => 500.0,
				'impressions_wow' => 500.0,
				'ctr'             => 0.9,
				'position'        => 1.0,
				'days_since_sync' => 0,
			)
		);
		$this->assertLessThanOrEqual( 100, $result['score'] );
	}
}
