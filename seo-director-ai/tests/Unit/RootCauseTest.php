<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Analysis\MoverRow;
use SEODirector\Analysis\RootCause\CauseCandidateEngine;
use SEODirector\Analysis\RootCause\CoreUpdateCalendar;

final class RootCauseTest extends TestCase {

	private CauseCandidateEngine $engine;

	protected function setUp(): void {
		$this->engine = new CauseCandidateEngine( new CoreUpdateCalendar() );
	}

	private function row( array $o = [] ): MoverRow {
		$d = [
			'cur_clicks' => 20, 'cur_impressions' => 800, 'cur_position' => 8.0, 'cur_ctr' => 0.025,
			'prev_clicks' => 100, 'prev_impressions' => 1000, 'prev_position' => 4.0, 'prev_ctr' => 0.10,
		];
		$v = array_merge( $d, $o );
		return new MoverRow( 'kw', str_repeat( 'a', 32 ), $v['cur_clicks'], $v['cur_impressions'], $v['cur_position'], $v['cur_ctr'], $v['prev_clicks'], $v['prev_impressions'], $v['prev_position'], $v['prev_ctr'] );
	}

	public function test_ranking_drop_is_a_strong_candidate(): void {
		$packet = $this->engine->build( $this->row(), null );
		$causes = array_column( $packet['candidates'], 'cause' );
		$this->assertContains( 'ranking_drop', $causes );
		$this->assertSame( -80, $packet['clicks_change'] );
	}

	public function test_ctr_drop_flagged_when_position_holds(): void {
		$packet = $this->engine->build(
			$this->row( [ 'cur_position' => 3.2, 'prev_position' => 3.0, 'cur_ctr' => 0.03, 'prev_ctr' => 0.10 ] ),
			null
		);
		$this->assertContains( 'ctr_drop', array_column( $packet['candidates'], 'cause' ) );
	}

	public function test_flags_add_candidates(): void {
		$packet = $this->engine->build( $this->row(), null, [ 'cannibalization' => true, 'cwv_regressed' => true ] );
		$causes = array_column( $packet['candidates'], 'cause' );
		$this->assertContains( 'cannibalization', $causes );
		$this->assertContains( 'technical_cwv', $causes );
	}

	public function test_core_update_proximity(): void {
		// CoreUpdateCalendar seed includes 2026-03-05.
		$packet = $this->engine->build( $this->row(), '2026-03-07' );
		$this->assertContains( 'core_update', array_column( $packet['candidates'], 'cause' ) );
	}

	public function test_default_content_decay_when_no_signal(): void {
		$flat   = $this->row( [ 'cur_position' => 4.1, 'prev_position' => 4.0, 'cur_ctr' => 0.10, 'cur_impressions' => 990 ] );
		$packet = $this->engine->build( $flat, null );
		$this->assertContains( 'content_decay', array_column( $packet['candidates'], 'cause' ) );
	}

	public function test_calendar_returns_null_when_far(): void {
		$this->assertNull( ( new CoreUpdateCalendar() )->near( '2026-01-01' ) );
	}
}
