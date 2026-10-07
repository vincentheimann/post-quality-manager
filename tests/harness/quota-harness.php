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
    'webhook_args'   => null,          // the $args of the last wp_remote_post() (story 35)
];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function current_user_can($cap) { return !empty($GLOBALS['tw']['user_caps'][$cap]); }
function get_current_user_id() { return $GLOBALS['tw']['user_id']; }
function get_user_meta($uid, $key, $single) { return $GLOBALS['tw']['user_meta'][$uid][$key] ?? ''; }
function update_user_meta($uid, $key, $value) { $GLOBALS['tw']['user_meta'][$uid][$key] = $value; return true; }
function pqm_get_settings() {
    return array_merge(['n8n_webhook_url' => 'https://n8n.example/webhook', 'webhook_token' => 'tok-35', 'author_quota' => 20], $GLOBALS['tw']['settings']);
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
/* Same shape as the real helper (unit-tested in rest-reviews-harness.php, which loads the bootstrap). */
function pqm_n8n_headers($settings) { return ['Content-Type' => 'application/json', 'X-PQM-Token' => (string) ($settings['webhook_token'] ?? '')]; }
function wp_remote_post($url, $args) {
    $GLOBALS['tw']['webhook_calls']++;
    $GLOBALS['tw']['webhook_args'] = $args;
    if ($GLOBALS['tw']['webhook_result'] === 'error') { return new WP_Error('http_request_failed', 'cURL error 7'); }
    return ['response' => ['code' => $GLOBALS['tw']['webhook_result']]];
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function is_wp_error($t) { return $t instanceof WP_Error; }
/* Processing-row stubs (story 5.4): PQM_DB::save_review() runs inside the trigger callback. */
function current_time($type, $gmt = 0) { return '2026-10-05 12:00:00'; }
function sanitize_text_field($s) { return (string) $s; }
function absint($n) { return abs((int) $n); }

class PQM_Stub_WPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $inserts = []; // every insert(): ['table' => ..., 'data' => [...]]
    public function insert($table, $data, $format = null) {
        $this->inserts[] = ['table' => $table, 'data' => $data];
        $this->insert_id = count($this->inserts);
        return 1;
    }
}
$wpdb = new PQM_Stub_WPDB();

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
require $base . '/includes/class-pqm-license.php'; // is_pro() delegates to it (story 3)
require $base . '/includes/class-pqm-features.php';
require $base . '/includes/class-pqm-db.php';       // processing placeholder (story 5.4)
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
    $GLOBALS['tw']['webhook_args'] = null;
    $GLOBALS['tw']['now_month'] = '2026-10';
    $GLOBALS['wpdb']->inserts = [];
}
/** The processing rows written during the current world, as [post_id, command, status] triples. */
function processing_rows() {
    return array_map(
        fn($i) => [$i['data']['post_id'], $i['data']['command'], $i['data']['status']],
        $GLOBALS['wpdb']->inserts
    );
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
check('the call carries X-PQM-Token = the webhook token, plus the JSON content type (story 35)',
    ($GLOBALS['tw']['webhook_args']['headers'] ?? null) === ['Content-Type' => 'application/json', 'X-PQM-Token' => 'tok-35']);
check('response is success', !is_wp_error($r) && $r->get_data()['success'] === true);
check('count incremented to 6', stored_count() === 6);
check('quota_remaining is 14', $r->get_data()['quota_remaining'] === 14);
check('one processing row for post 7 / review (story 5.4)', processing_rows() === [[7, 'review', 'processing']]);
check('processing row carries score 0 and a JSON summary',
    $GLOBALS['wpdb']->inserts[0]['data']['overall_score'] === 0
    && $GLOBALS['wpdb']->inserts[0]['table'] === 'wp_pqm_reviews'
    && json_decode($GLOBALS['wpdb']->inserts[0]['data']['result_data'], true) === ['summary' => 'Running asynchronously in n8n...']);
check('response keys unchanged: {success,message,n8n_status,quota_remaining}',
    array_keys($r->get_data()) === ['success', 'message', 'n8n_status', 'quota_remaining']);

/* ─── Row 2: Author at quota (20 of 20 used) ─────────────────────────── */
echo "Row 2 — Author at quota\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 20]);
$r = trigger('review');
check('refused with pqm_quota_exceeded', is_wp_error($r) && $r->get_error_code() === 'pqm_quota_exceeded');
check('HTTP status 429', is_wp_error($r) && ($r->get_error_data()['status'] ?? 0) === 429);
check('no webhook call', $GLOBALS['tw']['webhook_calls'] === 0);
check('count unchanged', stored_count() === 20);
check('no processing row on a quota refusal', processing_rows() === []);
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
check('no processing row when n8n is unreachable', processing_rows() === []);

/* non-2xx n8n answer also never spends quota */
reset_world(author_caps(), ['month' => '2026-10', 'count' => 5]);
$GLOBALS['tw']['webhook_result'] = 500;
$r = trigger('review');
check('n8n 500: success=false, count unchanged', !is_wp_error($r) && $r->get_data()['success'] === false && stored_count() === 5);
check('n8n 500: quota_remaining still 15', $r->get_data()['quota_remaining'] === 15);
check('n8n 500: no processing row', processing_rows() === []);

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
check('no processing row on a gate refusal', processing_rows() === []);

/* ─── Story 5.4: the placeholder is written for the command that ran, once per trigger ── */
echo "Extra — processing row follows the command, once per 2xx\n";
reset_world(editor_caps(), ['month' => '2026-10', 'count' => 0]);
trigger('takeaways');
trigger('review');
check('two triggers → two rows, each for its own command',
    processing_rows() === [[7, 'takeaways', 'processing'], [7, 'review', 'processing']]);

/* ─── Decision: score (free-tier registry entry since story 14) consumes quota too */
echo "Extra — score spends quota\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 5]);
$r = trigger('score');
check('score consumed one action', !is_wp_error($r) && stored_count() === 6);
reset_world(author_caps(), ['month' => '2026-10', 'count' => 20]);
$r = trigger('score');
check('score refused at the limit', is_wp_error($r) && $r->get_error_code() === 'pqm_quota_exceeded');

/* ─── Story 14: an unknown command fails closed before the webhook ───── */
echo "Extra — unknown command (foo) refused before any webhook\n";
foreach (['author' => author_caps(), 'editor' => editor_caps()] as $who => $caps) {
    reset_world($caps, ['month' => '2026-10', 'count' => 5]);
    $r = trigger('foo');
    check("$who: foo refused with pqm_feature_gated", is_wp_error($r) && $r->get_error_code() === 'pqm_feature_gated');
    check("$who: no webhook call, no processing row", $GLOBALS['tw']['webhook_calls'] === 0 && processing_rows() === []);
    check("$who: no quota consumed", stored_count() === 5);
}

/* ─── Story 11: quota refusal prints used of limit ───────────────────── */
echo "Extra — limit lowered to 10 with 12 used: copy says 12 of 10
";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 12], ['author_quota' => 10]);
$r = trigger('review');
$msg = is_wp_error($r) ? $r->get_error_message() : '';
check('429 pqm_quota_exceeded', is_wp_error($r) && $r->get_error_code() === 'pqm_quota_exceeded' && ($r->get_error_data()['status'] ?? 0) === 429);
check('copy reads "Monthly limit reached (12 of 10 AI actions used)…"', str_starts_with($msg, 'Monthly limit reached (12 of 10 AI actions used).'));

/* ─── Story 11: gate copy takes the label from the registry ──────────── */
echo "Extra — gate copy labels come from the registry
";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 0]);
$r = trigger('seo');
check('role refusal for seo: 403 "SEO is not available for your role…"', is_wp_error($r) && ($r->get_error_data()['status'] ?? 0) === 403
    && $r->get_error_message() === 'SEO is not available for your role. Ask an administrator to extend it.');
$r = trigger('foo');
check('unknown foo: 403, label is the raw key (no ucfirst)', is_wp_error($r) && ($r->get_error_data()['status'] ?? 0) === 403
    && str_starts_with($r->get_error_message(), 'foo is not available for your role.'));
reset_world(editor_caps(), ['month' => '2026-10', 'count' => 0]);
$r = trigger('sources');
check('plan refusal for sources on a free site: 403 "Sources is a Pro agent…"', is_wp_error($r) && ($r->get_error_data()['status'] ?? 0) === 403
    && str_starts_with($r->get_error_message(), 'Sources is a Pro agent.'));

/* ─── Story 11: the trigger message names the label and tells the truth ── */
echo "Extra — trigger message on 2xx and non-2xx
";
reset_world(editor_caps(), ['month' => '2026-10', 'count' => 0]);
$r = trigger('review');
check('2xx: success true, message "Review started for "Test Post"."', !is_wp_error($r) && $r->get_data()['success'] === true
    && $r->get_data()['message'] === 'Review started for "Test Post".');
$GLOBALS['tw']['webhook_result'] = 500;
$r = trigger('takeaways');
$msg = !is_wp_error($r) ? $r->get_data()['message'] : '';
check('non-2xx: 200 body with success false (unchanged), n8n_status 500', !is_wp_error($r) && $r->get_data()['success'] === false && $r->get_data()['n8n_status'] === 500);
check('non-2xx: message names the n8n status and says the run did not start (editor: no pqm_manage_settings, ticket 33)',
    $msg === 'Takeaways could not start — n8n answered HTTP 500. Ask an administrator to check the workflow in n8n, then try again.');

/* ─── Ticket 32: not configured — role-aware copy, nothing spent ─────── */
echo "Extra — webhook URL unset: 400 pqm_not_configured, copy names the next step per role\n";
foreach ([
    'admin'  => [editor_caps() + ['pqm_manage_settings' => true], 'n8n is not connected yet. Set up the workflow on the Content Quality → n8n Workflow page, then try again.'],
    'author' => [author_caps(), 'n8n is not connected yet. Ask an administrator to connect the n8n workflow, then try again.'],
] as $who => [$caps, $copy]) {
    reset_world($caps, ['month' => '2026-10', 'count' => 5], ['n8n_webhook_url' => '']);
    $r = trigger('review');
    check("$who: 400 pqm_not_configured", is_wp_error($r) && $r->get_error_code() === 'pqm_not_configured' && ($r->get_error_data()['status'] ?? 0) === 400);
    check("$who: copy is \"$copy\"", is_wp_error($r) && $r->get_error_message() === $copy);
    check("$who: no webhook call, no processing row, no quota spent",
        $GLOBALS['tw']['webhook_calls'] === 0 && processing_rows() === [] && stored_count() === 5);
}

/* ─── Ticket 33: failed-start copy names a step the clicker can take ─── */
echo "Extra — n8n unreachable / non-2xx: copy per role, response shape unchanged\n";
foreach ([
    'admin'  => [editor_caps() + ['pqm_manage_settings' => true],
        'Could not connect to n8n (cURL error 7). Check your Webhook URL in Settings.',
        'Review could not start — n8n answered HTTP 500. Check that the workflow is active in n8n, then try again.'],
    'author' => [author_caps(),
        'Could not connect to n8n (cURL error 7). Ask an administrator to check the n8n connection, then try again.',
        'Review could not start — n8n answered HTTP 500. Ask an administrator to check the workflow in n8n, then try again.'],
] as $who => [$caps, $unreachable, $non2xx]) {
    reset_world($caps, ['month' => '2026-10', 'count' => 5]);
    $GLOBALS['tw']['webhook_result'] = 'error';
    $r = trigger('review');
    check("$who unreachable: 502 pqm_n8n_error", is_wp_error($r) && $r->get_error_code() === 'pqm_n8n_error' && ($r->get_error_data()['status'] ?? 0) === 502);
    check("$who unreachable: copy is \"$unreachable\"", is_wp_error($r) && $r->get_error_message() === $unreachable);

    reset_world($caps, ['month' => '2026-10', 'count' => 5]);
    $GLOBALS['tw']['webhook_result'] = 500;
    $r = trigger('review');
    $d = !is_wp_error($r) ? $r->get_data() : [];
    check("$who non-2xx: 200 body, success false, n8n_status 500", ($d['success'] ?? null) === false && ($d['n8n_status'] ?? 0) === 500);
    check("$who non-2xx: copy is \"$non2xx\"", ($d['message'] ?? '') === $non2xx);
    check("$who non-2xx: response keys unchanged", array_keys($d) === ['success', 'message', 'n8n_status', 'quota_remaining']);
}

/* ─── Reset-date formatting sanity ───────────────────────────────────── */
echo "Extra — reset date is first of next month\n";
reset_world(author_caps(), ['month' => '2026-10', 'count' => 0]);
$s = PQM_Features::quota_state();
check('resets looks like a real date (non-empty)', is_string($s['resets']) && $s['resets'] !== '');
echo "  info resets = {$s['resets']}\n";

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
