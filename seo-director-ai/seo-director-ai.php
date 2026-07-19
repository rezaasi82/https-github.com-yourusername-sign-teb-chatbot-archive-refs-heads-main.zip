<?php
/**
 * Plugin Name:       SEO Director AI
 * Plugin URI:        https://seodirector.app
 * Description:       AI-powered SEO management platform — monitors, analyzes, prioritizes, explains, and recommends actions. Your virtual SEO Director inside WordPress.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            SEO Director
 * Author URI:        https://seodirector.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-director-ai
 * Domain Path:       /languages
 *
 * @package SEODirector
 */

defined( 'ABSPATH' ) || exit;

define( 'SDA_VERSION', '1.0.0' );
define( 'SDA_DB_VERSION', '1.0.0' );
define( 'SDA_PLUGIN_FILE', __FILE__ );
define( 'SDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Requirements gate — never fatal on unsupported environments.
 */
function sda_requirements_met(): bool {
	global $wp_version;
	return version_compare( PHP_VERSION, '8.2', '>=' )
		&& version_compare( $wp_version, '6.4', '>=' );
}

function sda_requirements_notice(): void {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'SEO Director AI requires PHP 8.2+ and WordPress 6.4+. The plugin is inactive until requirements are met.', 'seo-director-ai' );
	echo '</p></div>';
}

if ( ! sda_requirements_met() ) {
	add_action( 'admin_notices', 'sda_requirements_notice' );
	return;
}

// Composer autoloader when vendored; SPL fallback so the plugin runs from a plain checkout.
if ( file_exists( SDA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SDA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			if ( ! str_starts_with( $class, 'SEODirector\\' ) ) {
				return;
			}
			$relative = substr( $class, strlen( 'SEODirector\\' ) );
			$path     = SDA_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \SEODirector\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \SEODirector\Core\Deactivator::class, 'deactivate' ) );

/**
 * Bootstrap accessor — the single allowed "static" entry point.
 */
function sda(): \SEODirector\Core\Plugin {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new \SEODirector\Core\Plugin( new \SEODirector\Core\Container() );
	}
	return $plugin;
}

add_action( 'plugins_loaded', static fn() => sda()->boot(), 5 );
