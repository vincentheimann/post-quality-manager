<?php
/**
 * Plugin activation — creates the reviews database table and registers the
 * plugin capabilities; maybe_upgrade() does the same on upgraded installs.
 * Either grants a capability's defaults only while no role holding
 * manage_options has it yet, so roles set on Settings → Roles (story 42) are
 * never reset. Both first run migrate_stored_names(), the one-time move of
 * every stored name from the pqm prefix to postquality (story 91).
 * uninstall() removes everything the plugin stored, under either prefix.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PostQuality_Activator {

    /**
     * Bump when a stored name moves again; migrate_stored_names() runs once
     * per version and records the version it finished in NAMES_OPTION.
     */
    const NAMES_VERSION = 1;
    const NAMES_OPTION  = 'postquality_names_version';

    /**
     * Every name the plugin stored before story 91 (release 3.9.0 and the
     * 3.10.0 build of story 88) and its new name — the one legacy map, read
     * only by migrate_stored_names() and uninstall(). Nothing else reads an
     * old name. The new names are written literally: the harnesses load this
     * class without the main plugin file, so the constants that carry them
     * (PostQuality_N8n::OPTION, POSTQUALITY_SETUP_OPTION, …) may be absent.
     * The View as meta (story 34, retired by story 42) is deleted, not moved.
     */
    private const LEGACY_NAMES = [
        'options'           => [
            'pqm_settings'              => 'postquality_settings',
            'pqm_db_version'            => 'postquality_db_version',
            'pqm_workflow_version_seen' => 'postquality_workflow_version_seen',
            'pqm_cron_nudge_dismissed'  => 'postquality_cron_nudge_dismissed',
            'pqm_caps_version'          => 'postquality_caps_version',
            'pqm_setup_progress'        => 'postquality_setup_progress',
            'pqm_n8n_api'               => 'postquality_n8n_api',
            'pqm_n8n_setup'             => 'postquality_n8n_setup',
            'pqm_n8n_leftovers'         => 'postquality_n8n_leftovers',
            'pqm_n8n_provisioning'      => 'postquality_n8n_provisioning',
            'pqm_ping'                  => 'postquality_ping',
        ],
        'user_meta'         => [
            'pqm_monthly_usage'      => 'postquality_monthly_usage',
            'pqm_feedback_dismissed' => 'postquality_feedback_dismissed',
        ],
        'user_meta_deleted' => [ 'pqm_view_as' ],
        'capabilities'      => [
            'pqm_view_reviews'    => 'postquality_view_reviews',
            'pqm_run_free_agents' => 'postquality_run_free_agents',
            'pqm_run_paid_agents' => 'postquality_run_paid_agents',
            'pqm_manage_agents'   => 'postquality_manage_agents',
            'pqm_manage_settings' => 'postquality_manage_settings',
        ],
        'cron_hook'         => [ 'pqm_cron_review_posts' => 'postquality_cron_review_posts' ],
        'cron_schedule'     => [ 'pqm_weekly' => 'postquality_weekly' ],
        'table'             => [ 'pqm_reviews' => 'postquality_reviews' ],
    ];

    /**
     * Run on plugin activation. The name migration goes first, so an old
     * site's table is renamed before dbDelta would create an empty new one
     * beside it and its roles keep their grants before the defaults are
     * offered.
     */
    public static function activate(): void {
        self::migrate_stored_names();
        self::create_table();
        self::register_capabilities();

        // Generate a default webhook token if none exists.
        $settings = get_option( 'postquality_settings', [] );
        if ( empty( $settings['webhook_token'] ) ) {
            $settings['webhook_token'] = wp_generate_uuid4();
            update_option( 'postquality_settings', $settings );
        }
    }

    /**
     * Run on plugin deletion, from the Freemius SDK's `after_uninstall` hook
     * (story 44: WordPress would skip that hook if an uninstall.php existed).
     * Drops the reviews table, removes the plugin options and user meta,
     * strips the plugin capabilities from every role and every user's direct
     * grants and clears the review schedule — under both prefixes, so a site
     * deleted before its first load on this version is clean too. That site
     * (deactivated, updated, deleted: `init` has fired, maybe_upgrade() never
     * ran) first gets its names moved, so the revoke below finds the setup
     * map and the Application Password it names.
     */
    public static function uninstall(): void {
        global $wpdb;

        self::migrate_stored_names();

        foreach ( self::LEGACY_NAMES['table'] as $old => $new ) {
            $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }

        if ( postquality_fs()->is__premium_only() ) {
            PostQuality_N8n::revoke_setup_password__premium_only(); // The Application Password provisioning made for n8n (bug 73): revoked before its map goes.
        }
        foreach ( self::LEGACY_NAMES['options'] as $old => $new ) {
            delete_option( $new );
            delete_option( $old );
        }
        delete_option( self::NAMES_OPTION );

        // Per-user meta — all users: the monthly usage counters (author quota, CAP-4), the Dashboard
        // feedback card hidden per user (story 79), the leftover of the retired View as (story 42).
        foreach ( self::LEGACY_NAMES['user_meta'] as $old => $new ) {
            delete_metadata( 'user', 0, $new, '', true );
            delete_metadata( 'user', 0, $old, '', true );
        }
        foreach ( self::LEGACY_NAMES['user_meta_deleted'] as $old ) {
            delete_metadata( 'user', 0, $old, '', true );
        }

        PostQuality_Features::remove_capabilities( array_merge(
            array_keys( PostQuality_Features::capabilities() ),
            array_keys( self::LEGACY_NAMES['capabilities'] )
        ) );

        foreach ( self::LEGACY_NAMES['cron_hook'] as $old => $new ) {
            wp_clear_scheduled_hook( $new );
            wp_clear_scheduled_hook( $old );
        }
    }

    /**
     * Files replaced without reactivation never run activate(): hooked on
     * `init` so the first request of an upgraded install moves the stored
     * names (once) and registers any capability the stored version is
     * behind on — Admins are never locked out. When either ran, the current
     * user's capabilities are reloaded, so that same request's pages open
     * (bug 94); a finished site skips the reload.
     */
    public static function maybe_upgrade(): void {
        $changed = (int) get_option( self::NAMES_OPTION, 0 ) < self::NAMES_VERSION;
        self::migrate_stored_names();
        if ( (int) get_option( 'postquality_caps_version', 0 ) < PostQuality_Features::CAPS_VERSION ) {
            self::register_capabilities();
            $changed = true;
        }
        if ( $changed ) {
            wp_get_current_user()->for_site(); // Why the current user needs it: memory/freemium-and-roles.md (bug 94).
        }
    }

    /**
     * Move every stored name from the pqm prefix to postquality, once
     * (story 91). Step-wise idempotent: each option, meta key, capability
     * and the cron event moves only when its new name is absent, else the
     * old one is dropped; the table renames only when the new table is
     * absent or empty. The finished flag is written last, so an interrupted
     * run retries on the next load and a finished site pays one cached
     * option read. Values move unchanged; the caps move before the
     * capability version check, so a role keeps exactly the grants it had.
     */
    public static function migrate_stored_names(): void {
        if ( (int) get_option( self::NAMES_OPTION, 0 ) >= self::NAMES_VERSION ) {
            return;
        }
        self::migrate_options();
        self::migrate_user_meta();
        self::migrate_capabilities();
        self::migrate_cron();
        if ( ! self::migrate_table() ) {
            return; // A failed rename leaves the flag unset: the next load retries.
        }
        update_option( self::NAMES_OPTION, self::NAMES_VERSION, true );
    }

    /**
     * Options move through the Options API (caches stay right), each with
     * its autoload flag. The Settings API's sanitizer, once register_setting()
     * has attached it on admin_init, would rebuild the settings for the
     * current user and drop every admin-only key on a low-capability or
     * user-less request, so it is detached around the move and put back.
     */
    private static function migrate_options(): void {
        $sanitizer = [ 'PostQuality_Admin', 'sanitize_settings' ];
        $filter    = 'sanitize_option_postquality_settings';
        $priority  = has_filter( $filter, $sanitizer );
        if ( false !== $priority ) {
            remove_filter( $filter, $sanitizer, $priority );
        }
        $autoloaded = wp_load_alloptions();
        foreach ( self::LEGACY_NAMES['options'] as $old => $new ) {
            $value = get_option( $old );
            if ( false === $value ) {
                continue;
            }
            if ( false === get_option( $new ) ) {
                add_option( $new, $value, '', array_key_exists( $old, $autoloaded ) );
            }
            delete_option( $old );
        }
        if ( false !== $priority ) {
            add_filter( $filter, $sanitizer, $priority, 2 );
        }
    }

    /** User meta moves per user holding it; the retired View as meta is deleted for all users. */
    private static function migrate_user_meta(): void {
        foreach ( self::LEGACY_NAMES['user_meta'] as $old => $new ) {
            foreach ( get_users( [ 'meta_key' => $old, 'fields' => 'ids' ] ) as $user_id ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-time migration lookup
                if ( ! metadata_exists( 'user', $user_id, $new ) ) {
                    add_user_meta( $user_id, $new, get_user_meta( $user_id, $old, true ), true );
                }
                delete_user_meta( $user_id, $old );
            }
        }
        foreach ( self::LEGACY_NAMES['user_meta_deleted'] as $old ) {
            delete_metadata( 'user', 0, $old, '', true );
        }
    }

    /**
     * Capabilities move on every role, grant value kept (an explicit deny
     * stays a deny), then on every user's own grants: with the roles already
     * moved, capability__in on the old names matches only users whose own
     * meta names one (as remove_capabilities() relies on).
     */
    private static function migrate_capabilities(): void {
        $caps = self::LEGACY_NAMES['capabilities'];
        foreach ( wp_roles()->role_objects as $role ) {
            self::move_capabilities( $role, $role->capabilities, $caps );
        }
        foreach ( get_users( [ 'capability__in' => array_keys( $caps ) ] ) as $user ) {
            self::move_capabilities( $user, $user->caps, $caps );
        }
    }

    /**
     * @param WP_Role|WP_User $holder  Both offer add_cap( $cap, $grant ) and remove_cap( $cap ).
     * @param array           $held    The holder's capabilities as stored (cap => grant).
     */
    private static function move_capabilities( $holder, array $held, array $map ): void {
        foreach ( $map as $old => $new ) {
            if ( ! array_key_exists( $old, $held ) ) {
                continue;
            }
            if ( ! array_key_exists( $new, $held ) ) {
                $holder->add_cap( $new, $held[ $old ] );
            }
            $holder->remove_cap( $old );
        }
    }

    /**
     * The review event moves to the new hook at its next run time and
     * interval (the weekly schedule's key moves with it). A recurrence this
     * build does not register — the Free build has no weekly schedule — is
     * offered to wp_schedule_event() through a cron_schedules filter for the
     * one call, with the event's own interval; WordPress reschedules an
     * event whose schedule is unregistered from its stored interval, so the
     * event stays alive as it did under the old hook.
     */
    private static function migrate_cron(): void {
        foreach ( self::LEGACY_NAMES['cron_hook'] as $old => $new ) {
            $event = wp_get_scheduled_event( $old );
            if ( false === $event ) {
                continue;
            }
            if ( ! wp_next_scheduled( $new ) ) {
                $schedule = self::LEGACY_NAMES['cron_schedule'][ $event->schedule ] ?? $event->schedule;
                $offer    = static fn( array $schedules ): array => $schedules + [ $schedule => [ 'interval' => (int) $event->interval, 'display' => $schedule ] ];
                $missing  = ! isset( wp_get_schedules()[ $schedule ] );
                if ( $missing ) {
                    add_filter( 'cron_schedules', $offer );
                }
                wp_schedule_event( $event->timestamp, $schedule, $new );
                if ( $missing ) {
                    remove_filter( 'cron_schedules', $offer );
                }
            }
            wp_clear_scheduled_hook( $old );
        }
    }

    /**
     * The reviews table is renamed when the old one exists and the new one
     * is absent. A new table that exists and is empty (an activation that
     * ran dbDelta before this version) is dropped first — only on a count
     * that answered exactly '0', never on a failed read; one with rows, or
     * one whose count failed, is kept beside the old one, nothing
     * overwritten (uninstall drops both).
     *
     * @return bool False when a rename failed, so the caller leaves the flag unset.
     */
    private static function migrate_table(): bool {
        global $wpdb;

        foreach ( self::LEGACY_NAMES['table'] as $old => $new ) {
            $old = $wpdb->prefix . $old;
            $new = $wpdb->prefix . $new;
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ) !== $old ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                continue;
            }
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $new ) ) ) === $new ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ( '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $new ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    continue;
                }
                $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            }
            if ( false === $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old, $new ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                return false;
            }
        }

        return true;
    }

    /**
     * Grant the default postquality_* capability map — per capability, only
     * while no role holding manage_options has it yet (a fresh install, or a
     * capability new in this version) — and remember which version of it ran.
     */
    private static function register_capabilities(): void {
        PostQuality_Features::register_capabilities();
        update_option( 'postquality_caps_version', PostQuality_Features::CAPS_VERSION );
    }

    /**
     * Create the postquality_reviews table using dbDelta.
     */
    private static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'postquality_reviews';
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

        update_option( 'postquality_db_version', POSTQUALITY_VERSION );
    }
}
