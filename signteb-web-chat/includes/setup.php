<?php
/**
 * Marketplace activation gate. The plugin core boots only after the product
 * has been activated with a valid purchase license.
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

// --------------------------------------------------------------------------------------------------- Start RTL License
$rtlLicenseClassName  = 'RTL_License_0949e1086a8664d9';
$rtlLicenseFilePath   = __DIR__ . DIRECTORY_SEPARATOR . $rtlLicenseClassName . '.php';
$rtlLicenseFileHash   = @sha1_file($rtlLicenseFilePath);

if ( $rtlLicenseFileHash === '7a08b73e61f2c61ca287e1f88650085a47328a54' && file_exists($rtlLicenseFilePath) ) {
	require_once $rtlLicenseFilePath;

	if ( class_exists($rtlLicenseClassName) && method_exists($rtlLicenseClassName, 'isActive') ) {
		$rtlLicenseClass = new $rtlLicenseClassName();

		if ( $rtlLicenseClass->{'isActive'}() === true ) {
			// Product is Active Now, Enable Pro Features
			add_action('plugins_loaded', static function (): void {
				swc_plugin()->boot();
			});
		}
	}
}
// ----------------------------------------------------------------------------------------------------- End RTL License
