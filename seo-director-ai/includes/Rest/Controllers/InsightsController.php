<?php
/**
 * /sda/v1/insights/explain — on-demand AI explanation for the site or an entity.
 * Builds the evidence packet deterministically from stored metrics, then delegates
 * to InsightGenerator (cache → budget → provider).
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Ai\InsightGenerator;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\License\Edition;
use SEODirector\License\FeatureGate;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class InsightsController extends BaseController {

	public function __construct(
		private readonly InsightGenerator $generator,
		private readonly PropertiesRepository $properties,
		private readonly GscDailyTotalsRepository $totals,
		private readonly TrendAnalyzer $trend,
		private readonly FeatureGate $gate,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/insights/explain',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'explain' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'scope' => array(
						'type'    => 'string',
						'default' => 'site',
						'enum'    => array( 'site' ),
					),
				),
			)
		);
	}

	public function explain( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->gate->can( Edition::F_AI_INSIGHTS ) ) {
			return new WP_Error(
				'sda_upgrade_required',
				__( 'AI insights are a Pro feature. Upgrade your license to enable them.', 'seo-director-ai' ),
				array( 'status' => 403 )
			);
		}
		if ( ! $this->rate_limit( 'explain', 20 ) ) {
			return new WP_Error( 'sda_rate_limited', __( 'Too many explanation requests — try again shortly.', 'seo-director-ai' ), array( 'status' => 429 ) );
		}

		$property = $this->properties->active_property( 'gsc' );
		if ( ! $property ) {
			return new WP_Error( 'sda_no_data', __( 'Connect Search Console and sync before requesting insights.', 'seo-director-ai' ), array( 'status' => 409 ) );
		}

		$to     = gmdate( 'Y-m-d' );
		$from   = gmdate( 'Y-m-d', strtotime( '-56 days' ) );
		$series = $this->totals->series( (int) $property->id, $from, $to );
		if ( count( $series ) < 14 ) {
			return new WP_Error( 'sda_no_data', __( 'Not enough history yet for a meaningful explanation.', 'seo-director-ai' ), array( 'status' => 409 ) );
		}

		$clicks      = array_map( static fn( $r ) => (float) $r['clicks'], $series );
		$impressions = array_map( static fn( $r ) => (float) $r['impressions'], $series );
		$wow         = $this->trend->wow( $clicks );

		// Evidence packet: only stored, deterministic figures.
		$evidence = array(
			'period'          => array( 'from' => $from, 'to' => $to ),
			'clicks_recent_7' => (int) array_sum( array_slice( $clicks, -7 ) ),
			'clicks_prev_7'   => (int) array_sum( array_slice( $clicks, -14, 7 ) ),
			'clicks_wow_pct'  => null === $wow ? null : round( $wow, 1 ),
			'clicks_mom_pct'  => $this->trend->mom( $clicks ),
			'impressions_wow_pct' => $this->trend->wow( $impressions ),
			'avg_position_recent' => round( $this->trend->moving_average( array_map( static fn( $r ) => (float) $r['position'], $series ), 7 ), 1 ),
			'daily_clicks'    => array_map( 'intval', $clicks ),
		);

		$type   = ( null !== $wow && $wow < 0 ) ? 'explain_decline' : 'explain_growth';
		$result = $this->generator->generate(
			$type,
			$evidence,
			array(
				'entity_type'  => 'site',
				'entity_label' => (string) $property->display_name,
				'period_start' => $from,
				'period_end'   => $to,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'type'    => $type,
				'cached'  => $result['cached'],
				'insight' => $result['payload'],
			)
		);
	}
}
