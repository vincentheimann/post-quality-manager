<?php
/**
 * CLI harness for the license provider behind is_pro() — WP stubs, no WP
 * install, no Freemius SDK (tests/harness/fs-stub.php stands in for it).
 * Story 3 built the seam; story 44 fills it with the SDK's license state.
 * Exercises the story 44 plan's I/O & Edge-Case Matrix rows against
 * PQM_License::is_pro() / has_lapsed() and the PQM_Features::is_pro()
 * delegation: valid, absent and expired licenses, the pqm_is_pro filter
 * override, and a stub-era stored key that no longer unlocks anything.
 */

define('ABSPATH', __DIR__ . '/');

/* ─── Mutable test world ─────────────────────────────────────────────── */
$GLOBALS['tw'] = [
    'settings'   => [],     // merged over defaults in pqm_get_settings()
    'filter_pro' => null,   // null = filter not hooked (pass-through), bool = mu-plugin override
    'user_caps'  => [],     // capability => true (for why_not() checks)
];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function pqm_get_settings() {
    return array_merge(['license_key' => ''], $GLOBALS['tw']['settings']);
}
function apply_filters($tag, $value) {
    if ($tag === 'pqm_is_pro' && $GLOBALS['tw']['filter_pro'] !== null) {
        return $GLOBALS['tw']['filter_pro'];
    }
    return $value;
}
function current_user_can($cap) { return !empty($GLOBALS['tw']['user_caps'][$cap]); }
function get_role($r) { return null; }

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-license.php';
require $base . '/includes/class-pqm-features.php';

/* $pro: SDK can_use_premium_code(); $has_license: SDK has_any_license(). */
function reset_world($pro = false, $has_license = false, $settings = [], $filter_pro = null, $caps = []) {
    $GLOBALS['tw'] = ['settings' => $settings, 'filter_pro' => $filter_pro, 'user_caps' => $caps];
    pqm_fs()->pro = $pro;
    pqm_fs()->has_license = $has_license;
}
$all_run_caps = ['pqm_run_free_agents' => true, 'pqm_run_paid_agents' => true];
$paid_commands = ['rewrite', 'sources', 'seo', 'audit'];

/* ─── Row 1: Valid license → pro ─────────────────────────────────────── */
echo "Row 1 — A valid license puts the site on pro\n";
reset_world(true, true, [], null, $all_run_caps);
check('PQM_License::is_pro() true', PQM_License::is_pro() === true);
check('has_lapsed() false', PQM_License::has_lapsed() === false);
check('PQM_Features::is_pro() delegates (filter pass-through)', PQM_Features::is_pro() === true);
check("plan_allows('seo') true", PQM_Features::plan_allows('seo') === true);
check('gated_commands() empty', PQM_Features::gated_commands() === []);
foreach ($paid_commands as $cmd) {
    check("why_not('$cmd') null for a capable user", PQM_Features::why_not($cmd) === null);
}

/* ─── Row 2: No license → free ───────────────────────────────────────── */
echo "Row 2 — No license (SDK skipped or registered without one): free\n";
reset_world(false, false, [], null, $all_run_caps);
check('PQM_License::is_pro() false', PQM_License::is_pro() === false);
check('has_lapsed() false (nothing to renew)', PQM_License::has_lapsed() === false);
check('PQM_Features::is_pro() false', PQM_Features::is_pro() === false);
check("plan_allows('seo') false", PQM_Features::plan_allows('seo') === false);
check('every paid command is plan-gated', PQM_Features::gated_commands() === $paid_commands);
check("why_not('seo') is 'plan'", PQM_Features::why_not('seo') === 'plan');
check('free commands untouched', PQM_Features::plan_allows('review') === true
    && PQM_Features::why_not('takeaways') === null);

/* ─── Row 3: Expired or cancelled license → free, lapsed ─────────────── */
echo "Row 3 — Expired or cancelled license: free, has_lapsed() true\n";
reset_world(false, true, [], null, $all_run_caps);
check('PQM_License::is_pro() false', PQM_License::is_pro() === false);
check('has_lapsed() true', PQM_License::has_lapsed() === true);
check("plan_allows('rewrite') false", PQM_Features::plan_allows('rewrite') === false);
check('every paid command is plan-gated again', PQM_Features::gated_commands() === $paid_commands);

/* ─── Row 4: Filter override wraps the provider ──────────────────────── */
echo "Row 4 — pqm_is_pro filter (dev override) always wins\n";
reset_world(false, false, [], true, $all_run_caps);
check('no license + filter true → pro (mu-plugin demo path preserved)', PQM_Features::is_pro() === true);
check("plan_allows('seo') true under the override", PQM_Features::plan_allows('seo') === true);
check('provider itself still reports free', PQM_License::is_pro() === false);
reset_world(true, true, [], false, $all_run_caps);
check('valid license + filter false → filter wins (filter wraps the provider)', PQM_Features::is_pro() === false);

/* ─── Row 5: Stub-era key is never read ──────────────────────────────── */
echo "Row 5 — A stored stub-era key no longer unlocks pro\n";
reset_world(false, false, ['license_key' => 'anything'], null, $all_run_caps);
check('license_key = anything, no SDK license → free', PQM_License::is_pro() === false);
check('PQM_Features::is_pro() false', PQM_Features::is_pro() === false);
check('not lapsed either (the key is not a license)', PQM_License::has_lapsed() === false);

harness_exit();
