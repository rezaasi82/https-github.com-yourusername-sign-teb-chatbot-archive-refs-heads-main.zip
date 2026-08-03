<?php

declare(strict_types=1);

/**
 * Integration-test bootstrap.
 *
 * Unlike `tests/phpunit/bootstrap.php`, this loads a real WordPress test
 * install. It covers what the unit suite deliberately cannot: the custom
 * tables, `$wpdb` behaviour, capability enforcement and the REST routes as
 * WordPress actually dispatches them.
 *
 * Install the test library once with:
 *
 *     bin/install-wp-tests.sh wordpress_test root '' localhost latest
 *
 * @package Medora\Authority\Tests
 */

$medora_tests_dir = getenv('WP_TESTS_DIR');

if ($medora_tests_dir === false || $medora_tests_dir === '') {
    $medora_tmp = rtrim(sys_get_temp_dir(), '/\\');
    $medora_tests_dir = $medora_tmp . '/wordpress-tests-lib';
}

if (! is_readable($medora_tests_dir . '/includes/functions.php')) {
    fwrite(
        STDERR,
        "Could not find the WordPress test library at {$medora_tests_dir}.\n"
        . "Run bin/install-wp-tests.sh first, or set WP_TESTS_DIR.\n"
    );

    exit(1);
}

require_once $medora_tests_dir . '/includes/functions.php';

/**
 * Load the plugin into the test install before WordPress finishes booting.
 *
 * `muplugins_loaded` is the documented hook for this: it runs early enough
 * that the plugin's own `plugins_loaded` handler still fires normally, so the
 * module registry boots exactly as it would on a real site.
 */
tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__, 2) . '/medora-authority.php';
});

/**
 * Create the custom tables.
 *
 * The activation hook does not fire in the test harness, so the installer is
 * invoked directly once WordPress is loaded and `$wpdb` is available.
 */
tests_add_filter('wp_loaded', static function (): void {
    \Medora\Authority\Core\Activator::installTables();
    \Medora\Authority\Core\Capabilities::install();
});

require $medora_tests_dir . '/includes/bootstrap.php';
