<?php
/**
 * Conditional asset loading — scripts/styles enqueue ONLY on our admin screen.
 * The public site gets zero bytes from this plugin.
 *
 * @package SEODirector
 */

namespace SEODirector\Admin;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public function maybe_enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_' . AdminMenu::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'sda-admin',
			SDA_PLUGIN_URL . 'assets/dist/admin.css',
			array(),
			SDA_VERSION
		);

		wp_enqueue_script(
			'sda-admin',
			SDA_PLUGIN_URL . 'assets/dist/admin.js',
			array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ),
			SDA_VERSION,
			true
		);

		wp_set_script_translations( 'sda-admin', 'seo-director-ai', SDA_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'sda-admin',
			'sdaConfig',
			array(
				'restBase' => esc_url_raw( rest_url( 'sda/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'isRtl'    => is_rtl(),
				'canManage' => current_user_can( \SEODirector\Core\Capabilities::MANAGE ),
				'version'  => SDA_VERSION,
			)
		);
	}
}
