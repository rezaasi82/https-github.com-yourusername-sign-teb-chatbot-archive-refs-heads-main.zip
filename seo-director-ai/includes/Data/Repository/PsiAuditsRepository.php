<?php
/**
 * PageSpeed Insights audit history ({p}sda_psi_audits).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

final class PsiAuditsRepository extends BaseRepository {

	protected const TABLE = 'psi_audits';

	/** @param array<string, mixed> $audit Mapped PSI result. */
	public function insert( string $page_path, string $strategy, array $audit ): void {
		$db = $this->db();
		$db->insert(
			$this->table(),
			array(
				'site_id'      => $this->site_id(),
				'page_hash'    => $this->bin_hash( $page_path ),
				'page_path'    => $page_path,
				'strategy'     => $strategy,
				'audited_at'   => current_time( 'mysql', true ),
				'perf_score'   => $audit['perf_score'],
				'lcp_ms'       => $audit['lcp_ms'],
				'cls'          => $audit['cls'],
				'inp_ms'       => $audit['inp_ms'],
				'ttfb_ms'      => $audit['ttfb_ms'],
				'field_lcp_ms' => $audit['field_lcp_ms'],
				'field_cls'    => $audit['field_cls'],
				'field_inp_ms' => $audit['field_inp_ms'],
				'cwv_status'   => $audit['cwv_status'],
				'opportunities' => (string) wp_json_encode( $audit['opportunities'] ?? array() ),
			)
		);
	}

	/**
	 * Latest audit per strategy for a page (or site root).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function latest_for_page( string $page_path, int $limit = 2 ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT page_path, strategy, audited_at, perf_score, lcp_ms, cls, inp_ms, ttfb_ms,
						field_lcp_ms, field_cls, field_inp_ms, cwv_status
				 FROM {$this->table()}
				 WHERE site_id = %d AND page_hash = %s
				 ORDER BY audited_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$this->bin_hash( $page_path ),
				$limit
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	/** @return array<int, array<string, mixed>> Most recent audits across pages. */
	public function recent( int $limit = 20 ): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT page_path, strategy, audited_at, perf_score, lcp_ms, cls, inp_ms, ttfb_ms, cwv_status
				 FROM {$this->table()}
				 WHERE site_id = %d ORDER BY audited_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$limit
			),
			ARRAY_A
		);
		return $rows ?: array();
	}
}
