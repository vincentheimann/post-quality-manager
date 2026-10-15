<?php
/**
 * n8n API client harness — n8n provisioning, entry 1 (connect by key). Runs
 * every row of the plan's I/O & Edge-Case Matrix through GET|POST|DELETE
 * /n8n/connection and PQM_N8n, with wp_remote_request() answering from a
 * scripted queue. Every recorded call is checked for the plugin User-Agent,
 * no redirect following, and the key header; every reply is checked for the
 * key string. WP stubs, no WP install; callbacks are called directly.
 * Entry 2 rows: the failure record (`failed`) a manual-path cause leaves,
 * and what clears it. Entry 4 rows: POST /n8n/provision, one per line of
 * its plan's matrix (Pro build; the Free run is in free-build-harness.php),
 * and the provider-key leak scan. Entry 5 rows: the rollback of a failed
 * run, one per line of its plan's matrix (Pro build), checked on an
 * instance model: what the create calls answered minus what a DELETE
 * removed. Entry 6: a passing provisioning answers the page's status block;
 * the ping's record (`steps.connect`) keeps the dialog's step open on a failure.
 *
 * The answer shapes are the ones n8n 2.42.3 gave the owner's probe
 * (2026-10-13): no version is exposed to an API key; a missing route gets
 * the router's generic `{"message":"not found"}` while an existing one
 * refuses the unknown id with its handler's own message (ROUTE_OK below),
 * and a body-less PATCH answers 415 before the route is known.
 * Run: php n8n-api-harness.php
 */

error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['tw'] = [
    'caps'     => [],
    'options'  => [],
    'autoload' => [],
    'writes'   => [],
    'queue'    => [], // scripted wp_remote_request() / wp_remote_get() answers, in call order
    'calls'    => [], // wp_remote_request() / wp_remote_get() calls: [url, args, answer]
    'pings'    => [], // wp_remote_post() calls (the webhook ping): [url, args]
    'ping_response' => null,
    'app_passwords' => true, // wp_is_application_passwords_available_for_user()
    'app_created'   => [],   // Application Passwords created: [user_id, args]
    'app_revoked'   => [],   // Application Passwords deleted: [user_id, uuid]
    'home'          => 'https://example.com', // home_url()
];
$GLOBALS['pqm_routes'] = [];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function add_action(...$a) {}
function add_filter(...$a) {}
function register_activation_hook(...$a) {}
function register_deactivation_hook(...$a) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return ''; }
function is_admin() { return false; }
function get_option($k, $d = false) { return $GLOBALS['tw']['options'][$k] ?? $d; }
function update_option($k, $v, $autoload = null) {
    $GLOBALS['tw']['options'][$k] = $v;
    $GLOBALS['tw']['autoload'][$k] = $autoload;
    $GLOBALS['tw']['writes'][] = $k;
    return true;
}
function delete_option($k) { unset($GLOBALS['tw']['options'][$k]); $GLOBALS['tw']['writes'][] = "-$k"; return true; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function __($s, $d = null) { return $s; }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }
/* A scripted answer may be a closure: it runs when its call is made, and returns the answer. */
function wp_remote_request($url, $args) {
    $answer = array_shift($GLOBALS['tw']['queue']) ?? new WP_Error('harness', "unscripted call: {$args['method']} $url");
    $answer = $answer instanceof Closure ? $answer() : $answer;
    $GLOBALS['tw']['calls'][] = [$url, $args, $answer];
    return $answer;
}
function wp_remote_get($url, $args) { return wp_remote_request($url, $args + ['method' => 'GET']); }
function wp_remote_post($url, $args) { $GLOBALS['tw']['pings'][] = [$url, $args]; return $GLOBALS['tw']['ping_response']; }
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }
function wp_generate_uuid4() { return 'stub-uuid'; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function apply_filters($tag, $value) { return $value; }
function esc_url_raw($s) { return trim((string) $s); }
function absint($n) { return abs((int) $n); }
function home_url($p = '') { return $GLOBALS['tw']['home'] . $p; }
function wp_get_current_user() { return (object) ['ID' => 7, 'user_login' => 'admin-anna']; }
function wp_is_application_passwords_available_for_user($user) { return $GLOBALS['tw']['app_passwords']; }
const APP_PASSWORD = 'abcd EFGH ijkl MNOP qrst UVWX';
class WP_Application_Passwords {
    public static function create_new_application_password($user_id, $args = []) {
        $GLOBALS['tw']['app_created'][] = [$user_id, $args];
        return [APP_PASSWORD, ['uuid' => 'app-uuid', 'name' => $args['name']]];
    }
    public static function delete_application_password($user_id, $uuid) {
        $GLOBALS['tw']['app_revoked'][] = [$user_id, $uuid];
        return true;
    }
}
/* GET /setup, read once for the acceptance row. */
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
/* A passing ping answers the page's refreshed status block (entry 6). */
define('MINUTE_IN_SECONDS', 60);
function esc_html($s) { return (string) $s; }
function esc_html__($s, $d = null) { return (string) $s; } // the status block's re-import button label
function human_time_diff($from, $to = 0) { return 'a while'; }
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; }
function restore_previous_locale() {}
function rest_url($p = '') { return 'https://example.com/wp-json/' . $p; }
function admin_url($p = '') { return 'https://example.com/wp-admin/' . $p; }
function wp_create_nonce($a = '') { return 'nonce-' . $a; }
function add_query_arg($args, $url = '', $unused = null) {
    if (!is_array($args)) { [$args, $url] = [[$args => $url], $unused]; }
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
}
$wpdb = new class {
    public $prefix = 'wp_';
    public function get_var($sql) { return null; }
};

