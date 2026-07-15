<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\MoverRow;
use SEODirector\Analysis\OpportunityDetector\LowCtrDetector;
use SEODirector\Analysis\OpportunityDetector\NearTopDetector;
use SEODirector\Analysis\OpportunityDetector\StrikingDistanceDetector;

final class OpportunityDetectorTest extends TestCase {

	private ExpectedCtrCurve $curve;

	protected function setUp(): void {
		$this->curve = new ExpectedCtrCurve();
	}

	private function row( float $position, int $impressions, float $ctr, int $clicks = 0 ): MoverRow {
		return new MoverRow( 'e', str_repeat( 'a', 32 ), $clicks, $impressions, $position, $ctr, 0, 0, 0.0, 0.0 );
	}

	public function test_striking_distance_flags_position_4_to_20(): void {
		$hits = ( new StrikingDistanceDetector( $this->curve ) )->detect(
			[ $this->row( 6.0, 2000, 0.02 ) ]
		);

		$this->assertCount( 1, $hits );
		$this->assertSame( 'query', $hits[0]['entity_type'] );
		$this->assertGreaterThan( 0, $hits[0]['est_traffic_gain'] );
		$this->assertSame( 'top3', $hits[0]['data']['target'] );
	}

	public function test_striking_distance_ignores_top3_and_low_volume(): void {
		$detector = new StrikingDistanceDetector( $this->curve );

		$this->assertCount( 0, $detector->detect( [ $this->row( 2.0, 2000, 0.15 ) ] ) );
		$this->assertCount( 0, $detector->detect( [ $this->row( 6.0, 50, 0.01 ) ] ) );
	}

	public function test_low_ctr_flags_underperforming_high_impression_pages(): void {
		// Position 3 expects ~10% CTR; actual 3% is well below threshold.
		$hits = ( new LowCtrDetector( $this->curve ) )->detect(
			[ $this->row( 3.0, 5000, 0.03 ) ]
		);

		$this->assertCount( 1, $hits );
		$this->assertSame( 'page', $hits[0]['entity_type'] );
		$this->assertSame( 3, $hits[0]['difficulty'] );
		$this->assertGreaterThan( 0, $hits[0]['est_traffic_gain'] );
	}

	public function test_low_ctr_ignores_pages_meeting_expected_curve(): void {
		$hits = ( new LowCtrDetector( $this->curve ) )->detect(
			[ $this->row( 3.0, 5000, 0.10 ) ]
		);

		$this->assertCount( 0, $hits );
	}

	public function test_near_top_bands(): void {
		$detector = new NearTopDetector( $this->curve );

		$top3  = $detector->detect( [ $this->row( 5.0, 1000, 0.03 ) ] );
		$page1 = $detector->detect( [ $this->row( 12.0, 1000, 0.005 ) ] );
		$none  = $detector->detect( [ $this->row( 8.0, 1000, 0.02 ) ] ); // gap between bands

		$this->assertSame( 'top3', $top3[0]['data']['target'] );
		$this->assertSame( 'page1', $page1[0]['data']['target'] );
		$this->assertCount( 0, $none );
	}
}
