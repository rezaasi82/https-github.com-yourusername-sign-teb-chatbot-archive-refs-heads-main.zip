<?php
/**
 * Activation routine: create tables, capabilities, defaults, schedules.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			$site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single_site();
				restore_current_blog();
			}
			return;
		}

		self::activate_single_site();
	}

	private static function activate_single_site(): void {
		self::create_tables();
		Capabilities::add();

		add_option( 'sda_db_version', SDA_DB_VERSION );
		add_option( 'sda_installed_at', time() );
		add_option( 'sda_settings', self::default_settings() );

		// Flag consumed on the next admin page load to redirect into the setup wizard.
		add_option( 'sda_activation_redirect', 1 );
	}

	public static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::tables() as $ddl ) {
			dbDelta( $ddl );
		}

		update_option( 'sda_db_version', SDA_DB_VERSION );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return [
			'ai_provider'          => '',           // '' | openai | claude | gemini
			'ai_auto_explain'      => true,
			'ai_monthly_token_cap' => 500000,
			'ai_model_claude'      => 'claude-sonnet-5',
			'ai_model_openai'      => 'gpt-4.1-mini',
			'ai_model_gemini'      => 'gemini-2.0-flash',
			'site_niche'           => '',
			'site_goals'           => '',
			'gsc_query_rows_cap'   => 5000,
			'gsc_page_rows_cap'    => 2000,
			'report_day'           => 'saturday',
			'report_time'          => '07:00',
			'alert_email'          => get_option( 'admin_email' ),
			'alert_webhook_url'    => '',
			'alert_slack_url'      => '',
			'alert_telegram_token' => '',
			'alert_telegram_chat'  => '',
			'license_shared_secret' => '',
			// Agency: client-mode push target + branding (white-label).
			'agency_hub_url'        => '',
			'agency_pair_key'       => '',
			'brand_name'            => '',
			'brand_logo_url'        => '',
			'brand_primary_color'   => '',
			'brand_hide_powered_by' => false,
			'brand_string_overrides' => '',
			// Enterprise: brand-voice profile.
			'brand_voice_tone'      => '',
			'brand_voice_audience'  => '',
			'brand_voice_notes'     => '',
			'brand_voice_avoid'     => '',
			// Enterprise: SLA escalation (hours a critical alert may stay open).
			'sla_escalation_hours'  => 24,
			// Enterprise: task sync (Jira / Trello).
			'task_sync_provider'    => '',
			'jira_base_url'         => '',
			'jira_email'            => '',
			'jira_token'            => '',
			'jira_project_key'      => '',
			'trello_key'            => '',
			'trello_token'          => '',
			'trello_list_id'        => '',
			// Enterprise: SERP enrichment for root cause.
			'serp_provider'         => '',
			'serp_api_key'          => '',
			'delete_data_on_uninstall' => false,
		];
	}
}