class WP_REST_Server {
    const READABLE  = 'GET';
    const CREATABLE = 'POST';
}
class WP_REST_Request {
    public function __construct(private array $params = []) {}
    public function get_param($k) { return $this->params[$k] ?? null; }
}
class WP_REST_Response {
    public function __construct(public $data = null, public $status = 200) {}
    public function get_data() { return $this->data; }
}
class WP_Error {
    public function __construct(public $code = '', public $message = '', public $data = null) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($t) { return $t instanceof WP_Error; }

/* ─── Load the plugin bootstrap ──────────────────────────────────────── */
require __DIR__ . '/fs-stub.php';
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Runner ─────────────────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

const KEY     = 'n8n_api_key-SECRET-0123456789';
const OLD_KEY = 'n8n_api_key-OLD-9876543210';
const BASE    = 'https://n8n.example.com';
const UA      = 'PostQualityManager/' . PQM_VERSION . ' (+https://postquality.com)';

function resp(int $code, $body): array {
    return ['response' => ['code' => $code], 'body' => is_string($body) ? $body : json_encode($body)];
}
define('LIST_OK', resp(200, ['data' => [], 'nextCursor' => null]));
define('ROUTE_OK', resp(404, ['message' => 'Credential not found']));
define('ROUTER_404', resp(404, ['message' => 'not found'])); // what a missing route answers

$replies = []; // every REST reply, for the key-leak check

/** Fresh site: only the given options stored, the given answers scripted. */
function site(array $options = [], array $queue = []): void {
    $GLOBALS['tw']['options']  = $options;
    $GLOBALS['tw']['autoload'] = [];
    $GLOBALS['tw']['writes']   = [];
    $GLOBALS['tw']['queue']    = $queue;
    $GLOBALS['tw']['calls']    = [];
    $GLOBALS['tw']['home']     = 'https://example.com';
}

function post(array $params) {
    $r = PQM_REST_API::connect_n8n_callback(new WP_REST_Request($params));
    $GLOBALS['replies'][] = $r instanceof WP_Error ? $r->message : json_encode($r->get_data());
    return $r instanceof WP_Error ? $r : $r->get_data();
}
function get_state(): array {
    $d = PQM_REST_API::get_n8n_connection_callback()->get_data();
    $GLOBALS['replies'][] = json_encode($d);
    return $d;
}
function forget(): array {
    $d = PQM_REST_API::forget_n8n_callback()->get_data();
    $GLOBALS['replies'][] = json_encode($d);
    return $d;
}

/** "METHOD url" of every recorded call. */
function calls(): array {
    return array_map(static fn($c) => $c[1]['method'] . ' ' . $c[0], $GLOBALS['tw']['calls']);
}

/** Every recorded call: the plugin UA, no redirect; the key header on /api/v1 only, with $key. */
function calls_signed(string $key): bool {
    foreach ($GLOBALS['tw']['calls'] as [$url, $args]) {
        $api = str_contains($url, '/api/v1/');
        if (($args['user-agent'] ?? null) !== UA || ($args['redirection'] ?? null) !== 0
            || ($api ? ($args['headers']['X-N8N-API-KEY'] ?? null) !== $key : array_key_exists('X-N8N-API-KEY', $args['headers']))) {
            return false;
        }
    }
    return $GLOBALS['tw']['calls'] !== [];
}

/** Nothing written: the stored option is still $before. */
function unchanged(array $before): bool {
    return $GLOBALS['tw']['writes'] === [] && ($GLOBALS['tw']['options'][PQM_N8n::OPTION] ?? null) === ($before[PQM_N8n::OPTION] ?? null);
}

/** The stored option, its `failed` record left out. */
function without_failed(): ?array {
    $stored = $GLOBALS['tw']['options'][PQM_N8n::OPTION] ?? null;
    unset($stored['failed']);
    return $stored;
}

const MANUAL_SENTENCE = 'Or set up the workflow by hand with the Workflow File section.';

$stored_conn = [PQM_N8n::OPTION => [
    'base_url' => 'https://old.example.com',
    'api_key'  => OLD_KEY,
    'probe'    => ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => 1700000000],
]];

/* ─── Row: No right — every route gated ──────────────────────────────── */
echo "Routes and permission\n";
PQM_REST_API::register_routes();
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/n8n/connection'] ?? [];
$gate  = ['PQM_REST_API', 'check_manage_settings'];
check('/n8n/connection: GET, POST and DELETE endpoints', array_column($route, 'methods') === ['GET', 'POST', 'DELETE']);
check('... every one gated by check_manage_settings', count($route) === 3 && array_unique(array_column($route, 'permission_callback'), SORT_REGULAR) === [$gate]);
$GLOBALS['tw']['caps'] = ['pqm_manage_agents', 'pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents'];
check('user without pqm_manage_settings: refused (WP answers 403)', PQM_REST_API::check_manage_settings() === false);
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
check('pqm_manage_settings: allowed', PQM_REST_API::check_manage_settings() === true);

/* ─── Row: Connected ─────────────────────────────────────────────────── */
echo "Connected\n";
site([], [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post(['base_url' => '  https://n8n.example.com/home/workflows?x=1  ', 'api_key' => ' ' . KEY . ' ']);
check('probe ok, "Connected to n8n.", no version claimed', $r['probe'] === ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => $r['probe']['at']]
    && abs($r['probe']['at'] - time()) <= 5);
check('reply: base URL = the origin (editor path dropped), key set, the probe',
    $r['base_url'] === BASE && $r['key_set'] === true && array_keys($r) === ['probe', 'base_url', 'key_set']);
check('calls, in order: one-item workflow list, the two route probes (D2)', calls() === [
    'GET ' . BASE . '/api/v1/workflows?limit=1',
    'POST ' . BASE . '/api/v1/workflows/pqm-probe-unknown-id/activate',
    'PATCH ' . BASE . '/api/v1/credentials/pqm-probe-unknown-id',
]);
check('every call: plugin User-Agent, no redirect, the key (trimmed)', calls_signed(KEY));
check('the route probes carry an empty JSON object (a body-less PATCH gets 415 before the route is known)',
    array_map(static fn($c) => [$c[1]['headers']['Content-Type'] ?? null, $c[1]['body'] ?? null], array_slice($GLOBALS['tw']['calls'], 1))
    === [['application/json', '{}'], ['application/json', '{}']]);
check('stored in pqm_n8n_api: origin, key, probe — autoload off', $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => BASE, 'api_key' => KEY, 'probe' => $r['probe']]
    && $GLOBALS['tw']['autoload'][PQM_N8n::OPTION] === false);
check('... and only there: pqm_settings never written', $GLOBALS['tw']['writes'] === [PQM_N8n::OPTION]);

/* Acceptance: a reload shows the stored state without calling n8n. */
$GLOBALS['tw']['calls'] = [];
$s = get_state();
check('reload (GET): the stored state — connected, key set — and zero calls to n8n',
    $s === ['base_url' => BASE, 'key_set' => true, 'probe' => $r['probe']] && $GLOBALS['tw']['calls'] === []);

