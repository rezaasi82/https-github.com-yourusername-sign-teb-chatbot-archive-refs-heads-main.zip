<?php
/**
 * Evidence-hash keyed cache over {p}sda_insights — identical evidence never re-spends.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class InsightCache {

	/**
	 * @return array<string, mixed>|null Cached payload for this evidence, if any.
	 */
	public function get( string $type, string $evidence_hash, string $lang ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'sda_insights';
		$row   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT payload FROM {$table} WHERE site_id = %d AND type = %s AND evidence_hash = %s AND lang = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$type,
				$evidence_hash,
				$lang
			)
		);
		if ( null === $row ) {
			return null;
		}
		$decoded = json_decode( (string) $row, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Persist an insight (doubles as the cache row via the uq_cache unique key).
	 *
	 * @param array<string, mixed> $meta Keys: entity_type, entity_label, period_start, period_end,
	 *                                   evidence, ai_provider, ai_model, prompt_version, tokens_used.
	 * @param array<string, mixed> $payload Validated AI payload.
	 */
	public function put( string $type, string $evidence_hash, string $lang, array $payload, array $meta ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'sda_insights';
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
					(site_id, type, entity_type, entity_label, period_start, period_end, evidence,
					 evidence_hash, ai_provider, ai_model, prompt_version, payload, lang, tokens_used, created_at)
				 VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s)
				 ON DUPLICATE KEY UPDATE payload = VALUES(payload), tokens_used = VALUES(tokens_used)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				$type,
				(string) ( $meta['entity_type'] ?? 'site' ),
				(string) ( $meta['entity_label'] ?? '' ),
				(string) ( $meta['period_start'] ?? gmdate( 'Y-m-d' ) ),
				(string) ( $meta['period_end'] ?? gmdate( 'Y-m-d' ) ),
				(string) wp_json_encode( $meta['evidence'] ?? array() ),
				$evidence_hash,
				(string) ( $meta['ai_provider'] ?? '' ),
				(string) ( $meta['ai_model'] ?? '' ),
				(string) ( $meta['prompt_version'] ?? '' ),
				(string) wp_json_encode( $payload ),
				$lang,
				(int) ( $meta['tokens_used'] ?? 0 ),
				current_time( 'mysql', true )
			)
		);
	}
}
