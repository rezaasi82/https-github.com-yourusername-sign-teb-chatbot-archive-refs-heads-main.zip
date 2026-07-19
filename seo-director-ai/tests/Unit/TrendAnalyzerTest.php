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

	public function test_slope_of_increasing_series_is_positive(): void {
		$this->assertEqualsWithDelta( 1.0, $this->trend->slope( array( 1.0, 2.0, 3.0, 4.0, 5.0 ) ), 0.0001 );
	}

	public function test_slope_of_flat_series_is_zero(): void {
		$this->assertSame( 0.0, $this->trend->slope( array( 7.0, 7.0, 7.0, 7.0 ) ) );
	}

	public function test_slope_of_short_series_is_zero(): void {
		$this->assertSame( 0.0, $this->trend->slope( array( 42.0 ) ) );
	}

	public function test_wow_doubling_is_100_percent(): void {
		$values = array_merge( array_fill( 0, 7, 10.0 ), array_fill( 0, 7, 20.0 ) );
		$this->assertEqualsWithDelta( 100.0, $this->trend->wow( $values ), 0.0001 );
	}

	public function test_wow_returns_null_when_insufficient_data(): void {
		$this->assertNull( $this->trend->wow( array_fill( 0, 10, 5.0 ) ) );
	}

	public function test_wow_returns_null_on_zero_base(): void {
		$values = array_merge( array_fill( 0, 7, 0.0 ), array_fill( 0, 7, 20.0 ) );
		$this->assertNull( $this->trend->wow( $values ) );
	}

	public function test_moving_average_uses_last_window(): void {
		$this->assertEqualsWithDelta( 9.0, $this->trend->moving_average( array( 1.0, 1.0, 8.0, 9.0, 10.0 ), 3 ), 0.0001 );
	}

	public function test_stddev_of_constant_series_is_zero(): void {
		$this->assertSame( 0.0, $this->trend->stddev( array( 4.0, 4.0, 4.0 ) ) );
	}
}
