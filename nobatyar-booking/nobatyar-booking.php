<?php
/**
 * Plugin Name:       نوبتیار (Nobatyar)
 * Plugin URI:        https://mynobatyar.ir
 * Description:       پلاگین مستقل رزرو نوبت برای سالن، باشگاه، مشاوره و هر کسب‌وکار محلی نوبت‌محور.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Nobatyar
 * Author URI:        https://mynobatyar.ir
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nobatyar-booking
 * Domain Path:       /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('NOBATYAR_VERSION', '1.1.0');
define('NOBATYAR_DB_VERSION', '1.1.0');
define('NOBATYAR_PLUGIN_FILE', __FILE__);
define('NOBATYAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOBATYAR_PLUGIN_URL', plugin_dir_url(__FILE__));

if (file_exists(NOBATYAR_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once NOBATYAR_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once NOBATYAR_PLUGIN_DIR . 'includes/Core/Autoloader.php';
\Nobatyar\Core\Autoloader::register();

register_activation_hook(__FILE__, ['Nobatyar\\Core\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Nobatyar\\Core\\Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
    \Nobatyar\Core\Migrator::maybe_upgrade();
    \Nobatyar\Core\Plugin::instance()->init();
});
