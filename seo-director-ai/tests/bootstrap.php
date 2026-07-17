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
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) { // phpcs:ignore
		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) { // phpcs:ignore
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { // phpcs:ignore
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $str ) );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}

require SDA_PLUGIN_DIR . 'includes/Core/Autoloader.php';
\SEODirector\Core\Autoloader::register();
