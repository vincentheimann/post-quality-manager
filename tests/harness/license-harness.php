<?php
/**
 * CLI harness for the license provider behind is_pro() — WP stubs, no WP
 * install, no Freemius SDK (tests/harness/fs-stub.php stands in for it).
 * Story 3 built the seam; story 44 fills it with the SDK's license state.
 * Exercises the story 44 plan's I/O & Edge-Case Matrix rows against
 * PostQuality_License::is_pro() / has_lapsed() and the PostQuality_Features::is_pro()
 * delegation: valid, absent and expired licenses, the postquality_is_pro filter
 * override, and a stub-era stored key that no longer unlocks anything.
 * Bug 93 (Rows 3b, 3c): a license the SDK's sync detached after it expired
 * or was cancelled stays lapsed through the recorded lapse; a license
 * Freemius unlinked for another reason, a renewal, a license the sync puts
 * back, a deactivation and other transitions do not.
 */

define('ABSPATH', __DIR__ . '/');

/* ─── Mutable test world ─────────────────────────────────────────────── */
$GLOBALS['tw'] = [
    'settings'   => [],     // merged over defaults in postquality_get_settings()
    'filter_pro' => null,   // null = filter not hooked (pass-through), bool = mu-plugin override
    'user_caps'  => [],     // capability => true (for why_not() checks)
];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function postquality_get_settings() {
    return array_merge(['license_key' => ''], $GLOBALS['tw']['settings']);
}
function apply_filters($tag, $value) {
    if ($tag === 'postquality_is_pro' && $GLOBALS['tw']['filter_pro'] !== null) {
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
    postquality_fs()->pro = $pro;
    postquality_fs()->has_license = $has_license;
}
$all_run_caps = ['postquality_run_free_agents' => true, 'postquality_run_paid_agents' => true];
$paid_commands = ['rewrite', 'sources', 'seo', 'audit'];

/* ─── Row 1: Valid license → pro ─────────────────────────────────────── */
echo "Row 1 — A valid license puts the site on pro\n";
reset_world(true, true, [], null, $all_run_caps);
check('PostQuality_License::is_pro() true', PostQuality_License::is_pro() === true);
check('has_lapsed() false', PostQuality_License::has_lapsed() === false);
check('PostQuality_Features::is_pro() delegates (filter pass-through)', PostQuality_Features::is_pro() === true);
check("plan_allows('seo') true", PostQuality_Features::plan_allows('seo') === true);
check('gated_commands() empty', PostQuality_Features::gated_commands() === []);
foreach ($paid_commands as $cmd) {
    check("why_not('$cmd') null for a capable user", PostQuality_Features::why_not($cmd) === null);
}

/* ─── Row 2: No license → free ───────────────────────────────────────── */
echo "Row 2 — No license (SDK skipped or registered without one): free\n";
reset_world(false, false, [], null, $all_run_caps);
check('PostQuality_License::is_pro() false', PostQuality_License::is_pro() === false);
check('has_lapsed() false (nothing to renew)', PostQuality_License::has_lapsed() === false);
check('PostQuality_Features::is_pro() false', PostQuality_Features::is_pro() === false);
check("plan_allows('seo') false", PostQuality_Features::plan_allows('seo') === false);
check('every paid command is plan-gated', PostQuality_Features::gated_commands() === $paid_commands);
check("why_not('seo') is 'plan'", PostQuality_Features::why_not('seo') === 'plan');
check('free commands untouched', PostQuality_Features::plan_allows('review') === true
    && PostQuality_Features::why_not('takeaways') === null);

/* ─── Row 3: Expired or cancelled license → free, lapsed ─────────────── */
echo "Row 3 — Expired or cancelled license: free, has_lapsed() true\n";
reset_world(false, true, [], null, $all_run_caps);
check('PostQuality_License::is_pro() false', PostQuality_License::is_pro() === false);
check('has_lapsed() true', PostQuality_License::has_lapsed() === true);
check("plan_allows('rewrite') false", PostQuality_Features::plan_allows('rewrite') === false);
check('every paid command is plan-gated again', PostQuality_Features::gated_commands() === $paid_commands);

/* Bug 93: the SDK's sync detaches an expired license (no license on the site afterwards), so the Pro build records the
 * lapse from the SDK's own hooks: after_license_deactivation (the release on expiry) notes it, after_license_change
 * records `cancelled`, or `downgraded` after that release, and ends the lapse on any other transition;
 * after_license_activation ends it. The registration on postquality_fs() is setup-harness.php's; here the callbacks
 * are called as WordPress calls them (accepted_args 1), in the SDK's order. */
const LAPSED = 'postquality_license_lapsed';
function sdk_fires(string $method, $arg = ''): void {
    if (method_exists('PostQuality_License', $method)) { PostQuality_License::{$method}($arg); } // absent on the baseline: nothing recorded
}
/* A new request: the note of a release lives for one request only. */
function new_request(): void {
    if (property_exists('PostQuality_License', 'license_deactivated')) {
        (new ReflectionProperty('PostQuality_License', 'license_deactivated'))->setValue(null, false);
    }
}
function recorded(): bool { return array_key_exists(LAPSED, $GLOBALS['tw']['options'] ?? []); }
/* The SDK's sync meets an expired license under "block features" (L21328–21331): it releases the license, then reports downgraded. */
function sdk_expires(): void {
    new_request();
    sdk_fires('note_license_deactivation__premium_only', (object) ['id' => 1]);
    sdk_fires('record_lapse__premium_only', 'downgraded');
}
echo "Row 3b — Expired or cancelled, then detached by the SDK's sync (bug 93): still lapsed\n";
check('the record\'s option name is ' . LAPSED, defined('PostQuality_License::LAPSED_OPTION') && PostQuality_License::LAPSED_OPTION === LAPSED);
foreach (['downgraded' => 'Expired, detached by the sync', 'cancelled' => 'Cancelled, detached'] as $transition => $label) {
    reset_world(false, true, [], null, $all_run_caps);
    $GLOBALS['tw']['writes'] = [];
    if ($transition === 'downgraded') {
        sdk_expires();
    } else {
        new_request();
        sdk_fires('record_lapse__premium_only', 'cancelled'); // the cancelled branch (L21266) releases nothing
    }
    postquality_fs()->has_license = false; // the sync's detach: the site's license_id is null afterwards
    check("$label: the record holds '$transition', not autoloaded",
        ($GLOBALS['tw']['options'][LAPSED] ?? null) === $transition && ($GLOBALS['tw']['autoload'][LAPSED] ?? null) === false);
    check("$label: no license on the site, has_lapsed() true", PostQuality_License::has_lapsed() === true);
    check("$label: still free, every paid command refused", PostQuality_License::is_pro() === false
        && PostQuality_Features::gated_commands() === $paid_commands && PostQuality_Features::plan_allows('rewrite') === false);
}
$writes = $GLOBALS['tw']['writes'];
PostQuality_License::has_lapsed();
check('has_lapsed() only reads: nothing is written on a page load', $GLOBALS['tw']['writes'] === $writes);

echo "Row 3c — Unlinked by Freemius, renewed, put back by the sync, deactivated, other transitions (bug 93)\n";
reset_world(false, false, [], null, $all_run_caps);
new_request();
sdk_fires('record_lapse__premium_only', 'downgraded'); // the unlink branch (L21303): no release before it
check('Unlinked by Freemius, not expired (downgraded with no release earlier in the request): no record, not lapsed',
    !recorded() && PostQuality_License::has_lapsed() === false);
sdk_expires();
$before = recorded();
new_request();
sdk_fires('record_lapse__premium_only', 'downgraded');
check('... and an earlier record goes: a lapse the unlink follows ends', $before && !recorded());
$kept = [];
foreach (['activated', 'upgraded', 'changed', 'extended', 'expired', 'trial_started', 'trial_expired'] as $transition) {
    reset_world(false, false, [], null, $all_run_caps);
    sdk_expires();
    $before = recorded();
    new_request();
    sdk_fires('record_lapse__premium_only', $transition);
    if (!$before || recorded()) { $kept[] = $transition; }
}
check('License put back by the sync (activated, upgraded, changed, extended) or any other transition: the record goes'
    . ($kept ? ' — not recorded first, or kept: ' . implode(', ', $kept) : ''), $kept === []);
reset_world(false, false, [], null, $all_run_caps);
sdk_expires();
$before = recorded();
new_request();
sdk_fires('record_lapse__premium_only', 'changed'); // the sync puts a license back: no activation hook fires
postquality_fs()->pro = true;
postquality_fs()->has_license = true;
new_request();
sdk_fires('note_license_deactivation__premium_only', (object) ['id' => 1]); // later, the customer deactivates it
postquality_fs()->pro = false;
postquality_fs()->has_license = false;
check('... a later deactivation by the customer (only after_license_deactivation, no license left): the Free copy, not lapsed',
    $before && !recorded() && PostQuality_License::has_lapsed() === false);
reset_world(false, false, [], null, $all_run_caps);
sdk_expires();
$before = recorded();
new_request();
sdk_fires('forget_lapse__premium_only');
check('Renewed: after_license_activation deletes the record', $before && !recorded());
postquality_fs()->pro = true;
postquality_fs()->has_license = true;
check('... Pro once the license unlocks it, not lapsed', PostQuality_License::is_pro() === true && PostQuality_License::has_lapsed() === false);
reset_world(true, true, [], null, $all_run_caps);
$GLOBALS['tw']['options'][LAPSED] = 'downgraded';
check('A record left beside a license that unlocks Pro: not lapsed (the Pro copy)', PostQuality_License::has_lapsed() === false);
reset_world(false, false, [], null, $all_run_caps);
new_request();
sdk_fires('note_license_deactivation__premium_only', (object) ['id' => 1]);
check('The customer deactivates the license (only after_license_deactivation fires): no record, not lapsed, the Free copy',
    !recorded() && PostQuality_License::has_lapsed() === false);
new_request();
check('Never licensed, or the customer\'s license sits on another site (no license here, no record): not lapsed',
    PostQuality_License::has_lapsed() === false);

/* ─── Row 4: Filter override wraps the provider ──────────────────────── */
echo "Row 4 — postquality_is_pro filter (dev override) always wins\n";
reset_world(false, false, [], true, $all_run_caps);
check('no license + filter true → pro (mu-plugin demo path preserved)', PostQuality_Features::is_pro() === true);
check("plan_allows('seo') true under the override", PostQuality_Features::plan_allows('seo') === true);
check('provider itself still reports free', PostQuality_License::is_pro() === false);
reset_world(true, true, [], false, $all_run_caps);
check('valid license + filter false → filter wins (filter wraps the provider)', PostQuality_Features::is_pro() === false);

/* ─── Row 5: Stub-era key is never read ──────────────────────────────── */
echo "Row 5 — A stored stub-era key no longer unlocks pro\n";
reset_world(false, false, ['license_key' => 'anything'], null, $all_run_caps);
check('license_key = anything, no SDK license → free', PostQuality_License::is_pro() === false);
check('PostQuality_Features::is_pro() false', PostQuality_Features::is_pro() === false);
check('not lapsed either (the key is not a license)', PostQuality_License::has_lapsed() === false);

harness_exit();
