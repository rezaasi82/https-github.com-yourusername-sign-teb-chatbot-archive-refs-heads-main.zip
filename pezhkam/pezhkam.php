<?php
/**
 * Plugin Name:       Pezhkam
 * Description:       پژکام — دستیار گفتگوی آنلاین مطب و کلینیک. به سؤال بازدیدکننده پاسخ می‌دهد، شماره تماس او را می‌گیرد، لید را امتیاز می‌دهد و پرونده‌اش را برای پیگیری منشی آماده می‌کند.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Pezhkam
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pezhkam
 * Domain Path:       /languages
 *
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PZK_VERSION', '1.0.0');
define('PZK_FILE', __FILE__);
define('PZK_DIR', plugin_dir_path(__FILE__));
define('PZK_URL', plugin_dir_url(__FILE__));
define('PZK_BASENAME', plugin_basename(__FILE__));

require_once PZK_DIR . 'includes/class-autoloader.php';
\Pezhkam\Autoloader::register();

register_activation_hook(__FILE__, ['\Pezhkam\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\Pezhkam\Core\Deactivator', 'deactivate']);

require_once PZK_DIR . 'includes/setup.php';