/* ─── Row: Missing endpoint vs existing (D2, the 2.42.3 shapes) ─────── */
echo "Endpoint probe shapes\n";
site([], [LIST_OK, ROUTER_404]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('the router\'s generic 404 "not found" on activate = missing endpoint; only its cause stored (entry 2), no URL, no key', $r['probe']['cause'] === 'missing_endpoint'
    && $r['probe']['message'] === 'Your n8n lacks an API endpoint the setup needs. Update n8n to 2.42.3 or later. Or set up the workflow by hand with the Workflow File section.'
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['failed' => ['cause' => 'missing_endpoint', 'message' => 'Your n8n lacks an API endpoint the setup needs. Update n8n to 2.42.3 or later.', 'at' => $r['probe']['at']]]
    && PQM_N8n::public_state() === ['base_url' => '', 'key_set' => false, 'probe' => null]);
site([], [LIST_OK, ROUTE_OK, resp(404, ['message' => 'NOT FOUND '])]);
check('... case and spaces do not matter', post(['base_url' => BASE, 'api_key' => KEY])['probe']['cause'] === 'missing_endpoint');
foreach ([
    'activate refused for the unknown id (404)' => [resp(404, ['message' => 'You do not have permission to activate this workflow. Ask the owner to share it with you.']), ROUTE_OK],
    'a validator 400 in n8n\'s error shape'      => [ROUTE_OK, resp(400, ['message' => 'request/body must have required property \'data\''])],
    'a 415 in n8n\'s error shape'                => [ROUTE_OK, resp(415, ['message' => 'unsupported media type undefined'])],
] as $label => $routes) {
    site([], array_merge([LIST_OK], $routes));
    $r = post(['base_url' => BASE, 'api_key' => KEY]);
    check("$label = the endpoint exists: connected, stored", $r['probe']['ok'] === true && $r['key_set'] === true);
}

/* ─── Row: Insecure or unusable URL — zero calls ─────────────────────── */
echo "Insecure URL\n";
foreach (['http://n8n.example', 'http://n8n.example.com:5678/home', 'HTTP://10.0.0.5'] as $url) {
    site($stored_conn);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("$url: refused, cause insecure (HTTPS required), zero calls, nothing stored",
        $r['probe']['ok'] === false && $r['probe']['cause'] === 'insecure' && str_contains($r['probe']['message'], 'https://')
        && $GLOBALS['tw']['calls'] === [] && unchanged($stored_conn));
}
foreach (['n8n.example.com', 'ftp://n8n.example.com', 'https://', ''] as $url) {
    site($stored_conn);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("\"$url\": refused, cause invalid_url, zero calls, nothing stored",
        $r['probe']['cause'] === 'invalid_url' && $GLOBALS['tw']['calls'] === [] && unchanged($stored_conn));
}
foreach (['http://localhost:5678' => 'http://localhost:5678', 'http://127.0.0.1:5678/home' => 'http://127.0.0.1:5678', 'http://[::1]:5678' => 'http://[::1]:5678'] as $url => $origin) {
    site([], [LIST_OK, ROUTE_OK, ROUTE_OK]);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("$url: allowed over http (local), stored as $origin", $r['probe']['ok'] === true && $r['base_url'] === $origin
        && ($GLOBALS['tw']['calls'][0][0] ?? '') === "$origin/api/v1/workflows?limit=1");
}

/* ─── Rows: failures keep the stored URL and key ─────────────────────── */
$failures = [
    'Key refused (401)' => [[resp(401, ['message' => 'unauthorized'])], 'key', 'Settings → n8n API'],
    'API disabled (404 on /api/v1/workflows)' => [[resp(404, ['message' => 'not found'])], 'api_disabled', 'public API is turned off'],
    'API disabled, a non-JSON 404' => [[resp(404, 'Cannot GET /api/v1/workflows')], 'api_disabled', 'public API is turned off'],
    'WAF: 403' => [[resp(403, ['message' => 'Forbidden'])], 'proxy', 'proxy or firewall'],
    'WAF: Cloudflare 1010' => [[resp(403, 'error code: 1010')], 'proxy', 'proxy or firewall'],
    'WAF: a non-JSON 200 (a login or challenge page)' => [[resp(200, '<html>Just a moment...</html>')], 'proxy', 'proxy or firewall'],
    'WAF: a redirect (never followed)' => [[resp(302, '')], 'proxy', 'proxy or firewall'],
    'Unreachable (DNS)' => [[new WP_Error('http_request_failed', 'cURL error 6: Could not resolve host: n8n.example.com')], 'unreachable', 'cURL error 6: Could not resolve host: n8n.example.com'],
    'Unreachable (TLS)' => [[new WP_Error('http_request_failed', 'cURL error 60: SSL certificate problem')], 'unreachable', 'cURL error 60: SSL certificate problem'],
    'Missing endpoint: 405 on activate' => [[LIST_OK, resp(405, ['message' => 'method not allowed'])], 'missing_endpoint', 'Update n8n to 2.42.3 or later'],
    'Missing endpoint: a JSON 404 without n8n\'s shape on PATCH /credentials' => [[LIST_OK, ROUTE_OK, resp(404, ['error' => 'route'])], 'missing_endpoint', 'lacks an API endpoint'],
    'Route probe: a non-JSON 404 is a proxy, never a missing endpoint (D2)' => [[LIST_OK, resp(404, '<html>Not Found</html>')], 'proxy', 'proxy or firewall'],
    'Route probe unreachable' => [[LIST_OK, ROUTE_OK, new WP_Error('http_request_failed', 'cURL error 28: timed out')], 'unreachable', 'cURL error 28'],
    'n8n error (500 JSON)' => [[resp(500, ['message' => 'Internal error'])], 'unexpected', 'HTTP 500'],
];
foreach ($failures as $label => [$queue, $cause, $says]) {
    echo "$label\n";
    site($stored_conn, $queue);
    $r = post(['base_url' => BASE, 'api_key' => KEY]);
    check("cause $cause, message says \"$says\"", $r['probe']['ok'] === false && $r['probe']['cause'] === $cause && str_contains($r['probe']['message'], $says));
    check('... the manual path offered, once, after the cause sentence', $cause === 'key'
        || ($r['probe']['message'] === $r['probe']['reason'] . ' ' . MANUAL_SENTENCE && !str_contains($r['probe']['reason'], MANUAL_SENTENCE)));
    check('... stored URL, key and last passing probe as they were; the reply says so', without_failed() === $stored_conn[PQM_N8n::OPTION]
        && $r['base_url'] === 'https://old.example.com' && $r['key_set'] === true);
    if ($cause === 'key') {
        check('... a refused key stores no failure: nothing written', unchanged($stored_conn));
    } else {
        check('... the cause stored as `failed` {cause, the sentence without the manual path, at}, autoload off',
            $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed'] === ['cause' => $cause, 'message' => $r['probe']['reason'], 'at' => $r['probe']['at']]
            && $GLOBALS['tw']['writes'] === [PQM_N8n::OPTION] && $GLOBALS['tw']['autoload'][PQM_N8n::OPTION] === false);
    }
    check('... every call signed, nothing left unscripted', calls_signed(KEY) && $GLOBALS['tw']['queue'] === []);
}
site([], [LIST_OK, resp(405, '')]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('Missing endpoint: a 405 without a body, only its cause stored', $r['probe']['cause'] === 'missing_endpoint'
    && array_keys($GLOBALS['tw']['options'][PQM_N8n::OPTION]) === ['failed']);

/* ─── Rows: the failure record (entry 2, D1) ─────────────────────────── */
echo "Failure record\n";
$failed_conn = [PQM_N8n::OPTION => $stored_conn[PQM_N8n::OPTION] + ['failed' => ['cause' => 'proxy', 'message' => 'n8n did not answer as expected.', 'at' => 1700000100]]];
site($failed_conn, [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('a passing probe after a stored failure: `failed` gone', $r['probe']['ok'] === true
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => BASE, 'api_key' => KEY, 'probe' => $r['probe']]
    && PQM_N8n::connection()['failed'] === null);
site($failed_conn);
forget();
check('Forget after a stored failure: `failed` gone, URL kept', $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => 'https://old.example.com']);
foreach (['http://n8n.example' => 'insecure', 'n8n.example.com' => 'invalid_url'] as $url => $cause) {
    site($failed_conn);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("cause $cause stores nothing: the earlier failure stays as it was", $r['probe']['cause'] === $cause && unchanged($failed_conn));
}
site($failed_conn, [resp(500, ['message' => 'Internal error'])]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('a newer manual-path failure replaces the stored one', $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']['cause'] === 'unexpected'
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']['message'] === 'n8n answered HTTP 500. Try again in a moment.');
site();
check('fresh site: connection() carries failed => null', PQM_N8n::connection()['failed'] === null);
site([], [new WP_Error('http_request_failed', 'cURL error 6: Could not resolve host')]);
post(['base_url' => BASE, 'api_key' => KEY]);
check('the stored failure never carries the key', !str_contains(json_encode($GLOBALS['tw']['options']), KEY));
check('public_state() leaves the failure out (the island is unchanged)', array_keys(PQM_N8n::public_state()) === ['base_url', 'key_set', 'probe']);

/* ─── Row: Re-check ──────────────────────────────────────────────────── */
echo "Re-check\n";
site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => $stored_conn[PQM_N8n::OPTION]['probe']]], [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post([]);
check('POST without key or URL: the probe re-runs on the stored address with the stored key', $r['probe']['ok'] === true
    && count($GLOBALS['tw']['calls']) === 3 && $GLOBALS['tw']['calls'][0][0] === BASE . '/api/v1/workflows?limit=1' && calls_signed(KEY));
check('... the fresh probe is stored', $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe'] === $r['probe'] && $r['probe']['at'] !== 1700000000);
site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => $stored_conn[PQM_N8n::OPTION]['probe']]], [LIST_OK, ROUTE_OK, ROUTE_OK]);
check('POST with the stored address typed again, key empty: same re-check', post(['base_url' => BASE . '/home', 'api_key' => ''])['probe']['ok'] === true && calls_signed(KEY));
$before = [PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => $stored_conn[PQM_N8n::OPTION]['probe']]];
site($before, [resp(401, ['message' => 'unauthorized'])]);
$r = post([]);
check('re-check refused (key expired): cause key, stored key and last passing probe unchanged', $r['probe']['cause'] === 'key' && unchanged($before));
site($stored_conn);
$r = post(['base_url' => BASE]);
check('a new address without a key: 400, the stored key never sent there (zero calls)', $r instanceof WP_Error && $r->data['status'] === 400
    && $GLOBALS['tw']['calls'] === [] && unchanged($stored_conn));
