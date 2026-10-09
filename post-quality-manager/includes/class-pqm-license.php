<?php
/**
 * License provider (spec CAP-2) — the single swappable seam behind
 * PQM_Features::is_pro().
 *
 * Reads the Freemius SDK's license state (ADR 0013, story 44): the SDK is
 * initialised by pqm_fs() in the main plugin file, and its own screens
 * (license activation, Account) carry the key. Gate callers keep reading
 * PQM_Features::is_pro(), and the pqm_is_pro filter stays the dev override
 * on top. The stored `license_key` setting of the stub era is never read.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_License {

    /**
     * Whether this site may run Pro: a trial or a license whose features are
     * enabled. An expired or cancelled license blocks them (the Pro plan's
     * expiration behaviour, ADR 0013 D7). Read per call: the SDK holds the
     * state, so nothing is cached here.
     */
    public static function is_pro(): bool {
        return function_exists( 'pqm_fs' ) && pqm_fs()->can_use_premium_code();
    }

    /**
     * Whether this site's own license no longer unlocks Pro (expired or
     * cancelled). Not has_any_license(): that also counts the customer's
     * licenses on other sites. Stored overrides, schedules and role rights
     * stay as they are; the License section points to the Account page.
     */
    public static function has_lapsed(): bool {
        return function_exists( 'pqm_fs' )
            && is_object( pqm_fs()->_get_license() )
            && ! pqm_fs()->can_use_premium_code();
    }
}
