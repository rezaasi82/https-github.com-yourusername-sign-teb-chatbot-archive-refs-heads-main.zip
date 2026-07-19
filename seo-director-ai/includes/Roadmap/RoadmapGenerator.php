<?php
/**
 * Turns open opportunities into prioritized roadmap tasks.
 * Deterministic prioritization (impact × headroom ÷ difficulty); the opportunity
 * score already encodes that, so tasks inherit and rank on it. AI text is optional
 * and layered on later via InsightGenerator — never required to produce a roadmap.
 *
 * @package SEODirector
 */

namespace SEODirector\Roadmap;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\RoadmapTaskRepository;

final class RoadmapGenerator {

	/** Detector → roadmap category + human title template. */
	private const DETECTOR_MAP = array(
		'striking_distance' => array( 'category' => 'content', 'verb' => 'Improve ranking for' ),
		'low_ctr'           => array( 'category' => 'content', 'verb' => 'Rewrite title/meta for' ),
		'faq'               => array( 'category' => 'schema', 'verb' => 'Add FAQ content for' ),
		'internal_link'     => array( 'category' => 'links', 'verb' => 'Add internal links for' ),
	);

	public function __construct(
		private readonly OpportunitiesRepository $opportunities,
		private readonly RoadmapTaskRepository $tasks,
	) {}

	/**
	 * Generate up to $max tasks for the current month from the top open opportunities.
	 *
	 * @return array{created: int, skipped: int}
	 */
	public function generate( string $scope = 'monthly', int $max = 15 ): array {
		$opportunities = $this->opportunities->list_open( $max * 2 );
		$period_start  = 'weekly' === $scope
			? gmdate( 'Y-m-d', strtotime( 'monday this week' ) )
			: gmdate( 'Y-m-01' );

		$created = 0;
		$skipped = 0;
		$priority = $max; // Descending priority; highest-scoring opportunity first.

		foreach ( array_slice( $opportunities, 0, $max ) as $opp ) {
			$map      = self::DETECTOR_MAP[ $opp['detector'] ] ?? array( 'category' => 'content', 'verb' => 'Optimize' );
			$impact   = $this->impact_from_score( (float) $opp['score'] );
			$id       = $this->tasks->insert_if_absent(
				array(
					'roadmap_scope'   => $scope,
					'period_start'    => $period_start,
					'title'           => trim( $map['verb'] . ' “' . $this->truncate( (string) $opp['entity_label'] ) . '”' ),
					'description'     => $this->describe( $opp ),
					'category'        => $map['category'],
					'impact'          => $impact,
					'difficulty'      => (int) $opp['difficulty'],
					'priority'        => $priority,
					'expected_result' => $opp['est_traffic_gain']
						? sprintf(
							/* translators: %s: estimated monthly clicks */
							__( '~%s more clicks/month', 'seo-director-ai' ),
							number_format_i18n( (int) $opp['est_traffic_gain'] )
						)
						: null,
					'opportunity_id'  => (int) $opp['id'],
				)
			);

			if ( $id > 0 ) {
				$this->opportunities->set_status( (int) $opp['id'], 'in_roadmap' );
				$created++;
			} else {
				$skipped++;
			}
			$priority--;
		}

		if ( $created > 0 ) {
			/**
			 * Fires after the roadmap is (re)generated.
			 *
			 * @param string $scope   Roadmap scope.
			 * @param int    $created Tasks created.
			 */
			do_action( 'sda_roadmap_updated', $scope, $created );
		}

		return array( 'created' => $created, 'skipped' => $skipped );
	}

	/** @param array<string, mixed> $opp */
	private function describe( array $opp ): string {
		$data  = (array) ( $opp['data'] ?? array() );
		$parts = array();
		if ( isset( $data['position'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: average position */
				__( 'Currently at position %s.', 'seo-director-ai' ),
				number_format_i18n( (float) $data['position'], 1 )
			);
		}
		if ( isset( $data['impressions'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: impressions */
				__( '%s impressions in the last 28 days.', 'seo-director-ai' ),
				number_format_i18n( (int) $data['impressions'] )
			);
		}
		return implode( ' ', $parts );
	}

	private function impact_from_score( float $score ): int {
		return match ( true ) {
			$score >= 200 => 10,
			$score >= 100 => 8,
			$score >= 50  => 6,
			$score >= 20  => 4,
			default       => 2,
		};
	}

	private function truncate( string $label, int $len = 60 ): string {
		return mb_strlen( $label ) > $len ? mb_substr( $label, 0, $len - 1 ) . '…' : $label;
	}
}
