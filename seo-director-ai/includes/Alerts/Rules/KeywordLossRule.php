<?php
/**
 * Former top-10 keywords that fell out of the top 20 (or vanished)
 * between the last two 28-day windows.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Rules;

use SEODirector\Alerts\AlertRuleInterface;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class KeywordLossRule implements AlertRuleInterface {

	private const MIN_PREV_IMPRESSIONS = 100;
	private const MAX_ALERTS           = 10;

	public function __construct(
		private MoversRepository $movers,
		private PropertiesRepository $properties,
	) {}

	public function slug(): string {
		return 'keyword_loss';
	}

	public function evaluate(): array {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return [];
		}

		$cur_to    = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$cur_from  = gmdate( 'Y-m-d', strtotime( $cur_to . ' -27 days' ) );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $cur_from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -27 days' ) );

		$rows = $this->movers->period_aggregates( 'query', $property['id'], $cur_from, $cur_to, $prev_from, $prev_to, 1000 );

		$alerts = [];

		foreach ( $rows as $row ) {
			$was_top10 = $row->prev_position > 0 && $row->prev_position <= 10 && $row->prev_impressions >= self::MIN_PREV_IMPRESSIONS;
			$now_lost  = 0 === $row->cur_impressions || $row->cur_position > 20;

			if ( ! $was_top10 || ! $now_lost ) {
				continue;
			}

			$alerts[] = [
				'severity'        => 'high',
				'message'         => 0 === $row->cur_impressions
					? sprintf(
						/* translators: 1: keyword, 2: previous position. */
						__( 'Keyword "%1$s" disappeared from search results (was position %2$s).', 'seo-director-ai' ),
						$row->label,
						number_format_i18n( $row->prev_position, 1 )
					)
					: sprintf(
						/* translators: 1: keyword, 2: previous position, 3: current position. */
						__( 'Keyword "%1$s" fell from position %2$s to %3$s.', 'seo-director-ai' ),
						$row->label,
						number_format_i18n( $row->prev_position, 1 ),
						number_format_i18n( $row->cur_position, 1 )
					),
				'fingerprint_hex' => md5( 'keyword_loss|' . $row->hash_hex ),
				'entity_label'    => $row->label,
				'data'            => [
					'prev_position' => round( $row->prev_position, 1 ),
					'cur_position'  => round( $row->cur_position, 1 ),
					'prev_clicks'   => $row->prev_clicks,
					'cur_clicks'    => $row->cur_clicks,
				],
			];

			if ( count( $alerts ) >= self::MAX_ALERTS ) {
				break;
			}
		}

		return $alerts;
	}
}
