<?php
/**
 * CLI harness for story 6 (cron under the gate) — WP stubs, no WP install.
 * Exercises every row of the plan's I/O & Edge-Case Matrix against
 * PQM_Features::plan_allows() and PQM_Cron::run_scheduled_review().
 */

error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');
define('WEEK_IN_SECONDS', 604800);

/* ─── Mutable test world ─────────────────────────────────────────────── */
$GLOBALS['tw'] = [
    'settings'      => [],             // merged over defaults in pqm_get_settings()
    'is_pro'        => false,          // what the pqm_is_pro filter answers
    'user_caps'     => [],             // capability => true (for why_not() checks)
    'batch_posts'   => [['post_id' => 1], ['post_id' => 2], ['post_id' => 3]],
    'db_queries'    => 0,              // PQM_DB::get_posts_needing_review() calls
    'webhook_calls' => 0,              // wp_remote_post() calls
    'user_meta'     => [],             // user_id => [key => value]
];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function pqm_get_settings() {
    return array_merge([
        'n8n_webhook_url'   => 'https://n8n.example/webhook',
        'cron_command'      => 'review',
        'cron_batch_size'   => 5,
        'cron_min_score'    => 60,
        'cron_max_age_days' => 30,
    ], $GLOBALS['tw']['settings']);
}
function apply_filters($tag, $value) {
    if ($tag === 'pqm_is_pro') { return $GLOBALS['tw']['is_pro']; }
    return $value;
}
function current_user_can($cap) { return !empty($GLOBALS['tw']['user_caps'][$cap]); }
function get_user_meta($uid, $key, $single) { return $GLOBALS['tw']['user_meta'][$uid][$key] ?? ''; }
function update_user_meta($uid, $key, $value) { $GLOBALS['tw']['user_meta'][$uid][$key] = $value; return true; }
function absint($n) { return abs((int)$n); }
function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$k))); }
function get_role($r) { return null; }
function __($s, $d = null) { return $s; }
function get_post($id) {
    return (object)['ID' => $id, 'post_status' => 'publish', 'post_title' => 'Test Post'];
}
function pqm_n8n_payload($post, $command, $settings) { return ['command' => $command, 'post_id' => $post->ID]; }
function wp_json_encode($v) { return json_encode($v); }
function wp_remote_post($url, $args) {
    $GLOBALS['tw']['webhook_calls']++;
    return ['response' => ['code' => 200]];
}
class WP_Role {}

/* PQM_DB stub — counts queries so the gated skip can prove "before any DB query". */
class PQM_DB {
    public static function get_posts_needing_review($min_score, $max_age_days, $batch_size) {
        $GLOBALS['tw']['db_queries']++;
        return $GLOBALS['tw']['batch_posts'];
    }
}

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-license.php'; // is_pro() delegates to it (story 3)
require $base . '/includes/class-pqm-features.php';
require $base . '/includes/class-pqm-cron.php';

/* ─── Tiny assert runner ─────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}
function reset_world($settings = [], $is_pro = false) {
    $GLOBALS['tw']['settings'] = $settings;
    $GLOBALS['tw']['is_pro'] = $is_pro;
    $GLOBALS['tw']['db_queries'] = 0;
    $GLOBALS['tw']['webhook_calls'] = 0;
    $GLOBALS['tw']['user_meta'] = [
        42 => ['pqm_monthly_usage' => ['month' => '2026-10', 'count' => 5]],
        43 => ['pqm_monthly_usage' => ['month' => '2026-09', 'count' => 20]],
    ];
}

/* ─── Row 1: Free command on free site ───────────────────────────────── */
echo "Row 1 — Free command on free site\n";
reset_world(['cron_command' => 'review'], false);
PQM_Cron::run_scheduled_review();
check('one DB query', $GLOBALS['tw']['db_queries'] === 1);
check('webhook fired for each batch post', $GLOBALS['tw']['webhook_calls'] === 3);

/* ─── Row 2: Gated command on free site ──────────────────────────────── */
echo "Row 2 — Gated command on free site\n";
reset_world(['cron_command' => 'seo'], false);
PQM_Cron::run_scheduled_review();
check('batch skipped before the DB query', $GLOBALS['tw']['db_queries'] === 0);
check('zero webhook calls', $GLOBALS['tw']['webhook_calls'] === 0);
check('plan_allows(seo) is false on free', PQM_Features::plan_allows('seo') === false);

