<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (get_option('qrcodr_delete_data_on_uninstall') !== '1') {
    return;
}

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qrcodr_scans");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qrcodr_codes");

delete_option('qrcodr_version');
delete_option('qrcodr_delete_data_on_uninstall');
