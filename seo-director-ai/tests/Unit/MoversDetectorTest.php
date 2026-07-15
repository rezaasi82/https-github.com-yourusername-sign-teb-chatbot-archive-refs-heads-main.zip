<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Analysis\MoverRow;

final class MoversDetectorTest extends TestCase {

	private function row( array $overrides = [] ): MoverRow {
		$defaults = [
			'label' => 'kw', 'hash' => str_repeat( 'a', 32 ),
			'cur_clicks' => 100, 'cur_impressions' => 1000, 'cur_position' => 3.0, 'cur_ctr' => 0.10,
			'prev_clicks' => 50, 'prev_impressions' => 900, 'prev_position' => 5.0, 'prev_ctr' => 0.055,
		];
		$v = array_merge( $defaults, $overrides );

		return new MoverRow(
			$v['label'], $v['hash'],
			$v['cur_clicks'], $v['cur_impressions'], $v['cur_position'], $v['cur_ctr'],
			$v['prev_clicks'], $v['prev_impressions'], $v['prev_position'], $v['prev_ctr']
		);
	}

	public function test_growth_detects_and_classifies_ranking_gain(): void {
		$winners = ( new GrowthDetector() )->detect( [ $this->row() ] );

		$this->assertCount( 1, $winners );
		$this->assertSame( 50, $winners[0]['clicks_delta'] );
		$this->assertSame( 100.0, $winners[0]['growth_pct'] );
		$this->assertSame( 'ranking_gain', $winners[0]['reason'] );
	}

	public function test_growth_flags_new_entry(): void {
		$winners = ( new GrowthDetector() )->detect(
			[ $this->row( [ 'prev_clicks' => 0, 'prev_impressions' => 0, 'prev_position' => 0.0, 'prev_ctr' => 0.0 ] ) ]
		);

		$this->assertTrue( $winners[0]['is_new'] );
		$this->assertSame( 'new_entry', $winners[0]['reason'] );
		$this->assertNull( $winners[0]['growth_pct'] );
	}

	public function test_growth_ignores_declines_and_tiny_traffic(): void {
		$decline = $this->row( [ 'cur_clicks' => 10, 'prev_clicks' => 80 ] );
		$tiny    = $this->row( [ 'cur_clicks' => 3, 'prev_clicks' => 1 ] );

		$this->assertCount( 0, ( new GrowthDetector() )->detect( [ $decline, $tiny ] ) );
	}

	public function test_growth_sorted_by_delta_desc(): void {
		$small = $this->row( [ 'hash' => str_repeat( 'b', 32 ), 'cur_clicks' => 60, 'prev_clicks' => 50 ] );
		$big   = $this->row( [ 'hash' => str_repeat( 'c', 32 ), 'cur_clicks' => 200, 'prev_clicks' => 50 ] );

		$winners = ( new GrowthDetector() )->detect( [ $small, $big ] );

		$this->assertSame( 150, $winners[0]['clicks_delta'] );
		$this->assertSame( 10, $winners[1]['clicks_delta'] );
	}

	public function test_decline_classifies_ranking_loss_and_priority(): void {
		$row    = $this->row(
			[ 'cur_clicks' => 20, 'cur_position' => 9.0, 'prev_clicks' => 100, 'prev_position' => 4.0 ]
		);
		$losers = ( new DeclineDetector() )->detect( [ $row ] );

		$this->assertCount( 1, $losers );
		$this->assertSame( -80, $losers[0]['clicks_delta'] );
		$this->assertSame( 'ranking_loss', $losers[0]['cause'] );
		$this->assertSame( 'critical', $losers[0]['priority'] );
		$this->assertSame( 'refresh_content_and_links', $losers[0]['suggested_fix'] );
	}

	public function test_decline_detects_disappearance(): void {
		$row    = $this->row(
			[ 'cur_clicks' => 0, 'cur_impressions' => 0, 'cur_position' => 0.0, 'cur_ctr' => 0.0, 'prev_clicks' => 90 ]
		);
		$losers = ( new DeclineDetector() )->detect( [ $row ] );

		$this->assertSame( 'disappeared', $losers[0]['cause'] );
		$this->assertSame( 'critical', $losers[0]['priority'] );
	}

	public function test_decline_classifies_ctr_decline_when_position_holds(): void {
		$row    = $this->row(
			[ 'cur_clicks' => 40, 'cur_position' => 3.2, 'cur_ctr' => 0.04, 'prev_clicks' => 100, 'prev_position' => 3.0, 'prev_ctr' => 0.10 ]
		);
		$losers = ( new DeclineDetector() )->detect( [ $row ] );

		$this->assertSame( 'ctr_decline', $losers[0]['cause'] );
		$this->assertSame( 'rewrite_title_meta', $losers[0]['suggested_fix'] );
	}
}
