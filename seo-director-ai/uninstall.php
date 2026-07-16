<?php
/**
 * Uninstall handler. Data removal is opt-in: tables and options are only
 * dropped when the admin explicitly enabled "delete data on uninstall".
 *
 * @package SEODirector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sda_settings = get_option( 'sda_settings', [] );

if ( empty( $sda_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$sda_tables = [
	'connections',
	'properties',
	'gsc_daily_totals',
	'gsc_query_daily',
	'gsc_page_daily',
	'gsc_page_query_weekly',
	'gsc_dimension_daily',
	'gsc_query_weekly',
	'gsc_query_monthly',
	'gsc_page_weekly',
	'gsc_page_monthly',
	'ga4_daily',
	'ga4_daily_totals',
	'psi_audits',
	'insights',
	'opportunities',
	'roadmap_tasks',
	'alerts',
	'health_scores',
	'reports',
	'job_state',
	'agency_sites',
	'license',
];

foreach ( $sda_tables as $sda_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sda_{$sda_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( [ 'sda_settings', 'sda_db_version', 'sda_installed_at', 'sda_activation_redirect', 'sda_vault_salt', 'sda_upgrading' ] as $sda_option ) {
	delete_option( $sda_option );
}

// Drop the agency client role so no custom capabilities linger after removal.
remove_role( 'sda_client' );
