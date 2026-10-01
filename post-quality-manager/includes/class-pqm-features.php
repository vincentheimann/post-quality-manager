<?php
/**
 * Feature gate for agent commands (spec CAP-1 / CAP-5).
 *
 * One registry declares each agent's tier (free/paid) and the WP capability
 * it requires; one call — PQM_Features::can( $command ) — at the trigger
 * chokepoint decides whether the current user on this site may run it.
 * Designed so later stories never touch the callers: role mapping only
 * swaps capability values in registry(), licensing only replaces the body
 * of is_pro().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_Features {

    /**
     * Command registry: tier + required capability per agent.
     * Commands not listed here (score, apply, apply_*) keep the plain
     * edit_posts behavior they have today.
     */
    public static function registry(): array {
        return [
            'review'    => [ 'tier' => 'free', 'capability' => 'edit_posts' ],
            'takeaways' => [ 'tier' => 'free', 'capability' => 'edit_posts' ],
            'rewrite'   => [ 'tier' => 'paid', 'capability' => 'edit_posts' ],
            'sources'   => [ 'tier' => 'paid', 'capability' => 'edit_posts' ],
            'seo'       => [ 'tier' => 'paid', 'capability' => 'edit_posts' ],
            'audit'     => [ 'tier' => 'paid', 'capability' => 'edit_posts' ],
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
     * Can the current user run $command on this site?
     * Tier AND capability must pass; commands outside the registry fall
     * back to today's plain edit_posts check.
     */
    public static function can( string $command ): bool {
        $entry = self::registry()[ $command ] ?? null;

        if ( $entry === null ) {
            return current_user_can( 'edit_posts' );
        }

        if ( $entry['tier'] === 'paid' && ! self::is_pro() ) {
            return false;
        }

        return current_user_can( $entry['capability'] );
    }

    /**
     * Registry commands the current user/site cannot run right now —
     * shipped to the admin JS so paid buttons render visible-but-locked.
     */
    public static function gated_commands(): array {
        return array_values( array_filter(
            array_keys( self::registry() ),
            static fn( string $command ): bool => ! self::can( $command )
        ) );
    }
}
