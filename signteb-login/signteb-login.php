<?php
/**
 * Plugin Name:       SignTeb Login
 * Plugin URI:        https://signteb.com
 * Description:       راهکار امنیت و برندسازی صفحه ورود وردپرس: طراحی اختصاصی مدرن، تغییر آدرس صفحه ورود (مخفی‌سازی wp-login.php)، و شخصی‌سازی رنگ سازمانی و لوگو از پیشخوان. محصولی از تیم توسعه SignTeb.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            رضا آسیابی
 * Author URI:        https://signteb.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signteb-login
 */

if (! defined('ABSPATH')) {
    exit;
}

// Guard against a second copy of the plugin being active in another
// folder: bail before defining constants or classes so the site never
// fatals with a "cannot redeclare class" error, and the design never
// renders twice.
if (defined('SIGNTEB_LOGIN_VERSION')) {
    return;
}

define('SIGNTEB_LOGIN_VERSION', '1.4.0');
define('SIGNTEB_LOGIN_FILE', __FILE__);
define('SIGNTEB_LOGIN_DIR', plugin_dir_path(__FILE__));
define('SIGNTEB_LOGIN_URL', plugin_dir_url(__FILE__));

require_once SIGNTEB_LOGIN_DIR . 'includes/class-signteb-login.php';
require_once SIGNTEB_LOGIN_DIR . 'includes/class-signteb-login-settings.php';
require_once SIGNTEB_LOGIN_DIR . 'includes/class-signteb-login-slug.php';

add_action('plugins_loaded', static function () {
    (new SignTeb_Login())->register();
    (new SignTeb_Login_Settings())->register();
    (new SignTeb_Login_Slug())->register();
}, 1);
