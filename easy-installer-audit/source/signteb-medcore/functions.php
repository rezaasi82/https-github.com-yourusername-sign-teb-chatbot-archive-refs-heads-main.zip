<?php
/**
 * SignTeb MedCore — Theme Bootstrap
 *
 * این فایل فقط و فقط یک کار انجام می‌دهد:
 * بارگذاری ماژول‌های inc/ به ترتیب صحیح
 *
 * هیچ منطق اضافه‌ای در این فایل نباید نوشته شود.
 *
 * @package    SignTeb_MedCore
 * @version    1.0.0
 * @author     SignTeb <hello@signteb.com>
 * @license    GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────

define( 'MEDCORE_VERSION',   '1.0.0' );
define( 'MEDCORE_DIR',       get_template_directory() );
define( 'MEDCORE_URI',       get_template_directory_uri() );
define( 'MEDCORE_INC',       MEDCORE_DIR . '/inc/' );
define( 'MEDCORE_ASSETS',    MEDCORE_URI . '/assets/' );
define( 'MEDCORE_TEXT',      'signteb-medcore' );
define( 'MEDCORE_MIN_PHP',   '8.1' );
define( 'MEDCORE_MIN_WP',    '6.4' );

// ── PHP version check ─────────────────────────────────────────────────────────

if ( version_compare( PHP_VERSION, MEDCORE_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				esc_html__( 'SignTeb MedCore نیاز به PHP %1$s یا بالاتر دارد. نسخه فعلی شما %2$s است.', 'signteb-medcore' ),
				esc_html( MEDCORE_MIN_PHP ),
				esc_html( PHP_VERSION )
			)
		);
	} );
	return;
}

// ── Load modules in dependency order ─────────────────────────────────────────

$medcore_modules = [
	'helpers.php',                     // 1. Utility functions (no dependencies)
	'class-medcore-setup.php',         // 2. Theme supports, image sizes, menus
	'class-medcore-enqueue.php',       // 3. Scripts + Styles loader
	'class-medcore-template-tags.php', // 4. Template helper functions
	'class-medcore-customizer.php',    // 5. WordPress Customizer
	'class-medcore-block-patterns.php',// 6. Block patterns registration
	'class-medcore-nav-walker.php',    // 7. Accessible nav walker
];

foreach ( $medcore_modules as $module ) {
	$path = MEDCORE_INC . $module;
	if ( file_exists( $path ) ) {
		require_once $path;
	} else {
		// Log missing module in debug mode only
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[SignTeb MedCore] Missing module: %s', $path ) );
		}
	}
}

unset( $medcore_modules, $module, $path );
