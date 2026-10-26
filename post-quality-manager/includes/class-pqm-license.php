<?php
/**
 * License provider (spec CAP-2) — the single swappable seam behind
 * PostQuality_Features::is_pro().
 *
 * Reads the Freemius SDK's license state (ADR 0013, story 44): the SDK is
 * initialised by postquality_fs() in the main plugin file, and its own screens
 * (license activation, Account) carry the key. Gate callers keep reading
 * PostQuality_Features::is_pro(), and the postquality_is_pro filter stays the dev override
 * on top. The stored `license_key` setting of the stub era is never read.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PostQuality_License {

    /**
     * Whether this site may run Pro: a trial or a license whose features are
     * enabled. An expired or cancelled license blocks them (the Pro plan's
     * expiration behaviour, ADR 0013 D7). Read per call: the SDK holds the
     * state, so nothing is cached here.
     */
    public static function is_pro(): bool {
        return function_exists( 'postquality_fs' ) && postquality_fs()->can_use_premium_code();
    }

    /**
     * Whether this site's own license no longer unlocks Pro (expired or
     * cancelled). Not has_any_license(): that also counts the customer's
     * licenses on other sites. Stored overrides, schedules and role rights
     * stay as they are; the License section points to the Account page.
     */
    public static function has_lapsed(): bool {
        return function_exists( 'postquality_fs' )
            && is_object( postquality_fs()->_get_license() )
            && ! postquality_fs()->can_use_premium_code();
    }

    /**
     * Whether this site shares usage data now: it opted in and has not opted
     * out since. The one definition behind the deactivation feedback form and
     * the stored answer (bug 90, see memory/freemium-and-roles.md).
     */
    public static function shares_usage_data(): bool {
        if ( ! function_exists( 'postquality_fs' ) ) {
            return false;
        }
        $fs = postquality_fs();

        return $fs->is_registered() && $fs->is_tracking_allowed();
    }

    /**
     * At deletion, a site that does not share usage data now drops any stored
     * deactivation feedback answer, so none is sent; a site that shares keeps
     * it, as agreed. Only the SDK's public storage is touched, and only this
     * one key (bug 90, see memory/freemium-and-roles.md).
     */
    public static function forget_feedback_unless_sharing(): void {
        if ( ! function_exists( 'postquality_fs' ) || self::shares_usage_data() ) {
            return;
        }
        postquality_fs()->get_storage()->remove( 'uninstall_reason' );
    }
}
