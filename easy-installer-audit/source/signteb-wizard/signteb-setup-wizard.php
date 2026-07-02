<?php
/**
 * Plugin Name:       SignTeb Setup Wizard
 * Plugin URI:        https://signteb.com/medcore
 * Description:       ویزارد راه‌اندازی ۶ مرحله‌ای SignTeb MedCore — برند، کلینیک، تماس، دمو، پلاگین‌ها، پایان
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            SignTeb
 * License:           GPL-2.0-or-later
 * Text Domain:       signteb-wizard
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'STWIZ_VERSION', '1.0.0' );
define( 'STWIZ_DIR',     plugin_dir_path( __FILE__ ) );
define( 'STWIZ_URI',     plugin_dir_url( __FILE__ ) );
define( 'STWIZ_TEXT',    'signteb-wizard' );

require_once STWIZ_DIR . 'includes/class-wizard-controller.php';

add_action( 'plugins_loaded', static function (): void {
	( new SignTeb\Wizard\Controller() )->boot();
} );

// Auto-redirect to wizard on first activation
register_activation_hook( __FILE__, static function (): void {
	set_transient( 'stwiz_redirect', true, 30 );
} );

add_action( 'admin_init', static function (): void {
	if ( ! get_transient( 'stwiz_redirect' ) ) {
		return;
	}

	delete_transient( 'stwiz_redirect' );

	// از ریدایرکت در AJAX/Cron (که پاسخ را خراب می‌کند)، فعال‌سازی گروهی
	// (bulk activate)، و کاربران بدون دسترسی ادمین جلوگیری می‌شود.
	if (
		wp_doing_ajax()
		|| wp_doing_cron()
		|| isset( $_GET['activate-multi'] )
		|| ! current_user_can( 'manage_options' )
	) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=signteb-wizard' ) );
	exit;
} );
