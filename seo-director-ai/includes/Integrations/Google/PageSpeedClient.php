<?php
/**
 * PageSpeed Insights API client (API-key based, no OAuth needed).
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Integrations\Http\RetryingHttpClient;

defined( 'ABSPATH' ) || exit;

final class PageSpeedClient {

	private const BASE = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	public function __construct(
		private ConnectionsRepository $connections,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
	) {}

	/**
	 * Audit a URL. Returns normalized lab + field metrics.
	 *
	 * @param string $strategy 'mobile' or 'desktop'.
	 * @return array{perf_score: int|null, lcp_ms: int|null, cls: float|null, inp_ms: int|null, ttfb_ms: int|null, field_lcp_ms: int|null, field_cls: float|null, field_inp_ms: int|null, cwv_status: string, opportunities: array<int, array{id: string, title: string, savings_ms: int}>}|\WP_Error
	 */
	public function audit( string $url, string $strategy = 'mobile' ): array|\WP_Error {
		if ( ! $this->quota->allow( 'psi' ) ) {
			return new \WP_Error( 'sda_quota', 'PSI API budget exhausted or circuit breaker open.' );
		}

		$connection = $this->connections->get( 'psi' );
		$api_key    = (string) ( $connection['credentials']['api_key'] ?? '' );

		$request_url = add_query_arg(
			array_filter(
				[
					'url'      => rawurlencode( $url ),
					'strategy' => $strategy,
					'category' => 'performance',
					'key'      => $api_key ? rawurlencode( $api_key ) : null,
				]
			),
			self::BASE
		);

		$result = $this->http->get( $request_url, [ 'timeout' => 60 ] );
		$this->quota->record( 'psi' );

		if ( 429 === $result->status || $result->status >= 500 ) {
			$this->quota->trip( 'psi' );
		}

		$data = $result->json();
		if ( ! $result->ok() || ! is_array( $data ) ) {
			return new \WP_Error( 'sda_psi_api', (string) ( $data['error']['message'] ?? $result->error ?? ( 'HTTP ' . $result->status ) ) );
		}

		$audits     = $data['lighthouseResult']['audits'] ?? [];
		$metrics    = $data['loadingExperience']['metrics'] ?? [];
		$categories = $data['lighthouseResult']['categories'] ?? [];

		$opportunities = [];
		foreach ( (array) $audits as $id => $audit ) {
			$savings = (int) ( $audit['details']['overallSavingsMs'] ?? 0 );
			if ( $savings > 100 ) {
				$opportunities[] = [
					'id'         => (string) $id,
					'title'      => (string) ( $audit['title'] ?? $id ),
					'savings_ms' => $savings,
				];
			}
		}
		usort( $opportunities, static fn( $a, $b ) => $b['savings_ms'] <=> $a['savings_ms'] );

		$field_category = (string) ( $data['loadingExperience']['overall_category'] ?? '' );
		$cwv_status     = match ( $field_category ) {
			'FAST'    => 'good',
			'AVERAGE' => 'needs_improvement',
			'SLOW'    => 'poor',
			default   => 'unknown',
		};

		$num = static fn( $v ) => null === $v ? null : (float) $v;

		$score = $categories['performance']['score'] ?? null;

		return [
			'perf_score'    => null === $score ? null : (int) round( 100 * (float) $score ),
			'lcp_ms'        => ( $v = $num( $audits['largest-contentful-paint']['numericValue'] ?? null ) ) === null ? null : (int) $v,
			'cls'           => $num( $audits['cumulative-layout-shift']['numericValue'] ?? null ),
			'inp_ms'        => ( $v = $num( $audits['interaction-to-next-paint']['numericValue'] ?? null ) ) === null ? null : (int) $v,
			'ttfb_ms'       => ( $v = $num( $audits['server-response-time']['numericValue'] ?? null ) ) === null ? null : (int) $v,
			'field_lcp_ms'  => isset( $metrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ) ? (int) $metrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] : null,
			'field_cls'     => isset( $metrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ) ? (float) $metrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] / 100 : null,
			'field_inp_ms'  => isset( $metrics['INTERACTION_TO_NEXT_PAINT']['percentile'] ) ? (int) $metrics['INTERACTION_TO_NEXT_PAINT']['percentile'] : null,
			'cwv_status'    => $cwv_status,
			'opportunities' => array_slice( $opportunities, 0, 10 ),
		];
	}
}
