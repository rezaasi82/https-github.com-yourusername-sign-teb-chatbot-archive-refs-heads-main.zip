<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Analysis\OpportunityDetector\LowCtrDetector;
use SEODirector\Analysis\OpportunityDetector\StrikingDistanceDetector;

final class OpportunityDetectorTest extends TestCase {

	public function test_striking_distance_flags_position_8_to_20_with_demand(): void {
		$stats = array(
			array( 'query' => 'buy widgets', 'clicks' => 20, 'impressions' => 3000, 'ctr' => 0.0067, 'position' => 11.2 ),
			array( 'query' => 'widget faq', 'clicks' => 500, 'impressions' => 4000, 'ctr' => 0.125, 'position' => 2.1 ),   // already top — skip.
			array( 'query' => 'rare widget', 'clicks' => 1, 'impressions' => 40, 'ctr' => 0.025, 'position' => 15.0 ),     // too little demand — skip.
		);

		$results = ( new StrikingDistanceDetector() )->detect( $stats );

		$this->assertCount( 1, $results );
		$this->assertSame( 'buy widgets', $results[0]->entity_label );
		$this->assertGreaterThan( 0, $results[0]->est_traffic_gain );
	}

	public function test_low_ctr_flags_top10_underperformers_only(): void {
		$stats = array(
			array( 'query' => 'underperformer', 'clicks' => 5, 'impressions' => 2000, 'ctr' => 0.0025, 'position' => 4.0 ), // expected ~6%.
			array( 'query' => 'healthy', 'clicks' => 240, 'impressions' => 2000, 'ctr' => 0.12, 'position' => 3.0 ),        // fine — skip.
			array( 'query' => 'page two', 'clicks' => 2, 'impressions' => 2000, 'ctr' => 0.001, 'position' => 14.0 ),       // not top 10 — skip.
		);

		$results = ( new LowCtrDetector() )->detect( $stats );

		$this->assertCount( 1, $results );
		$this->assertSame( 'underperformer', $results[0]->entity_label );
		$this->assertSame( 2, $results[0]->difficulty );
	}

	public function test_changepoint_finds_the_drop(): void {
		$series = array_merge( array_fill( 0, 14, 100.0 ), array_fill( 0, 14, 40.0 ) );

		$result = ( new ChangepointDetector() )->detect( $series );

		$this->assertNotNull( $result );
		$this->assertSame( 14, $result['index'] );
		$this->assertLessThan( 0, $result['shift_pct'] );
	}

	public function test_changepoint_ignores_noise(): void {
		$series = array( 100.0, 102.0, 98.0, 101.0, 99.0, 100.0, 103.0, 97.0, 100.0, 101.0 );
		$this->assertNull( ( new ChangepointDetector() )->detect( $series ) );
	}
}
