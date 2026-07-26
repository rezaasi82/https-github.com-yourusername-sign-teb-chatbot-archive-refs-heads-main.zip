<?php
/**
 * Plugin Name:       Medora AI
 * Description:       Medora AI — دستیار هوشمند جذب و راهنمایی بیماران. ویجت چت هوش مصنوعی مستقل و سفیدبرچسب برای پزشکان و کلینیک‌ها: جذب لید، امتیازدهی هوشمند لید، خلاصه خودکار گفتگو و افزایش رزرو نوبت. کاملاً مستقل و قابل نصب روی هر سایت وردپرسی.
 * Version:           4.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Medora
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signteb-web-chat
 * Domain Path:       /languages
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SWC_VERSION', '4.0.0');
define('SWC_FILE', __FILE__);
define('SWC_DIR', plugin_dir_path(__FILE__));
define('SWC_URL', plugin_dir_url(__FILE__));
define('SWC_BASENAME', plugin_basename(__FILE__));

require_once SWC_DIR . 'includes/class-autoloader.php';
\Medora\Autoloader::register();

register_activation_hook(__FILE__, ['\Medora\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\Medora\Core\Deactivator', 'deactivate']);

require_once SWC_DIR . 'includes/setup.php';
