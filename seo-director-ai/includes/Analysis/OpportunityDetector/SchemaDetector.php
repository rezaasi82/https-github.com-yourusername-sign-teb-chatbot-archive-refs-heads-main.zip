<?php
/**
 * Structured-data opportunities: queries with transactional/review/recipe
 * intent whose landing pages likely lack the matching schema. Adding the
 * right structured data can earn rich results and lift CTR. Pure heuristic
 * on query intent (the definitive page-type check happens at fix time).
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\OpportunityDetector;

use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

final class SchemaDetector implements OpportunityDetectorInterface {

	private const MIN_IMPRESSIONS = 200;

	/** Intent keyword => schema type suggested. */
	private const INTENT = [
		'price'    => 'Product',
		'cost'     => 'Product',
		'buy'      => 'Product',
		'review'   => 'Review',
		'reviews'  => 'Review',
		'rating'   => 'AggregateRating',
		'recipe'   => 'Recipe',
		'how to'   => 'HowTo',
		'vs'       => 'FAQPage',
		'near me'  => 'LocalBusiness',
	];

	public function slug(): string {
		return 'schema';
	}

	public function detect( array $rows ): array {
		$opportunities = [];

		foreach ( $rows as $row ) {
			if ( $row->cur_impressions < self::MIN_IMPRESSIONS || $row->cur_position > 15 ) {
				continue;
			}

			$type = $this->schema_type( $row->label );
			if ( null === $type ) {
				continue;
			}

			// Rich results typically add ~15% relative CTR.
			$gain = (int) round( $row->cur_impressions * $row->cur_ctr * 0.15 );
			if ( $gain < 5 ) {
				continue;
			}

			$opportunities[] = [
				'entity_type'      => 'query',
				'hash'             => $row->hash_hex,
				'label'            => $row->label,
				'secondary_label'  => $type,
				'score'            => round( $gain / 4, 2 ),
				'est_traffic_gain' => $gain,
				'difficulty'       => 4,
				'data'             => [
					'position'    => round( $row->cur_position, 1 ),
					'impressions' => $row->cur_impressions,
					'schema_type' => $type,
				],
			];
		}

		return $opportunities;
	}

	private function schema_type( string $query ): ?string {
		$query = mb_strtolower( $query );
		foreach ( self::INTENT as $keyword => $type ) {
			if ( str_contains( $query, $keyword ) ) {
				return $type;
			}
		}

		return null;
	}
}
