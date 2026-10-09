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
    'db_command'    => null,           // the $command it last received (ticket 3)
    'sent_commands' => [],             // the command of each webhook payload
    'webhook_calls' => 0,              // wp_remote_post() calls
    'webhook_headers' => [],           // the headers of each wp_remote_post() (story 35)
    'user_meta'     => [],             // user_id => [key => value]
];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function pqm_get_settings() {
    return array_merge([
        'n8n_webhook_url'   => 'https://n8n.example/webhook',
        'webhook_token'     => 'tok-35',
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
function pqm_n8n_payload($post, $command, $settings) { $GLOBALS['tw']['sent_commands'][] = $command; return ['command' => $command, 'post_id' => $post->ID]; }
function wp_json_encode($v) { return json_encode($v); }
/* Same shape as the real helper (unit-tested in rest-reviews-harness.php, which loads the bootstrap). */
function pqm_n8n_headers($settings) { return ['Content-Type' => 'application/json', 'X-PQM-Token' => (string) ($settings['webhook_token'] ?? '')]; }
function wp_remote_post($url, $args) {
    $GLOBALS['tw']['webhook_calls']++;
    $GLOBALS['tw']['webhook_headers'][] = $args['headers'] ?? null;
    return ['response' => ['code' => 200]];
}
class WP_Role {}
/* WP-Cron stubs — a hook => timestamp map (ticket 13: schedule() clears the event). */
$GLOBALS['tw']['events'] = [];
function wp_next_scheduled($hook) { return $GLOBALS['tw']['events'][$hook] ?? false; }
function wp_unschedule_event($ts, $hook) { unset($GLOBALS['tw']['events'][$hook]); return true; }
function wp_schedule_event($ts, $recurrence, $hook) { $GLOBALS['tw']['events'][$hook] = $ts; return true; }

/* PQM_DB stub — counts queries so the gated skip can prove "before any DB query";
   same signature as the real one, records the command it selects by (ticket 3). */
class PQM_DB {
    public static function get_posts_needing_review(int $min_score = 60, int $max_age_days = 30, int $limit = 10, string $command = 'review'): array {
        $GLOBALS['tw']['db_queries']++;
        $GLOBALS['tw']['db_command'] = $command;
        return $GLOBALS['tw']['batch_posts'];
    }
}

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require __DIR__ . '/fs-stub.php'; // the Freemius SDK stand-in (story 44)
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
    $GLOBALS['tw']['webhook_headers'] = [];
    $GLOBALS['tw']['db_command'] = null;
    $GLOBALS['tw']['sent_commands'] = [];
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
check('every batch call carries X-PQM-Token = the webhook token (story 35)', $GLOBALS['tw']['webhook_headers']
    === array_fill(0, 3, ['Content-Type' => 'application/json', 'X-PQM-Token' => 'tok-35']));

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

/* ─── Row 4: score, a free-tier registry entry since story 14 ────────── */
echo "Row 4 — score (free-tier registry entry) on free site\n";
reset_world(['cron_command' => 'score'], false);
PQM_Cron::run_scheduled_review();
check('runs (free tier, mirrors why_not)', $GLOBALS['tw']['db_queries'] === 1 && $GLOBALS['tw']['webhook_calls'] === 3);
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

/* ─── Row 7 (ticket 13): switching cron off clears the event ─────────── */
echo "Row 7 — schedule() with cron_enabled false clears the event
";
reset_world(['cron_enabled' => true, 'cron_frequency' => 'daily'], false);
$GLOBALS['tw']['events'] = [];
PQM_Cron::schedule();
check('fixture: cron on schedules pqm_cron_review_posts', wp_next_scheduled('pqm_cron_review_posts') !== false);
$GLOBALS['tw']['settings']['cron_enabled'] = false;
PQM_Cron::schedule();
check('cron off: pqm_cron_review_posts cleared, nothing scheduled', $GLOBALS['tw']['events'] === []);

/* ─── Row 8 (ticket 3): the batch selects by the scheduled command ───── */
echo "Row 8 — selection per scheduled command\n";
foreach (['audit', 'seo', 'review'] as $cmd) {
    reset_world(['cron_command' => $cmd], true);
    PQM_Cron::run_scheduled_review();
    check("$cmd: get_posts_needing_review() receives '$cmd'", $GLOBALS['tw']['db_command'] === $cmd);
    check("$cmd: each batch post sent as '$cmd'", $GLOBALS['tw']['sent_commands'] === [$cmd, $cmd, $cmd]);
}
reset_world(['cron_command' => null], false);
PQM_Cron::run_scheduled_review();
check("cron_command missing: selects and sends 'review'", $GLOBALS['tw']['db_command'] === 'review'
    && $GLOBALS['tw']['sent_commands'] === ['review', 'review', 'review']);
reset_world(['cron_command' => 'audit'], false);
PQM_Cron::run_scheduled_review();
check('audit on a lapsed (free) site: skipped before the query', $GLOBALS['tw']['db_command'] === null
    && $GLOBALS['tw']['sent_commands'] === []);

/* ─── why_not() refactor is pure (hidden/locked behavior unchanged) ──── */
echo "Extra — why_not() unchanged after the plan_allows refactor\n";
$GLOBALS['tw']['user_caps'] = ['pqm_run_free_agents' => true, 'pqm_run_paid_agents' => true];
$GLOBALS['tw']['is_pro'] = false;
check("why_not(seo) is 'plan' (capable user, free site)", PQM_Features::why_not('seo') === 'plan');
check('why_not(review) is null', PQM_Features::why_not('review') === null);
check('why_not(score) is null (free-tier entry)', PQM_Features::why_not('score') === null);
$GLOBALS['tw']['is_pro'] = true;
check('why_not(seo) is null on pro', PQM_Features::why_not('seo') === null);
$GLOBALS['tw']['user_caps'] = [];
$GLOBALS['tw']['is_pro'] = false;
check("why_not(seo) is 'role' before 'plan'", PQM_Features::why_not('seo') === 'role');
check("why_not(review) is 'role' without the free cap", PQM_Features::why_not('review') === 'role');

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
