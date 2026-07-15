<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Analysis\TrendAnalyzer;

final class ChangepointDetectorTest extends TestCase {

	private ChangepointDetector $detector;

	protected function setUp(): void {
		$this->detector = new ChangepointDetector( new TrendAnalyzer() );
	}

	public function test_returns_null_for_short_series(): void {
		$this->assertNull( $this->detector->detect( [ '2026-01-01' => 5.0, '2026-01-02' => 6.0 ] ) );
	}

	public function test_returns_null_for_flat_series(): void {
		$series = [];
		for ( $i = 1; $i <= 28; $i++ ) {
			$series[ sprintf( '2026-01-%02d', $i ) ] = 100.0;
		}
		$this->assertNull( $this->detector->detect( $series ) );
	}

	public function test_detects_sharp_drop(): void {
		// 14 days at ~100, then 14 days at ~40: a clear level shift.
		$series = [];
		for ( $i = 1; $i <= 14; $i++ ) {
			$series[ sprintf( '2026-01-%02d', $i ) ] = 100.0 + ( $i % 2 );
		}
		for ( $i = 15; $i <= 28; $i++ ) {
			$series[ sprintf( '2026-01-%02d', $i ) ] = 40.0 + ( $i % 2 );
		}

		$result = $this->detector->detect( $series );

		$this->assertNotNull( $result );
		$this->assertSame( '2026-01-15', $result['date'] );
		$this->assertLessThan( 0, $result['change_pct'] );
		$this->assertGreaterThanOrEqual( 2.0, $result['score'] );
	}
}
