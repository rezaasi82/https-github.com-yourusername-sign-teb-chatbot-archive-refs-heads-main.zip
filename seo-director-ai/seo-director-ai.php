<?php
/**
 * Plugin Name:       SEO Director AI
 * Plugin URI:        https://signteb.com
 * Description:       AI-powered SEO management platform that monitors, analyzes, prioritizes, explains, and recommends actions — a virtual SEO Director inside WordPress.
 * Version:           0.14.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Reza Asiabi (رضا آسیابی)
 * Author URI:        https://signteb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-director-ai
 * Domain Path:       /languages
 *
 * @package SEODirector
 */

defined( 'ABSPATH' ) || exit;

define( 'SDA_VERSION', '0.14.0' );
define( 'SDA_DB_VERSION', '3' );
define( 'SDA_PLUGIN_FILE', __FILE__ );
define( 'SDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ============================================================================
 * TESTING / QA — LICENSE BYPASS
 * All Pro/Agency/Enterprise features are UNLOCKED while this constant is true,
 * so every screen can be tested without a license. To re-enable licensing,
 * delete (or set to false) the line below and ship a new build.
 * ========================================================================== */
if ( ! defined( 'SDA_UNLOCK_ALL' ) ) {
	define( 'SDA_UNLOCK_ALL', true );
}

/**
 * Requirements gate: never fatal on unsupported environments, degrade to an admin notice.
 */
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: current PHP version. */
						__( 'SEO Director AI requires PHP 8.2 or newer. Your server runs PHP %s. The plugin is inactive until PHP is upgraded.', 'seo-director-ai' ),
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

if ( file_exists( SDA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require SDA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	require SDA_PLUGIN_DIR . 'includes/Core/Autoloader.php';
	\SEODirector\Core\Autoloader::register();
}

register_activation_hook( __FILE__, [ \SEODirector\Core\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \SEODirector\Core\Deactivator::class, 'deactivate' ] );

/**
 * Global accessor for the plugin container. The only static entry point in the codebase.
 *
 * @return \SEODirector\Core\Plugin
 */
function sda(): \SEODirector\Core\Plugin {
	return \SEODirector\Core\Plugin::instance();
}

add_action( 'plugins_loaded', static function () {
	sda()->boot();
} );
