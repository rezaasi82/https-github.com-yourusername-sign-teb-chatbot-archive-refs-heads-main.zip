<?php
/**
 * Plugin Name:       Pazira
 * Description:       Pazira — دستیار هوشمند جذب و راهنمایی بیماران. ویجت چت هوش مصنوعی مستقل و سفیدبرچسب برای پزشکان و کلینیک‌ها: جذب لید، امتیازدهی هوشمند لید، خلاصه خودکار گفتگو و افزایش رزرو نوبت. کاملاً مستقل و قابل نصب روی هر سایت وردپرسی.
 * Version:           4.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Pazira
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pazira
 * Domain Path:       /languages
 *
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PZR_VERSION', '4.0.0');
define('PZR_FILE', __FILE__);
define('PZR_DIR', plugin_dir_path(__FILE__));
define('PZR_URL', plugin_dir_url(__FILE__));
define('PZR_BASENAME', plugin_basename(__FILE__));

require_once PZR_DIR . 'includes/class-autoloader.php';
\Pazira\Autoloader::register();

register_activation_hook(__FILE__, ['\Pazira\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\Pazira\Core\Deactivator', 'deactivate']);

require_once PZR_DIR . 'includes/setup.php';
