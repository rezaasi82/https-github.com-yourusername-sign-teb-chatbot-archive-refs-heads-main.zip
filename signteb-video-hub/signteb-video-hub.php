<?php
/**
 * Plugin Name:       SignTeb Video Hub
 * Plugin URI:        https://signteb.com
 * Description:       مرکز مدیریت و نمایش ویدئوهای پزشکی — همگام‌سازی خودکار آپارات/یوتیوب، خلاصه و لینک‌سازی داخلی با هوش مصنوعی، اسکیمای ویدئویی، سایت‌مپ ویدئو و ویجت المنتور.
 * Version:           1.0.5
 * Requires at least: 5.8
 * Requires PHP:      8.1
 * Author:            SignTeb
 * Author URI:        https://signteb.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signteb-video-hub
 * Domain Path:       /languages
 *
 * @package SignTeb\VideoHub
 */

if (! defined('ABSPATH')) {
    exit;
}

define('STVH_VERSION', '1.0.5');
define('STVH_FILE', __FILE__);
define('STVH_DIR', plugin_dir_path(__FILE__));
define('STVH_URL', plugin_dir_url(__FILE__));
define('STVH_BASENAME', plugin_basename(__FILE__));

require_once STVH_DIR . 'includes/Core/Autoloader.php';
\SignTeb\VideoHub\Core\Autoloader::register();

register_activation_hook(__FILE__, [\SignTeb\VideoHub\Core\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\SignTeb\VideoHub\Core\Deactivator::class, 'deactivate']);

/**
 * Shared plugin instance.
 */
function stvh(): \SignTeb\VideoHub\Core\Plugin
{
    static $instance = null;
    if ($instance === null) {
        $instance = new \SignTeb\VideoHub\Core\Plugin();
    }
    return $instance;
}

// The post type must exist before `init` consumers (rewrites, Elementor, REST)
// run, so boot on plugins_loaded and let Plugin::boot() order the hooks.
add_action('plugins_loaded', static function (): void {
    stvh()->boot();
});
