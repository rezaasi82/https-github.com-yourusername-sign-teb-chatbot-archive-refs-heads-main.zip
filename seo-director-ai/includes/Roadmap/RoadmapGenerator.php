<?php
/**
 * Turns open opportunities into prioritized roadmap tasks. Priority and
 * effort are deterministic (impact ÷ difficulty), so the roadmap is useful
 * with AI disabled; the AI layer only enriches titles/descriptions when
 * available. Idempotent — regenerating won't duplicate existing open tasks.
 *
 * @package SEODirector
 */

namespace SEODirector\Roadmap;

use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\TaskRepository;

defined( 'ABSPATH' ) || exit;

final class RoadmapGenerator {

	/** Detector slug → task category + human title template. */
	private const DETECTOR_MAP = [
		'striking_distance' => [ 'links', 'Push "%s" from position %s toward the top 3' ],
		'low_ctr'           => [ 'content', 'Rewrite title & meta for %s to lift CTR' ],
		'near_top'          => [ 'content', 'Strengthen "%s" to break into %s' ],
	];

	public function __construct(
		private OpportunitiesRepository $opportunities,
		private TaskRepository $tasks,
	) {}

	/**
	 * @param 'weekly'|'monthly'|'quarterly' $scope
	 * @param int                            $limit Max tasks to generate this run.
	 */
	public function generate( string $scope = 'monthly', int $limit = 15 ): void {
		$period_start = 'weekly' === $scope
			? gmdate( 'Y-m-d', strtotime( 'monday this week' ) )
			: ( 'quarterly' === $scope ? gmdate( 'Y-m-01', strtotime( 'first day of this month' ) ) : gmdate( 'Y-m-01' ) );

		$opportunities = $this->opportunities->list_open( $limit );

		foreach ( $opportunities as $opportunity ) {
			if ( 'open' !== $opportunity['status'] ) {
				continue;
			}

			[ $category, $title ] = $this->task_shape( $opportunity );

			$impact     = $this->impact_from_score( (int) $opportunity['est_traffic_gain'] );
			$difficulty = max( 1, min( 10, (int) $opportunity['difficulty'] ) );

			$this->tasks->insert_unique(
				[
					'scope'           => $scope,
					'period_start'    => $period_start,
					'title'           => $title,
					'description'     => $this->describe( $opportunity ),
					'category'        => $category,
					'impact'          => $impact,
					'difficulty'      => $difficulty,
					'est_hours'       => round( $difficulty * 0.75, 1 ),
					'priority'        => (int) round( 100 * $impact / max( 1, $difficulty ) ),
					'expected_result' => sprintf( '~+%s clicks/month if resolved', number_format_i18n( (int) $opportunity['est_traffic_gain'] ) ),
					'opportunity_id'  => (int) $opportunity['id'],
				]
			);

			$this->opportunities->set_status( (int) $opportunity['id'], 'in_roadmap' );
		}

		/**
		 * Fires after a roadmap generation pass.
		 *
		 * @param string $scope Roadmap scope.
		 */
		do_action( 'sda_roadmap_updated', $scope );
	}

	/**
	 * @param array<string, mixed> $opportunity
	 * @return array{0: string, 1: string} [category, title]
	 */
	private function task_shape( array $opportunity ): array {
		$detector = (string) $opportunity['detector'];
		$map      = self::DETECTOR_MAP[ $detector ] ?? [ 'content', 'Improve "%s"' ];
		$position = isset( $opportunity['data']['position'] ) ? (string) $opportunity['data']['position'] : '';
		$target   = isset( $opportunity['data']['target'] ) && 'page1' === $opportunity['data']['target'] ? 'page 1' : 'the top 3';
		$label    = mb_substr( (string) $opportunity['label'], 0, 120 );

		$title = match ( $detector ) {
			'striking_distance' => sprintf( $map[1], $label, $position ),
			'near_top'          => sprintf( $map[1], $label, $target ),
			default             => sprintf( $map[1], $label ),
		};

		return [ $map[0], $title ];
	}

	/**
	 * @param array<string, mixed> $opportunity
	 */
	private function describe( array $opportunity ): string {
		$data = $opportunity['data'];
		$bits = [];
		if ( isset( $data['position'] ) ) {
			$bits[] = sprintf( 'Currently at position %s', $data['position'] );
		}
		if ( isset( $data['impressions'] ) ) {
			$bits[] = sprintf( '%s impressions/period', number_format_i18n( (int) $data['impressions'] ) );
		}

		return implode( ' · ', $bits );
	}

	private function impact_from_score( int $traffic_gain ): int {
		return match ( true ) {
			$traffic_gain >= 500 => 10,
			$traffic_gain >= 200 => 8,
			$traffic_gain >= 100 => 6,
			$traffic_gain >= 30  => 4,
			default              => 2,
		};
	}
}
