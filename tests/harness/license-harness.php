<?php
/**
 * CLI harness for story 3 (license provider behind is_pro()) — WP stubs,
 * no WP install. Exercises the plan's I/O & Edge-Case Matrix rows against
 * PQM_License::is_pro() and the PQM_Features::is_pro() delegation:
 * key present/absent/whitespace, the pqm_is_pro filter override, and the
 * downstream gate (plan_allows / gated_commands) flipping with the key.
 */

error_reporting(E_ALL);

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
function __($s, $d = null) { return $s; }
class WP_Role {}

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-license.php';
require $base . '/includes/class-pqm-features.php';

/* ─── Tiny assert runner ─────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}
function reset_world($settings = [], $filter_pro = null, $caps = []) {
    $GLOBALS['tw'] = ['settings' => $settings, 'filter_pro' => $filter_pro, 'user_caps' => $caps];
}
$all_run_caps = ['pqm_run_free_agents' => true, 'pqm_run_paid_agents' => true];
$paid_commands = ['rewrite', 'sources', 'seo', 'audit'];

/* ─── Row 1: Key entered → pro ───────────────────────────────────────── */
echo "Row 1 — Non-empty key flips the site to pro\n";
reset_world(['license_key' => 'PQM-ANY-KEY'], null, $all_run_caps);
check('PQM_License::is_pro() true', PQM_License::is_pro() === true);
check('PQM_Features::is_pro() delegates (filter pass-through)', PQM_Features::is_pro() === true);
check("plan_allows('seo') true", PQM_Features::plan_allows('seo') === true);
check('gated_commands() empty', PQM_Features::gated_commands() === []);
foreach ($paid_commands as $cmd) {
    check("why_not('$cmd') null for a capable user", PQM_Features::why_not($cmd) === null);
}

/* ─── Row 2: Key removed → reverts to free ───────────────────────────── */
echo "Row 2 — Empty key: site is (back to) free\n";
reset_world(['license_key' => ''], null, $all_run_caps);
check('PQM_License::is_pro() false', PQM_License::is_pro() === false);
check('PQM_Features::is_pro() false', PQM_Features::is_pro() === false);
check("plan_allows('seo') false", PQM_Features::plan_allows('seo') === false);
check('every paid command is plan-gated again', PQM_Features::gated_commands() === $paid_commands);
check("why_not('seo') is 'plan'", PQM_Features::why_not('seo') === 'plan');
check('free commands untouched', PQM_Features::plan_allows('review') === true
    && PQM_Features::why_not('takeaways') === null);

/* missing settings key (pre-upgrade option) reads as free, no notice */
reset_world([], null, $all_run_caps);
check('absent license_key reads as free', PQM_License::is_pro() === false);

/* ─── Row 3: Whitespace-only key stays free ──────────────────────────── */
echo "Row 3 — Whitespace-only key trims to empty\n";
reset_world(['license_key' => '   '], null, $all_run_caps);
check('PQM_License::is_pro() false on whitespace', PQM_License::is_pro() === false);
check("plan_allows('rewrite') false", PQM_Features::plan_allows('rewrite') === false);

/* ─── Row 4: Filter override wraps the provider ──────────────────────── */
echo "Row 4 — pqm_is_pro filter (dev override) always wins\n";
reset_world(['license_key' => ''], true, $all_run_caps);
check('no key + filter true → pro (mu-plugin demo path preserved)', PQM_Features::is_pro() === true);
check("plan_allows('seo') true under the override", PQM_Features::plan_allows('seo') === true);
check('provider itself still reports free', PQM_License::is_pro() === false);
reset_world(['license_key' => 'PQM-ANY-KEY'], false, $all_run_caps);
check('key + filter false → filter wins (filter wraps the provider)', PQM_Features::is_pro() === false);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