site();
$r = post(['base_url' => BASE]);
check('no key given, none stored: 400, zero calls, nothing stored', $r instanceof WP_Error && $r->data['status'] === 400 && $GLOBALS['tw']['calls'] === [] && $GLOBALS['tw']['writes'] === []);

/* ─── Row: Forget ────────────────────────────────────────────────────── */
echo "Forget\n";
site($stored_conn);
$r = forget();
check('DELETE: key and probe removed, URL kept, "not set"', $r === ['base_url' => 'https://old.example.com', 'key_set' => false, 'probe' => null]
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => 'https://old.example.com'] && $GLOBALS['tw']['autoload'][PQM_N8n::OPTION] === false);
check('... nothing sent to n8n', $GLOBALS['tw']['calls'] === []);
check('... a reload shows "not set"', get_state() === $r);
site();
check('fresh site GET: nothing stored, not set', get_state() === ['base_url' => '', 'key_set' => false, 'probe' => null] && $GLOBALS['tw']['writes'] === []);

/* ─── The client boundary later entries call ─────────────────────────── */
echo "PQM_N8n::request()\n";
site();
$r = PQM_N8n::request('GET', '/credentials?limit=250');
check('no key stored: WP_Error, zero calls', $r instanceof WP_Error && $r->code === 'pqm_n8n_not_connected' && $GLOBALS['tw']['calls'] === []);
site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => null]], [resp(200, ['id' => 'abc'])]);
$r = PQM_N8n::request('POST', '/credentials', ['name' => 'x', 'type' => 'httpHeaderAuth', 'data' => []]);
[$url, $args] = $GLOBALS['tw']['calls'][0];
check('a call: /api/v1 + path on the stored origin, signed, JSON body with its Content-Type, decoded answer',
    $url === BASE . '/api/v1/credentials' && $args['method'] === 'POST' && calls_signed(KEY)
    && $args['headers']['Content-Type'] === 'application/json' && $args['body'] === '{"name":"x","type":"httpHeaderAuth","data":[]}'
    && $r === ['code' => 200, 'json' => ['id' => 'abc']]);

/* ─── Entry 4: POST /n8n/provision ───────────────────────────────────── */
echo "Provisioning: route\n";
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/n8n/provision'] ?? [];
check('/n8n/provision: POST, gated by check_manage_settings, anthropic_key required', ($route['methods'] ?? null) === 'POST'
    && ($route['permission_callback'] ?? null) === $gate && ($route['args']['anthropic_key']['required'] ?? null) === true);
check('the setup map has its own option, pqm_n8n_setup', PQM_N8n::SETUP_OPTION === 'pqm_n8n_setup');

const PROVIDER_KEY = 'sk-ant-PROVIDER-SECRET-4242';
const TOKEN        = 'webhook-token-1234';
const MODELS       = 'GET https://api.anthropic.com/v1/models?limit=1';
const HOOK         = BASE . '/webhook/pqm-execute-example-com';
define('MODELS_OK', resp(200, ['data' => [['id' => 'claude-model', 'type' => 'model']], 'has_more' => true]));
define('ACTIVE', resp(200, ['id' => 'wf1', 'active' => true]));
function created(string $id): array { return resp(200, ['id' => $id, 'name' => 'stored name']); }
define('PRO_OK', [MODELS_OK, created('c-webhook'), created('c-anthropic'), created('c-wp'), created('wf1'), ACTIVE]);

/** A connected site, nothing provisioned, the given answers scripted; the ping answers the current version. */
function connected(array $queue, array $extra = []): void {
    site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => 1700000000]],
        'pqm_settings' => ['webhook_token' => TOKEN]] + $extra, $queue);
    $GLOBALS['tw']['pings']         = [];
    $GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION]);
    $GLOBALS['tw']['app_passwords'] = true;
    $GLOBALS['tw']['app_created']   = [];
    $GLOBALS['tw']['app_revoked']   = [];
}

$provision_runs = []; // [options, calls, pings] after every run, for the leak scan
function provision(string $key = PROVIDER_KEY) {
    $r = PQM_REST_API::provision_n8n_callback(new WP_REST_Request(['anthropic_key' => $key]));
    $GLOBALS['replies'][] = $r instanceof WP_Error ? json_encode([$r->code, $r->message, $r->data]) : json_encode($r->get_data());
    $GLOBALS['provision_runs'][] = [$GLOBALS['tw']['options'], $GLOBALS['tw']['calls'], $GLOBALS['tw']['pings']];
    return $r instanceof WP_Error ? $r : $r->get_data();
}

