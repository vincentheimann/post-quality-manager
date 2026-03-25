<?php
/**
 * Fired when the plugin is uninstalled.
 * Drops the custom database table and removes plugin options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'pqm_reviews';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'pqm_settings' );
delete_option( 'pqm_db_version' );

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'pqm_cron_review_posts' );
