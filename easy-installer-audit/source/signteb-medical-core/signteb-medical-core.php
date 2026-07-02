<?php
/**
 * Plugin Name:       SignTeb Medical Core
 * Plugin URI:        https://signteb.com/medcore
 * Description:       موتور اصلی پزشکی برای SignTeb MedCore — Custom Post Types، Meta Fields، SEO Engine، و سیستم رزرو نوبت.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            SignTeb
 * Author URI:        https://signteb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signteb-medical-core
 * Domain Path:       /languages
 * Network:           false
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC;

defined( 'ABSPATH' ) || exit;

// ── Version requirements ──────────────────────────────────────────────────────

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action( 'admin_notices', static function (): void {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'SignTeb Medical Core نیاز به PHP 8.1 یا بالاتر دارد.', 'signteb-medical-core' ) .
			'</p></div>';
	} );
	return;
}

if ( version_compare( get_bloginfo( 'version' ), '6.4', '<' ) ) {
	add_action( 'admin_notices', static function (): void {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'SignTeb Medical Core نیاز به WordPress 6.4 یا بالاتر دارد.', 'signteb-medical-core' ) .
			'</p></div>';
	} );
	return;
}

// ── Constants ─────────────────────────────────────────────────────────────────

define( 'STMC_VERSION',   '1.0.0' );
define( 'STMC_FILE',      __FILE__ );
define( 'STMC_DIR',       plugin_dir_path( __FILE__ ) );
define( 'STMC_URI',       plugin_dir_url( __FILE__ ) );
define( 'STMC_INC',       STMC_DIR . 'includes/' );
define( 'STMC_TEXT',      'signteb-medical-core' );
define( 'STMC_DB_VERSION','1.0.0' );
define( 'STMC_MIN_WP',    '6.4' );
define( 'STMC_MIN_PHP',   '8.1' );

// ── PSR-4-ish Autoloader ───────────────────────────────────────────────────────
//
// قرارداد نام‌گذاری فایل‌ها در این پلاگین:
//   STMC\Foo                 →  includes/class-stmc-foo.php
//   STMC\SubNs\ClassName     →  includes/sub-ns/class-stmc-{prefix}-class-name.php
//
// هر زیرپوشه یک پیشوند ثابت (و گاهی singular) قبل از نام کلاس دارد —
// مثلاً Taxonomies → 'taxonomy' (نه 'taxonomies'), PostTypes → 'cpt'.
// این نگاشت صریح، چون از یک قاعده عمومی قابل استخراج نیست.

spl_autoload_register( static function ( string $class ): void {
	if ( ! str_starts_with( $class, 'STMC\\' ) ) {
		return;
	}

	$relative = substr( $class, strlen( 'STMC\\' ) );
	$parts    = explode( '\\', $relative );
	$last     = array_pop( $parts );

	// kebab-case نام کلاس: FooBar → foo-bar
	$class_kebab = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $last ) );

	// بدون زیرپوشه (مثل STMC\Plugin, STMC\Loader) → includes/class-stmc-plugin.php
	if ( empty( $parts ) ) {
		$file = STMC_INC . 'class-stmc-' . $class_kebab . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
		return;
	}

	// نگاشت پوشه → پیشوند فایل (طبق قرارداد واقعی پروژه)
	$folder_prefix_map = [
		'Admin'       => [ 'dir' => 'admin',       'prefix' => 'admin' ],
		'PostTypes'   => [ 'dir' => 'post-types',  'prefix' => 'cpt' ],
		'Taxonomies'  => [ 'dir' => 'taxonomies',  'prefix' => 'taxonomy' ],
		'Meta'        => [ 'dir' => 'meta',        'prefix' => 'meta' ],
		'Seo'         => [ 'dir' => 'seo',         'prefix' => 'seo' ],
		'Appointment' => [ 'dir' => 'appointment', 'prefix' => 'appointment' ],
		'Sms'         => [ 'dir' => 'sms',         'prefix' => 'sms' ],
		'Reviews'     => [ 'dir' => 'reviews',     'prefix' => 'reviews' ],
	];

	$top_ns = $parts[0];

	if ( isset( $folder_prefix_map[ $top_ns ] ) ) {
		$map      = $folder_prefix_map[ $top_ns ];
		$filename = 'class-stmc-' . $map['prefix'] . '-' . $class_kebab . '.php';
		$file     = STMC_INC . $map['dir'] . '/' . $filename;

		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}

	// Fallback: قاعده عمومی قدیمی (برای سازگاری با هر کلاس آینده‌ای که
	// از این نگاشت پیروی نکند ولی نام فایلش مطابق نام کلاس خام باشد)
	$path = STMC_INC . strtolower( implode( '/', $parts ) ) . '/';
	$file = $path . 'class-stmc-' . $class_kebab . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// ── Activation / Deactivation hooks ──────────────────────────────────────────

register_activation_hook( __FILE__, static function (): void {
	require_once STMC_INC . 'class-stmc-activator.php';
	Activator::activate();
} );

register_deactivation_hook( __FILE__, static function (): void {
	require_once STMC_INC . 'class-stmc-deactivator.php';
	Deactivator::deactivate();
} );

// ── Bootstrap ─────────────────────────────────────────────────────────────────

/**
 * Main plugin entry
 *
 * @return Plugin
 */
function stmc(): Plugin {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new Plugin();
	}
	return $instance;
}

// Initialize on plugins_loaded (after all plugins are loaded)
add_action( 'plugins_loaded', static function (): void {
	stmc()->run();
} );
