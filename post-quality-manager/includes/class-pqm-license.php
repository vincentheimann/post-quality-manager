<?php
/**
 * License provider (spec CAP-2) — the single swappable seam behind
 * PQM_Features::is_pro().
 *
 * Stub era (owner decision 2026-10-02): any non-empty license key flips
 * the site to pro — there is nothing to protect until a paid product
 * exists. The real Freemius integration replaces the internals of this
 * class only; gate callers keep reading PQM_Features::is_pro(), and the
 * pqm_is_pro filter stays the dev override on top.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_License {

    /**
     * Whether the stored license key puts this site on the pro plan.
     * Read per call, no caching: pqm_get_settings() is an option read
     * WordPress already caches per request, and a stale pro flag across
     * a save would contradict "save is the commit point".
     */
    public static function is_pro(): bool {
        return trim( (string) ( pqm_get_settings()['license_key'] ?? '' ) ) !== '';
    }
}
