<?php
/**
 * Low-CTR detector: queries ranking well (top 10) whose CTR badly underperforms
 * the position-expected curve — usually a title/meta/snippet problem.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

defined( 'ABSPATH' ) || exit;

final class LowCtrDetector {

	public const SLUG = 'low_ctr';

	private const MIN_IMPRESSIONS = 200;
	private const MAX_POSITION    = 10.0;
	private const UNDERPERFORM    = 0.5; // flag when CTR < 50% of expected.

	/**
	 * @param array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float}> $query_stats
	 * @return Opportunity[]
	 */
	public function detect( array $query_stats ): array {
		$opportunities = array();

		foreach ( $query_stats as $row ) {
			$position    = (float) $row['position'];
			$impressions = (int) $row['impressions'];
			$ctr         = (float) $row['ctr'];

			if ( $position <= 0 || $position > self::MAX_POSITION || $impressions < self::MIN_IMPRESSIONS ) {
				continue;
			}

			$expected = $this->expected_ctr( $position );
			if ( $expected <= 0 || $ctr >= $expected * self::UNDERPERFORM ) {
				continue;
			}

			$est_gain = (int) round( $impressions * ( $expected - $ctr ) );
			if ( $est_gain < 10 ) {
				continue;
			}

			// Snippet rewrites are cheap: fixed low difficulty.
			$difficulty = 2;
			$score      = round( $est_gain / $difficulty, 2 );

			$opportunities[] = new Opportunity(
				'query',
				(string) $row['query'],
				null,
				(float) $score,
				$est_gain,
				$difficulty,
				array(
					'position'     => round( $position, 1 ),
					'impressions'  => $impressions,
					'ctr'          => round( $ctr, 4 ),
					'expected_ctr' => round( $expected, 4 ),
				)
			);
		}

		usort( $opportunities, static fn( Opportunity $a, Opportunity $b ) => $b->score <=> $a->score );
		return array_slice( $opportunities, 0, 100 );
	}

	private function expected_ctr( float $position ): float {
		return match ( true ) {
			$position <= 1.5 => 0.28,
			$position <= 3   => 0.11,
			$position <= 5   => 0.06,
			$position <= 10  => 0.025,
			default          => 0.0,
		};
	}
}
