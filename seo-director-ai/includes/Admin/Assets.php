<?php
/**
 * Conditional asset loading: the SPA bundle is enqueued ONLY on the plugin's
 * own admin screen. The plugin never enqueues anything on the public site.
 *
 * @package SEODirector
 */

namespace SEODirector\Admin;

use SEODirector\Agency\WhiteLabel;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public function __construct(
		private Settings $settings,
		private WhiteLabel $white_label,
	) {}

	public function enqueue( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, AdminMenu::SLUG ) ) {
			return;
		}

		$manifest = $this->manifest();

		if ( null === $manifest ) {
			add_action( 'admin_notices', [ $this, 'missing_build_notice' ] );
			return;
		}

		$js  = $manifest['js'] ?? '';
		$css = $manifest['css'] ?? '';

		if ( '' !== $css ) {
			wp_enqueue_style( 'sda-app', SDA_PLUGIN_URL . 'assets/dist/' . $css, [], SDA_VERSION );
		}

		if ( '' !== $js ) {
			wp_enqueue_script( 'sda-app', SDA_PLUGIN_URL . 'assets/dist/' . $js, [], SDA_VERSION, true );
			// Vite emits an ES module bundle.
			add_filter(
				'script_loader_tag',
				static function ( string $tag, string $handle ) {
					if ( 'sda-app' === $handle ) {
						$tag = str_replace( '<script ', '<script type="module" ', $tag );
					}
					return $tag;
				},
				10,
				2
			);

			wp_localize_script(
				'sda-app',
				'sdaBoot',
				[
					'restUrl'   => esc_url_raw( rest_url( 'sda/v1' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'locale'    => get_user_locale(),
					'isRtl'     => is_rtl(),
					'canManage' => current_user_can( \SEODirector\Core\Capabilities::MANAGE ),
					'canManageClients' => current_user_can( \SEODirector\Core\Capabilities::MANAGE_CLIENTS ),
					'version'   => SDA_VERSION,
					'siteName'  => get_bloginfo( 'name' ),
					'branding'  => $this->white_label->boot_payload(),
				]
			);
		}
	}

	public function missing_build_notice(): void {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'SEO Director AI: dashboard assets are missing. Run "npm install && npm run build" inside the plugin directory (development install), or reinstall the release package.', 'seo-director-ai' )
		);
	}

	/**
	 * Resolve built entry files from Vite's manifest.
	 *
	 * @return array{js: string, css: string}|null Null when no build exists.
	 */
	private function manifest(): ?array {
		$path = SDA_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$manifest = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $manifest ) ) {
			return null;
		}

		foreach ( $manifest as $entry ) {
			if ( ! empty( $entry['isEntry'] ) ) {
				return [
					'js'  => (string) ( $entry['file'] ?? '' ),
					'css' => (string) ( $entry['css'][0] ?? '' ),
				];
			}
		}

		return null;
	}
}
