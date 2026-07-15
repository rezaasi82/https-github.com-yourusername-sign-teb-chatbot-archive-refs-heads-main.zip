<?php
/**
 * FAQ / People-Also-Ask opportunities: question-form queries that earn
 * impressions but sit outside the top 3, where a concise FAQ answer block
 * can win the PAA slot. Pure.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

final class FaqDetector implements OpportunityDetectorInterface {

	private const MIN_IMPRESSIONS = 150;
	private const QUESTION_WORDS   = [ 'how', 'what', 'why', 'when', 'where', 'who', 'which', 'is', 'are', 'can', 'does', 'do', 'should', 'will' ];

	public function __construct( private ExpectedCtrCurve $ctr_curve ) {}

	public function slug(): string {
		return 'faq';
	}

	public function detect( array $rows ): array {
		$opportunities = [];

		foreach ( $rows as $row ) {
			if ( ! $this->is_question( $row->label ) || $row->cur_impressions < self::MIN_IMPRESSIONS || $row->cur_position <= 3 ) {
				continue;
			}

			$gain = (int) round( $row->cur_impressions * max( 0, $this->ctr_curve->expected( 3.0 ) - $row->cur_ctr ) );
			if ( $gain < 5 ) {
				continue;
			}

			$opportunities[] = [
				'entity_type'      => 'query',
				'hash'             => $row->hash_hex,
				'label'            => $row->label,
				'secondary_label'  => null,
				'score'            => round( $gain / 3, 2 ),
				'est_traffic_gain' => $gain,
				'difficulty'       => 3,
				'data'             => [
					'position'    => round( $row->cur_position, 1 ),
					'impressions' => $row->cur_impressions,
					'intent'      => 'question',
				],
			];
		}

		return $opportunities;
	}

	private function is_question( string $query ): bool {
		$query = mb_strtolower( trim( $query ) );
		if ( str_contains( $query, '?' ) ) {
			return true;
		}

		$first = strtok( $query, ' ' );

		return in_array( $first, self::QUESTION_WORDS, true );
	}
}
