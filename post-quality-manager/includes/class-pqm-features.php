<?php
/**
 * Feature gate for agent commands (spec CAP-1 / CAP-3 / CAP-5).
 *
 * One registry declares each agent's tier (free/paid) and the plugin
 * capability it requires; one call — PostQuality_Features::can( $command ) — at
 * the trigger chokepoint decides whether the current user on this site may
 * run it. The five postquality_* capabilities and their default role map live here
 * too, so activation, the versioned upgrade and uninstall share one list.
 * Licensing lives in PostQuality_License; is_pro() only delegates to it.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PostQuality_Features {

    /**
     * Bump when capabilities() changes; PostQuality_Activator::maybe_upgrade()
     * then registers any capability new in it on the first init of an
     * upgraded install (register_capabilities() leaves registered ones alone).
     */
    const CAPS_VERSION = 1;

    /**
     * Plugin capabilities → default roles (spec access matrix, CAP-3).
     * The map is only the shipped default: a role editor can remap any
     * capability afterwards and every check follows the new mapping.
     */
    public static function capabilities(): array {
        return [
            'postquality_view_reviews'    => [ 'administrator', 'editor', 'author' ],
            'postquality_run_free_agents' => [ 'administrator', 'editor', 'author' ],
            'postquality_run_paid_agents' => [ 'administrator', 'editor' ],
            'postquality_manage_agents'   => [ 'administrator', 'editor' ],
            'postquality_manage_settings' => [ 'administrator' ],
        ];
    }

    /**
     * Grant the default map via WP_Role::add_cap(), per capability, only
     * while no role holding manage_options has it yet (a fresh install, or a
     * capability new in this version). Once registered, the site's roles are
     * the one store (Settings → Roles, story 42): activation and upgrade never
     * reset them. Ceiling: a role editor stripping a capability from every
     * manage_options role makes the next activation grant its defaults again.
     * Idempotent; roles missing on this site are skipped.
     */
    public static function register_capabilities(): void {
        $admin_roles = array_filter(
            wp_roles()->role_objects,
            static fn( $role ) => $role->has_cap( 'manage_options' )
        );
        foreach ( self::capabilities() as $cap => $role_names ) {
            foreach ( $admin_roles as $admin_role ) {
                if ( $admin_role->has_cap( $cap ) ) {
                    continue 2;
                }
            }
            foreach ( $role_names as $role_name ) {
                $role = get_role( $role_name );
                if ( $role instanceof WP_Role ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    /**
     * Strip every postquality_* capability from every role and every user's
     * direct grants (uninstall only — a remapped role must keep its grant
     * across deactivation). Unconditional: an explicit deny (cap => false)
     * fails has_cap() yet must go too, and remove_cap() is safe on a missing
     * key. Uninstall passes the capabilities of both prefixes (story 91).
     *
     * @param string[]|null $caps  The capabilities to strip; the current map when null.
     */
    public static function remove_capabilities( ?array $caps = null ): void {
        $caps = $caps ?? array_keys( self::capabilities() );

        foreach ( wp_roles()->role_objects as $role ) {
            foreach ( $caps as $cap ) {
                $role->remove_cap( $cap );
            }
        }

        // Grants made to a user directly (WP_User::add_cap, a role editor's
        // per-user grant) live in user meta. With the roles stripped,
        // capability__in matches only users whose own meta names a pqm cap.
        foreach ( get_users( [ 'capability__in' => $caps ] ) as $user ) {
            foreach ( $caps as $cap ) {
                $user->remove_cap( $cap );
            }
        }
    }

    /**
     * Command registry: tier, required capability and display label per
     * command — the one command whitelist and the one label source (REST
     * copy, and the React pages via window.postqualityCommandLabels). The REST
     * `command` enums are its keys, and a command missing here is refused
     * (fail closed). score and apply are free-tier entries on
     * postquality_run_free_agents, the 1:1 successor of the old edit_posts check.
     * The paid entries exist in the Pro build only (ADR 0014): the Free
     * build has nothing paid to gate, list or accept.
     */
    public static function registry(): array {
        $registry = [
            'review'    => [ 'tier' => 'free', 'capability' => 'postquality_run_free_agents', 'label' => __( 'Review', 'post-quality-manager' ) ],
            'takeaways' => [ 'tier' => 'free', 'capability' => 'postquality_run_free_agents', 'label' => __( 'Takeaways', 'post-quality-manager' ) ],
        ];
        if ( postquality_fs()->is__premium_only() ) {
            $registry += self::paid_registry__premium_only();
        }

        return $registry + [
            'score'     => [ 'tier' => 'free', 'capability' => 'postquality_run_free_agents', 'label' => __( 'Score', 'post-quality-manager' ) ],
            'apply'     => [ 'tier' => 'free', 'capability' => 'postquality_run_free_agents', 'label' => __( 'Apply', 'post-quality-manager' ) ],
        ];
    }

    /** The paid agents' registry entries, in the order the pages list them. */
    private static function paid_registry__premium_only(): array {
        return [
            'rewrite'   => [ 'tier' => 'paid', 'capability' => 'postquality_run_paid_agents', 'label' => __( 'Rewrite', 'post-quality-manager' ) ],
            'sources'   => [ 'tier' => 'paid', 'capability' => 'postquality_run_paid_agents', 'label' => __( 'Sources', 'post-quality-manager' ) ],
            'seo'       => [ 'tier' => 'paid', 'capability' => 'postquality_run_paid_agents', 'label' => __( 'SEO', 'post-quality-manager' ) ],
            'audit'     => [ 'tier' => 'paid', 'capability' => 'postquality_run_paid_agents', 'label' => __( 'Audit', 'post-quality-manager' ) ],
        ];
    }

    /**
     * Translated display label for $command; an unknown command passes
     * through raw so the copy is never blank.
     */
    public static function label( string $command ): string {
        return self::registry()[ $command ]['label'] ?? $command;
    }

    /**
     * Whether this site is on a paid plan. Delegates to the confined
     * license provider (PostQuality_License, spec CAP-2); the `postquality_is_pro`
     * filter wraps it so a dev override (e.g. a mu-plugin returning
     * true) always wins — same contract as the pre-licensing stub.
     */
    public static function is_pro(): bool {
        return (bool) apply_filters( 'postquality_is_pro', PostQuality_License::is_pro() );
    }

    /**
     * Plan half of the gate alone: may this site's plan run $command?
     * The single source for the plan decision — why_not() delegates here,
     * and site-level runs with no user (cron, per ADR 0003) call it
     * directly. An unknown command is refused (fail closed), mirroring
     * why_not().
     */
    public static function plan_allows( string $command ): bool {
        $entry = self::registry()[ $command ] ?? null;

        return $entry !== null && ( $entry['tier'] !== 'paid' || self::is_pro() );
    }

    /**
     * Why the current user cannot run $command right now:
     * 'role' (lacks the capability — hidden in the UI), 'plan' (paid tier
     * on a free site — visible but locked), or null when allowed.
     * Role is checked first: there is nothing to upsell to a user whose
     * role cannot run the agent at all. An unknown command is 'role' for
     * everyone (fail closed) — no role can run what the registry lacks.
     */
    public static function why_not( string $command ): ?string {
        $entry = self::registry()[ $command ] ?? null;

        if ( $entry === null || ! current_user_can( $entry['capability'] ) ) {
            return 'role';
        }

        if ( ! self::plan_allows( $command ) ) {
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

    /* ─── Author quota (spec CAP-4) ──────────────────────────────────── */

    /**
     * Per-user monthly usage lives in one self-resetting user-meta entry:
     * [ 'month' => 'YYYY-MM', 'count' => N ]. A stored month that differs
     * from the current one reads as zero and is overwritten on the next
     * increment — no cron, no usage table.
     */
    const USAGE_META_KEY = 'postquality_monthly_usage';

    /**
     * Quota applies to users who cannot run paid agents. Granting a role
     * (or user) postquality_run_paid_agents therefore lifts the quota too — no
     * extra capability, CAPS_VERSION unchanged.
     */
    public static function is_quota_subject(): bool {
        return ! current_user_can( 'postquality_run_paid_agents' );
    }

    /**
     * Quota snapshot for the current user, or null when no quota applies
     * (exempt user, or the Admin set the limit to 0 = unlimited).
     *
     * @return array{limit:int,used:int,remaining:int,resets:string}|null
     */
    public static function quota_state(): ?array {
        if ( ! self::is_quota_subject() ) {
            return null;
        }

        $limit = (int) ( postquality_get_settings()['author_quota'] ?? 0 );
        if ( $limit < 1 ) {
            return null;
        }

        $used = self::usage_this_month( get_current_user_id() );

        return [
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => max( 0, $limit - $used ),
            'resets'    => self::quota_reset_date(),
        ];
    }

    /**
     * Record one successful manual trigger for the current user. Called
     * only after the n8n webhook answered 2xx — a refusal or an
     * unreachable n8n never spends quota. No-op for exempt/unlimited.
     */
    public static function consume_quota(): void {
        $state = self::quota_state();
        if ( $state === null ) {
            return;
        }

        update_user_meta( get_current_user_id(), self::USAGE_META_KEY, [
            'month' => self::current_month(),
            'count' => $state['used'] + 1,
        ] );
    }

    /**
     * Usage count for the current calendar month; a stale entry from an
     * earlier month reads as zero (the self-reset).
     */
    private static function usage_this_month( int $user_id ): int {
        $usage = get_user_meta( $user_id, self::USAGE_META_KEY, true );

        if ( ! is_array( $usage ) || ( $usage['month'] ?? '' ) !== self::current_month() ) {
            return 0;
        }

        return max( 0, (int) ( $usage['count'] ?? 0 ) );
    }

    /**
     * Current calendar month in the site timezone, e.g. '2026-10'.
     */
    private static function current_month(): string {
        return wp_date( 'Y-m' );
    }

    /**
     * First day of the next month, formatted with the site's date format —
     * the "resets on" date shown in quota copy.
     */
    private static function quota_reset_date(): string {
        $first_of_next_month = ( new DateTimeImmutable( 'now', wp_timezone() ) )
            ->modify( 'first day of next month' )
            ->setTime( 0, 0 );

        return wp_date( get_option( 'date_format', 'F j, Y' ), $first_of_next_month->getTimestamp() );
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
