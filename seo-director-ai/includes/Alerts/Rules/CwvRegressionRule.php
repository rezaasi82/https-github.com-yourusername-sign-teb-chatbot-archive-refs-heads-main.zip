<?php
/**
 * Core Web Vitals regression: a page whose latest audit is "poor" after a
 * previous audit of the same page/strategy that was better.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Rules;

use SEODirector\Alerts\AlertRuleInterface;
use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class CwvRegressionRule implements AlertRuleInterface {

	public function slug(): string {
		return 'cwv_regression';
	}

	public function evaluate(): array {
		global $wpdb;

		$table = Schema::table( 'psi_audits' );

		// Latest two audits per page×strategy; flag good/ni → poor transitions.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT HEX(page_hash) AS hash_hex, page_path, strategy, cwv_status, audited_at
				FROM {$table} WHERE site_id = %d AND audited_at >= %s
				ORDER BY page_hash, strategy, audited_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) )
			),
			ARRAY_A
		);

		$alerts   = [];
		$previous = null;

		foreach ( $rows ?: [] as $row ) {
			$key = $row['hash_hex'] . '|' . $row['strategy'];

			if ( null === $previous || $previous['key'] !== $key ) {
				// First (= latest) row for this page×strategy.
				$previous = [
					'key'    => $key,
					'latest' => $row,
					'done'   => false,
				];
				continue;
			}

			if ( $previous['done'] ) {
				continue;
			}
			$previous['done'] = true;

			$latest_status = (string) $previous['latest']['cwv_status'];
			$prior_status  = (string) $row['cwv_status'];

			if ( 'poor' === $latest_status && in_array( $prior_status, [ 'good', 'needs_improvement' ], true ) ) {
				$alerts[] = [
					'severity'        => 'high',
					'message'         => sprintf(
						/* translators: 1: page path, 2: device strategy. */
						__( 'Core Web Vitals regressed to "poor" on %1$s (%2$s).', 'seo-director-ai' ),
						(string) $previous['latest']['page_path'],
						(string) $previous['latest']['strategy']
					),
					'fingerprint_hex' => md5( 'cwv|' . $key ),
					'entity_label'    => (string) $previous['latest']['page_path'],
					'data'            => [
						'strategy' => (string) $previous['latest']['strategy'],
						'from'     => $prior_status,
						'to'       => $latest_status,
					],
				];
			}
		}

		return $alerts;
	}
}