/** The decoded JSON body of call $i. */
function body(int $i) { return json_decode($GLOBALS['tw']['calls'][$i][1]['body'] ?? 'null', true); }

/** Node name => credentials, for every node of the sent workflow that names one. */
function linked(array $workflow): array {
    return array_column(array_filter($workflow['nodes'], static fn($n) => isset($n['credentials'])), 'credentials', 'name');
}

/** Nothing written, nothing pinged, no Application Password created or revoked. */
function untouched(): bool {
    return $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === [] && $GLOBALS['tw']['app_revoked'] === [];
}

$template = json_decode((string) pqm_workflow_json(), true);
$strip    = static fn(array $nodes) => array_map(static function ($n) { unset($n['credentials']); return $n; }, $nodes);

echo "Provisioning: Pro, fresh instance\n";
connected(PRO_OK);
$r = provision();
check('calls, in order: models GET, 3x POST /credentials, POST /workflows, activate', calls() === [
    MODELS,
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/workflows',
    'POST ' . BASE . '/api/v1/workflows/wf1/activate',
] && $GLOBALS['tw']['queue'] === []);
check('the models request: x-api-key = the provider key, anthropic-version 2023-06-01, no n8n key; every call with the plugin UA, no redirect',
    $GLOBALS['tw']['calls'][0][1]['headers'] === ['x-api-key' => PROVIDER_KEY, 'anthropic-version' => '2023-06-01'] && calls_signed(KEY));
check('webhook credential: httpHeaderAuth {X-PQM-Token, the site token}, under its deterministic name',
    body(1) === ['name' => 'Post Quality webhook - example.com', 'type' => 'httpHeaderAuth', 'data' => ['name' => 'X-PQM-Token', 'value' => TOKEN]]);
check('Anthropic credential: httpHeaderAuth {x-api-key, the provider key}',
    body(2) === ['name' => 'Anthropic - Claude', 'type' => 'httpHeaderAuth', 'data' => ['name' => 'x-api-key', 'value' => PROVIDER_KEY]]);
check('WordPress credential: httpBasicAuth {the current user, a new Application Password}',
    body(3) === ['name' => 'WordPress - example.com', 'type' => 'httpBasicAuth', 'data' => ['user' => 'admin-anna', 'password' => APP_PASSWORD]]);
check('... one Application Password, for the current user', count($GLOBALS['tw']['app_created']) === 1 && $GLOBALS['tw']['app_created'][0][0] === 7
    && str_starts_with($GLOBALS['tw']['app_created'][0][1]['name'], 'Post Quality Manager (n8n) '));
$sent = body(4);
check('workflow: {name, nodes, connections, settings}, no `active`, the build template otherwise unchanged',
    array_keys($sent) === ['name', 'nodes', 'connections', 'settings'] && $sent['name'] === 'Post Quality — example.com'
    && $strip($sent['nodes']) === $strip($template['nodes']) && $sent['connections'] === $template['connections'] && $sent['settings'] === $template['settings']);
$links = linked($sent);
$claude = ['httpHeaderAuth' => ['id' => 'c-anthropic', 'name' => 'Anthropic - Claude']];
check('acceptance: all 8 credentialed nodes carry the created id, none left name-only', count($links) === 8
    && $links['Webhook Trigger'] === ['httpHeaderAuth' => ['id' => 'c-webhook', 'name' => 'Post Quality webhook - example.com']]
    && $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => ['id' => 'c-wp', 'name' => 'WordPress - example.com']]
    && count(array_filter($links, static fn($c) => $c === $claude)) === 6);
check('the trigger path is pqm_webhook_path()', array_column($sent['nodes'], null, 'name')['Webhook Trigger']['parameters']['path'] === pqm_webhook_path());
check('activate is sent {}', $GLOBALS['tw']['calls'][5][1]['body'] === '{}');
$setup = $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] ?? null;
check('setup map stored, autoload off', $setup === ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic', 'wordpress' => 'c-wp'],
    'path' => 'pqm-execute-example-com', 'wizard_created' => true, 'at' => $setup['at'] ?? null] && abs($setup['at'] - time()) <= 5
    && $GLOBALS['tw']['autoload'][PQM_N8n::SETUP_OPTION] === false);
check('webhook URL saved: connected origin + /webhook/ + the path; the token kept', $GLOBALS['tw']['options']['pqm_settings'] === ['webhook_token' => TOKEN, 'n8n_webhook_url' => HOOK]);
check('pinged that URL once, with the token', count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === HOOK
    && $GLOBALS['tw']['pings'][0][1]['headers']['X-PQM-Token'] === TOKEN);
check('replied version recorded', ($GLOBALS['tw']['options']['pqm_workflow_version_seen']['version'] ?? null) === PQM_WORKFLOW_VERSION);
check('the ping recorded as the connect route does (autoload off): Set up the workflow reads done', ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === true
    && $GLOBALS['tw']['autoload'][PQM_SETUP_OPTION] === false);
check('reply: {provisioned, ping_ok, workflow_id, message, status_html, rewrite_ready}', array_keys($r) === ['provisioned', 'ping_ok', 'workflow_id', 'message', 'status_html', 'rewrite_ready']
    && $r['provisioned'] === true && $r['ping_ok'] === true && $r['workflow_id'] === 'wf1' && $r['rewrite_ready'] === true
    && str_starts_with($r['message'], 'Ping OK'));
check('... status_html = the page\'s refreshed status block (entry 6)', $r['status_html'] === PQM_Admin::render_workflow_status()
    && str_contains($r['status_html'], '<div class="pqm-workflow-status">'));
$hits = array_keys(array_filter($GLOBALS['tw']['calls'], static fn($c) => str_contains(json_encode($c), PROVIDER_KEY)));
check('the provider key travels in the models request and the Anthropic credential body only', $hits === [0, 2]
    && !str_contains(json_encode($GLOBALS['tw']['calls'][2][1]['headers']), PROVIDER_KEY) && !str_contains(json_encode($GLOBALS['tw']['pings']), PROVIDER_KEY));

echo "Provisioning: acceptance — the provider key is in neither read reply\n";
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
$conn        = json_encode(get_state());
$setup_reply = json_encode(PQM_REST_API::get_setup_callback()->get_data());
$replies[]   = $setup_reply;
check('GET /n8n/connection and GET /setup after provisioning: no provider key, no Application Password',
    !str_contains($conn . $setup_reply, PROVIDER_KEY) && !str_contains($conn . $setup_reply, APP_PASSWORD) && str_contains($setup_reply, '"webhook_url"'));

echo "Provisioning: Pro, no Application Passwords (D1)\n";
connected([MODELS_OK, created('c-webhook'), created('c-anthropic'), created('wf1'), ACTIVE]);
$GLOBALS['tw']['app_passwords'] = false;
$r = provision();
$links = linked(body(3));
check('2 credentials, no Application Password, no httpBasicAuth credential', count(calls()) === 5 && $GLOBALS['tw']['app_created'] === []
    && !str_contains(json_encode(array_slice($GLOBALS['tw']['calls'], 1, 2)), 'httpBasicAuth'));
