<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\TrendAnalyzer;

final class TrendAnalyzerTest extends TestCase {

	private TrendAnalyzer $trend;

	protected function setUp(): void {
		$this->trend = new TrendAnalyzer();
	}

	public function test_slope_of_perfect_line(): void {
		$this->assertEqualsWithDelta( 2.0, $this->trend->slope( [ 0.0, 2.0, 4.0, 6.0 ] ), 0.0001 );
	}

	public function test_slope_of_flat_series_is_zero(): void {
		$this->assertSame( 0.0, $this->trend->slope( [ 5.0, 5.0, 5.0 ] ) );
	}

	public function test_slope_single_point_is_zero(): void {
		$this->assertSame( 0.0, $this->trend->slope( [ 9.0 ] ) );
	}

	public function test_weekly_relative_slope_bounds(): void {
		// Steep positive slope clamps at +1.
		$this->assertSame( 1.0, $this->trend->weekly_relative_slope( [ 1.0, 100.0 ] ) );
	}

	public function test_percent_change_null_on_zero_base(): void {
		$this->assertNull( $this->trend->percent_change( 0.0, 50.0 ) );
		$this->assertSame( 50.0, $this->trend->percent_change( 100.0, 150.0 ) );
		$this->assertSame( -25.0, $this->trend->percent_change( 100.0, 75.0 ) );
	}

	public function test_week_over_week_requires_14_days(): void {
		$this->assertNull( $this->trend->week_over_week( array_fill( 0, 13, 1.0 ) ) );
	}

	public function test_week_over_week_compares_blocks(): void {
		$daily  = array_merge( array_fill( 0, 7, 10.0 ), array_fill( 0, 7, 5.0 ) );
		$result = $this->trend->week_over_week( $daily );

		$this->assertSame( 70.0, $result['previous'] );
		$this->assertSame( 35.0, $result['current'] );
		$this->assertSame( -50.0, $result['change_pct'] );
	}

	public function test_mean_and_stddev(): void {
		$this->assertSame( 3.0, $this->trend->mean( [ 1.0, 3.0, 5.0 ] ) );
		$this->assertEqualsWithDelta( 2.0, $this->trend->stddev( [ 1.0, 3.0, 5.0 ] ), 0.0001 );
	}
}
