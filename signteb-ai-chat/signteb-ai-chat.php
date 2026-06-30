<?php
/**
 * Plugin Name:       SignTeb AI Chat
 * Plugin URI:        https://signteb.com
 * Description:       دستیار چت هوشمند پزشکی برای کلینیک‌ها و پزشکان — تبدیل بازدیدکننده به بیمار رزرو‌شده. مستقل، با اتصال خودکار به SignTeb Medical Core.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.1
 * Author:            SignTeb
 * Author URI:        https://signteb.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signteb-ai-chat
 * Domain Path:       /languages
 *
 * @package STMC_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

define('STMC_CHAT_VERSION', '1.0.0');
define('STMC_CHAT_FILE', __FILE__);
define('STMC_CHAT_DIR', plugin_dir_path(__FILE__));
define('STMC_CHAT_URL', plugin_dir_url(__FILE__));
define('STMC_CHAT_BASENAME', plugin_basename(__FILE__));

require_once STMC_CHAT_DIR . 'includes/Core/Autoloader.php';
\STMC_Chat\Core\Autoloader::register();

register_activation_hook(__FILE__, [\STMC_Chat\Core\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\STMC_Chat\Core\Deactivator::class, 'deactivate']);

/**
 * Boot the plugin on plugins_loaded so integrations (e.g. Medical Core) are ready.
 */
function stmc_chat(): \STMC_Chat\Core\Plugin
{
    static $instance = null;
    if ($instance === null) {
        $instance = new \STMC_Chat\Core\Plugin();
    }
    return $instance;
}

add_action('plugins_loaded', static function (): void {
    stmc_chat()->boot();
});
