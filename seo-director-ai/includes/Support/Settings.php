<?php
/**
 * Typed access to the sda_settings option with per-field sanitization.
 * The REST settings controller and admin code always go through this class,
 * so unknown keys and malformed values can never reach the database.
 *
 * @package SEODirector
 */

namespace SEODirector\Support;

use SEODirector\Core\Activator;

defined( 'ABSPATH' ) || exit;

final class Settings {

	private const OPTION = 'sda_settings';

	/** @var array<string, mixed>|null */
	private ?array $cache = null;

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, [] );
			$this->cache = array_merge( Activator::default_settings(), is_array( $stored ) ? $stored : [] );
		}

		return $this->cache;
	}

	public function get( string $key, mixed $default = null ): mixed {
		return $this->all()[ $key ] ?? $default;
	}

	/**
	 * Sanitize and persist a partial update. Unknown keys are dropped.
	 *
	 * @param array<string, mixed> $values Incoming key/value pairs.
	 * @return array<string, mixed> The full, sanitized settings after the update.
	 */
	public function update( array $values ): array {
		$clean = [];

		foreach ( $values as $key => $value ) {
			$sanitizer = $this->sanitizers()[ $key ] ?? null;
			if ( null === $sanitizer ) {
				continue;
			}
			$clean[ $key ] = $sanitizer( $value );
		}

		$merged      = array_merge( $this->all(), $clean );
		$this->cache = $merged;
		update_option( self::OPTION, $merged );

		return $merged;
	}

	/**
	 * @return array<string, callable(mixed): mixed>
	 */
	private function sanitizers(): array {
		return [
			'ai_provider'          => static fn( $v ) => in_array( $v, [ '', 'openai', 'claude', 'gemini' ], true ) ? $v : '',
			'ai_auto_explain'      => static fn( $v ) => (bool) $v,
			'ai_monthly_token_cap' => static fn( $v ) => max( 0, (int) $v ),
			'ai_model_claude'      => static fn( $v ) => sanitize_text_field( (string) $v ),
			'ai_model_openai'      => static fn( $v ) => sanitize_text_field( (string) $v ),
			'ai_model_gemini'      => static fn( $v ) => sanitize_text_field( (string) $v ),
			'site_niche'           => static fn( $v ) => sanitize_text_field( (string) $v ),
			'site_goals'           => static fn( $v ) => sanitize_text_field( (string) $v ),
			'gsc_query_rows_cap'   => static fn( $v ) => min( 25000, max( 100, (int) $v ) ),
			'gsc_page_rows_cap'    => static fn( $v ) => min( 25000, max( 100, (int) $v ) ),
			'report_day'           => static fn( $v ) => in_array( $v, [ 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ], true ) ? $v : 'saturday',
			'report_time'          => static fn( $v ) => preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $v ) ? (string) $v : '07:00',
			'alert_email'          => static fn( $v ) => sanitize_email( (string) $v ),
			'alert_webhook_url'    => static fn( $v ) => esc_url_raw( (string) $v ),
			'alert_slack_url'      => static fn( $v ) => esc_url_raw( (string) $v ),
			'alert_telegram_token' => static fn( $v ) => sanitize_text_field( (string) $v ),
			'alert_telegram_chat'  => static fn( $v ) => sanitize_text_field( (string) $v ),
			'license_shared_secret' => static fn( $v ) => sanitize_text_field( (string) $v ),
			'agency_hub_url'        => static fn( $v ) => esc_url_raw( (string) $v ),
			'agency_pair_key'       => static fn( $v ) => preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $v ) ),
			'brand_name'            => static fn( $v ) => sanitize_text_field( (string) $v ),
			'brand_logo_url'        => static fn( $v ) => esc_url_raw( (string) $v ),
			'brand_primary_color'   => static fn( $v ) => preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $v ) ? (string) $v : '',
			'brand_hide_powered_by' => static fn( $v ) => (bool) $v,
			'brand_string_overrides' => static fn( $v ) => sanitize_textarea_field( (string) $v ),
			'brand_voice_tone'      => static fn( $v ) => in_array( $v, \SEODirector\Ai\BrandVoice::tones(), true ) ? $v : '',
			'brand_voice_audience'  => static fn( $v ) => sanitize_text_field( (string) $v ),
			'brand_voice_notes'     => static fn( $v ) => sanitize_textarea_field( (string) $v ),
			'brand_voice_avoid'     => static fn( $v ) => sanitize_text_field( (string) $v ),
			'sla_escalation_hours'  => static fn( $v ) => min( 168, max( 1, (int) $v ) ),
			'task_sync_provider'    => static fn( $v ) => in_array( $v, [ '', 'jira', 'trello' ], true ) ? $v : '',
			'jira_base_url'         => static fn( $v ) => esc_url_raw( (string) $v ),
			'jira_email'            => static fn( $v ) => sanitize_email( (string) $v ),
			'jira_token'            => static fn( $v ) => sanitize_text_field( (string) $v ),
			'jira_project_key'      => static fn( $v ) => sanitize_text_field( (string) $v ),
			'trello_key'            => static fn( $v ) => sanitize_text_field( (string) $v ),
			'trello_token'          => static fn( $v ) => sanitize_text_field( (string) $v ),
			'trello_list_id'        => static fn( $v ) => sanitize_text_field( (string) $v ),
			'serp_provider'         => static fn( $v ) => in_array( $v, [ '', 'serpapi' ], true ) ? $v : '',
			'serp_api_key'          => static fn( $v ) => sanitize_text_field( (string) $v ),
			'demo_mode'             => static fn( $v ) => in_array( $v, [ 'auto', 'off' ], true ) ? $v : 'auto',
			'delete_data_on_uninstall' => static fn( $v ) => (bool) $v,
		];
	}
}
