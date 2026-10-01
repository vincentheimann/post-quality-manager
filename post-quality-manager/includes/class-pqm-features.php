<?php
/**
 * Feature gate for agent commands (spec CAP-1 / CAP-3 / CAP-5).
 *
 * One registry declares each agent's tier (free/paid) and the plugin
 * capability it requires; one call — PQM_Features::can( $command ) — at
 * the trigger chokepoint decides whether the current user on this site may
 * run it. The five pqm_* capabilities and their default role map live here
 * too, so activation, the versioned upgrade and uninstall share one list.
 * Licensing only replaces the body of is_pro().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_Features {

    /**
     * Bump when capabilities() changes; PQM_Activator::maybe_upgrade()
     * re-registers the caps on the first init of an upgraded install.
     */
    const CAPS_VERSION = 1;

    /**
     * Plugin capabilities → default roles (spec access matrix, CAP-3).
     * The map is only the shipped default: a role editor can remap any
     * capability afterwards and every check follows the new mapping.
     */
    public static function capabilities(): array {
        return [
            'pqm_view_reviews'    => [ 'administrator', 'editor', 'author' ],
            'pqm_run_free_agents' => [ 'administrator', 'editor', 'author' ],
            'pqm_run_paid_agents' => [ 'administrator', 'editor' ],
            'pqm_manage_agents'   => [ 'administrator', 'editor' ],
            'pqm_manage_settings' => [ 'administrator' ],
        ];
    }

    /**
     * Grant the default map via WP_Role::add_cap(). Idempotent; roles
     * missing on this site (e.g. removed by another plugin) are skipped.
     */
    public static function register_capabilities(): void {
        foreach ( self::capabilities() as $cap => $role_names ) {
            foreach ( $role_names as $role_name ) {
                $role = get_role( $role_name );
                if ( $role instanceof WP_Role ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    /**
     * Strip every pqm_* capability from every role (uninstall only — a
     * remapped role must keep its grant across deactivation). Unconditional:
     * an explicit deny (cap => false) fails has_cap() yet must go too, and
     * remove_cap() is safe on a missing key.
     */
    public static function remove_capabilities(): void {
        $caps = array_keys( self::capabilities() );

        foreach ( wp_roles()->role_objects as $role ) {
            foreach ( $caps as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }

    /**
     * Command registry: tier + required capability per agent.
     * Commands not listed here (score, apply, apply_*) fall back to
     * pqm_run_free_agents, the 1:1 successor of the old edit_posts check.
     */
    public static function registry(): array {
        return [
            'review'    => [ 'tier' => 'free', 'capability' => 'pqm_run_free_agents' ],
            'takeaways' => [ 'tier' => 'free', 'capability' => 'pqm_run_free_agents' ],
            'rewrite'   => [ 'tier' => 'paid', 'capability' => 'pqm_run_paid_agents' ],
            'sources'   => [ 'tier' => 'paid', 'capability' => 'pqm_run_paid_agents' ],
            'seo'       => [ 'tier' => 'paid', 'capability' => 'pqm_run_paid_agents' ],
            'audit'     => [ 'tier' => 'paid', 'capability' => 'pqm_run_paid_agents' ],
        ];
    }

    /**
     * Whether this site is on a paid plan. Stub until licensing exists:
     * filter `pqm_is_pro` to true (e.g. in a mu-plugin) to demo the
     * paid path end to end.
     */
    public static function is_pro(): bool {
        return (bool) apply_filters( 'pqm_is_pro', false );
    }

    /**
     * Why the current user cannot run $command right now:
     * 'role' (lacks the capability — hidden in the UI), 'plan' (paid tier
     * on a free site — visible but locked), or null when allowed.
     * Role is checked first: there is nothing to upsell to a user whose
     * role cannot run the agent at all.
     */
    public static function why_not( string $command ): ?string {
        $entry = self::registry()[ $command ] ?? [ 'tier' => 'free', 'capability' => 'pqm_run_free_agents' ];

        if ( ! current_user_can( $entry['capability'] ) ) {
            return 'role';
        }

        if ( $entry['tier'] === 'paid' && ! self::is_pro() ) {
            return 'plan';
        }

        return null;
    }

    /**
     * Can the current user run $command on this site?
     */
    public static function can( string $command ): bool {
        return self::why_not( $command ) === null;
    }

    /**
     * Registry commands locked by the plan only — shipped to the admin JS
     * so paid buttons render visible-but-locked with upgrade copy.
     */
    public static function gated_commands(): array {
        return self::commands_refused_for( 'plan' );
    }

    /**
     * Registry commands the current user's role cannot run — shipped to
     * the admin JS so their buttons are not rendered at all.
     */
    public static function hidden_commands(): array {
        return self::commands_refused_for( 'role' );
    }

    private static function commands_refused_for( string $reason ): array {
        return array_values( array_filter(
            array_keys( self::registry() ),
            static fn( string $command ): bool => self::why_not( $command ) === $reason
        ) );
    }
}
