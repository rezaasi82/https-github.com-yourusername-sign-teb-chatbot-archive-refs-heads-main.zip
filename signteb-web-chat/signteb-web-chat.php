<?php
/**
 * Plugin Name:       SignTeb AI Web Chat
 * Plugin URI:        https://signteb.com/web-chat
 * Description:       ویجت چت هوشمند پزشکی مستقل و سفید‌برچسب (white-label) که مستقیماً روی وب‌سایت اجرا می‌شود — تبدیل بازدیدکننده به بیمار رزرو‌شده. کاملاً مستقل، بدون هیچ وابستگی به افزونه دیگری، قابل نصب روی هر سایت وردپرسی.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            SignTeb
 * Author URI:        https://signteb.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signteb-web-chat
 * Domain Path:       /languages
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SWC_VERSION', '1.0.0');
define('SWC_FILE', __FILE__);
define('SWC_DIR', plugin_dir_path(__FILE__));
define('SWC_URL', plugin_dir_url(__FILE__));
define('SWC_BASENAME', plugin_basename(__FILE__));

require_once SWC_DIR . 'includes/class-autoloader.php';
SWC_Autoloader::register();

register_activation_hook(__FILE__, ['SWC_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SWC_Deactivator', 'deactivate']);

/**
 * Plugin singleton accessor.
 */
function swc_plugin(): SWC_Plugin
{
    static $instance = null;
    if ($instance === null) {
        $instance = new SWC_Plugin();
    }
    return $instance;
}

add_action('plugins_loaded', static function (): void {
    swc_plugin()->boot();
});
