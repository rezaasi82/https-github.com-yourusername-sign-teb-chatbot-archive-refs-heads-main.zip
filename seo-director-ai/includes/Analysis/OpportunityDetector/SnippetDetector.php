<?php
/**
 * Featured-snippet opportunities: informational queries ranking 2–8 with
 * strong impressions, where a well-structured answer (list/table/definition)
 * can capture position zero. Pure.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

final class SnippetDetector implements OpportunityDetectorInterface {

	private const MIN_IMPRESSIONS = 300;
	private const INFORMATIONAL    = [ 'how', 'what', 'why', 'best', 'guide', 'tips', 'ways', 'examples', 'ideas', 'steps', 'list', 'checklist' ];

	public function slug(): string {
		return 'snippet';
	}

	public function detect( array $rows ): array {
		$opportunities = [];

		foreach ( $rows as $row ) {
			if ( $row->cur_position < 1.5 || $row->cur_position > 8 || $row->cur_impressions < self::MIN_IMPRESSIONS ) {
				continue;
			}
			if ( ! $this->is_informational( $row->label ) ) {
				continue;
			}

			// Snippet capture roughly adds a position-1-equivalent CTR uplift.
			$gain = (int) round( $row->cur_impressions * 0.08 );
			if ( $gain < 10 ) {
				continue;
			}

			$opportunities[] = [
				'entity_type'      => 'query',
				'hash'             => $row->hash_hex,
				'label'            => $row->label,
				'secondary_label'  => null,
				'score'            => round( $gain / 4, 2 ),
				'est_traffic_gain' => $gain,
				'difficulty'       => 4,
				'data'             => [
					'position'    => round( $row->cur_position, 1 ),
					'impressions' => $row->cur_impressions,
					'intent'      => 'informational',
				],
			];
		}

		return $opportunities;
	}

	private function is_informational( string $query ): bool {
		$query = mb_strtolower( $query );
		foreach ( self::INFORMATIONAL as $word ) {
			if ( str_contains( $query, $word ) ) {
				return true;
			}
		}

		return false;
	}
}
