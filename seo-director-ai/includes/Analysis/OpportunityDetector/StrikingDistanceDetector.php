<?php
/**
 * Keywords ranking 4–20: the classic striking-distance set. Scored by
 * impression volume × CTR headroom if the keyword reached position 3.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

final class StrikingDistanceDetector implements OpportunityDetectorInterface {

	private const MIN_IMPRESSIONS = 100;

	public function __construct( private ExpectedCtrCurve $ctr_curve ) {}

	public function slug(): string {
		return 'striking_distance';
	}

	public function detect( array $rows ): array {
		$opportunities = [];

		foreach ( $rows as $row ) {
			if ( $row->cur_position < 3.5 || $row->cur_position > 20 || $row->cur_impressions < self::MIN_IMPRESSIONS ) {
				continue;
			}

			$target_ctr = $this->ctr_curve->expected( 3.0 );
			$gain       = (int) round( $row->cur_impressions * max( 0, $target_ctr - $row->cur_ctr ) );
			if ( $gain < 5 ) {
				continue;
			}

			$difficulty = $row->cur_position <= 10 ? 4 : 6;

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
					'clicks'      => $row->cur_clicks,
					'target'      => 'top3',
				],
			];
		}

		return $opportunities;
	}
}
