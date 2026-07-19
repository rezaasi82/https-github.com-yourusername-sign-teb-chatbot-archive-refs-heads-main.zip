<?php
/**
 * Plugin capabilities. Single source of truth for role → cap mapping.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const MANAGE         = 'manage_sda';
	public const VIEW_REPORTS   = 'view_sda_reports';
	public const MANAGE_CLIENTS = 'manage_sda_clients';

	public static function add_caps(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::MANAGE );
			$admin->add_cap( self::VIEW_REPORTS );
			$admin->add_cap( self::MANAGE_CLIENTS );
		}
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::VIEW_REPORTS );
		}
	}

	public static function remove_caps(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			$role->remove_cap( self::MANAGE );
			$role->remove_cap( self::VIEW_REPORTS );
			$role->remove_cap( self::MANAGE_CLIENTS );
		}
	}
}