check('7 nodes linked, Update WP Post left name-only', count(array_filter($links, static fn($c) => isset(reset($c)['id']))) === 7
    && $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => ['name' => 'WordPress - example.com']]);
check('reply: provisioned, rewrite_ready false, the message says Rewrite stays off until a credential is added by hand',
    $r['provisioned'] === true && $r['ping_ok'] === true && $r['rewrite_ready'] === false
    && str_contains($r['message'], 'Rewrite stays off') && str_contains($r['message'], 'does not run on HTTPS')
    && str_contains($r['message'], 'a WordPress credential is added in n8n on the Update WP Post (Rewrite) node'));
check('map without a wordpress credential', $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]['credentials'] === ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic']);

echo "Provisioning: the provider key refused\n";
foreach ([401, 403] as $code) {
    connected([resp($code, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']])]);
    $r = provision();
    check("Anthropic answers $code: 400 pqm_provider_key_invalid, zero n8n calls, nothing saved",
        $r instanceof WP_Error && $r->code === 'pqm_provider_key_invalid' && $r->data['status'] === 400 && calls() === [MODELS] && untouched());
}
connected([]);
$r = provision('  ');
check('an empty key: refused before any call', $r instanceof WP_Error && $r->code === 'pqm_provider_key_invalid' && calls() === [] && untouched());

echo "Provisioning: Anthropic unreachable\n";
foreach (['a WP_Error' => new WP_Error('http_request_failed', 'cURL error 28: timed out'), 'a 500' => resp(500, ['type' => 'error']), 'a 529 (overloaded)' => resp(529, ['type' => 'error'])] as $label => $answer) {
    connected([$answer]);
    $r = provision();
    check("$label: 502 pqm_provider_unreachable, zero n8n calls, nothing saved", $r instanceof WP_Error && $r->code === 'pqm_provider_unreachable'
        && $r->data['status'] === 502 && calls() === [MODELS] && untouched());
}

echo "Provisioning: not connected, already provisioned\n";
site(['pqm_settings' => ['webhook_token' => TOKEN]]);
$GLOBALS['tw']['pings'] = [];
$GLOBALS['tw']['app_created'] = [];
$r = provision();
check('no n8n key stored: pqm_n8n_not_connected, zero calls', $r instanceof WP_Error && $r->code === 'pqm_n8n_not_connected' && calls() === [] && untouched());
connected(PRO_OK, [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'old', 'credentials' => [], 'path' => 'p', 'wizard_created' => true, 'at' => 1]]);
$r = provision();
check('a setup map exists: 409 pqm_already_provisioned, zero calls', $r instanceof WP_Error && $r->code === 'pqm_already_provisioned'
    && $r->data['status'] === 409 && calls() === [] && untouched());

/* ─── Entry 5: a failed run leaves nothing behind ────────────────────── */
echo "Rollback: the option and the instance model\n";
check('what a rollback could not remove has its own option, pqm_n8n_leftovers', PQM_N8n::LEFTOVERS_OPTION === 'pqm_n8n_leftovers');

define('DELETED', resp(200, ['id' => 'deleted']));
define('INACTIVE', resp(200, ['id' => 'wf1', 'active' => false]));
define('NOT_FOUND', resp(404, ['message' => 'Not Found'])); // n8n 2.42.3, an unknown id
define('UNPUBLISHING', resp(409, ['message' => 'Workflow is still being unpublished. Please try again in a few moments']));
function timed_out(): WP_Error { return new WP_Error('http_request_failed', 'cURL error 28: timed out'); }
const N8N        = 'POST ' . BASE . '/api/v1/';
const DEL        = 'DELETE ' . BASE . '/api/v1/';
const CREATE_ALL = [MODELS, N8N . 'credentials', N8N . 'credentials', N8N . 'credentials', N8N . 'workflows', N8N . 'workflows/wf1/activate'];
const UNDO_CREDS = [DEL . 'credentials/c-webhook', DEL . 'credentials/c-anthropic', DEL . 'credentials/c-wp'];
const UNDO_ALL   = [N8N . 'workflows/wf1/deactivate', DEL . 'workflows/wf1', ...UNDO_CREDS];
const CLEAN      = 'Nothing was left in n8n. Try again in a moment.';
const LEFT       = 'Some of what it created is still in n8n: the next try removes it first. Try again in a moment.';

/**
 * The instance model: "kind/id" of every object on n8n after the run — $before,
 * plus the ids the create POSTs answered, minus the ids a DELETE removed
 * (2xx, or a JSON 404: already gone; a non-JSON 404 is a proxy's).
 */
function on_instance(array $before = []): array {
    $there = array_fill_keys($before, true);
    foreach ($GLOBALS['tw']['calls'] as [$url, $args, $answer]) {
        if (!preg_match('#/api/v1/(credentials|workflows)(?:/([^/]+))?$#', $url, $m) || $answer instanceof WP_Error) {
            continue;
        }
        $code = $answer['response']['code'];
        $id   = json_decode($answer['body'], true)['id'] ?? null;
        if ($args['method'] === 'POST' && !isset($m[2]) && $code >= 200 && $code < 300 && $id !== null) {
            $there["$m[1]/$id"] = true;
        } elseif ($args['method'] === 'DELETE' && isset($m[2])
            && (($code === 404 && is_array(json_decode($answer['body'], true))) || ($code >= 200 && $code < 300))) {
            unset($there["$m[1]/" . rawurldecode($m[2])]);
        }
    }
    ksort($there);
    return array_keys($there);
}

/** The forced order: deactivate < the workflow DELETE < every credential DELETE; no deactivate without a workflow DELETE. */
function rollback_ordered(): bool {
    $calls = calls();
    $off   = array_keys(preg_grep('#/workflows/[^/]+/deactivate$#', $calls));
    $wf    = array_keys(preg_grep('#^DELETE .*/workflows/#', $calls));
    $creds = array_keys(preg_grep('#^DELETE .*/credentials/#', $calls));
    if ($wf === []) {
        return $off === [];
    }
    return count($off) === 1 && count($wf) === 1 && $off[0] < $wf[0] && ($creds === [] || min($creds) > $wf[0]);
}

/** The Pro run's own: the Application Password it created, revoked once with its uuid. */
function revoked(): bool {
    return count($GLOBALS['tw']['app_created']) === 1 && $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']];
}

/** Nothing of a provisioned site saved, nothing pinged. */
function nothing_saved(): bool {
    return !array_intersect([PQM_N8n::SETUP_OPTION, 'pqm_settings', PQM_SETUP_OPTION], $GLOBALS['tw']['writes']) && $GLOBALS['tw']['pings'] === [];
}

/** The message names the step in plain words and never repeats n8n's reply body. */
function plain(WP_Error $r, string $says): bool {
    return str_contains($r->message, $says) && !preg_match('/\b(credential_\w+|activate|cleanup)\b/', $r->message)
        && !preg_match('/request\/body|Internal error|Not Found|still being unpublished|has no node|HTTP 200/', $r->message);
}

echo "Rollback: n8n refuses a step, everything removed\n";
foreach ([
    'credential_webhook'   => [1, resp(400, ['message' => 'request/body/data must have required property']), [], 'while creating the webhook credential (n8n answered HTTP 400)'],
    'credential_anthropic' => [2, resp(500, ['message' => 'Internal error']), [DELETED], 'while creating the Anthropic credential (n8n answered HTTP 500)'],
    'credential_wordpress' => [3, timed_out(), [DELETED, DELETED], 'while creating the WordPress credential (n8n could not be reached: cURL error 28: timed out)'],
    'workflow'             => [4, resp(400, ['message' => 'request/body must NOT have additional properties']), [DELETED, DELETED, DELETED], 'while creating the workflow (n8n answered HTTP 400)'],
    'workflow (a 200 without an id)' => [4, resp(200, ['message' => 'ok']), [DELETED, DELETED, DELETED], 'while creating the workflow (n8n did not return what it created)'],
    'activate (refused)'   => [5, resp(400, ['message' => 'Workflow has no node to start the workflow']), [INACTIVE, DELETED, DELETED, DELETED, DELETED], 'while activating the workflow (n8n answered HTTP 400)'],
    'activate (timed out, may be active)' => [5, timed_out(), [INACTIVE, DELETED, DELETED, DELETED, DELETED], 'while activating the workflow (n8n could not be reached: cURL error 28: timed out)'],
    'activate (objects already gone: 404 on DELETE)' => [5, resp(400, ['message' => 'Workflow has no node to start the workflow']), [NOT_FOUND, NOT_FOUND, DELETED, NOT_FOUND, DELETED], 'while activating the workflow (n8n answered HTTP 400)'],
] as $label => [$at, $answer, $undo, $says]) {
    $step = explode(' ', $label)[0];
    connected(array_merge(array_slice(PRO_OK, 0, $at), [$answer], $undo));
    $r = provision();
    $expected = array_merge(array_slice(CREATE_ALL, 0, $at + 1), $at === 5 ? UNDO_ALL : array_slice(UNDO_CREDS, 0, min($at - 1, 3)));
    check("$label: 502 pqm_provision_failed, data.step = $step", $r instanceof WP_Error && $r->code === 'pqm_provision_failed'
        && $r->data === ['status' => 502, 'step' => $step]);
    check('... calls: up to the refused step, then the rollback of what it created, in the forced order', calls() === $expected
        && rollback_ordered() && $GLOBALS['tw']['queue'] === []);
    check('... the instance model holds zero plugin objects; no leftovers recorded', on_instance() === []
        && !in_array(PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));
    check('... the Application Password revoked with the created uuid', revoked());
    check("... the message says \"$says\", that nothing was left, never n8n's reply body", plain($r, $says) && str_ends_with($r->message, CLEAN));
    check('... nothing saved, no ping', nothing_saved());
}
check('a deactivate is sent {}, like activate', ($GLOBALS['tw']['calls'][6][1]['body'] ?? null) === '{}');

echo "Rollback: the template names an unknown credential (link)\n";
// The template is filled before the models check, the credential names after it: another host in between.
connected(array_merge([static function () { $GLOBALS['tw']['home'] = 'https://other.example'; return MODELS_OK; }], array_slice(PRO_OK, 1, 3), [DELETED, DELETED, DELETED]));
$r = provision();
check('500, data.step = link; the 3 credentials removed, no workflow sent', $r instanceof WP_Error && $r->data === ['status' => 500, 'step' => 'link']
    && calls() === array_merge(array_slice(CREATE_ALL, 0, 4), UNDO_CREDS) && on_instance() === [] && revoked() && nothing_saved());
check('... the message names the credential, says nothing was left, then to update the plugin',
    str_contains($r->message, 'names a credential the setup does not create (') && str_ends_with($r->message, 'Nothing was left in n8n. Update the plugin, then try again.'));

echo "Rollback: n8n refuses the rollback\n";
foreach ([
    'the workflow DELETE answers 409 (still unpublishing)' => [[resp(400, ['message' => 'x']), INACTIVE, UNPUBLISHING], 2],
    'the workflow DELETE unreachable'                      => [[resp(400, ['message' => 'x']), INACTIVE, timed_out()], 2],
    'n8n unreachable during the rollback: the deactivate stops it, no DELETE sent' => [[timed_out(), timed_out()], 1],
] as $label => [$tail, $undone]) {
    connected(array_merge(array_slice(PRO_OK, 0, 5), $tail));
    $r = provision();
    $record = $GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION] ?? null;
    check("$label: 502, the original step (activate); stops at the workflow after $undone rollback call(s), no credential deleted", $r instanceof WP_Error
        && $r->data === ['status' => 502, 'step' => 'activate'] && count(calls()) === count(CREATE_ALL) + $undone
        && calls() === array_merge(CREATE_ALL, array_slice(UNDO_ALL, 0, $undone)) && $GLOBALS['tw']['queue'] === []);
    check('... the Application Password revoked all the same', revoked());
    check('... pqm_n8n_leftovers = {base_url, the workflow, every credential id, at}, autoload off', $record === [
        'base_url' => BASE, 'workflow_id' => 'wf1', 'credentials' => ['c-webhook', 'c-anthropic', 'c-wp'], 'at' => $record['at'] ?? null]
        && abs($record['at'] - time()) <= 5 && $GLOBALS['tw']['autoload'][PQM_N8n::LEFTOVERS_OPTION] === false);
    check('... the model holds exactly what the record names', on_instance() === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']);
    check('... the message says some objects are still in n8n and the next try removes them first', plain($r, 'while activating the workflow') && str_ends_with($r->message, LEFT));
    check('... nothing saved, no ping', nothing_saved());
}
connected(array_merge(array_slice(PRO_OK, 0, 4), [resp(400, ['message' => 'x']), resp(409, ['message' => 'x']), DELETED, resp(500, ['message' => 'Internal error'])]));
$r = provision();
check('no workflow, two credential DELETEs refused (HTTP): every credential tried, only the refused ones recorded', $r instanceof WP_Error && $r->data['step'] === 'workflow'
    && calls() === array_merge(array_slice(CREATE_ALL, 0, 5), UNDO_CREDS) && revoked() && str_ends_with($r->message, LEFT)
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-webhook', 'c-wp']]
    && on_instance() === ['credentials/c-webhook', 'credentials/c-wp']);
connected(array_merge(array_slice(PRO_OK, 0, 4), [resp(400, ['message' => 'x']), DELETED, timed_out()]));
$r = provision();
check('no workflow, a credential DELETE unreachable: stops there, that id and the untried one recorded without a call', $r instanceof WP_Error
    && count(calls()) === 7 && calls() === array_merge(array_slice(CREATE_ALL, 0, 5), array_slice(UNDO_CREDS, 0, 2)) && $GLOBALS['tw']['queue'] === []
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-anthropic', 'c-wp']]
    && on_instance() === ['credentials/c-anthropic', 'credentials/c-wp'] && str_ends_with($r->message, LEFT));
connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(400, ['message' => 'x']), INACTIVE, DELETED, resp(404, '<html>Not Found</html>'), DELETED, DELETED]));
$r = provision();
check('a credential DELETE answers a non-JSON 404 (a proxy): not counted as removed, the id recorded as left', $r instanceof WP_Error
    && calls() === array_merge(CREATE_ALL, UNDO_ALL) && $GLOBALS['tw']['queue'] === [] && str_ends_with($r->message, LEFT)
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-webhook']]
    && on_instance() === ['credentials/c-webhook']);

