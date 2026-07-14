<?php
/**
 * PSR-4 autoloader fallback used when Composer's autoloader is absent
 * (e.g. installed from a marketplace zip built without vendor/).
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	private const PREFIX   = 'SEODirector\\';
	private const BASE_DIR = 'includes/';

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$path     = SDA_PLUGIN_DIR . self::BASE_DIR . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
}
