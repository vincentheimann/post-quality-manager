<?php
/**
 * "View as" (story 34): an Administrator sees the plugin as an Editor,
 * Author or Contributor would.
 *
 * The chosen role is stored per user (user meta). A user_has_cap filter
 * narrows that user's five pqm_* capabilities to those the chosen role
 * holds on this site right now — it can only remove, never add — so every
 * existing gate renders and refuses as for that role, unchanged. WordPress
 * core capabilities are never touched. "Administrator" means a holder of
 * core manage_options, never a role-name check. A toolbar node offers the
 * switch and, while a view is on, names it and offers a one-click Exit.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_View_As {

    /** User meta holding the chosen role key; deleted on uninstall. */
    const META_KEY = 'pqm_view_as';

    /** The roles an Administrator may view as (owner decision: default roles only). */
    const ROLES = [ 'editor', 'author', 'contributor' ];

    /** admin-post action and nonce action of the switch handler. */
    const ACTION = 'pqm_view_as';

    /**
     * The view $user_id has on: one of ROLES, or '' for none (an unknown
     * stored value reads as no view).
     */
    public static function current( int $user_id ): string {
        $role = get_user_meta( $user_id, self::META_KEY, true );

        return in_array( $role, self::ROLES, true ) ? $role : '';
    }

    /**
     * user_has_cap filter: for an Administrator with a view on, each pqm_*
     * capability stays granted only if the user holds it AND the chosen
     * role holds it now. A role missing from the site holds nothing (fail
     * closed). Everything else passes through unchanged.
     */
    public static function filter_caps( $allcaps, $caps, $args, $user ) {
        if ( empty( $allcaps['manage_options'] ) ) {
            return $allcaps;
        }

        $view = self::current( (int) $user->ID );
        if ( $view === '' ) {
            return $allcaps;
        }

        $role = get_role( $view );
        foreach ( array_keys( PQM_Features::capabilities() ) as $cap ) {
            $allcaps[ $cap ] = ! empty( $allcaps[ $cap ] ) && $role instanceof WP_Role && $role->has_cap( $cap );
        }

        return $allcaps;
    }

    /**
     * Toolbar nodes, Administrators only. No view: "View as" with one child
     * per listed role. View on: "Viewing as %s" with the other roles as
     * children, plus a separate top-level Exit node.
     */
    public static function admin_bar( WP_Admin_Bar $bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $view       = self::current( get_current_user_id() );
        $role_names = wp_roles()->role_names;

        $title = __( 'View as', 'post-quality-manager' );
        if ( $view !== '' ) {
            /* translators: %s: role name, e.g. Author */
            $title = sprintf( __( 'Viewing as %s', 'post-quality-manager' ), self::role_label( $view ) );
        }
        // No href: core renders a div, so tabindex keeps the menu keyboard-reachable.
        $bar->add_node( [ 'id' => 'pqm-view-as', 'title' => esc_html( $title ), 'meta' => [ 'tabindex' => 0 ] ] );

        foreach ( self::ROLES as $role ) {
            if ( $role === $view || ! isset( $role_names[ $role ] ) ) {
                continue;
            }
            $bar->add_node( [
                'id'     => 'pqm-view-as-' . $role,
                'parent' => 'pqm-view-as',
                'title'  => esc_html( self::role_label( $role ) ),
                'href'   => self::switch_url( $role ),
            ] );
        }

        if ( $view !== '' ) {
            $bar->add_node( [
                'id'    => 'pqm-view-as-exit',
                'title' => esc_html__( 'Exit', 'post-quality-manager' ),
                'href'  => self::switch_url( '' ),
            ] );
        }
    }

    /**
     * admin-post handler: store the requested view, or delete it (Exit) when
     * the role is empty or not one of ROLES. Checks manage_options, never a
     * pqm_* capability, so Exit works from the Contributor view.
     */
    public static function handle_switch(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'Only administrators can switch the plugin view.', 'post-quality-manager' ),
                '',
                [ 'response' => 403 ]
            );
        }
        check_admin_referer( self::ACTION );

        $role    = sanitize_key( wp_unslash( $_GET['role'] ?? '' ) );
        $user_id = get_current_user_id();

        if ( ! in_array( $role, self::ROLES, true ) ) {
            delete_user_meta( $user_id, self::META_KEY );
            wp_safe_redirect( wp_get_referer() ?: admin_url() );
            exit;
        }

        update_user_meta( $user_id, self::META_KEY, $role );
        // The referring page may be one the view cannot open, and WordPress's
        // refusal screen has no toolbar: land where the view is allowed.
        wp_safe_redirect( current_user_can( 'pqm_view_reviews' )
            ? admin_url( 'admin.php?page=pqm-dashboard' )
            : admin_url() );
        exit;
    }

    /** Handler URL for $role ('' = Exit), nonce included. */
    private static function switch_url( string $role ): string {
        return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&role=' . $role ), self::ACTION );
    }

    /** Translated display name of $role; the raw key when the role is gone. */
    private static function role_label( string $role ): string {
        $role_names = wp_roles()->role_names;

        return isset( $role_names[ $role ] ) ? translate_user_role( $role_names[ $role ] ) : $role;
    }
}
