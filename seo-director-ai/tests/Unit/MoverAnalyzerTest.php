<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\MoverAnalyzer;

final class MoverAnalyzerTest extends TestCase {

	private MoverAnalyzer $analyzer;

	protected function setUp(): void {
		$this->analyzer = new MoverAnalyzer();
	}

	/** @return array<string, array<string, mixed>> */
	private function row( string $label, int $clicks, float $position ): array {
		return array( 'clicks' => $clicks, 'impressions' => $clicks * 20, 'ctr' => 0.05, 'position' => $position, 'best_position' => $position );
	}

	public function test_splits_winners_and_losers_by_click_delta(): void {
		$recent = array(
			'rising'  => $this->row( 'rising', 200, 4.0 ),
			'falling' => $this->row( 'falling', 30, 9.0 ),
		);
		$previous = array(
			'rising'  => $this->row( 'rising', 80, 6.0 ),
			'falling' => $this->row( 'falling', 150, 5.0 ),
		);

		$movers = $this->analyzer->movers( $recent, $previous );

		$this->assertCount( 1, $movers['winners'] );
		$this->assertSame( 'rising', $movers['winners'][0]['label'] );
		$this->assertSame( 120, $movers['winners'][0]['clicks_delta'] );

		$this->assertCount( 1, $movers['losers'] );
		$this->assertSame( 'falling', $movers['losers'][0]['label'] );
		$this->assertSame( -120, $movers['losers'][0]['clicks_delta'] );
	}

	public function test_new_entry_has_null_pct_change_and_is_new_flag(): void {
		$movers = $this->analyzer->movers(
			array( 'fresh' => $this->row( 'fresh', 50, 7.0 ) ),
			array()
		);
		$this->assertCount( 1, $movers['winners'] );
		$this->assertTrue( $movers['winners'][0]['is_new'] );
		$this->assertNull( $movers['winners'][0]['pct_change'] );
	}

	public function test_lost_entity_flagged_and_counted_as_loser(): void {
		$movers = $this->analyzer->movers(
			array(),
			array( 'gone' => $this->row( 'gone', 90, 3.0 ) )
		);
		$this->assertCount( 1, $movers['losers'] );
		$this->assertTrue( $movers['losers'][0]['is_lost'] );
	}

	public function test_noise_below_floor_is_ignored(): void {
		$movers = $this->analyzer->movers(
			array( 'tiny' => $this->row( 'tiny', 3, 12.0 ) ),
			array( 'tiny' => $this->row( 'tiny', 1, 15.0 ) )
		);
		$this->assertCount( 0, $movers['winners'] );
		$this->assertCount( 0, $movers['losers'] );
	}

	public function test_winners_sorted_by_delta_descending(): void {
		$recent = array(
			'a' => $this->row( 'a', 60, 5.0 ),
			'b' => $this->row( 'b', 300, 4.0 ),
		);
		$previous = array(
			'a' => $this->row( 'a', 40, 6.0 ),
			'b' => $this->row( 'b', 40, 8.0 ),
		);
		$movers = $this->analyzer->movers( $recent, $previous );
		$this->assertSame( 'b', $movers['winners'][0]['label'] );
		$this->assertSame( 'a', $movers['winners'][1]['label'] );
	}
}
