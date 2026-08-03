<?php
/**
 * Plugin Name:       Medora Authority
 * Plugin URI:        https://medora.ai/authority
 * Description:       AI Authority Platform — turns a WordPress site into a trusted, machine-readable knowledge source for AI search engines and large language models.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Medora
 * Author URI:        https://medora.ai
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       medora-authority
 * Domain Path:       /languages
 *
 * @package Medora\Authority
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const MEDORA_VERSION    = '0.4.0';
const MEDORA_DB_VERSION = '1.0.0';

define('MEDORA_PLUGIN_FILE', __FILE__);
define('MEDORA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEDORA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MEDORA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Composer autoload is optional: the bundled PSR-4 autoloader covers the
 * plugin's own namespace so the plugin runs from a plain checkout without a
 * `composer install` step. Composer is still preferred in production because
 * it produces an optimised classmap.
 */
if (is_readable(MEDORA_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once MEDORA_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once MEDORA_PLUGIN_DIR . 'includes/Core/Autoloader.php';
\Medora\Authority\Core\Autoloader::register();

register_activation_hook(__FILE__, [\Medora\Authority\Core\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\Medora\Authority\Core\Deactivator::class, 'deactivate']);

/**
 * The platform boots on `plugins_loaded` at a late priority so third-party
 * integrations registered on the default priority can hook into the module
 * registry before modules are resolved.
 */
add_action('plugins_loaded', static function (): void {
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Medora Authority requires PHP 8.2 or newer.', 'medora-authority')
            );
        });

        return;
    }

    \Medora\Authority\Core\Migrator::maybe_upgrade();
    \Medora\Authority\Core\Plugin::instance()->boot();
}, 20);
