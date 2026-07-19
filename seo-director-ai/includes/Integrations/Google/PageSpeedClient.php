<?php
/**
 * PageSpeed Insights v5 client (API-key auth, no OAuth needed).
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class PageSpeedClient {

	private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/** Thresholds per web.dev: good / needs-improvement boundaries. */
	private const CWV_GOOD = array( 'lcp_ms' => 2500, 'cls' => 0.1, 'inp_ms' => 200 );
	private const CWV_POOR = array( 'lcp_ms' => 4000, 'cls' => 0.25, 'inp_ms' => 500 );

	public function __construct(
		private readonly RetryingHttpClient $http,
		private readonly ConnectionsRepository $connections,
		private readonly QuotaManager $quota,
	) {}

	/**
	 * Audit one absolute URL. Returns normalized lab + field metrics.
	 *
	 * @param 'mobile'|'desktop' $strategy Strategy.
	 * @return array<string, mixed>|WP_Error
	 */
	public function audit( string $url, string $strategy = 'mobile' ): array|WP_Error {
		if ( ! $this->quota->can_request( 'psi' ) ) {
			return new WP_Error( 'sda_quota', __( 'PageSpeed daily budget reached; audits resume tomorrow.', 'seo-director-ai' ) );
		}

		$api_key = $this->connections->get_api_key( 'psi' );
		$request = add_query_arg(
			array_filter(
				array(
					'url'      => rawurlencode( $url ),
					'strategy' => $strategy,
					'category' => 'performance',
					'key'      => $api_key ? rawurlencode( $api_key ) : null,
				)
			),
			self::ENDPOINT
		);

		$response = $this->http->get_json( $request, array( 'timeout' => 90 ) );
		$this->quota->record_request( 'psi' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( $response['code'] >= 400 ) {
			$message = (string) ( $response['body']['error']['message'] ?? __( 'PageSpeed API error.', 'seo-director-ai' ) );
			return new WP_Error( 'sda_psi_' . $response['code'], $message, array( 'status' => $response['code'] ) );
		}

		return $this->normalize( $response['body'] );
	}

	/** @param array<string, mixed> $body @return array<string, mixed> */
	private function normalize( array $body ): array {
		$lighthouse = (array) ( $body['lighthouseResult'] ?? array() );
		$audits     = (array) ( $lighthouse['audits'] ?? array() );
		$field      = (array) ( $body['loadingExperience']['metrics'] ?? array() );

		$lab = array(
			'perf_score' => isset( $lighthouse['categories']['performance']['score'] )
				? (int) round( (float) $lighthouse['categories']['performance']['score'] * 100 )
				: null,
			'lcp_ms'     => isset( $audits['largest-contentful-paint']['numericValue'] ) ? (int) $audits['largest-contentful-paint']['numericValue'] : null,
			'cls'        => isset( $audits['cumulative-layout-shift']['numericValue'] ) ? round( (float) $audits['cumulative-layout-shift']['numericValue'], 3 ) : null,
			'inp_ms'     => isset( $audits['interaction-to-next-paint']['numericValue'] ) ? (int) $audits['interaction-to-next-paint']['numericValue'] : null,
			'ttfb_ms'    => isset( $audits['server-response-time']['numericValue'] ) ? (int) $audits['server-response-time']['numericValue'] : null,
		);

		$field_metrics = array(
			'field_lcp_ms' => isset( $field['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ) ? (int) $field['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] : null,
			'field_cls'    => isset( $field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ) ? round( (float) $field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] / 100, 3 ) : null,
			'field_inp_ms' => isset( $field['INTERACTION_TO_NEXT_PAINT']['percentile'] ) ? (int) $field['INTERACTION_TO_NEXT_PAINT']['percentile'] : null,
		);

		// Actionable opportunities: failed audits with savings, mapped to titles.
		$opportunities = array();
		foreach ( $audits as $id => $audit ) {
			$details = (array) ( $audit['details'] ?? array() );
			if ( 'opportunity' === ( $details['type'] ?? '' ) && ( $audit['score'] ?? 1 ) < 0.9 ) {
				$opportunities[] = array(
					'id'         => (string) $id,
					'title'      => (string) ( $audit['title'] ?? $id ),
					'savings_ms' => (int) ( $details['overallSavingsMs'] ?? 0 ),
				);
			}
		}
		usort( $opportunities, static fn( $a, $b ) => $b['savings_ms'] <=> $a['savings_ms'] );

		return array_merge(
			$lab,
			$field_metrics,
			array(
				'cwv_status'    => $this->cwv_status( $field_metrics, $lab ),
				'opportunities' => array_slice( $opportunities, 0, 10 ),
			)
		);
	}

	/**
	 * Field data wins; lab data is the fallback signal.
	 *
	 * @param array<string, mixed> $field Field metrics.
	 * @param array<string, mixed> $lab   Lab metrics.
	 */
	private function cwv_status( array $field, array $lab ): string {
		$lcp = $field['field_lcp_ms'] ?? $lab['lcp_ms'];
		$cls = $field['field_cls'] ?? $lab['cls'];
		$inp = $field['field_inp_ms'] ?? $lab['inp_ms'];

		if ( null === $lcp && null === $cls && null === $inp ) {
			return 'unknown';
		}
		$checks = array(
			'lcp_ms' => $lcp,
			'cls'    => $cls,
			'inp_ms' => $inp,
		);
		$status = 'good';
		foreach ( $checks as $metric => $value ) {
			if ( null === $value ) {
				continue;
			}
			if ( $value > self::CWV_POOR[ $metric ] ) {
				return 'poor';
			}
			if ( $value > self::CWV_GOOD[ $metric ] ) {
				$status = 'needs_improvement';
			}
		}
		return $status;
	}
}
