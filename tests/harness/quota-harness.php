<?php
/**
 * CLI harness for story 4 (author quota) — WP stubs, no WP install.
 * Exercises every row of the plan's I/O & Edge-Case Matrix against
 * PQM_Features quota helpers and PQM_REST_API::trigger_review_callback().
 */

error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');

/* ─── Mutable test world ─────────────────────────────────────────────── */
$GLOBALS['tw'] = [
    'user_caps'      => [],            // capability => true
    'user_id'        => 42,
    'user_meta'      => [],            // user_id => [key => value]
    'settings'       => [],
    'now_month'      => '2026-10',
    'webhook_result' => 200,           // int status, or 'error' for WP_Error
    'webhook_calls'  => 0,
];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function current_user_can($cap) { return !empty($GLOBALS['tw']['user_caps'][$cap]); }
function get_current_user_id() { return $GLOBALS['tw']['user_id']; }
function get_user_meta($uid, $key, $single) { return $GLOBALS['tw']['user_meta'][$uid][$key] ?? ''; }
function update_user_meta($uid, $key, $value) { $GLOBALS['tw']['user_meta'][$uid][$key] = $value; return true; }
function pqm_get_settings() {
    return array_merge(['n8n_webhook_url' => 'https://n8n.example/webhook', 'author_quota' => 20], $GLOBALS['tw']['settings']);
}
function wp_date($format, $ts = null) {
    if ($format === 'Y-m' && $ts === null) { return $GLOBALS['tw']['now_month']; }
    return gmdate($format, $ts ?? time());
}
function wp_timezone() { return new DateTimeZone('Europe/Zurich'); }
function get_option($k, $d = false) { return $k === 'date_format' ? 'F j, Y' : $d; }
function apply_filters($tag, $value) { return $value; }
function get_role($r) { return null; }
function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$k))); }
function __($s, $d = null) { return $s; }
function get_post($id) {
    return (object)['ID' => $id, 'post_status' => 'publish', 'post_title' => 'Test Post'];
}
function pqm_n8n_payload($post, $command, $settings) { return ['command' => $command, 'post_id' => $post->ID]; }
function wp_json_encode($v) { return json_encode($v); }
function wp_remote_post($url, $args) {
    $GLOBALS['tw']['webhook_calls']++;
    if ($GLOBALS['tw']['webhook_result'] === 'error') { return new WP_Error('http_request_failed', 'cURL error 7'); }
    return ['response' => ['code' => $GLOBALS['tw']['webhook_result']]];
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function is_wp_error($t) { return $t instanceof WP_Error; }

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code = '', $message = '', $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Request {
    private $params = [];
    public function __construct($m = '', $r = '') {}
    public function set_param($k, $v) { $this->params[$k] = $v; }
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function get_header($k) { return null; }
}
class WP_REST_Response {
    private $data; private $status;
    public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
    public function get_data() { return $this->data; }
    public function header($k, $v) {}
}
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
class WP_Role {}

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-features.php';
require $base . '/includes/class-pqm-rest-api.php';

/* ─── Tiny assert runner ─────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}
function author_caps() { return ['pqm_view_reviews' => true, 'pqm_run_free_agents' => true]; }
function editor_caps() { return author_caps() + ['pqm_run_paid_agents' => true]; }
function reset_world($caps, $meta = null, $settings = []) {
    $GLOBALS['tw']['user_caps'] = $caps;
    $GLOBALS['tw']['user_meta'] = $meta === null ? [] : [42 => ['pqm_monthly_usage' => $meta]];
    $GLOBALS['tw']['settings'] = $settings;
    $GLOBALS['tw']['webhook_result'] = 200;
    $GLOBALS['tw']['webhook_calls'] = 0;
    $GLOBALS['tw']['now_month'] = '2026-10';
}
function trigger($command = 'review') {
    $req = new WP_REST_Request();
    $req->set_param('post_id', 7);
    $req->set_param('command', $command);
    return PQM_REST_API::trigger_review_callback($req);
}
function stored_count() { return $GLOBALS['tw']['user_meta'][42]['pqm_monthly_usage']['count'] ?? null; }

/* ─── Row 1: Author under quota (5 of 20 used) ───────────────────────── */
echo "Row 1 — Author under quota\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 5]);
$r = trigger('review');
check('webhook fired once', $GLOBALS['tw']['webhook_calls'] === 1);
check('response is success', !is_wp_error($r) && $r->get_data()['success'] === true);
check('count incremented to 6', stored_count() === 6);
check('quota_remaining is 14', $r->get_data()['quota_remaining'] === 14);

/* ─── Row 2: Author at quota (20 of 20 used) ─────────────────────────── */
echo "Row 2 — Author at quota\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 20]);
$r = trigger('review');
check('refused with pqm_quota_exceeded', is_wp_error($r) && $r->get_error_code() === 'pqm_quota_exceeded');
check('HTTP status 429', is_wp_error($r) && ($r->get_error_data()['status'] ?? 0) === 429);
check('no webhook call', $GLOBALS['tw']['webhook_calls'] === 0);
check('count unchanged', stored_count() === 20);
$msg = is_wp_error($r) ? $r->get_error_message() : '';
$GLOBALS['tw']['user_meta'][42]['pqm_monthly_usage'] = ['month' => '2026-10', 'count' => 0];
$resets = PQM_Features::quota_state()['resets'];
check('copy names count and reset date', strpos($msg, '(20 of 20') !== false && strpos($msg, $resets) !== false);

/* ─── Row 3: Exempt user (holds pqm_run_paid_agents) ─────────────────── */
echo "Row 3 — Exempt user\n";
reset_world(editor_caps(), ['month' => '2026-10', 'count' => 999]);
check('quota_state() is null', PQM_Features::quota_state() === null);
$r = trigger('review');
check('trigger succeeds', !is_wp_error($r) && $r->get_data()['success'] === true);
check('no increment', stored_count() === 999);
check('quota_remaining is null', $r->get_data()['quota_remaining'] === null);
PQM_Features::consume_quota();
check('consume_quota() is a no-op', stored_count() === 999);

/* ─── Row 4: n8n unreachable ─────────────────────────────────────────── */
echo "Row 4 — n8n unreachable\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 5]);
$GLOBALS['tw']['webhook_result'] = 'error';
$r = trigger('review');
check('existing pqm_n8n_error kept', is_wp_error($r) && $r->get_error_code() === 'pqm_n8n_error');
check('HTTP status 502', is_wp_error($r) && ($r->get_error_data()['status'] ?? 0) === 502);
check('count unchanged', stored_count() === 5);

/* non-2xx n8n answer also never spends quota */
reset_world(author_caps(), ['month' => '2026-10', 'count' => 5]);
$GLOBALS['tw']['webhook_result'] = 500;
$r = trigger('review');
check('n8n 500: success=false, count unchanged', !is_wp_error($r) && $r->get_data()['success'] === false && stored_count() === 5);
check('n8n 500: quota_remaining still 15', $r->get_data()['quota_remaining'] === 15);

/* ─── Row 5: Month rollover ──────────────────────────────────────────── */
echo "Row 5 — Month rollover\n";
reset_world(author_caps(), ['month' => '2026-09', 'count' => 20]);
$s = PQM_Features::quota_state();
check('stale month reads as 0 used', $s !== null && $s['used'] === 0 && $s['remaining'] === 20);
$r = trigger('review');
check('trigger allowed after rollover', !is_wp_error($r));
$meta = $GLOBALS['tw']['user_meta'][42]['pqm_monthly_usage'];
check('old value overwritten on increment', $meta['month'] === '2026-10' && $meta['count'] === 1);

/* ─── Row 6: Cron stays outside the chokepoint (static check) ────────── */
echo "Row 6 — Cron batch untouched\n";
$cron = file_get_contents($base . '/includes/class-pqm-cron.php');
check('cron never calls trigger_review_callback', strpos($cron, 'trigger_review_callback') === false);
check('cron never touches quota helpers', strpos($cron, 'consume_quota') === false && strpos($cron, 'quota_state') === false && strpos($cron, 'pqm_monthly_usage') === false);

/* ─── Row 7: Quota raised mid-month ──────────────────────────────────── */
echo "Row 7 — Quota raised mid-month\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 20], ['author_quota' => 50]);
$s = PQM_Features::quota_state();
check('existing count kept, 30 more available', $s['used'] === 20 && $s['remaining'] === 30);
$r = trigger('review');
check('trigger allowed again', !is_wp_error($r) && stored_count() === 21);

/* ─── Decision: 0 = unlimited escape hatch ───────────────────────────── */
echo "Extra — quota 0 means unlimited\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 500], ['author_quota' => 0]);
check('quota_state() null at limit 0', PQM_Features::quota_state() === null);
$r = trigger('review');
check('trigger allowed, no increment', !is_wp_error($r) && stored_count() === 500);

/* ─── Decision: gate refusals keep priority over quota ───────────────── */
echo "Extra — feature gate fires before quota\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 20]);
$r = trigger('rewrite'); // paid agent, author lacks pqm_run_paid_agents AND is at quota
check('role refusal wins over quota', is_wp_error($r) && $r->get_error_code() === 'pqm_feature_gated');

/* ─── Decision: non-registry commands consume quota too ──────────────── */
echo "Extra — non-registry command (score) spends quota\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 5]);
$r = trigger('score');
check('score consumed one action', !is_wp_error($r) && stored_count() === 6);
reset_world(author_caps(), ['month' => '2026-10', 'count' => 20]);
$r = trigger('score');
check('score refused at the limit', is_wp_error($r) && $r->get_error_code() === 'pqm_quota_exceeded');

/* ─── Reset-date formatting sanity ───────────────────────────────────── */
echo "Extra — reset date is first of next month\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 0]);
$s = PQM_Features::quota_state();
check('resets looks like a real date (non-empty)', is_string($s['resets']) && $s['resets'] !== '');
echo "  info resets = {$s['resets']}\n";

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
