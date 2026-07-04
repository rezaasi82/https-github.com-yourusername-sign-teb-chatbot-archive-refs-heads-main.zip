<?php
/**
 * Plugin Name: QRCODR by SignTeb
 * Plugin URI: https://signteb.com
 * Description: تولید و مدیریت QR Code های داینامیک با آمار اسکن دقیق
 * Version: 0.1.0
 * Author: رضا آسیابی
 * Author URI: https://signteb.com
 * License: GPL v2 or later
 * Text Domain: qrcodr
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('QRCODR_VERSION', '0.1.0');
define('QRCODR_PLUGIN_FILE', __FILE__);
define('QRCODR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('QRCODR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once QRCODR_PLUGIN_PATH . 'includes/Core/Autoloader.php';
QRCODR\Core\Autoloader::register();

register_activation_hook(__FILE__, array(QRCODR\Core\Activator::class, 'activate'));
register_deactivation_hook(__FILE__, array(QRCODR\Core\Deactivator::class, 'deactivate'));

add_action('plugins_loaded', array(QRCODR\Core\Plugin::class, 'boot'));
