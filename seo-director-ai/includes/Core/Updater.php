<?php
/**
 * Self-hosted update engine. The plugin is distributed outside wordpress.org,
 * so updates come from the SignTeb update endpoint: a JSON manifest with the
 * latest version, package URL, and changelog. When the manifest advertises a
 * newer version it is injected into the update_plugins transient, which lights
 * up the normal WordPress update UI; the auto_update setting additionally
 * opts the plugin into WordPress background auto-updates.
 *
 * Manifest shape (all string fields):
 * {
 *   "version":      "1.0.0",
 *   "package":      "https://…/seo-director-ai-1.0.0.zip",
 *   "requires":     "6.4",
 *   "requires_php": "8.2",
 *   "tested":       "6.8",
 *   "url":          "https://signteb.com/seo-director-ai",
 *   "sections":     { "changelog": "<h4>1.0.0</h4>…" }
 * }
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class Updater {

	private const MANIFEST_URL = 'https://signteb.com/updates/seo-director-ai.json';
	private const CACHE_KEY    = 'sda_update_manifest';
	private const CACHE_TTL    = 12 * HOUR_IN_SECONDS;

	public function __construct( private Settings $settings ) {}

	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'auto_update_plugin', [ $this, 'maybe_auto_update' ], 10, 2 );
	}

	/**
	 * Add our update to the transient WordPress uses for the Plugins screen
	 * and for background updates.
	 *
	 * @param mixed $transient Value being saved to update_plugins.
	 */
	public function inject_update( mixed $transient ): mixed {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$manifest = $this->manifest();
		$basename = plugin_basename( SDA_PLUGIN_FILE );

		if ( null === $manifest || ! version_compare( $manifest['version'], SDA_VERSION, '>' ) ) {
			// Record "no update" so WP shows the plugin as up to date.
			if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
				$transient->no_update[ $basename ] = $this->as_update_object( $manifest ?? [ 'version' => SDA_VERSION ] );
			}
			return $transient;
		}

		$transient->response[ $basename ] = $this->as_update_object( $manifest );

		return $transient;
	}

	/**
	 * Serve the "View details" popup for our plugin from the manifest.
	 *
	 * @param mixed  $result Default false.
	 * @param string $action plugins_api action.
	 * @param object $args   Request args.
	 */
	public function plugin_info( mixed $result, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action || 'seo-director-ai' !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$manifest = $this->manifest();
		if ( null === $manifest ) {
			return $result;
		}

		return (object) [
			'name'          => 'SEO Director AI',
			'slug'          => 'seo-director-ai',
			'version'       => $manifest['version'],
			'author'        => '<a href="https://signteb.com">Reza Asiabi</a>',
			'homepage'      => $manifest['url'] ?: 'https://signteb.com',
			'requires'      => $manifest['requires'],
			'requires_php'  => $manifest['requires_php'],
			'tested'        => $manifest['tested'],
			'download_link' => $manifest['package'],
			'sections'      => $manifest['sections'],
		];
	}

	/**
	 * Opt into WordPress background auto-updates when the setting is on.
	 *
	 * @param bool|null $update Whether to auto-update.
	 * @param object    $item   Update offer.
	 */
	public function maybe_auto_update( mixed $update, object $item ): mixed {
		if ( plugin_basename( SDA_PLUGIN_FILE ) === ( $item->plugin ?? '' ) ) {
			return (bool) $this->settings->get( 'auto_update', true );
		}

		return $update;
	}

	/**
	 * Fetch + cache the update manifest. Errors cache as a miss for one hour
	 * so a down server never slows wp-admin on every load.
	 *
	 * @return array{version: string, package: string, requires: string, requires_php: string, tested: string, url: string, sections: array<string, string>}|null
	 */
	private function manifest(): ?array {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached['manifest'] ?: null;
		}

		/** Filterable so agencies can point at their own update server. */
		$url      = (string) apply_filters( 'sda_update_manifest_url', self::MANIFEST_URL );
		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );
		$body     = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );

		$manifest = null;
		if ( is_array( $body ) && '' !== (string) ( $body['version'] ?? '' ) && '' !== (string) ( $body['package'] ?? '' ) ) {
			$manifest = [
				'version'      => (string) $body['version'],
				'package'      => esc_url_raw( (string) $body['package'] ),
				'requires'     => (string) ( $body['requires'] ?? '6.4' ),
				'requires_php' => (string) ( $body['requires_php'] ?? '8.2' ),
				'tested'       => (string) ( $body['tested'] ?? '' ),
				'url'          => esc_url_raw( (string) ( $body['url'] ?? '' ) ),
				'sections'     => array_map( 'wp_kses_post', (array) ( $body['sections'] ?? [] ) ),
			];
		}

		set_site_transient(
			self::CACHE_KEY,
			[ 'manifest' => $manifest ],
			null === $manifest ? HOUR_IN_SECONDS : self::CACHE_TTL
		);

		return $manifest;
	}

	/**
	 * @param array<string, mixed> $manifest Manifest (or minimal stub).
	 */
	private function as_update_object( array $manifest ): object {
		return (object) [
			'id'           => 'signteb.com/seo-director-ai',
			'slug'         => 'seo-director-ai',
			'plugin'       => plugin_basename( SDA_PLUGIN_FILE ),
			'new_version'  => (string) ( $manifest['version'] ?? SDA_VERSION ),
			'package'      => (string) ( $manifest['package'] ?? '' ),
			'url'          => (string) ( $manifest['url'] ?? 'https://signteb.com' ),
			'requires'     => (string) ( $manifest['requires'] ?? '6.4' ),
			'requires_php' => (string) ( $manifest['requires_php'] ?? '8.2' ),
			'tested'       => (string) ( $manifest['tested'] ?? '' ),
		];
	}
}
