<?php
/**
 * Registers all REST controllers.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest;

use SEODirector\Core\Container;
use SEODirector\Rest\Controllers\OverviewController;
use SEODirector\Rest\Controllers\SettingsController;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class RestServiceProvider {

	public function __construct( private Container $container ) {}

	public function register_routes(): void {
		$controllers = [
			new OverviewController(),
			new SettingsController( $this->container->get( Settings::class ) ),
		];

		/**
		 * Filters the REST controllers to register (add-on extension point).
		 *
		 * @param array     $controllers Controller instances exposing register_routes().
		 * @param Container $container   Plugin container.
		 */
		$controllers = apply_filters( 'sda_rest_controllers', $controllers, $this->container );

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
