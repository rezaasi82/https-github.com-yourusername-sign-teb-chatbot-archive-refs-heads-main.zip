<?php
/**
 * Unit test bootstrap — stubs the few WP functions the pure Analysis/Ai classes touch,
 * so the deterministic layer runs without a WordPress install.
 *
 * @package SEODirector
 */

define( 'ABSPATH', __DIR__ . '/' );

// Core time constants (wp-includes/default-constants.php).
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

spl_autoload_register(
	static function ( string $class ): void {
		if ( ! str_starts_with( $class, 'SEODirector\\' ) ) {
			return;
		}
		$relative = substr( $class, strlen( 'SEODirector\\' ) );
		$path     = dirname( __DIR__ ) . '/includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
