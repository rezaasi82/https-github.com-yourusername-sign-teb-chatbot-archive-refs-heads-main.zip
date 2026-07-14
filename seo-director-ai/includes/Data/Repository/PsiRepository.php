<?php
/**
 * Writes/reads {p}sda_psi_audits.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class PsiRepository {

	/**
	 * @param array{perf_score: int|null, lcp_ms: int|null, cls: float|null, inp_ms: int|null, ttfb_ms: int|null, field_lcp_ms: int|null, field_cls: float|null, field_inp_ms: int|null, cwv_status: string, opportunities: array<mixed>} $audit
	 */
	public function insert( string $page_path, string $page_hash_hex, string $strategy, array $audit ): void {
		global $wpdb;

		$table = Schema::table( 'psi_audits' );

		$sql = "INSERT INTO {$table}
			(site_id, page_hash, page_path, strategy, audited_at, perf_score, lcp_ms, cls, inp_ms, ttfb_ms, field_lcp_ms, field_cls, field_inp_ms, cwv_status, opportunities)
			VALUES (%d, UNHEX(%s), %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)";

		// %s placeholders for nullable numerics: $wpdb->prepare has no nullable
		// support, so nulls are pre-substituted via the NULLIF trick below.
		$values = [
			get_current_blog_id(),
			$page_hash_hex,
			$page_path,
			$strategy,
			current_time( 'mysql', true ),
			$audit['perf_score'] ?? '',
			$audit['lcp_ms'] ?? '',
			$audit['cls'] ?? '',
			$audit['inp_ms'] ?? '',
			$audit['ttfb_ms'] ?? '',
			$audit['field_lcp_ms'] ?? '',
			$audit['field_cls'] ?? '',
			$audit['field_inp_ms'] ?? '',
			$audit['cwv_status'],
			wp_json_encode( $audit['opportunities'] ),
		];

		$prepared = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// Convert empty-string sentinels for numeric columns into SQL NULL.
		$prepared = str_replace( "''", 'NULL', $prepared );

		$wpdb->query( $prepared ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Latest audit per strategy for a page.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function latest_for_page( string $page_hash_hex ): array {
		global $wpdb;

		$table = Schema::table( 'psi_audits' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE site_id = %d AND page_hash = UNHEX(%s) ORDER BY audited_at DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$page_hash_hex
			),
			ARRAY_A
		);

		$latest = [];
		foreach ( $rows ?: [] as $row ) {
			$strategy = (string) $row['strategy'];
			if ( ! isset( $latest[ $strategy ] ) ) {
				unset( $row['page_hash'] );
				$latest[ $strategy ] = $row;
			}
		}

		return $latest;
	}
}
