<?php
/**
 * Plugin activation — creates the reviews database table and registers the
 * plugin capabilities; maybe_upgrade() re-registers them on upgraded installs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_Activator {

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
     * Files replaced without reactivation never run activate(): hooked on
     * `init` so the first request of an upgraded install registers any
     * capability the stored version is behind on — Admins are never locked out.
     */
    public static function maybe_upgrade(): void {
        if ( (int) get_option( 'pqm_caps_version', 0 ) < PQM_Features::CAPS_VERSION ) {
            self::register_capabilities();
        }
    }

    /**
     * Grant the default pqm_* capability map and remember which version of it ran.
     */
    private static function register_capabilities(): void {
        PQM_Features::register_capabilities();
        update_option( 'pqm_caps_version', PQM_Features::CAPS_VERSION );
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

        update_option( 'pqm_db_version', PQM_VERSION );
    }
}
