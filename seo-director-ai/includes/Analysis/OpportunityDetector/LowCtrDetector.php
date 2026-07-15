<?php
/**
 * Pages with high impressions whose CTR falls well below the
 * position-expected curve — title/meta rewrites with fast payoff.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

final class LowCtrDetector implements OpportunityDetectorInterface {

	private const MIN_IMPRESSIONS = 500;
	private const SHORTFALL       = 0.65; // Flag when actual CTR < 65% of expected.

	public function __construct( private ExpectedCtrCurve $ctr_curve ) {}

	public function slug(): string {
		return 'low_ctr';
	}

	public function detect( array $rows ): array {
		$opportunities = [];

		foreach ( $rows as $row ) {
			if ( $row->cur_impressions < self::MIN_IMPRESSIONS || $row->cur_position > 20 ) {
				continue;
			}

			$expected = $this->ctr_curve->expected( $row->cur_position );
			if ( $expected <= 0 || $row->cur_ctr >= $expected * self::SHORTFALL ) {
				continue;
			}

			$gain = (int) round( $row->cur_impressions * ( $expected - $row->cur_ctr ) );
			if ( $gain < 10 ) {
				continue;
			}

			$opportunities[] = [
				'entity_type'      => 'page',
				'hash'             => $row->hash_hex,
				'label'            => $row->label,
				'secondary_label'  => null,
				'score'            => round( $gain / 3, 2 ), // Difficulty 3: meta rewrite is cheap.
				'est_traffic_gain' => $gain,
				'difficulty'       => 3,
				'data'             => [
					'position'     => round( $row->cur_position, 1 ),
					'impressions'  => $row->cur_impressions,
					'ctr'          => round( $row->cur_ctr, 4 ),
					'expected_ctr' => round( $expected, 4 ),
				],
			];
		}

		return $opportunities;
	}
}
