<?php

declare(strict_types=1);

/**
 * Uninstall handler.
 *
 * Data is only destroyed when the operator has explicitly opted in, by setting
 * `medora_delete_data_on_uninstall` or defining `MEDORA_REMOVE_ALL_DATA`.
 * Deleting a knowledge graph that took weeks to build because someone
 * uninstalled to test something is not a recoverable mistake, so the default
 * is to leave every table intact.
 *
 * @package Medora\Authority
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$medora_should_purge = (bool) get_option('medora_delete_data_on_uninstall', false)
    || (defined('MEDORA_REMOVE_ALL_DATA') && constant('MEDORA_REMOVE_ALL_DATA'));

// Scheduled events always go: leaving cron hooks behind for a plugin that no
// longer exists produces a warning on every cron run.
foreach (
    [
        'medora_queue_worker',
        'medora_daily_analysis',
        'medora_rebuild_graph',
        'medora_license_check',
        'medora_prune_logs',
    ] as $medora_hook
) {
    wp_clear_scheduled_hook($medora_hook);
}

delete_transient('medora_llms_txt');
delete_transient('medora_sitemap_index');
delete_transient('medora_update_info');
delete_transient('medora_flush_rewrites');

if (! $medora_should_purge) {
    return;
}

global $wpdb;

$medora_tables = [
    'entities',
    'entity_relations',
    'entity_index',
    'vectors',
    'analysis',
    'prompt_packs',
    'crawler_hits',
    'citations',
    'ai_referrals',
    'audit_log',
    'jobs',
];

foreach ($medora_tables as $medora_table) {
    $medora_name = $wpdb->prefix . 'mdra_' . $medora_table;

    // Table names cannot be bound as parameters; every value here comes from
    // the hardcoded list above, never from input.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS `{$medora_name}`");
}

delete_option('medora_settings');
delete_option('medora_db_version');
delete_option('medora_needs_onboarding');
delete_option('medora_scored_mode');
delete_option('medora_delete_data_on_uninstall');

// Post and user meta written by the plugin.
foreach (['_medora_ai_summary', '_medora_llm_pack_hash', '_medora_canonical_answer', '_medora_primary_entity', '_medora_noindex', '_medora_exclude_llms', '_medora_reviewer_id', '_medora_reviewed_at', '_medora_schema_type', '_medora_faq', '_medora_audience'] as $medora_meta) {
    delete_post_meta_by_key($medora_meta);
}

foreach (['_medora_credentials', '_medora_job_title', '_medora_affiliation', '_medora_license_no', '_medora_orcid', '_medora_scholar_url', '_medora_researchgate', '_medora_linkedin', '_medora_years_active', '_medora_awards', '_medora_education'] as $medora_user_meta) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($wpdb->usermeta, ['meta_key' => $medora_user_meta]);
}

foreach (wp_roles()->role_objects as $medora_role) {
    foreach (['medora_view_dashboard', 'medora_manage_settings', 'medora_manage_entities', 'medora_run_analysis', 'medora_view_audit_log'] as $medora_cap) {
        $medora_role->remove_cap($medora_cap);
    }
}
