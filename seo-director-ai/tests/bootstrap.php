<?php
/**
 * PHPUnit bootstrap. The Analysis layer is pure PHP with no WordPress
 * dependency, so tests load it directly through the fallback autoloader.
 * A couple of WP i18n/hook shims keep classes that call apply_filters()
 * loadable in isolation.
 *
 * @package SEODirector
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SDA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) { // phpcs:ignore
		return $value;
	}
}

require SDA_PLUGIN_DIR . 'includes/Core/Autoloader.php';
\SEODirector\Core\Autoloader::register();
