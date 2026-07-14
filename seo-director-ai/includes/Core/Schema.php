<?php
/**
 * Canonical database schema, consumed by Activator (fresh install) and
 * Upgrader (migrations). Single source of truth — matches docs/03-database-schema.md.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/**
	 * Full table name with WordPress prefix.
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'sda_' . $name;
	}

	/**
	 * All CREATE TABLE statements in dbDelta-compatible form.
	 *
	 * @return string[] Keyed by short table name.
	 */
	public static function tables(): array {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'sda_';

		$tables = [];

		$tables['connections'] = "CREATE TABLE {$p}connections (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			service VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'connected',
			account_label VARCHAR(190) NOT NULL DEFAULT '',
			credentials LONGTEXT NOT NULL,
			scopes TEXT NULL,
			last_used_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_site_service (site_id, service)
		) $charset";

		$tables['properties'] = "CREATE TABLE {$p}properties (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			connection_id BIGINT UNSIGNED NOT NULL,
			service VARCHAR(10) NOT NULL,
			external_id VARCHAR(190) NOT NULL,
			display_name VARCHAR(190) NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			meta LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_prop (site_id, service, external_id),
			KEY idx_connection (connection_id)
		) $charset";

		$tables['gsc_daily_totals'] = "CREATE TABLE {$p}gsc_daily_totals (
			site_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ctr DECIMAL(6,4) NOT NULL DEFAULT 0,
			position DECIMAL(6,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (property_id, date),
			KEY idx_site_date (site_id, date)
		) $charset";

		$tables['gsc_query_daily'] = "CREATE TABLE {$p}gsc_query_daily (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			query_hash BINARY(16) NOT NULL,
			query VARCHAR(750) NOT NULL,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			impressions INT UNSIGNED NOT NULL DEFAULT 0,
			ctr DECIMAL(6,4) NOT NULL DEFAULT 0,
			position DECIMAL(6,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_row (property_id, date, query_hash),
			KEY idx_query_time (property_id, query_hash, date),
			KEY idx_date_clicks (property_id, date, clicks)
		) $charset";

		$tables['gsc_page_daily'] = "CREATE TABLE {$p}gsc_page_daily (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			page_hash BINARY(16) NOT NULL,
			page_path VARCHAR(750) NOT NULL,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			impressions INT UNSIGNED NOT NULL DEFAULT 0,
			ctr DECIMAL(6,4) NOT NULL DEFAULT 0,
			position DECIMAL(6,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_row (property_id, date, page_hash),
			KEY idx_page_time (property_id, page_hash, date),
			KEY idx_date_clicks (property_id, date, clicks)
		) $charset";

		$tables['gsc_page_query_weekly'] = "CREATE TABLE {$p}gsc_page_query_weekly (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			week_start DATE NOT NULL,
			page_hash BINARY(16) NOT NULL,
			query_hash BINARY(16) NOT NULL,
			page_path VARCHAR(750) NOT NULL,
			query VARCHAR(750) NOT NULL,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			impressions INT UNSIGNED NOT NULL DEFAULT 0,
			position DECIMAL(6,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_row (property_id, week_start, page_hash, query_hash),
			KEY idx_query (property_id, query_hash, week_start)
		) $charset";

		$tables['gsc_dimension_daily'] = "CREATE TABLE {$p}gsc_dimension_daily (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			dim_type VARCHAR(20) NOT NULL,
			dim_value VARCHAR(64) NOT NULL,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ctr DECIMAL(6,4) NOT NULL DEFAULT 0,
			position DECIMAL(6,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_row (property_id, date, dim_type, dim_value)
		) $charset";

		foreach ( [ 'weekly' => 'week_start', 'monthly' => 'month_start' ] as $grain => $date_col ) {
			foreach ( [ 'query' => 'query_hash', 'page' => 'page_hash' ] as $entity => $hash_col ) {
				$label_col                      = 'query' === $entity ? 'query' : 'page_path';
				$tables[ "gsc_{$entity}_{$grain}" ] = "CREATE TABLE {$p}gsc_{$entity}_{$grain} (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					site_id BIGINT UNSIGNED NOT NULL,
					property_id BIGINT UNSIGNED NOT NULL,
					$date_col DATE NOT NULL,
					$hash_col BINARY(16) NOT NULL,
					$label_col VARCHAR(750) NOT NULL,
					clicks INT UNSIGNED NOT NULL DEFAULT 0,
					impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
					ctr DECIMAL(6,4) NOT NULL DEFAULT 0,
					position DECIMAL(6,2) NOT NULL DEFAULT 0,
					best_position DECIMAL(6,2) NOT NULL DEFAULT 0,
					PRIMARY KEY  (id),
					UNIQUE KEY uq_row (property_id, $date_col, $hash_col),
					KEY idx_entity_time (property_id, $hash_col, $date_col),
					KEY idx_date_clicks (property_id, $date_col, clicks)
				) $charset";
			}
		}

		$tables['ga4_daily'] = "CREATE TABLE {$p}ga4_daily (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			channel VARCHAR(64) NOT NULL,
			landing_hash BINARY(16) NOT NULL,
			landing_path VARCHAR(750) NOT NULL,
			sessions INT UNSIGNED NOT NULL DEFAULT 0,
			total_users INT UNSIGNED NOT NULL DEFAULT 0,
			engaged_sessions INT UNSIGNED NOT NULL DEFAULT 0,
			engagement_rate DECIMAL(6,4) NOT NULL DEFAULT 0,
			conversions DECIMAL(12,2) NOT NULL DEFAULT 0,
			event_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_row (property_id, date, channel, landing_hash),
			KEY idx_landing (property_id, landing_hash, date),
			KEY idx_channel_date (property_id, channel, date)
		) $charset";

		$tables['ga4_daily_totals'] = "CREATE TABLE {$p}ga4_daily_totals (
			site_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			channel VARCHAR(64) NOT NULL,
			sessions INT UNSIGNED NOT NULL DEFAULT 0,
			total_users INT UNSIGNED NOT NULL DEFAULT 0,
			engaged_sessions INT UNSIGNED NOT NULL DEFAULT 0,
			engagement_rate DECIMAL(6,4) NOT NULL DEFAULT 0,
			conversions DECIMAL(12,2) NOT NULL DEFAULT 0,
			event_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (property_id, date, channel),
			KEY idx_site_date (site_id, date)
		) $charset";

		$tables['psi_audits'] = "CREATE TABLE {$p}psi_audits (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			page_hash BINARY(16) NOT NULL,
			page_path VARCHAR(750) NOT NULL,
			strategy VARCHAR(10) NOT NULL,
			audited_at DATETIME NOT NULL,
			perf_score TINYINT UNSIGNED NULL,
			lcp_ms INT UNSIGNED NULL,
			cls DECIMAL(6,3) NULL,
			inp_ms INT UNSIGNED NULL,
			ttfb_ms INT UNSIGNED NULL,
			field_lcp_ms INT UNSIGNED NULL,
			field_cls DECIMAL(6,3) NULL,
			field_inp_ms INT UNSIGNED NULL,
			cwv_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
			opportunities LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY idx_page_time (site_id, page_hash, strategy, audited_at)
		) $charset";

		$tables['insights'] = "CREATE TABLE {$p}insights (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL,
			entity_type VARCHAR(20) NOT NULL,
			entity_hash BINARY(16) NULL,
			entity_label VARCHAR(750) NULL,
			period_start DATE NOT NULL,
			period_end DATE NOT NULL,
			evidence LONGTEXT NOT NULL,
			evidence_hash BINARY(16) NOT NULL,
			ai_provider VARCHAR(32) NULL,
			ai_model VARCHAR(64) NULL,
			prompt_version VARCHAR(20) NULL,
			payload LONGTEXT NOT NULL,
			lang VARCHAR(10) NOT NULL DEFAULT 'en',
			tokens_used INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_cache (site_id, type, evidence_hash, lang),
			KEY idx_entity (site_id, entity_type, entity_hash, created_at)
		) $charset";

		$tables['opportunities'] = "CREATE TABLE {$p}opportunities (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			detector VARCHAR(48) NOT NULL,
			entity_type VARCHAR(10) NOT NULL,
			entity_hash BINARY(16) NOT NULL,
			entity_label VARCHAR(750) NOT NULL,
			secondary_label VARCHAR(750) NULL,
			score DECIMAL(8,2) NOT NULL,
			est_traffic_gain INT UNSIGNED NULL,
			difficulty TINYINT UNSIGNED NOT NULL DEFAULT 5,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			data LONGTEXT NULL,
			detected_at DATETIME NOT NULL,
			refreshed_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_opp (site_id, detector, entity_hash),
			KEY idx_status_score (site_id, status, score)
		) $charset";

		$tables['roadmap_tasks'] = "CREATE TABLE {$p}roadmap_tasks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			roadmap_scope VARCHAR(10) NOT NULL,
			period_start DATE NOT NULL,
			title VARCHAR(300) NOT NULL,
			description LONGTEXT NULL,
			category VARCHAR(48) NOT NULL,
			impact TINYINT UNSIGNED NOT NULL,
			difficulty TINYINT UNSIGNED NOT NULL,
			est_hours DECIMAL(5,1) NULL,
			priority SMALLINT UNSIGNED NOT NULL,
			owner_user_id BIGINT UNSIGNED NULL,
			expected_result VARCHAR(500) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'todo',
			opportunity_id BIGINT UNSIGNED NULL,
			insight_id BIGINT UNSIGNED NULL,
			completed_at DATETIME NULL,
			measured_result LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_scope (site_id, roadmap_scope, period_start, status),
			KEY idx_owner (site_id, owner_user_id, status)
		) $charset";

		$tables['alerts'] = "CREATE TABLE {$p}alerts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			rule VARCHAR(48) NOT NULL,
			severity VARCHAR(10) NOT NULL,
			entity_label VARCHAR(750) NULL,
			message TEXT NOT NULL,
			fingerprint BINARY(16) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			snoozed_until DATETIME NULL,
			data LONGTEXT NULL,
			raised_at DATETIME NOT NULL,
			resolved_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_active (site_id, fingerprint, status),
			KEY idx_status (site_id, status, severity, raised_at)
		) $charset";

		$tables['health_scores'] = "CREATE TABLE {$p}health_scores (
			site_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			score TINYINT UNSIGNED NOT NULL,
			components LONGTEXT NOT NULL,
			PRIMARY KEY  (site_id, date)
		) $charset";

		$tables['reports'] = "CREATE TABLE {$p}reports (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(10) NOT NULL,
			period_start DATE NOT NULL,
			period_end DATE NOT NULL,
			formats VARCHAR(100) NOT NULL,
			storage LONGTEXT NOT NULL,
			recipients TEXT NULL,
			status VARCHAR(20) NOT NULL,
			created_at DATETIME NOT NULL,
			sent_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY idx_period (site_id, type, period_start)
		) $charset";

		$tables['job_state'] = "CREATE TABLE {$p}job_state (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			job VARCHAR(64) NOT NULL,
			job_cursor LONGTEXT NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'idle',
			last_run_at DATETIME NULL,
			last_error TEXT NULL,
			fail_count SMALLINT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_job (site_id, job)
		) $charset";

		$tables['agency_sites'] = "CREATE TABLE {$p}agency_sites (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL,
			client_name VARCHAR(190) NOT NULL,
			site_url VARCHAR(300) NOT NULL,
			pair_key_hash CHAR(64) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			last_seen_at DATETIME NULL,
			snapshot LONGTEXT NULL,
			branding LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_url (site_id, site_url)
		) $charset";

		$tables['license'] = "CREATE TABLE {$p}license (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			license_key VARCHAR(190) NOT NULL,
			edition VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL,
			domain_hash CHAR(64) NOT NULL,
			expires_at DATETIME NULL,
			last_check_at DATETIME NULL,
			server_payload LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_site (site_id)
		) $charset";

		return $tables;
	}
}
