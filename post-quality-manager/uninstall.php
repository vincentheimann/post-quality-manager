<?php
/**
 * Fired when the plugin is uninstalled.
 * Drops the custom database table, removes plugin options and strips the
 * pqm_* capabilities from every role and every user's direct grants.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'pqm_reviews';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'pqm_settings' );
delete_option( 'pqm_db_version' );
delete_option( 'pqm_workflow_version_seen' );
delete_option( 'pqm_cron_nudge_dismissed' );
delete_option( 'pqm_caps_version' );
delete_option( 'pqm_setup_progress' ); // PQM_SETUP_OPTION (story 36); this file loads without the main plugin file.

// Per-user monthly usage counters (author quota, CAP-4) — all users.
delete_metadata( 'user', 0, 'pqm_monthly_usage', '', true );

// WordPress loads this file on its own, so pull in the capability map directly.
require_once __DIR__ . '/includes/class-pqm-features.php';
PQM_Features::remove_capabilities();

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'pqm_cron_review_posts' );
