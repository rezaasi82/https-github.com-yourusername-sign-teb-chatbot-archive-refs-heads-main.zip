<?php
/**
 * Single top-level admin page that mounts the React SPA.
 *
 * @package SEODirector
 */

namespace SEODirector\Admin;

use SEODirector\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

	public const SLUG = 'seo-director-ai';

	private string $hook_suffix = '';

	public function register(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'SEO Director AI', 'seo-director-ai' ),
			__( 'SEO Director', 'seo-director-ai' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG,
			[ $this, 'render' ],
			'dashicons-chart-line',
			58
		);
	}

	public function hook_suffix(): string {
		return $this->hook_suffix;
	}

	/**
	 * The SPA mount point. Everything visible is rendered by React;
	 * the noscript fallback keeps the page honest without JS.
	 */
	public function render(): void {
		?>
		<div id="sda-root" class="sda-root" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
			<div class="sda-boot-splash">
				<span class="spinner is-active" style="float:none"></span>
			</div>
		</div>
		<noscript>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'SEO Director AI requires JavaScript. Please enable it to use the dashboard.', 'seo-director-ai' ); ?></p>
			</div>
		</noscript>
		<?php
	}
}
