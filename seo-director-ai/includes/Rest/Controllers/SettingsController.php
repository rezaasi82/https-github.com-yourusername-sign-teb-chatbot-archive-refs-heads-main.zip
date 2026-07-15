<?php
/**
 * /sda/v1/settings — read/write plugin settings (sanitized by Options schema).
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Options;
use WP_REST_Request;
use WP_REST_Response;

final class SettingsController extends BaseController {

	public function __construct( private readonly Options $options ) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( array( 'settings' => $this->options->all() ) );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$saved = $this->options->update( is_array( $body ) ? $body : array() );
		return new WP_REST_Response( array( 'settings' => $saved ) );
	}
}
