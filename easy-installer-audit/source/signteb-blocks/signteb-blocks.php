<?php
/**
 * Plugin Name:       SignTeb Medical Blocks
 * Plugin URI:        https://signteb.com/medcore
 * Description:       ۱۰ Gutenberg Block اختصاصی پزشکی برای SignTeb MedCore — Doctor Hero، Appointment CTA، Service Grid، Before/After، FAQ و بیشتر.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            SignTeb
 * Author URI:        https://signteb.com
 * License:           GPL-2.0-or-later
 * Text Domain:       signteb-blocks
 *
 * @package SignTeb_Blocks
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'STMB_VERSION', '1.0.0' );
define( 'STMB_DIR',     plugin_dir_path( __FILE__ ) );
define( 'STMB_URI',     plugin_dir_url( __FILE__ ) );
define( 'STMB_TEXT',    'signteb-blocks' );

require_once STMB_DIR . 'includes/class-stmb-blocks.php';

add_action( 'plugins_loaded', static function (): void {
	( new STMB\Blocks() )->run();
} );
