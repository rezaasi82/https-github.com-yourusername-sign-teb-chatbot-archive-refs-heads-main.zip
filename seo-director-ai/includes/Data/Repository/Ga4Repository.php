<?php
/**
 * Writes GA4 fact tables (idempotent batched upserts).
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class Ga4Repository {

	private const BATCH = 500;

	/**
	 * @param array<int, array{date: string, channel: string, landing_hash_hex: string, landing_path: string, sessions: int, total_users: int, engaged_sessions: int, engagement_rate: float, conversions: float, event_count: int}> $rows
	 */
	public function upsert_daily( int $property_id, array $rows ): void {
		global $wpdb;

		$table   = Schema::table( 'ga4_daily' );
		$site_id = get_current_blog_id();

		foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
			$values       = [];
			$placeholders = [];
			foreach ( $chunk as $row ) {
				$placeholders[] = '(%d, %d, %s, %s, UNHEX(%s), %s, %d, %d, %d, %f, %f, %d)';
				array_push(
					$values,
					$site_id,
					$property_id,
					$row['date'],
					$row['channel'],
					$row['landing_hash_hex'],
					$row['landing_path'],
					$row['sessions'],
					$row['total_users'],
					$row['engaged_sessions'],
					$row['engagement_rate'],
					$row['conversions'],
					$row['event_count']
				);
			}

			$sql = "INSERT INTO {$table} (site_id, property_id, date, channel, landing_hash, landing_path, sessions, total_users, engaged_sessions, engagement_rate, conversions, event_count) VALUES "
				. implode( ', ', $placeholders )
				. ' ON DUPLICATE KEY UPDATE sessions = VALUES(sessions), total_users = VALUES(total_users), engaged_sessions = VALUES(engaged_sessions), engagement_rate = VALUES(engagement_rate), conversions = VALUES(conversions), event_count = VALUES(event_count)';

			$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Rebuild channel-level daily totals from the landing-page grain for a date range.
	 */
	public function rebuild_daily_totals( int $property_id, string $from, string $to ): void {
		global $wpdb;

		$daily  = Schema::table( 'ga4_daily' );
		$totals = Schema::table( 'ga4_daily_totals' );

		$sql = "INSERT INTO {$totals} (site_id, property_id, date, channel, sessions, total_users, engaged_sessions, engagement_rate, conversions, event_count)
			SELECT site_id, property_id, date, channel,
				SUM(sessions), SUM(total_users), SUM(engaged_sessions),
				COALESCE(SUM(engaged_sessions) / NULLIF(SUM(sessions), 0), 0),
				SUM(conversions), SUM(event_count)
			FROM {$daily}
			WHERE property_id = %d AND date BETWEEN %s AND %s
			GROUP BY site_id, property_id, date, channel
			ON DUPLICATE KEY UPDATE
				sessions = VALUES(sessions), total_users = VALUES(total_users),
				engaged_sessions = VALUES(engaged_sessions), engagement_rate = VALUES(engagement_rate),
				conversions = VALUES(conversions), event_count = VALUES(event_count)";

		$wpdb->query( $wpdb->prepare( $sql, $property_id, $from, $to ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Most recent synced date, or null.
	 */
	public function latest_date( int $property_id ): ?string {
		global $wpdb;

		$table = Schema::table( 'ga4_daily' );
		$date  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT MAX(date) FROM {$table} WHERE property_id = %d", $property_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $date ?: null;
	}
}
