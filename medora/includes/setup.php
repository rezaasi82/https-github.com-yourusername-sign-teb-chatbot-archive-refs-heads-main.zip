<?php
/**
 * Boots the plugin core once WordPress has loaded every active plugin.
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', static function (): void {
    \Medora\Core\Plugin::start();
});
