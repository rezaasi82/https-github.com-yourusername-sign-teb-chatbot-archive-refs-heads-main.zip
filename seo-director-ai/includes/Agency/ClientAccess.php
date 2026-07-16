<?php
/**
 * The client-facing read-only role (Agency edition). A "SEO Client" can view
 * reports and dashboards for their site but cannot change connections,
 * settings, or licensing. The role is created on activation and removed on
 * uninstall so no orphan capabilities linger.
 *
 * @package SEODirector
 */

namespace SEODirector\Agency;

use SEODirector\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

final class ClientAccess {

	public const ROLE = 'sda_client';

	public static function add(): void {
		if ( null !== get_role( self::ROLE ) ) {
			// Ensure the read cap is present even if the role predates this build.
			get_role( self::ROLE )->add_cap( Capabilities::VIEW_REPORTS );
			get_role( self::ROLE )->add_cap( 'read' );
			return;
		}

		add_role(
			self::ROLE,
			__( 'SEO Client', 'seo-director-ai' ),
			[
				'read'                      => true,
				Capabilities::VIEW_REPORTS  => true,
			]
		);
	}

	public static function remove(): void {
		if ( null !== get_role( self::ROLE ) ) {
			remove_role( self::ROLE );
		}
	}
}
