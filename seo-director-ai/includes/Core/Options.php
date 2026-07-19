<?php
/**
 * Typed, sanitized access to the single sda_settings option blob.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Options {

	private const OPTION = 'sda_settings';

	/**
	 * Per-field sanitizers — the write-side schema. Unknown keys are dropped.
	 *
	 * @var array<string, callable(mixed): mixed>
	 */
	private array $schema;

	public function __construct() {
		$this->schema = array(
			'ai_enabled'          => static fn( $v ) => (bool) $v,
			'ai_provider'         => static fn( $v ) => in_array( $v, array( 'openai', 'claude', 'gemini' ), true ) ? $v : 'claude',
			'ai_model'            => static fn( $v ) => sanitize_text_field( (string) $v ),
			'ai_monthly_budget'   => static fn( $v ) => max( 0, (int) $v ),
			'ai_language'         => static fn( $v ) => sanitize_text_field( (string) $v ),
			'gsc_top_n_queries'   => static fn( $v ) => min( 25000, max( 100, (int) $v ) ),
			'gsc_top_n_pages'     => static fn( $v ) => min( 25000, max( 100, (int) $v ) ),
			'retention_months'    => static fn( $v ) => min( 48, max( 3, (int) $v ) ),
			'delete_on_uninstall' => static fn( $v ) => (bool) $v,
			'google_client_id'    => static fn( $v ) => sanitize_text_field( (string) $v ),
		);
	}

	public function get( string $key, mixed $default = null ): mixed {
		$all = (array) get_option( self::OPTION, array() );
		return $all[ $key ] ?? $default;
	}

	/** @return array<string, mixed> */
	public function all(): array {
		return (array) get_option( self::OPTION, array() );
	}

	/**
	 * Merge-write settings; every field passes its sanitizer, unknown fields ignored.
	 *
	 * @param array<string, mixed> $values Incoming values.
	 * @return array<string, mixed> The persisted settings.
	 */
	public function update( array $values ): array {
		$current = $this->all();
		foreach ( $values as $key => $value ) {
			if ( isset( $this->schema[ $key ] ) ) {
				$current[ $key ] = ( $this->schema[ $key ] )( $value );
			}
		}
		update_option( self::OPTION, $current );
		return $current;
	}
}
