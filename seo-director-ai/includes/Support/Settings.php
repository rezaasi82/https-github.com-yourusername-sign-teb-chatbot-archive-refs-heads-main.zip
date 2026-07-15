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
			'delete_data_on_uninstall' => static fn( $v ) => (bool) $v,
		];
	}
}
