<?php
/**
 * Striking-distance detector: queries ranking 8–20 with meaningful impressions —
 * one push away from page-1/top-3 traffic.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

defined( 'ABSPATH' ) || exit;

final class StrikingDistanceDetector {

	public const SLUG = 'striking_distance';

	private const MIN_POSITION    = 8.0;
	private const MAX_POSITION    = 20.0;
	private const MIN_IMPRESSIONS = 100;

	/**
	 * @param array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float}> $query_stats
	 *        Aggregated per-query stats over the analysis window (e.g. 28 days).
	 * @return Opportunity[]
	 */
	public function detect( array $query_stats ): array {
		$opportunities = array();

		foreach ( $query_stats as $row ) {
			$position    = (float) $row['position'];
			$impressions = (int) $row['impressions'];

			if ( $position < self::MIN_POSITION || $position > self::MAX_POSITION || $impressions < self::MIN_IMPRESSIONS ) {
				continue;
			}

			// Headroom: CTR at position ~5 vs. current CTR; impact scales with demand.
			$target_ctr = 0.06;
			$current    = (float) $row['ctr'];
			$headroom   = max( 0.0, $target_ctr - $current );
			$est_gain   = (int) round( $impressions * $headroom );
			if ( $est_gain < 5 ) {
				continue;
			}

			// Difficulty grows the further from page 1 the query sits.
			$difficulty = (int) min( 10, max( 2, round( ( $position - 6 ) / 1.5 ) ) );
			$score      = round( $est_gain / max( 1, $difficulty ), 2 );

			$opportunities[] = new Opportunity(
				'query',
				(string) $row['query'],
				null,
				(float) $score,
				$est_gain,
				$difficulty,
				array(
					'position'    => round( $position, 1 ),
					'impressions' => $impressions,
					'clicks'      => (int) $row['clicks'],
					'ctr'         => round( $current, 4 ),
				)
			);
		}

		usort( $opportunities, static fn( Opportunity $a, Opportunity $b ) => $b->score <=> $a->score );
		return array_slice( $opportunities, 0, 100 );
	}
}
