<?php
/**
 * Plugin Name:       Clinovix AI
 * Description:       Clinovix AI — دستیار هوشمند جذب و راهنمایی بیماران. ویجت چت هوش مصنوعی مستقل و سفیدبرچسب برای پزشکان و کلینیک‌ها: جذب لید، امتیازدهی هوشمند لید، خلاصه خودکار گفتگو و افزایش رزرو نوبت. کاملاً مستقل و قابل نصب روی هر سایت وردپرسی.
 * Version:           4.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Clinovix
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       clinovix
 * Domain Path:       /languages
 *
 * @package Clinovix
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CLX_VERSION', '4.0.0');
define('CLX_FILE', __FILE__);
define('CLX_DIR', plugin_dir_path(__FILE__));
define('CLX_URL', plugin_dir_url(__FILE__));
define('CLX_BASENAME', plugin_basename(__FILE__));

require_once CLX_DIR . 'includes/class-autoloader.php';
\Clinovix\Autoloader::register();

register_activation_hook(__FILE__, ['\Clinovix\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\Clinovix\Core\Deactivator', 'deactivate']);

require_once CLX_DIR . 'includes/setup.php';
