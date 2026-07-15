<?php
/**
 * Persists AI insights with the evidence snapshot that produced them
 * ({p}sda_insights). The unique cache key (type, evidence_hash, lang) makes
 * writes idempotent and lets InsightCache short-circuit re-spends.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class InsightRepository {

	/**
	 * @return array{id: int, payload: array<string, mixed>, created_at: string}|null
	 */
	public function find_by_cache( string $type, string $evidence_hash_hex, string $lang ): ?array {
		global $wpdb;

		$table = Schema::table( 'insights' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, payload, created_at FROM {$table} WHERE site_id = %d AND type = %s AND evidence_hash = UNHEX(%s) AND lang = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$type,
				$evidence_hash_hex,
				$lang
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$payload = json_decode( (string) $row['payload'], true );

		return [
			'id'         => (int) $row['id'],
			'payload'    => is_array( $payload ) ? $payload : [],
			'created_at' => (string) $row['created_at'],
		];
	}

	/**
	 * @param array<string, mixed> $insight Everything needed to persist a cached insight.
	 */
	public function save( array $insight ): int {
		global $wpdb;

		$table = Schema::table( 'insights' );

		$sql = "INSERT INTO {$table}
				(site_id, type, entity_type, entity_hash, entity_label, period_start, period_end,
				 evidence, evidence_hash, ai_provider, ai_model, prompt_version, payload, lang, tokens_used, created_at)
			VALUES (%d, %s, %s, " . ( isset( $insight['entity_hash_hex'] ) ? 'UNHEX(%s)' : '%s' ) . ", %s, %s, %s, %s, UNHEX(%s), %s, %s, %s, %s, %s, %d, %s)
			ON DUPLICATE KEY UPDATE payload = VALUES(payload), ai_provider = VALUES(ai_provider),
				ai_model = VALUES(ai_model), tokens_used = VALUES(tokens_used), created_at = VALUES(created_at)";

		$values = [
			get_current_blog_id(),
			(string) $insight['type'],
			(string) ( $insight['entity_type'] ?? 'site' ),
			(string) ( $insight['entity_hash_hex'] ?? '' ),
			(string) ( $insight['entity_label'] ?? '' ),
			(string) ( $insight['period_start'] ?? gmdate( 'Y-m-d' ) ),
			(string) ( $insight['period_end'] ?? gmdate( 'Y-m-d' ) ),
			(string) wp_json_encode( $insight['evidence'] ?? [] ),
			(string) $insight['evidence_hash_hex'],
			(string) ( $insight['ai_provider'] ?? '' ),
			(string) ( $insight['ai_model'] ?? '' ),
			(string) ( $insight['prompt_version'] ?? '' ),
			(string) wp_json_encode( $insight['payload'] ?? [] ),
			(string) ( $insight['lang'] ?? 'en' ),
			(int) ( $insight['tokens_used'] ?? 0 ),
			current_time( 'mysql', true ),
		];

		$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		return (int) $wpdb->insert_id;
	}

	/**
	 * Most recent insight of a type for the site-level entity.
	 *
	 * @return array<string, mixed>|null
	 */
	public function latest_site( string $type ): ?array {
		global $wpdb;

		$table = Schema::table( 'insights' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT payload, created_at FROM {$table} WHERE site_id = %d AND type = %s AND entity_type = 'site' ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$type
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$payload = json_decode( (string) $row['payload'], true );

		return is_array( $payload ) ? $payload : null;
	}
}