echo "Rollback: the next try removes the leftovers first\n";
const OLD_OBJECTS = ['credentials/c-old1', 'credentials/c-old2', 'credentials/c-old3', 'workflows/wf-old'];
$old = [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'workflow_id' => 'wf-old', 'credentials' => ['c-old1', 'c-old2', 'c-old3'], 'at' => 1700000000]];
$cleanup = [N8N . 'workflows/wf-old/deactivate', DEL . 'workflows/wf-old', DEL . 'credentials/c-old1', DEL . 'credentials/c-old2', DEL . 'credentials/c-old3'];
connected(array_merge([MODELS_OK, INACTIVE, DELETED, DELETED, NOT_FOUND, DELETED], array_slice(PRO_OK, 1)), $old);
$r = provision();
check('removable: deactivate → DELETE workflow → DELETE credentials, before anything is created; then the normal run',
    calls() === array_merge([MODELS], $cleanup, array_slice(CREATE_ALL, 1)) && rollback_ordered() && $r['provisioned'] === true && $GLOBALS['tw']['queue'] === []);
check('... the record deleted; the model holds only the new setup', !isset($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION])
    && in_array('-' . PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true)
    && on_instance(OLD_OBJECTS) === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']);
connected([MODELS_OK, INACTIVE, UNPUBLISHING], $old);
$r = provision();
$record = $GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION] ?? null;
check('still refused (409): 502 step cleanup, zero creations, no Application Password', $r instanceof WP_Error && $r->code === 'pqm_provision_failed'
    && $r->data === ['status' => 502, 'step' => 'cleanup'] && calls() === array_merge([MODELS], array_slice($cleanup, 0, 2))
    && $GLOBALS['tw']['app_created'] === [] && $GLOBALS['tw']['app_revoked'] === [] && nothing_saved());
