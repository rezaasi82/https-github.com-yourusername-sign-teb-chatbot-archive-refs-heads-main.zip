<?php
/**
 * Persists/reads daily health scores ({p}sda_health_scores).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class HealthScoreRepository {

	/**
	 * @param array<string, mixed> $components
	 */
	public function put_today( int $score, array $components ): void {
		global $wpdb;

		$table = Schema::table( 'health_scores' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"INSERT INTO {$table} (site_id, date, score, components) VALUES (%d, %s, %d, %s)
				ON DUPLICATE KEY UPDATE score = VALUES(score), components = VALUES(components)",
				get_current_blog_id(),
				gmdate( 'Y-m-d' ),
				$score,
				wp_json_encode( $components )
			)
		);
	}

	/**
	 * @return array{score: int, band: string, delta: int|null, components: array<string, mixed>}|null
	 */
	public function latest(): ?array {
		global $wpdb;

		$table = Schema::table( 'health_scores' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT date, score, components FROM {$table} WHERE site_id = %d ORDER BY date DESC LIMIT 31", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return null;
		}

		$latest     = $rows[0];
		$score      = (int) $latest['score'];
		$oldest     = end( $rows );
		$components = json_decode( (string) $latest['components'], true );

		return [
			'score'      => $score,
			'band'       => $score >= 75 ? 'green' : ( $score >= 50 ? 'yellow' : 'red' ),
			'delta'      => count( $rows ) > 1 ? $score - (int) $oldest['score'] : null,
			'components' => is_array( $components ) ? $components : [],
		];
	}
}
