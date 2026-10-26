<?php
/**
 * Plugin activation — creates the reviews database table and registers the
 * plugin capabilities; maybe_upgrade() does the same on upgraded installs.
 * Either grants a capability's defaults only while no role holding
 * manage_options has it yet, so roles set on Settings → Roles (story 42) are
 * never reset. uninstall() removes everything the plugin stored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PostQuality_Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        self::create_table();
        self::register_capabilities();

        // Generate a default webhook token if none exists.
        $settings = get_option( 'pqm_settings', [] );
        if ( empty( $settings['webhook_token'] ) ) {
            $settings['webhook_token'] = wp_generate_uuid4();
            update_option( 'pqm_settings', $settings );
        }
    }

    /**
     * Run on plugin deletion, from the Freemius SDK's `after_uninstall` hook
     * (story 44: WordPress would skip that hook if an uninstall.php existed).
     * Drops the custom database table, removes plugin options and strips the
     * pqm_* capabilities from every role and every user's direct grants.
     */
    public static function uninstall(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pqm_reviews';
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        delete_option( 'pqm_settings' );
        delete_option( 'pqm_db_version' );
        delete_option( 'pqm_workflow_version_seen' );
        delete_option( 'pqm_cron_nudge_dismissed' );
        delete_option( 'pqm_caps_version' );
        delete_option( 'pqm_setup_progress' ); // POSTQUALITY_SETUP_OPTION (story 36), named literally: the harnesses load this class without the main plugin file.
        delete_option( 'pqm_n8n_api' ); // PostQuality_N8n::OPTION, named literally for the same reason: the n8n base URL and API key.
        if ( postquality_fs()->is__premium_only() ) {
            PostQuality_N8n::revoke_setup_password__premium_only(); // The Application Password provisioning made for n8n (bug 73): revoked before its map goes.
        }
        delete_option( 'pqm_n8n_setup' ); // PostQuality_N8n::SETUP_OPTION, named literally for the same reason: what provisioning created in n8n.
        delete_option( 'pqm_n8n_leftovers' ); // PostQuality_N8n::LEFTOVERS_OPTION, named literally for the same reason: what a failed provisioning left in n8n.
        delete_option( 'pqm_n8n_provisioning' ); // PostQuality_N8n::LOCK_OPTION, named literally for the same reason: the lock of a provisioning run.
        delete_option( 'pqm_ping' ); // PostQuality_Admin::PING_OPTION, named literally for the same reason: a test ping waiting for n8n's callback (story 2).

        // Per-user monthly usage counters (author quota, CAP-4) — all users.
        delete_metadata( 'user', 0, 'pqm_monthly_usage', '', true );
        // The Dashboard feedback card hidden per user (story 79; PostQuality_REST_API::FEEDBACK_META, named literally for the same reason) — all users.
        delete_metadata( 'user', 0, 'pqm_feedback_dismissed', '', true );
        // Leftover from the retired View as (story 34, removed by story 42): kept one release.
        delete_metadata( 'user', 0, 'pqm_view_as', '', true );

        PostQuality_Features::remove_capabilities();

        // Clear any scheduled cron events.
        wp_clear_scheduled_hook( 'pqm_cron_review_posts' );
    }

    /**
     * Files replaced without reactivation never run activate(): hooked on
     * `init` so the first request of an upgraded install registers any
     * capability the stored version is behind on — Admins are never locked out.
     */
    public static function maybe_upgrade(): void {
        if ( (int) get_option( 'pqm_caps_version', 0 ) < PostQuality_Features::CAPS_VERSION ) {
            self::register_capabilities();
        }
    }

    /**
     * Grant the default pqm_* capability map — per capability, only while no
     * role holding manage_options has it yet (a fresh install, or a capability
     * new in this version) — and remember which version of it ran.
     */
    private static function register_capabilities(): void {
        PostQuality_Features::register_capabilities();
        update_option( 'pqm_caps_version', PostQuality_Features::CAPS_VERSION );
    }

    /**
     * Create the pqm_reviews table using dbDelta.
     */
    private static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'pqm_reviews';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            command VARCHAR(32) NOT NULL DEFAULT 'review',
            overall_score TINYINT(3) UNSIGNED DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT '',
            result_data LONGTEXT NOT NULL,
            reviewed_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY command (command),
            KEY post_command (post_id, command)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'pqm_db_version', POSTQUALITY_VERSION );
    }
}
