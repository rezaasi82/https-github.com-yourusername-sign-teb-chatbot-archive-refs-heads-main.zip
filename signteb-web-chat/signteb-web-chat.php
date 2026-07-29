<?php
/**
 * Plugin Name:       SignTeb AI Web Chat
 * Plugin URI:        https://signteb.com/web-chat
 * Description:       ویجت چت هوشمند پزشکی مستقل و سفید‌برچسب (white-label) که مستقیماً روی وب‌سایت اجرا می‌شود — جذب لید، امتیازدهی هوشمند لید، خلاصه خودکار گفتگو، بورد CRM، پیامک و خروجی. کاملاً مستقل و قابل نصب روی هر سایت وردپرسی.
 * Version:           4.1.0
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

define('SWC_VERSION', '4.1.0');
define('SWC_FILE', __FILE__);
define('SWC_DIR', plugin_dir_path(__FILE__));
define('SWC_URL', plugin_dir_url(__FILE__));
define('SWC_BASENAME', plugin_basename(__FILE__));

require_once SWC_DIR . 'includes/class-autoloader.php';
\SignTeb\WebChat\Autoloader::register();

register_activation_hook(__FILE__, ['\SignTeb\WebChat\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\SignTeb\WebChat\Core\Deactivator', 'deactivate']);

require_once SWC_DIR . 'includes/setup.php';
