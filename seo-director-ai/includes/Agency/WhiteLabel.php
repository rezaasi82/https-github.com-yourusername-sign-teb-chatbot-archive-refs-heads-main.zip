<?php
/**
 * Rebrands the plugin for agencies that resell it. When the white_label
 * feature is unlocked and a brand name is set, the admin menu label, the SPA
 * brand string, and the "powered by" footer are overridden, and terminology
 * labels can be swapped via the existing sda_terminology_label filter.
 *
 * Everything degrades to the stock branding when the feature is locked, so a
 * downgrade never leaves the UI half-branded.
 *
 * @package SEODirector
 */

namespace SEODirector\Agency;

use SEODirector\License\FeatureGate;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class WhiteLabel {

	public function __construct(
		private Settings $settings,
		private FeatureGate $gate,
	) {}

	public function is_active(): bool {
		return $this->gate->allows( 'white_label' ) && '' !== $this->brand_name();
	}

	public function brand_name(): string {
		return (string) $this->settings->get( 'brand_name', '' );
	}

	/**
	 * Register branding overrides on boot. Cheap when inactive (single guard).
	 */
	public function register(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'sda_brand_name', fn() => $this->brand_name() );
		add_filter( 'sda_terminology_label', [ $this, 'apply_string_overrides' ], 20, 2 );
	}

	/**
	 * Branding payload surfaced to the SPA (safe when inactive → stock brand).
	 *
	 * @return array<string, mixed>
	 */
	public function boot_payload(): array {
		if ( ! $this->is_active() ) {
			return [
				'active'         => false,
				'name'           => 'SEO Director AI',
				'logo_url'       => '',
				'primary_color'  => '',
				'hide_powered_by' => false,
			];
		}

		return [
			'active'          => true,
			'name'            => $this->brand_name(),
			'logo_url'        => (string) $this->settings->get( 'brand_logo_url', '' ),
			'primary_color'   => (string) $this->settings->get( 'brand_primary_color', '' ),
			'hide_powered_by' => (bool) $this->settings->get( 'brand_hide_powered_by', false ),
		];
	}

	/**
	 * Apply admin-defined string overrides to terminology labels.
	 * Overrides are stored as "key=value" lines in brand_string_overrides.
	 *
	 * @param string $label Default label.
	 * @param string $key   Terminology key being resolved.
	 */
	public function apply_string_overrides( string $label, string $key ): string {
		$overrides = $this->parse_overrides( (string) $this->settings->get( 'brand_string_overrides', '' ) );

		return $overrides[ $key ] ?? $label;
	}

	/**
	 * @return array<string, string>
	 */
	private function parse_overrides( string $raw ): array {
		$map = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) ?: [] as $line ) {
			$line = trim( $line );
			if ( '' === $line || ! str_contains( $line, '=' ) ) {
				continue;
			}
			[ $key, $value ] = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( '' !== $key ) {
				$map[ $key ] = $value;
			}
		}

		return $map;
	}
}
