<?php
/**
 * Boots the plugin core once WordPress has loaded every active plugin.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', static function (): void {
    \SignTeb\WebChat\Core\Plugin::start();
});
