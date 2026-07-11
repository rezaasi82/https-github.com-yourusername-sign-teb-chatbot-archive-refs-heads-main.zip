<?php
/**
 * Plugin Name:       Medora AI
 * Plugin URI:        https://signteb.com
 * Description:       Medora AI — دستیار هوشمند جذب و راهنمایی بیماران. ویجت چت هوش مصنوعی مستقل و سفیدبرچسب برای پزشکان و کلینیک‌ها: جذب لید، امتیازدهی هوشمند لید، خلاصه خودکار گفتگو و افزایش رزرو نوبت. کاملاً مستقل و قابل نصب روی هر سایت وردپرسی.
 * Version:           3.7.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            رضا آسیابی
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

define('SWC_VERSION', '3.7.0');
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