/* every paid-tier registry command is skipped on a free site */
foreach (['rewrite', 'sources', 'audit'] as $paid) {
    reset_world(['cron_command' => $paid], false);
    PQM_Cron::run_scheduled_review();
    check("$paid skipped too", $GLOBALS['tw']['db_queries'] === 0 && $GLOBALS['tw']['webhook_calls'] === 0);
}

/* ─── Row 3: Gated command on pro site ───────────────────────────────── */
echo "Row 3 — Gated command on pro site\n";
reset_world(['cron_command' => 'seo'], true);
PQM_Cron::run_scheduled_review();
check('one DB query', $GLOBALS['tw']['db_queries'] === 1);
check('webhook fired for each batch post', $GLOBALS['tw']['webhook_calls'] === 3);
check('plan_allows(seo) is true on pro', PQM_Features::plan_allows('seo') === true);

/* ─── Row 4: Non-registry command (free-tier fallback) ───────────────── */
echo "Row 4 — Non-registry command on free site\n";
reset_world(['cron_command' => 'score'], false);
PQM_Cron::run_scheduled_review();
check('runs (free-tier fallback, mirrors why_not)', $GLOBALS['tw']['db_queries'] === 1 && $GLOBALS['tw']['webhook_calls'] === 3);
check('plan_allows(score) is true on free', PQM_Features::plan_allows('score') === true);

/* ─── Row 5: Quota neutrality (CAP-4) ────────────────────────────────── */
echo "Row 5 — Quota neutrality\n";
reset_world(['cron_command' => 'review'], false);
$before = serialize($GLOBALS['tw']['user_meta']);
PQM_Cron::run_scheduled_review();
check('free batch leaves pqm_monthly_usage byte-identical', serialize($GLOBALS['tw']['user_meta']) === $before);
reset_world(['cron_command' => 'seo'], false);
$before = serialize($GLOBALS['tw']['user_meta']);
PQM_Cron::run_scheduled_review();
check('skipped batch leaves pqm_monthly_usage byte-identical', serialize($GLOBALS['tw']['user_meta']) === $before);
reset_world(['cron_command' => 'audit'], true);
$before = serialize($GLOBALS['tw']['user_meta']);
PQM_Cron::run_scheduled_review();
check('pro batch leaves pqm_monthly_usage byte-identical', serialize($GLOBALS['tw']['user_meta']) === $before);
$cron = file_get_contents($base . '/includes/class-pqm-cron.php');
check('cron source never touches quota helpers', strpos($cron, 'consume_quota') === false && strpos($cron, 'quota_state') === false && strpos($cron, 'pqm_monthly_usage') === false);

/* ─── Row 6: No webhook URL ──────────────────────────────────────────── */
echo "Row 6 — No webhook URL\n";
reset_world(['cron_command' => 'review', 'n8n_webhook_url' => ''], false);
PQM_Cron::run_scheduled_review();
check('early return: no DB query, no webhook call', $GLOBALS['tw']['db_queries'] === 0 && $GLOBALS['tw']['webhook_calls'] === 0);

/* ─── why_not() refactor is pure (hidden/locked behavior unchanged) ──── */
echo "Extra — why_not() unchanged after the plan_allows refactor\n";
$GLOBALS['tw']['user_caps'] = ['pqm_run_free_agents' => true, 'pqm_run_paid_agents' => true];
$GLOBALS['tw']['is_pro'] = false;
check("why_not(seo) is 'plan' (capable user, free site)", PQM_Features::why_not('seo') === 'plan');
check('why_not(review) is null', PQM_Features::why_not('review') === null);
check('why_not(score) is null (free fallback)', PQM_Features::why_not('score') === null);
$GLOBALS['tw']['is_pro'] = true;
check('why_not(seo) is null on pro', PQM_Features::why_not('seo') === null);
$GLOBALS['tw']['user_caps'] = [];
$GLOBALS['tw']['is_pro'] = false;
check("why_not(seo) is 'role' before 'plan'", PQM_Features::why_not('seo') === 'role');
check("why_not(review) is 'role' without the free cap", PQM_Features::why_not('review') === 'role');

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
