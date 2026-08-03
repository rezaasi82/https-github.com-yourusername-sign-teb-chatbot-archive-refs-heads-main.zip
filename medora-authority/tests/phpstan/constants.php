<?php

declare(strict_types=1);

/**
 * Constants PHPStan needs to analyse the plugin.
 *
 * These are defined at runtime by the bootstrap file, which PHPStan does not
 * execute, so they are declared here instead.
 *
 * @package Medora\Authority
 */

define('MEDORA_PLUGIN_DIR', __DIR__ . '/');
define('MEDORA_PLUGIN_URL', 'https://example.test/wp-content/plugins/medora-authority/');
define('MEDORA_PLUGIN_FILE', __DIR__ . '/medora-authority.php');
define('MEDORA_PLUGIN_BASENAME', 'medora-authority/medora-authority.php');

const MEDORA_VERSION    = '0.1.0';
const MEDORA_DB_VERSION = '1.0.0';
