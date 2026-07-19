<?php
/**
 * Single top-level admin page that mounts the React SPA.
 * Also terminates the Google OAuth redirect (?sda_oauth=callback).
 *
 * @package SEODirector
 */

namespace SEODirector\Admin;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Capabilities;
use SEODirector\Integrations\Google\OAuthClient;

final class AdminMenu {

	public const SLUG = 'seo-director-ai';

	public function register_menu(): void {
		add_menu_page(
			__( 'SEO Director AI', 'seo-director-ai' ),
			__( 'SEO Director', 'seo-director-ai' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG,
			array( $this, 'render_app' ),
			'dashicons-chart-area',
			58
		);

		add_action( 'load-toplevel_page_' . self::SLUG, array( $this, 'maybe_handle_oauth_callback' ) );
	}

	public function render_app(): void {
		echo '<div id="sda-app" class="sda-app" dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '">';
		echo '<p class="sda-loading">' . esc_html__( 'Loading SEO Director AI…', 'seo-director-ai' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Google redirects back to this admin page; exchange the code before the page renders.
	 */
	public function maybe_handle_oauth_callback(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth 'state' param is the CSRF token here.
		if ( ! isset( $_GET['sda_oauth'] ) || 'callback' !== $_GET['sda_oauth'] ) {
			return;
		}
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:enable

		$notice = 'connected';
		if ( '' === $code || '' === $state ) {
			$notice = 'oauth_denied';
		} else {
			/** @var OAuthClient $oauth */
			$oauth  = sda()->container()->get( OAuthClient::class );
			$result = $oauth->handle_callback( $code, $state );
			if ( is_wp_error( $result ) ) {
				$notice = 'oauth_failed';
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&sda_notice=' . $notice ) );
		exit;
	}
}
