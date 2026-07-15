<?php
/**
 * Queries just outside Top 3 (positions 4–6) and just outside Page 1
 * (positions 11–15): the smallest pushes with the biggest CTR jumps.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

final class NearTopDetector implements OpportunityDetectorInterface {

	private const MIN_IMPRESSIONS = 200;

	public function __construct( private ExpectedCtrCurve $ctr_curve ) {}

	public function slug(): string {
		return 'near_top';
	}

	public function detect( array $rows ): array {
		$opportunities = [];

		foreach ( $rows as $row ) {
			$band = $this->band( $row->cur_position );
			if ( null === $band || $row->cur_impressions < self::MIN_IMPRESSIONS ) {
				continue;
			}

			[ $target_position, $difficulty, $target_label ] = 'top3' === $band
				? [ 3.0, 5, 'top3' ]
				: [ 8.0, 4, 'page1' ];

			$gain = (int) round(
				$row->cur_impressions * max( 0, $this->ctr_curve->expected( $target_position ) - $row->cur_ctr )
			);
			if ( $gain < 5 ) {
				continue;
			}

			$opportunities[] = [
				'entity_type'      => 'query',
				'hash'             => $row->hash_hex,
				'label'            => $row->label,
				'secondary_label'  => null,
				'score'            => round( $gain / $difficulty, 2 ),
				'est_traffic_gain' => $gain,
				'difficulty'       => $difficulty,
				'data'             => [
					'position'    => round( $row->cur_position, 1 ),
					'impressions' => $row->cur_impressions,
					'target'      => $target_label,
				],
			];
		}

		return $opportunities;
	}

	private function band( float $position ): ?string {
		if ( $position >= 3.5 && $position <= 6.4 ) {
			return 'top3';
		}
		if ( $position >= 10.5 && $position <= 15.4 ) {
			return 'page1';
		}

		return null;
	}
}
