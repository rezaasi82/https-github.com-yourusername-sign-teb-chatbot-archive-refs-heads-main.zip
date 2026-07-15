<?php
/**
 * Unit test bootstrap — stubs the few WP functions the pure Analysis/Ai classes touch,
 * so the deterministic layer runs without a WordPress install.
 *
 * @package SEODirector
 */

define( 'ABSPATH', __DIR__ . '/' );

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
