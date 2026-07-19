<?php
/**
 * Uninstall handler — honors the "delete data on uninstall" setting.
 * Default: keep everything (16 months of analytics history is valuable).
 *
 * @package SEODirector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = (array) get_option( 'sda_settings', array() );
if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$sda_tables = array(
	'connections', 'properties', 'gsc_daily_totals', 'gsc_query_daily', 'gsc_page_daily',
	'gsc_page_query_weekly', 'gsc_dimension_daily', 'ga4_daily', 'psi_audits', 'insights',
	'opportunities', 'roadmap_tasks', 'alerts', 'health_scores', 'reports', 'job_state',
	'agency_sites', 'license',
);

foreach ( $sda_tables as $sda_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sda_{$sda_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
}

// Options (settings, versions, vault salt, monthly AI usage counters).
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sda\\_%'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
);

// Transients (quota counters, rate limits, oauth state).
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sda\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sda\\_%'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
);