check('... the record updated to what remains (all of it), a fresh `at`', array_diff_key($record, ['at' => 0]) === array_diff_key($old[PQM_N8n::LEFTOVERS_OPTION], ['at' => 0])
    && $record['at'] !== 1700000000 && on_instance(OLD_OBJECTS) === OLD_OBJECTS);
check('... the message says nothing was created, to try again, and the way out when it keeps happening', plain($r, 'nothing was created. Try again in a moment.')
    && str_ends_with($r->message, 'If this keeps happening, check that your n8n API key is still valid by connecting again on the n8n Workflow page, or remove the Post Quality workflow and credentials in n8n by hand.'));
connected([MODELS_OK, DELETED, timed_out()], [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'credentials' => ['c-old1', 'c-old2'], 'at' => 1700000000]]);
$r = provision();
check('credentials only, the last one unreachable: no deactivate, the record keeps the one that remains', $r instanceof WP_Error && $r->data['step'] === 'cleanup'
    && calls() === [MODELS, DEL . 'credentials/c-old1', DEL . 'credentials/c-old2']
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-old2']]);
connected(PRO_OK, [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://old.example.com'] + $old[PQM_N8n::LEFTOVERS_OPTION]]);
$r = provision();
check('a record of another instance: dropped, nothing sent there, the normal run', calls() === CREATE_ALL && $r['provisioned'] === true
    && !isset($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION]));
connected([resp(401, ['type' => 'error'])], $old);
$r = provision();
check('a refused provider key: the leftovers are not touched (no n8n call)', $r->code === 'pqm_provider_key_invalid' && calls() === [MODELS] && untouched());

echo "Provisioning: the ping fails\n";
connected(PRO_OK);
$GLOBALS['tw']['ping_response'] = resp(404, ['code' => 404, 'message' => 'The requested webhook is not registered.']);
$r = provision();
check('200 {provisioned, ping_ok false, message}: points to pasting the Webhook Trigger URL', $r['provisioned'] === true && $r['ping_ok'] === false
    && str_contains($r['message'], 'n8n responded with 404') && str_contains($r['message'], 'paste it as the n8n Webhook URL'));
check('... everything kept: map stored, URL saved, no version recorded', isset($GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION])
    && $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] === HOOK && !isset($GLOBALS['tw']['options']['pqm_workflow_version_seen']));
check('... the failed ping recorded, so Set up the workflow stays open (setup incomplete); no status_html', ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === false
    && !isset($r['status_html']) && !pqm_setup_complete());
check('... no rollback: nothing deactivated, deleted or revoked, no leftovers recorded', calls() === CREATE_ALL && $GLOBALS['tw']['app_revoked'] === []
    && !in_array(PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));

echo "Provisioning: the provider key leak scan\n";
check('after every run (' . count($provision_runs) . '): the provider key and the Application Password are in no option and no ping',
    !array_filter($provision_runs, static fn($run) => str_contains(json_encode([$run[0], $run[2]]), PROVIDER_KEY) || str_contains(json_encode([$run[0], $run[2]]), APP_PASSWORD)));
check('... and in no outgoing call but the models request and the Anthropic credential body',
    !array_filter($provision_runs, static fn($run) => array_filter($run[1], static fn($c) => str_contains(json_encode($c), PROVIDER_KEY)
        && $c[0] !== 'https://api.anthropic.com/v1/models?limit=1' && !str_starts_with($c[1]['body'] ?? '', '{"name":"Anthropic - Claude"'))));

/* ─── The key never leaves the server ────────────────────────────────── */
echo "The key never leaves the server\n";
check('no REST reply above carries either n8n key, the provider key or the Application Password (' . count($replies) . ' replies)', $replies !== []
    && !array_filter($replies, static fn($r) => str_contains($r, KEY) || str_contains($r, OLD_KEY) || str_contains($r, PROVIDER_KEY) || str_contains($r, APP_PASSWORD)));
$src  = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-n8n.php');
$rest = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-rest-api.php');
check('the client and the REST class log nothing (no error_log, print_r, var_dump)', !preg_match('/\b(error_log|print_r|var_dump|var_export)\s*\(/', $src . $rest));
check('the client sends to n8n through wp_remote_request() in one place; its one wp_remote_get() goes to Anthropic', substr_count($src, 'wp_remote_request(') === 1
    && !str_contains($src, 'wp_remote_post(') && substr_count($src, 'wp_remote_get(') === 1 && str_contains($src, "wp_remote_get( 'https://api.anthropic.com/v1/models?limit=1'"));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
