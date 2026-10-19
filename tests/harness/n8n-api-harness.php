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
 * Entry 7 rows: the mis-wiring diagnosis a passing ping of the saved URL
 * runs, one per line of its plan's matrix, on workflows built from the
 * build's template with the ids rewired; every diagnosis call is a GET.
 * Entry 8 rows: the adoption of a hand-made workflow (GET|POST
 * /n8n/adoption), one per line of its plan's matrix, with freely named
 * credentials; every adoption call is a GET, and D3's rule (the map's
 * workflow only while the URL's path is the map's) in the diagnosis rows.
 * Entry 10 rows: the webhook token rotation (POST /n8n/token), one per line
 * of its plan's matrix; its one n8n call is the PATCH of the map's webhook
 * credential, and the new token is in no reply, error or other option.
 * Entry 9 rows: the one-click workflow update (POST /n8n/workflow), one per
 * line of its plan's matrix; its n8n calls are one GET then one PUT of the
 * map's workflow, the PUT body asserted (four keys, the map's ids on every
 * credentialed node, the map's path on the trigger, the read name), and the
 * status block's fix per gate.
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
/* WP core: add_option() writes only a key that does not exist yet (entry 8, adopt()). */
function add_option($k, $v, $deprecated = '', $autoload = null) {
    if (array_key_exists($k, $GLOBALS['tw']['options'])) { return false; }
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
function wp_generate_uuid4() { return $GLOBALS['tw']['uuid'] ?? 'stub-uuid'; } // entry 10 rotates to a distinct token
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
/* A ping answers the page's refreshed body (entry 6; story 67: the whole page under the H1). */
define('MINUTE_IN_SECONDS', 60);
function esc_html($s) { return (string) $s; }
function esc_html__($s, $d = null) { return (string) $s; } // the status block's re-import button label
function esc_html_e($s, $d = null) { echo $s; } // the status block's notices (entry 7: Test again)
function esc_attr($s) { return (string) $s; }
function esc_attr__($s, $d = null) { return (string) $s; }
function esc_attr_e($s, $d = null) { echo $s; }
function esc_url($s) { return (string) $s; }
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') { return $url . '&_wpnonce=stub'; }
function wp_kses($s, $allowed_html, $allowed_protocols = []) { return (string) $s; } // the build notice, unbuilt (CI)
function wp_date($format, $ts = null) { return gmdate($format, $ts ?? time()); }
/* Entry 7: the diagnosis lists node names through wp_sprintf('%l'), as core's wp_sprintf_l() joins them. */
function wp_sprintf($pattern, ...$args) {
    if ($pattern !== '%l') { return vsprintf($pattern, $args); }
    $list = array_values($args[0]);
    if (count($list) < 3) { return implode(' and ', $list); }
    return implode(', ', array_slice($list, 0, -1)) . ', and ' . end($list);
}
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
check('reply: base URL = the origin (editor path dropped), key set, its last 4 characters, the probe',
    $r['base_url'] === BASE && $r['key_set'] === true && $r['key_hint'] === '6789' && array_keys($r) === ['probe', 'base_url', 'key_set', 'key_hint', 'setup', 'adoptable', 'rotatable']);
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
    $s === ['base_url' => BASE, 'key_set' => true, 'key_hint' => '6789', 'probe' => $r['probe'], 'setup' => null, 'adoptable' => false, 'rotatable' => false] && $GLOBALS['tw']['calls'] === []);

/* ─── Rows: the key hint (last 4 characters, computed at read time) ──── */
echo "Key hint\n";
foreach ([
    'a 40-character key ending ab12: its last 4' => [str_repeat('k', 36) . 'ab12', 'ab12'],
    'a 16-character key (the shortest with a hint)' => ['0123456789abcdef', 'cdef'],
    'a 15-character key: set, no hint (too large a share)' => ['0123456789abcde', ''],
    'a 4-character key: set, no hint' => ['ab12', ''],
] as $label => [$stored_key, $hint]) {
    site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => $stored_key, 'probe' => null]]);
    $s = get_state();
    check($label, $s['key_set'] === true && $s['key_hint'] === $hint && $GLOBALS['tw']['writes'] === []);
}

/* ─── Row: Missing endpoint vs existing (D2, the 2.42.3 shapes) ─────── */
echo "Endpoint probe shapes\n";
site([], [LIST_OK, ROUTER_404]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('the router\'s generic 404 "not found" on activate = missing endpoint; only its cause stored (entry 2), no URL, no key', $r['probe']['cause'] === 'missing_endpoint'
    && $r['probe']['message'] === 'Your n8n lacks an API endpoint the setup needs. Update n8n to 2.42.3 or later. Or set up the workflow by hand with the Workflow File section.'
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['failed' => ['cause' => 'missing_endpoint', 'message' => 'Your n8n lacks an API endpoint the setup needs. Update n8n to 2.42.3 or later.', 'at' => $r['probe']['at']]]
    && PQM_N8n::public_state() === ['base_url' => '', 'key_set' => false, 'key_hint' => '', 'probe' => null, 'setup' => null, 'adoptable' => false, 'rotatable' => false]);
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
check('public_state() leaves the failure out (entries 8 and 10 add setup, adoptable and rotatable only)', array_keys(PQM_N8n::public_state()) === ['base_url', 'key_set', 'key_hint', 'probe', 'setup', 'adoptable', 'rotatable']);

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
site($stored_conn, [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('Connect with a new key: the reply carries the last 4 of the new key at once', $r['probe']['ok'] === true && $r['key_hint'] === '6789');
site($stored_conn, [resp(401, ['message' => 'unauthorized'])]);
$r = post(['base_url' => 'https://old.example.com', 'api_key' => KEY]);
check('Connect with a refused key: the hint of the stored key is unchanged', $r['probe']['cause'] === 'key' && $r['key_hint'] === '3210' && unchanged($stored_conn));
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
check('DELETE: key and probe removed, URL kept, "not set", no hint', $r === ['base_url' => 'https://old.example.com', 'key_set' => false, 'key_hint' => '', 'probe' => null, 'setup' => null, 'adoptable' => false, 'rotatable' => false]
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => 'https://old.example.com'] && $GLOBALS['tw']['autoload'][PQM_N8n::OPTION] === false);
check('... nothing sent to n8n', $GLOBALS['tw']['calls'] === []);
check('... a reload shows "not set"', get_state() === $r);
site();
check('fresh site GET: nothing stored, not set, no hint', get_state() === ['base_url' => '', 'key_set' => false, 'key_hint' => '', 'probe' => null, 'setup' => null, 'adoptable' => false, 'rotatable' => false] && $GLOBALS['tw']['writes'] === []);

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

/* Entry 7: the workflow as n8n stores it, read back by the passing ping's diagnosis. */
const WEBHOOK_CRED   = ['id' => 'c-webhook', 'name' => 'Post Quality webhook - example.com'];
const ANTHROPIC_CRED = ['id' => 'c-anthropic', 'name' => 'Anthropic - Claude'];
const WP_CRED        = ['id' => 'c-wp', 'name' => 'WordPress - example.com'];
const WIRED          = ['Post Quality webhook - example.com' => WEBHOOK_CRED, 'Anthropic - Claude' => ANTHROPIC_CRED, 'WordPress - example.com' => WP_CRED];

/**
 * The build's template nodes (pqm_workflow_json()) with the ids rewired:
 * template credential name => the {id, name} n8n stores, or null (name-only).
 * `$edit`: node name => fn(array $node): array, for a single node.
 */
function wired(array $credentials = WIRED, array $edit = []): array {
    $nodes = json_decode((string) pqm_workflow_json(), true)['nodes'];
    foreach ($nodes as &$node) {
        foreach ($node['credentials'] ?? [] as $type => $ref) {
            $node['credentials'][$type] = $credentials[$ref['name']] ?? ['name' => $ref['name']];
        }
        if (isset($edit[$node['name']])) {
            $node = $edit[$node['name']]($node);
        }
    }
    return $nodes;
}

/** The same nodes, the Webhook Trigger listening on $path. */
function with_path(array $nodes, string $path): array {
    return array_map(static function ($n) use ($path) {
        if ($n['type'] === 'n8n-nodes-base.webhook') { $n['parameters']['path'] = $path; }
        return $n;
    }, $nodes);
}

/** A workflow as GET /workflows answers it: draft `nodes`, and `activeVersion` = the published version. */
function stored_workflow(string $id, array $published, ?array $draft = null): array {
    return ['id' => $id, 'name' => 'Post Quality — example.com', 'active' => true, 'nodes' => $draft ?? $published, 'connections' => [],
        'activeVersion' => ['versionId' => "v-$id", 'nodes' => $published, 'connections' => []]];
}

define('CLEAN_WF', resp(200, stored_workflow('wf1', wired())));
const READ_WF = 'GET ' . BASE . '/api/v1/workflows/wf1';
define('PRO_OK', [MODELS_OK, created('c-webhook'), created('c-anthropic'), created('c-wp'), created('wf1'), ACTIVE, CLEAN_WF]);

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
check('calls, in order: models GET, 3x POST /credentials, POST /workflows, activate; then the diagnosis of the ping reads the workflow (entry 7)', calls() === [
    MODELS,
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/workflows',
    'POST ' . BASE . '/api/v1/workflows/wf1/activate',
    READ_WF,
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
check('reply: {provisioned, ping_ok, workflow_id, message, rewrite_ready, status_html}', array_keys($r) === ['provisioned', 'ping_ok', 'workflow_id', 'message', 'rewrite_ready', 'status_html']
    && $r['provisioned'] === true && $r['ping_ok'] === true && $r['workflow_id'] === 'wf1' && $r['rewrite_ready'] === true
    && str_starts_with($r['message'], 'Ping OK'));
check('... status_html = the page\'s refreshed body with the message first (entry 6; story 67): set up', $r['status_html'] === PQM_Admin::render_workflow_body($r['message'], 'success')
    && str_contains($r['status_html'], '<div class="pqm-workflow-status" data-state="set-up">') && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">' . $r['message'] . '</p>'));
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
// What n8n stores of it: Update WP Post (Rewrite) without a credential id.
connected([MODELS_OK, created('c-webhook'), created('c-anthropic'), created('wf1'), ACTIVE,
    resp(200, stored_workflow('wf1', wired(['WordPress - example.com' => null] + WIRED)))]);
$GLOBALS['tw']['app_passwords'] = false;
$r = provision();
$links = linked(body(3));
check('entry 7, D1: the diagnosis reads GET /workflows/{id} only and skips the name-only Update WP Post: clean, nothing stored, the ping message unchanged',
    calls()[5] === READ_WF && !isset(pqm_workflow_report()['miswired']) && str_starts_with($r['message'], 'Ping OK'));
check('2 credentials, no Application Password, no httpBasicAuth credential', count(calls()) === 6 && $GLOBALS['tw']['app_created'] === []
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
    calls() === array_merge([MODELS], $cleanup, array_slice(CREATE_ALL, 1), [READ_WF]) && rollback_ordered() && $r['provisioned'] === true && $GLOBALS['tw']['queue'] === []);
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
check('a record of another instance: dropped, nothing sent there, the normal run', calls() === [...CREATE_ALL, READ_WF] && $r['provisioned'] === true
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
check('... everything kept: map stored, URL saved, no version recorded (the failure is: story 67)', isset($GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION])
    && $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] === HOOK && pqm_workflow_report()['version'] === 0
    && str_starts_with(pqm_workflow_report()['failed']['message'] ?? '', 'n8n responded with 404'));
check('... the failed ping recorded, so Set up the workflow stays open (setup incomplete); status_html needs attention, the message its outcome line', ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === false
    && str_contains($r['status_html'], 'data-state="failing"') && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-error">' . $r['message'] . '</p>') && !pqm_setup_complete());
check('... no rollback: nothing deactivated, deleted or revoked, no leftovers recorded', calls() === CREATE_ALL && $GLOBALS['tw']['app_revoked'] === []
    && !in_array(PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));

echo "Provisioning: the provider key leak scan\n";
check('after every run (' . count($provision_runs) . '): the provider key and the Application Password are in no option and no ping',
    !array_filter($provision_runs, static fn($run) => str_contains(json_encode([$run[0], $run[2]]), PROVIDER_KEY) || str_contains(json_encode([$run[0], $run[2]]), APP_PASSWORD)));
check('... and in no outgoing call but the models request and the Anthropic credential body',
    !array_filter($provision_runs, static fn($run) => array_filter($run[1], static fn($c) => str_contains(json_encode($c), PROVIDER_KEY)
        && $c[0] !== 'https://api.anthropic.com/v1/models?limit=1' && !str_starts_with($c[1]['body'] ?? '', '{"name":"Anthropic - Claude"'))));

/* ─── Entry 7: the handshake reports a mis-wired workflow ───────────── */
echo "Diagnosis: one row per line of the plan's matrix\n";
const LIST_READ = 'GET ' . BASE . '/api/v1/workflows?active=true&limit=250&excludePinnedData=true';
const V5_HOOK   = BASE . '/webhook/pqm-execute';
const SHARED    = ['id' => 'c-hdr', 'name' => 'Header Auth account']; // n8n's default name for a Header Auth credential
const SHARED_SENTENCE = 'In n8n, the webhook credential “Header Auth account” is set on Claude - Review Agent, Claude - Takeaways Agent, Claude - Sources Agent, Claude - SEO Agent, Claude - Audit Agent, and Claude - Rewrite Agent instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again.';
const MISSING_SENTENCE = 'In n8n, no credential is set on Update WP Post (Rewrite): add one there, publish the workflow, then test again.';
const PING_OK  = 'Ping OK — this site reaches n8n, the webhook credential is right and your workflow is up to date. Your first review confirms the return path.';
$diagnosis_calls = []; // every call the rows below made, for the GET-only check
$status_blocks   = []; // every status block a connect ping below answered
$setup_map = [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic', 'wordpress' => 'c-wp'], 'path' => 'pqm-execute-example-com', 'wizard_created' => true, 'at' => 1]];
$miswired_map = static fn(array $published, ?array $draft = null): array => [resp(200, stored_workflow('wf1', $published, $draft))];

/** POST /setup/connect: save the URL, ping it, record (the dialog's connect step, D2). */
function connect_ping(string $url): array {
    $r = PQM_REST_API::setup_connect_callback(new WP_REST_Request(['webhook_url' => $url]))->get_data();
    $GLOBALS['replies'][] = json_encode($r);
    $GLOBALS['diagnosis_calls'] = array_merge($GLOBALS['diagnosis_calls'], $GLOBALS['tw']['calls']);
    $GLOBALS['status_blocks'][]  = $r['status_html'] ?? '';
    return $r;
}
/** diagnose() on the given site, its calls kept for the GET-only check. */
function diagnosed(string $url = HOOK): ?string {
    $r = PQM_N8n::diagnose($url);
    $GLOBALS['diagnosis_calls'] = array_merge($GLOBALS['diagnosis_calls'], $GLOBALS['tw']['calls']);
    return $r;
}
/** The page's body holds exactly one primary action in every state (story 67). */
function one_primary(string $html): bool {
    return substr_count($html, 'button-primary') === 1;
}
/** The status block's state accent (data-state), '' when no block. */
function state_of(string $html): string {
    return preg_match('/<div class="pqm-workflow-status" data-state="([a-z-]+)">/', $html, $m) ? $m[1] : '';
}
/** A REST reply's JSON without its status_html (the page's body, which the saving page shows anyway). */
function without_body(string $json): string {
    $reply = json_decode($json, true);
    return is_array($reply) ? json_encode(array_diff_key($reply, ['status_html' => 1])) : $json;
}
$stored = static fn() => pqm_workflow_report()['miswired'] ?? null;
$a_sentence = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000, 'miswired' => SHARED_SENTENCE]];

// Row: the 2026-10-13 shape — no setup map, the v5 path, the trigger's Header Auth credential spread to every Claude node.
$oct13 = with_path(wired(['Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + WIRED), 'pqm-execute');
connected([resp(200, ['data' => [stored_workflow('wf-other', with_path(wired(), 'pqm-execute-other-example')), stored_workflow('wf-prod', $oct13)], 'nextCursor' => null])]);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => 5]);
$r = connect_ping(V5_HOOK);
check('2026-10-13 shape: one GET of the active workflows, signed; the one whose trigger path is the URL\'s last segment is read', calls() === [LIST_READ] && calls_signed(KEY));
check('... the reply: the sentence names the six Claude nodes, the credential and the fix; a warning (outdated, D2), the step ticks',
    $r['ping_ok'] === true && $r['message'] === SHARED_SENTENCE && $r['outdated'] === true
    && ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === true);
check('... the sentence stored with the report; the reply keeps its shape', $stored() === SHARED_SENTENCE
    && array_keys($r) === ['saved', 'ping_ok', 'outdated', 'message', 'status_html']);
$block = $r['status_html'];
check('... the body: needs attention (failing), the sentence is the status sentence, Test again the one primary; the message its outcome line', state_of($block) === 'failing'
    && str_contains($block, '<p class="pqm-workflow-sentence">' . SHARED_SENTENCE . '</p>') && str_contains($block, '<button type="button" class="button button-primary pqm-test-connection">Test again</button>')
    && one_primary($block) && str_contains($block, '<p class="pqm-workflow-outcome is-warning">' . SHARED_SENTENCE . '</p>'));
check('... the mis-wiring comes first among the fixes; the v5 workflow is also older and hand-made: Re-import the workflow secondary after Test again, its Set up by hand disclosure present, no update',
    str_contains($block, 'Test again</button> <a class="button pqm-reimport" href="#pqm-by-hand">') && str_contains($block, 'id="pqm-by-hand"') && !str_contains($block, 'pqm-update-workflow'));

// Row: a node that needs a credential has none (no setup map: every rule applies, D1).
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(['WordPress - example.com' => null] + WIRED))]])]);
check('missing credential: the sentence names Update WP Post (Rewrite) and the fix', diagnosed() === MISSING_SENTENCE && calls() === [LIST_READ]);
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(['WordPress - example.com' => null, 'Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + WIRED))]])]);
check('... shared and missing at once: the shared credential is reported first', diagnosed() === SHARED_SENTENCE);
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(WIRED, ['Claude - SEO Agent' => static function ($n) { unset($n['credentials']); return $n; }]))]])]);
check('... a Claude node with no credential at all is missing too', diagnosed() === 'In n8n, no credential is set on Claude - SEO Agent: add one there, publish the workflow, then test again.');
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(['Post Quality webhook - example.com' => ['id' => 'c-webhook', 'name' => ''], 'Anthropic - Claude' => ['id' => 'c-webhook', 'name' => '']] + WIRED))]])]);
check('... a shared credential n8n gives no name: the sentence without one', str_starts_with((string) diagnosed(), 'In n8n, the webhook credential is set on Claude - Review Agent, '));

// Row: provisioned — GET /workflows/{id} only (the provisioning rows above run it end to end).
connected($miswired_map(wired()), $setup_map);
check('provisioned: GET /workflows/{id} only, clean', diagnosed() === '' && calls() === [READ_WF]);
connected($miswired_map(wired(['WordPress - example.com' => null] + WIRED)), [PQM_N8n::SETUP_OPTION => ['credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic']] + $setup_map[PQM_N8n::SETUP_OPTION]]);
check('... without a wordpress credential in the map (D1): Update WP Post (Rewrite) skipped, clean', diagnosed() === '');
connected($miswired_map(wired(['WordPress - example.com' => null] + WIRED)), $setup_map);
check('... with one in the map, the same node is reported', diagnosed() === MISSING_SENTENCE);
// Entry 8, D3: the map's workflow only while the URL's path is the map's; else the workflow the URL calls.
connected($miswired_map(wired()), $setup_map);
check('... D3, paths agree: the map\'s workflow, GET /workflows/{id}', diagnosed(BASE . '/webhook/pqm-execute-example-com/') === '' && calls() === [READ_WF]);
connected([resp(200, ['data' => [stored_workflow('wf-else', with_path($oct13, 'elsewhere'))], 'nextCursor' => null])], $setup_map);
check('... D3, the URL now names another path: the list read, that workflow diagnosed; the map kept', diagnosed(BASE . '/webhook/elsewhere') === SHARED_SENTENCE
    && calls() === [LIST_READ] && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $setup_map[PQM_N8n::SETUP_OPTION]);
connected([resp(200, ['data' => [stored_workflow('wf-else', with_path(wired(['WordPress - example.com' => null] + WIRED), 'elsewhere'))]])],
    [PQM_N8n::SETUP_OPTION => ['credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic']] + $setup_map[PQM_N8n::SETUP_OPTION]]);
check('... D3, another path: the map\'s D1 skip does not reach that workflow (its Update WP Post is reported)', diagnosed(BASE . '/webhook/elsewhere') === MISSING_SENTENCE);

// Row: fixed in the draft, not published — the published version runs.
connected($miswired_map($oct13, wired()), $setup_map);
check('draft fixed, not published: reported (activeVersion is read, not nodes)', diagnosed() === SHARED_SENTENCE);
connected($miswired_map(wired(), $oct13), $setup_map);
check('... and the reverse: a mis-wired draft over a clean published version is clean', diagnosed() === '');
connected([resp(200, ['activeVersion' => null] + stored_workflow('wf1', $oct13))], $setup_map);
check('... activeVersion null: the draft nodes are read', diagnosed() === SHARED_SENTENCE);

// Row: a disabled node is not reported.
connected($miswired_map(wired(['WordPress - example.com' => null] + WIRED, ['Update WP Post (Rewrite)' => static fn($n) => ['disabled' => true] + $n])), $setup_map);
check('disabled node without a credential: not reported', diagnosed() === '');

// Row: no key stored — the stored sentence clears on the next test, zero n8n calls.
site(['pqm_settings' => ['webhook_token' => TOKEN]] + $a_sentence);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION]);
$r = connect_ping(HOOK);
check('no key stored: diagnose answers \'\', zero calls; the sentence clears, the handshake is ping-only', PQM_N8n::diagnose(HOOK) === '' && calls() === []
    && $stored() === null && $r['message'] === PING_OK && $r['outdated'] === false && state_of($r['status_html']) === 'set-up');

// Row: the check could not run — the stored sentence (or none) stays, the ping message unchanged.
foreach ([
    'n8n unreachable (WP_Error)'           => [timed_out()],
    'the key refused (401)'                => [resp(401, ['message' => 'unauthorized'])],
    'a proxy\'s non-JSON 200'              => [resp(200, '<html>ok</html>')],
    'no active workflow with this path'    => [resp(200, ['data' => [stored_workflow('wf-other', with_path($oct13, 'pqm-execute-other-example'))], 'nextCursor' => null])],
    'an empty list'                        => [LIST_OK],
] as $label => $queue) {
    connected($queue, $a_sentence);
    $r = connect_ping(HOOK);
    check("could not run ($label): the ping message unchanged, the stored sentence kept", $r['ping_ok'] === true && $r['message'] === PING_OK
        && $r['outdated'] === false && $stored() === SHARED_SENTENCE && calls() === [LIST_READ]);
}
connected([NOT_FOUND], $setup_map + $a_sentence);
$r = connect_ping(HOOK);
check('could not run (the setup map\'s workflow is gone, 404): the stored sentence kept, still shown', calls() === [READ_WF] && $r['message'] === PING_OK
    && $stored() === SHARED_SENTENCE && str_contains($r['status_html'], SHARED_SENTENCE));
connected([resp(200, (object) [])], $setup_map + $a_sentence);
$r = connect_ping(HOOK);
check('could not run (a 2xx `{}` by id: no nodes, no trigger): null, the stored sentence kept', calls() === [READ_WF] && $r['message'] === PING_OK && $stored() === SHARED_SENTENCE);
connected([LIST_OK]);
check('... with none stored, none stored', connect_ping(HOOK)['message'] === PING_OK && $stored() === null);
connected([]);
check('... a URL without /webhook/: null, no call', PQM_N8n::diagnose(BASE . '/hooks/pqm-execute') === null && calls() === []);

// Row: a command reports the version — only a test clears the sentence (rest-reviews-harness runs the callback itself).
connected([], $a_sentence);
pqm_record_workflow_version(PQM_WORKFLOW_VERSION, 'command', 'pro');
check('command report while a sentence is stored: kept', $stored() === SHARED_SENTENCE && pqm_workflow_report()['via'] === 'command');
pqm_record_workflow_version(PQM_WORKFLOW_VERSION, 'test', 'pro', null);
check('... a test that could not diagnose (null): kept', $stored() === SHARED_SENTENCE);
pqm_record_workflow_version(PQM_WORKFLOW_VERSION, 'test', 'pro', '');
check('... a clean test (\'\'): cleared, the stored report back to its shape before entry 7',
    $GLOBALS['tw']['options']['pqm_workflow_version_seen'] === ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => $GLOBALS['tw']['options']['pqm_workflow_version_seen']['at']]);

// Row: fixed in n8n, published, Test again — the status shows the version line.
connected($miswired_map(wired()), $setup_map + $a_sentence);
$r = connect_ping(HOOK);
check('fixed, Test again: \'\' stored (the sentence gone), the up-to-date message', $stored() === null && $r['message'] === PING_OK && $r['outdated'] === false);
check('... the body: set up, the version line, one primary (the first review)', state_of($r['status_html']) === 'set-up' && one_primary($r['status_html'])
    && str_contains($r['status_html'], 'Version ' . PQM_WORKFLOW_VERSION . ' · checked just now by a test.') && str_contains($r['status_html'], 'Run your first review'));

// The Settings field pings an unsaved URL: not diagnosed.
connected([LIST_OK]);
$GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] = HOOK;
$ping = PQM_Admin::ping_webhook(BASE . '/webhook/pqm-execute-unsaved');
check('a ping of an unsaved URL: miswired null, no n8n call', array_key_exists('miswired', $ping) && $ping['miswired'] === null && calls() === []);
$ping = PQM_Admin::ping_webhook(HOOK);
check('... the saved URL: diagnosed', $ping['miswired'] === null && calls() === [LIST_READ]); // LIST_OK holds no workflow: could not run
$GLOBALS['tw']['ping_response'] = resp(404, ['message' => 'not registered']);
$GLOBALS['tw']['calls'] = [];
check('... a failed ping: no miswired key, no n8n call', !array_key_exists('miswired', PQM_Admin::ping_webhook(HOOK)) && calls() === []);

echo "Diagnosis: n8n is only read\n";
check('every call the diagnosis rows made is a GET (' . count($diagnosis_calls) . ' calls)', $diagnosis_calls !== []
    && array_unique(array_map(static fn($c) => $c[1]['method'], $diagnosis_calls)) === ['GET']);
check('every status block the pings above answered (' . count($status_blocks) . '): at most one primary, one whenever a notice shows',
    count($status_blocks) >= 9 && !array_filter($status_blocks, static fn($b) => !one_primary($b)));

/* ─── Entry 8: an Administrator adopts an existing manual setup ─────── */
echo "Adoption: routes\n";
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/n8n/adoption'] ?? [];
check('/n8n/adoption: GET and POST, each gated by check_manage_settings; POST requires workflow_id (string)', array_column($route, 'methods') === ['GET', 'POST']
    && array_unique(array_column($route, 'permission_callback'), SORT_REGULAR) === [$gate]
    && ($route[1]['args']['workflow_id'] ?? null) === ['required' => true, 'type' => 'string']);

// A hand-made setup: credential names chosen freely in n8n, never matched.
const HAND_HOOK   = ['id' => 'c-h1', 'name' => 'Header Auth account'];
const HAND_CLAUDE = ['id' => 'c-k1', 'name' => 'my claude key'];
const HAND_WP     = ['id' => 'c-b1', 'name' => 'wp basic'];
const HAND_NAMES  = ['Post Quality webhook - example.com' => HAND_HOOK, 'Anthropic - Claude' => HAND_CLAUDE, 'WordPress - example.com' => HAND_WP];
const READ_HAND   = 'GET ' . BASE . '/api/v1/workflows/wf-hand';
const HAND_MAP_AT = 1700000500;
$adoption_calls = []; // every call the adoption rows made, for the GET-only check

/** The active list: another site's workflow, then the hand-made one on $path. */
function hand_list(array $nodes, string $path = 'pqm-execute'): array {
    return resp(200, ['data' => [
        stored_workflow('wf-other', with_path(wired(), 'pqm-execute-other-example')),
        ['name' => 'My blog reviews'] + stored_workflow('wf-hand', with_path($nodes, $path)),
    ], 'nextCursor' => null]);
}
/** A site set up by hand: a passing key, the webhook URL saved, no setup map. */
function hand_made(array $queue, string $url = V5_HOOK, array $extra = []): void {
    connected($queue, $extra);
    $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] = $url;
    $GLOBALS['tw']['writes'] = [];
}
function proposal() {
    $r = PQM_REST_API::get_n8n_adoption_callback();
    $GLOBALS['replies'][] = $r instanceof WP_Error ? json_encode([$r->code, $r->message, $r->data]) : json_encode($r->get_data());
    $GLOBALS['adoption_calls'] = array_merge($GLOBALS['adoption_calls'], $GLOBALS['tw']['calls']);
    return $r instanceof WP_Error ? $r : $r->get_data();
}
function adopt_post(string $workflow_id) {
    $r = PQM_REST_API::adopt_n8n_callback(new WP_REST_Request(['workflow_id' => $workflow_id]));
    $GLOBALS['replies'][] = $r instanceof WP_Error ? json_encode([$r->code, $r->message, $r->data]) : json_encode($r->get_data());
    $GLOBALS['adoption_calls'] = array_merge($GLOBALS['adoption_calls'], $GLOBALS['tw']['calls']);
    return $r instanceof WP_Error ? $r : $r->get_data();
}
/** A refusal: this code and status, zero writes, no ping. */
function refused($r, string $code, int $status): bool {
    return $r instanceof WP_Error && $r->code === $code && $r->data === ['status' => $status] && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === [];
}
$hand_nodes = wired(HAND_NAMES);
$proposed   = [
    'workflow'    => ['id' => 'wf-hand', 'name' => 'My blog reviews', 'path' => 'pqm-execute'],
    'credentials' => ['webhook' => HAND_HOOK, 'anthropic' => HAND_CLAUDE, 'wordpress' => HAND_WP],
];

echo "Adoption: offered or not\n";
hand_made([]);
$s = get_state();
check('offer: a passing key, the URL saved, no map — adoptable, no setup; zero calls', $s['adoptable'] === true && $s['setup'] === null && calls() === []);
foreach ([
    'no key' => static function () { unset($GLOBALS['tw']['options'][PQM_N8n::OPTION]['api_key']); },
    'a key without a passing check' => static function () { $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe'] = null; },
    'no URL saved' => static function () { unset($GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url']); },
] as $label => $change) {
    hand_made([]);
    $change();
    check("not offered ($label): adoptable false", get_state()['adoptable'] === false);
}
foreach ([true, false] as $wizard_created) {
    hand_made([], V5_HOOK, [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => [], 'path' => 'pqm-execute', 'wizard_created' => $wizard_created, 'at' => HAND_MAP_AT]]);
    $s = get_state();
    check('not offered (a map, wizard_created ' . var_export($wizard_created, true) . '): adoptable false, setup = {wizard_created, at}',
        $s['adoptable'] === false && $s['setup'] === ['wizard_created' => $wizard_created, 'at' => HAND_MAP_AT]);
}

echo "Adoption: the proposal\n";
hand_made([hand_list($hand_nodes)]);
$r = proposal();
check('v5 path, free names: the workflow at the URL\'s path, its credentials by role from its own nodes, by name; rewrite_ready (Pro)',
    $r === $proposed + ['rewrite_ready' => true]);
check('... one GET, the list read of the diagnosis, signed; nothing written, no ping', calls() === [LIST_READ] && calls_signed(KEY) && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === []);
hand_made([hand_list($hand_nodes, '/my-own-path/')], BASE . '/webhook/my-own-path');
check('a custom trigger path: found by it, the path as the trigger carries it (slashes trimmed)', proposal()['workflow'] === ['id' => 'wf-hand', 'name' => 'My blog reviews', 'path' => 'my-own-path']);

echo "Adoption: not adoptable — zero calls\n";
foreach (['proposal' => static fn() => proposal(), 'confirm' => static fn() => adopt_post('wf-hand')] as $which => $run) {
    site(['pqm_settings' => ['n8n_webhook_url' => V5_HOOK]]);
    $GLOBALS['tw']['pings'] = [];
    check("$which, no key: 400 pqm_n8n_not_connected", refused($run(), 'pqm_n8n_not_connected', 400) && calls() === []);
    hand_made([]);
    $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe'] = null;
    check("$which, a key without a passing check: 400 pqm_n8n_not_connected", refused($run(), 'pqm_n8n_not_connected', 400) && calls() === []);
    hand_made([], '');
    check("$which, no URL saved: 400 pqm_webhook_url_missing", refused($run(), 'pqm_webhook_url_missing', 400) && calls() === []);
    $map = [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => [], 'path' => 'pqm-execute', 'wizard_created' => true, 'at' => 1]];
    hand_made([], V5_HOOK, $map);
    $r = $run();
    check("$which, a map exists: 409 pqm_already_provisioned, provision's message; the map untouched", refused($r, 'pqm_already_provisioned', 409)
        && $r->message === 'n8n is already set up for this site.' && calls() === [] && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $map[PQM_N8n::SETUP_OPTION]);
}

echo "Adoption: no workflow at the URL\n";
foreach ([
    'another path only' => [[hand_list($hand_nodes, 'pqm-execute-elsewhere')], V5_HOOK, [LIST_READ]],
    'an empty list'     => [[LIST_OK], V5_HOOK, [LIST_READ]],
    'a workflow there without a Claude node' => [[hand_list(array_values(array_filter($hand_nodes, static fn($n) => !str_contains($n['parameters']['url'] ?? '', 'api.anthropic.com'))))], V5_HOOK, [LIST_READ]],
    'a URL without /webhook/' => [[], BASE . '/hooks/pqm-execute', []],
] as $label => [$queue, $url, $expected]) {
    hand_made($queue, $url);
    $r = proposal();
    check("$label: 404 pqm_workflow_not_found, check the URL or publish, then try again", refused($r, 'pqm_workflow_not_found', 404)
        && str_contains($r->message, 'Check the URL, or publish the workflow in n8n, then try again.') && calls() === $expected);
}

echo "Adoption: n8n did not answer\n";
foreach ([
    'unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
    'a 500'                  => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'the key refused (401)'  => [resp(401, ['message' => 'unauthorized']), 'n8n answered HTTP 401'],
    'a proxy\'s non-JSON 200' => [resp(200, '<html>Just a moment...</html>'), 'n8n did not answer with your workflows'],
] as $label => [$answer, $says]) {
    hand_made([$answer]);
    $r = proposal();
    check("$label: 502 pqm_adoption_failed, could_not()'s sentence, n8n's body never repeated", refused($r, 'pqm_adoption_failed', 502)
        && $r->message === "The plugin could not finish reading your workflows ($says). Try again in a moment."
        && !str_contains($r->message, 'Internal error') && !str_contains($r->message, 'Just a moment'));
}

echo "Adoption: mis-wired workflows are refused with the diagnosis sentence\n";
$then_try = static fn(string $sentence) => str_replace('then test again.', 'then try again.', $sentence);
hand_made([hand_list(wired(['Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + HAND_NAMES))]);
$r = proposal();
check('shared (the 2026-10-13 shape): 409 pqm_workflow_miswired, the 6.7 sentence ending "then try again."', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === $then_try(SHARED_SENTENCE));
hand_made([hand_list(wired(HAND_NAMES, ['Claude - SEO Agent' => static function ($n) { unset($n['credentials']); return $n; }]))]);
$r = proposal();
check('none (a Claude node without a credential): 409, the missing sentence', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === 'In n8n, no credential is set on Claude - SEO Agent: add one there, publish the workflow, then try again.');
hand_made([hand_list(wired(['Post Quality webhook - example.com' => ['name' => 'x']] + HAND_NAMES))]);
$r = proposal();
check('... the trigger without a credential: 409, the sentence names it', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === 'In n8n, no credential is set on Webhook Trigger: add one there, publish the workflow, then try again.');
hand_made([hand_list(wired(HAND_NAMES, ['Claude - SEO Agent' => static fn($n) => array_replace_recursive($n, ['credentials' => ['httpHeaderAuth' => ['id' => 'c-k2', 'name' => 'old claude key']]])]))]);
$r = proposal();
check('two Anthropic credentials: 409 pqm_workflow_miswired, a sentence naming the Claude nodes: pick one credential, publish, try again', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === 'In n8n, Claude - Review Agent, Claude - Takeaways Agent, Claude - Sources Agent, Claude - SEO Agent, Claude - Audit Agent, and Claude - Rewrite Agent use different credentials: pick one Anthropic credential on all of them, publish the workflow, then try again.');

echo "Adoption: Rewrite unwired (Pro, D2)\n";
$unwired = wired(['WordPress - example.com' => null] + HAND_NAMES);
hand_made([hand_list($unwired)]);
$r = proposal();
check('Update WP Post (Rewrite) without a credential: the proposal without wordpress, rewrite_ready false', $r === [
    'workflow' => $proposed['workflow'], 'credentials' => ['webhook' => HAND_HOOK, 'anthropic' => HAND_CLAUDE], 'rewrite_ready' => false]);
hand_made([hand_list($unwired)]);
$r = adopt_post('wf-hand');
check('... confirmed: the map stores no wordpress, the reply says rewrite_ready false', $r['rewrite_ready'] === false
    && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]['credentials'] === ['webhook' => 'c-h1', 'anthropic' => 'c-k1']);
$GLOBALS['tw']['queue'] = [resp(200, ['name' => 'My blog reviews'] + stored_workflow('wf-hand', with_path($unwired, 'pqm-execute')))];
$GLOBALS['tw']['calls'] = [];
check('... so the diagnosis then skips that node (6.7\'s D1): clean, by id', diagnosed(V5_HOOK) === '' && calls() === [READ_HAND]);

echo "Adoption: confirm\n";
hand_made([hand_list($hand_nodes)]);
$r   = adopt_post('wf-hand');
$map = $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] ?? null;
check('the map stored in provision()\'s shape: {workflow_id, credentials: {webhook, anthropic, wordpress}, path: the trigger\'s, wizard_created false, at}, autoload off',
    $map === ['workflow_id' => 'wf-hand', 'credentials' => ['webhook' => 'c-h1', 'anthropic' => 'c-k1', 'wordpress' => 'c-b1'], 'path' => 'pqm-execute', 'wizard_created' => false, 'at' => $map['at'] ?? null]
    && abs($map['at'] - time()) <= 5 && $GLOBALS['tw']['autoload'][PQM_N8n::SETUP_OPTION] === false);
check('... the same keys as a provisioned map, in the same order', array_keys($map) === array_keys($setup_map[PQM_N8n::SETUP_OPTION])
    && array_keys($map['credentials']) === array_keys($setup_map[PQM_N8n::SETUP_OPTION]['credentials']));
check('... the proposal read again, one GET; nothing else written (report, progress, settings untouched), no ping', calls() === [LIST_READ]
    && $GLOBALS['tw']['writes'] === [PQM_N8n::SETUP_OPTION] && $GLOBALS['tw']['pings'] === []);
check('... reply = public_state() + rewrite_ready: managed since now, no longer adoptable', $r === PQM_N8n::public_state() + ['rewrite_ready' => true]
    && $r['setup'] === ['wizard_created' => false, 'at' => $map['at']] && $r['adoptable'] === false && $r['key_set'] === true);
$adopted = $GLOBALS['tw']['options'];
$GLOBALS['tw']['calls']  = [];
$GLOBALS['tw']['writes'] = [];
check('... a second confirm: 409 pqm_already_provisioned, zero calls, the map kept', refused(adopt_post('wf-hand'), 'pqm_already_provisioned', 409) && calls() === []
    && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $map);

// A provisioning run stores its map while the confirm's read is under way: add_option() keeps it.
$raced = ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic'], 'path' => 'pqm-execute', 'wizard_created' => true, 'at' => HAND_MAP_AT];
hand_made([static function () use ($hand_nodes, $raced) { $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] = $raced; return hand_list($hand_nodes); }]);
$r = adopt_post('wf-hand');
check('a map stored between the gate and the write: 409 pqm_already_provisioned, the map untouched, nothing written', refused($r, 'pqm_already_provisioned', 409)
    && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $raced && calls() === [LIST_READ]);

echo "Adoption: changed since the proposal\n";
hand_made([hand_list($hand_nodes)]);
$r = adopt_post('wf-shown-earlier');
check('another workflow listens there now: 409 pqm_adoption_changed, look again; nothing stored', refused($r, 'pqm_adoption_changed', 409)
    && $r->message === 'n8n changed since you looked: another workflow listens on your n8n Webhook URL now. Press Let the plugin manage this workflow again to see it.' && calls() === [LIST_READ]);
hand_made([hand_list($hand_nodes)]);
check('... an empty id: the same 409', refused(adopt_post(''), 'pqm_adoption_changed', 409));
hand_made([timed_out()]);
check('the fresh read fails: its own error (502 pqm_adoption_failed), nothing stored', refused(adopt_post('wf-hand'), 'pqm_adoption_failed', 502));
hand_made([hand_list(wired(['Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + HAND_NAMES))]);
check('... mis-wired since: its own error (409 pqm_workflow_miswired), nothing stored', refused(adopt_post('wf-hand'), 'pqm_workflow_miswired', 409));

echo "Adoption: after\n";
$GLOBALS['tw']['options'] = $adopted;
$s = PQM_REST_API::get_setup_callback()->get_data();
$replies[] = json_encode($s);
check('wizard after (URL saved, no failed ping): provisioned, not manual, Connect your n8n and Set up the workflow ticked', $s['provisioned'] === true && $s['manual'] === false
    && array_slice($s['steps'], 0, 3, true) === ['n8n' => true, 'provision' => true, 'manual' => true]);
$GLOBALS['tw']['queue'] = [resp(200, stored_workflow('wf-hand', with_path($hand_nodes, 'pqm-execute')))];
$GLOBALS['tw']['calls'] = [];
$r = connect_ping(V5_HOOK);
$adoption_calls = array_merge($adoption_calls, $GLOBALS['tw']['calls']);
check('diagnosis after: a test ping of the saved URL reads GET /workflows/{id} (the paths agree), clean', calls() === [READ_HAND] && $r['message'] === PING_OK);
$GLOBALS['tw']['queue'] = [hand_list($hand_nodes, 'pqm-execute-example-com')];
$GLOBALS['tw']['calls'] = [];
$r = connect_ping(HOOK);
$adoption_calls = array_merge($adoption_calls, $GLOBALS['tw']['calls']);
check('... the URL changed to another path: the list read, the map kept', calls() === [LIST_READ] && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $map);
$GLOBALS['tw']['options'] = $adopted;
$GLOBALS['tw']['calls']   = [];
$r = forget();
check('forget key after: the map stays, not adoptable, the managed line stays (setup)', $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $map
    && $r['adoptable'] === false && $r['setup'] === ['wizard_created' => false, 'at' => $map['at']] && calls() === []);

echo "Adoption: n8n is only read\n";
check('every call the adoption rows made is a GET (' . count($adoption_calls) . ' calls)', count($adoption_calls) >= 20
    && array_unique(array_map(static fn($c) => $c[1]['method'], $adoption_calls)) === ['GET']);
$src_n8n = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-n8n.php');
preg_match('/function propose_adoption\(.*?\n    \/\* ─── Provisioning/s', $src_n8n, $m);
check('the adoption code sends no POST, PUT, PATCH or DELETE and never calls GET /credentials', ($m[0] ?? '') !== ''
    && !preg_match("/'(POST|PUT|PATCH|DELETE)'|\\/credentials/", $m[0]));

/* ─── Entry 10: an Administrator rotates the webhook token ──────────── */
echo "Rotation: route\n";
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/n8n/token'] ?? [];
check('/n8n/token: POST, gated by check_manage_settings, no arguments', ($route['methods'] ?? null) === 'POST'
    && ($route['permission_callback'] ?? null) === $gate && !array_key_exists('args', $route));

const ROTATED          = 'rotated-token-SECRET';
const PATCH_WEBHOOK    = 'PATCH ' . BASE . '/api/v1/credentials/c-webhook';
const ROTATION_STOPPED = 'The plugin could not finish updating the webhook credential (%s). Try again in a moment.';
const ROTATED_PREFIX   = 'The token was rotated in n8n and saved here.';
const OTHER_CREDENTIAL = ROTATED_PREFIX . ' n8n still refused the test ping: your Webhook Trigger uses another credential than the one the plugin manages. In n8n, pick that credential on the Webhook Trigger, publish the workflow, then send a test ping.';
define('PATCHED', resp(200, ['id' => 'c-webhook', 'name' => 'Post Quality webhook - example.com', 'type' => 'httpHeaderAuth', 'updatedAt' => '2026-10-16T10:00:00.000Z']));
$GLOBALS['tw']['uuid'] = ROTATED; // wp_generate_uuid4(): the new token, distinct from TOKEN
$rotation_runs = []; // [reply, options, calls, pings] after every rotation, for the leak scan
$adopted_map   = [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf-hand', 'credentials' => ['webhook' => 'c-h1', 'anthropic' => 'c-k1'], 'path' => 'pqm-execute', 'wizard_created' => false, 'at' => HAND_MAP_AT]];

/** A managed site: a passing key, a setup map (the provisioned one by default), the saved URL on its path (D3). */
function managed(array $queue, ?array $map = null, string $url = HOOK): void {
    connected($queue, $map ?? $GLOBALS['setup_map']);
    $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] = $url;
    $GLOBALS['tw']['writes'] = [];
}
function rotate() {
    $r     = PQM_REST_API::rotate_n8n_token_callback();
    $reply = $r instanceof WP_Error ? json_encode([$r->code, $r->message, $r->data]) : json_encode($r->get_data());
    $GLOBALS['replies'][]       = $reply;
    $GLOBALS['rotation_runs'][] = [$reply, $GLOBALS['tw']['options'], $GLOBALS['tw']['calls'], $GLOBALS['tw']['pings']];
    return $r instanceof WP_Error ? $r : $r->get_data();
}
/** The stored webhook token. */
function token_now(): ?string { return $GLOBALS['tw']['options']['pqm_settings']['webhook_token'] ?? null; }

echo "Rotation: offered or not\n";
managed([]);
$s = get_state();
check('offer: a passing key, a map, the saved URL on the map\'s path — rotatable; zero calls', $s['rotatable'] === true && $s['setup'] !== null && calls() === []);
managed([], $adopted_map, V5_HOOK);
check('... an adopted map (wizard_created false) on its own path: rotatable', get_state()['rotatable'] === true);
$not_offered = [
    'no key'                             => static function () { unset($GLOBALS['tw']['options'][PQM_N8n::OPTION]['api_key']); },
    'a key without a passing check'      => static function () { $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe'] = null; },
    'no map'                             => static function () { unset($GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]); },
    'a map without a webhook credential' => static function () { unset($GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]['credentials']['webhook']); },
    'no URL saved'                       => static function () { unset($GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url']); },
    'the URL on another path'            => static function () { $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] = BASE . '/webhook/elsewhere'; },
];
foreach ($not_offered as $label => $change) {
    managed([]);
    $change();
    check("not offered ($label): rotatable false, zero calls", get_state()['rotatable'] === false && calls() === []);
}

echo "Rotation: rotate\n";
$token_at_patch = null;
managed([static function () use (&$token_at_patch) { $token_at_patch = token_now(); return PATCHED; }]);
$settings_before = $GLOBALS['tw']['options']['pqm_settings'];
$r = rotate();
check('one call: PATCH /credentials/{the map\'s webhook id}, signed with the key and the plugin User-Agent', calls() === [PATCH_WEBHOOK] && calls_signed(KEY));
check('... its body: {data: {name: X-PQM-Token, value: the new token}}, JSON', body(0) === ['data' => ['name' => 'X-PQM-Token', 'value' => ROTATED]]
    && $GLOBALS['tw']['calls'][0][1]['headers']['Content-Type'] === 'application/json');
check('... PATCH first: the stored token was still the old one when n8n got the new one', $token_at_patch === TOKEN);
check('... then pqm_settings written once, only webhook_token changed; then the version record', $GLOBALS['tw']['writes'] === ['pqm_settings', 'pqm_workflow_version_seen']
    && $GLOBALS['tw']['options']['pqm_settings'] === ['webhook_token' => ROTATED] + $settings_before);
check('... then one ping of the saved URL, with the new token', count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === HOOK
    && $GLOBALS['tw']['pings'][0][1]['headers']['X-PQM-Token'] === ROTATED);
check('... reply {rotated, ping_ok, outdated, message, status_html}: "Token rotated." + the ping\'s message', is_array($r)
    && array_keys($r) === ['rotated', 'ping_ok', 'outdated', 'message', 'status_html'] && $r['rotated'] === true && $r['ping_ok'] === true && $r['outdated'] === false
    && $r['message'] === 'Token rotated. ' . PING_OK);
check('... the version recorded by a test, as provisioning does; the body says so, the message first', pqm_workflow_report()['via'] === 'test'
    && str_contains($r['status_html'], 'Version ' . PQM_WORKFLOW_VERSION . ' · checked just now by a test.') && one_primary($r['status_html'])
    && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">Token rotated. ' . PING_OK . '</p>'));
check('... no diagnosis read: n8n is not read after the PATCH', calls() === [PATCH_WEBHOOK]);
check('... the map and the connection untouched', $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $setup_map[PQM_N8n::SETUP_OPTION]
    && !in_array(PQM_N8n::SETUP_OPTION, $GLOBALS['tw']['writes'], true) && !in_array(PQM_N8n::OPTION, $GLOBALS['tw']['writes'], true));
managed([PATCHED], $adopted_map, V5_HOOK);
$r = rotate();
check('an adopted map: the PATCH goes to its own webhook credential id', calls() === ['PATCH ' . BASE . '/api/v1/credentials/c-h1'] && $r['rotated'] === true && token_now() === ROTATED);
managed([PATCHED], [PQM_N8n::SETUP_OPTION => ['credentials' => ['webhook' => 'c/../x y'] + $setup_map[PQM_N8n::SETUP_OPTION]['credentials']] + $setup_map[PQM_N8n::SETUP_OPTION]]);
rotate();
check('... an id with path characters: URL-encoded', calls() === ['PATCH ' . BASE . '/api/v1/credentials/c%2F..%2Fx%20y']);
managed([PATCHED], null, HOOK . '/');
check('... the saved URL with a trailing slash: the same path, rotated', rotate()['rotated'] === true);
managed([PATCHED]);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION - 1]);
$r = rotate();
check('... an older workflow answers the ping: rotated, ping_ok and outdated true, its message after "Token rotated.", status_html present', $r['rotated'] === true
    && $r['ping_ok'] === true && $r['outdated'] === true && str_starts_with($r['message'], 'Token rotated. Ping OK — this site reaches n8n, but your n8n workflow is an older version')
    && isset($r['status_html']) && token_now() === ROTATED);
managed([PATCHED]);
$GLOBALS['tw']['options']['pqm_workflow_version_seen'] = ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000, 'miswired' => SHARED_SENTENCE];
rotate();
check('... a stored mis-wiring sentence: kept (no diagnosis ran), still shown', pqm_workflow_report()['miswired'] === SHARED_SENTENCE);

echo "Rotation: refused at the gate — zero calls, nothing written\n";
foreach ([
    'no key'                             => ['pqm_n8n_not_connected', 400],
    'a key without a passing check'      => ['pqm_n8n_not_connected', 400],
    'no map'                             => ['pqm_workflow_not_managed', 409],
    'a map without a webhook credential' => ['pqm_workflow_not_managed', 409],
    'no URL saved'                       => ['pqm_webhook_url_changed', 409],
    'the URL on another path'            => ['pqm_webhook_url_changed', 409],
] as $label => [$code, $status]) {
    managed([PATCHED]);
    $not_offered[$label]();
    $r = rotate();
    check("$label: $status $code, zero calls, nothing written, no ping, the token kept", refused($r, $code, $status) && calls() === [] && token_now() === TOKEN);
}
managed([]);
$not_offered['no map']();
check('... not managed: the sentence says to let the plugin set up or manage the workflow first', rotate()->message
    === 'The plugin does not manage your n8n workflow yet. Let it set the workflow up, or manage the one you set up by hand, then try again.');
managed([]);
$not_offered['the URL on another path']();
check('... the URL changed: the sentence says the URL no longer points at the managed workflow, nothing was changed (action-neutral: the update shows it too, entry 9)', rotate()->message
    === 'Your n8n Webhook URL no longer points at the workflow the plugin manages, so nothing was changed. Save that workflow\'s production URL as the n8n Webhook URL, then try again.');

echo "Rotation: n8n refuses the PATCH — the stored token unchanged, no ping\n";
foreach ([
    'a 400 quoting the request'  => [resp(400, ['message' => 'request/body/data must match ' . ROTATED]), 'n8n answered HTTP 400'],
    'a 500'                      => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'the key refused (401)'      => [resp(401, ['message' => 'unauthorized']), 'n8n answered HTTP 401'],
    'a proxy\'s non-JSON 404'    => [resp(404, '<html>Not Found</html>'), 'n8n answered HTTP 404'],
    'a 2xx without JSON'         => [resp(200, '<html>Just a moment...</html>'), 'n8n did not confirm the change'],
    'n8n unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
] as $label => [$answer, $says]) {
    managed([$answer]);
    $r = rotate();
    check("$label: 502 pqm_rotation_failed, stopped('credential')'s sentence, n8n's body not repeated", refused($r, 'pqm_rotation_failed', 502)
        && $r->message === sprintf(ROTATION_STOPPED, $says) && !str_contains($r->message, 'Internal error') && !str_contains($r->message, 'must match')
        && calls() === [PATCH_WEBHOOK] && token_now() === TOKEN);
}
managed([NOT_FOUND]);
$r = rotate();
check('the credential is gone (a JSON 404): 502 pqm_rotation_failed, the sentence names it and the check in n8n', refused($r, 'pqm_rotation_failed', 502)
    && $r->message === 'n8n no longer has the webhook credential the plugin manages. Check the Webhook Trigger\'s credential in n8n, then try again.'
    && calls() === [PATCH_WEBHOOK] && token_now() === TOKEN);

echo "Rotation: the ping fails after — the token kept on both sides\n";
foreach ([
    'the URL does not answer (404)' => [resp(404, ['message' => 'The requested webhook is not registered.']), ROTATED_PREFIX . ' n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.'],
    'the webhook unreachable'       => [timed_out(), ROTATED_PREFIX . ' Could not connect to n8n: cURL error 28: timed out'],
    'a 401'                         => [resp(401, ''), OTHER_CREDENTIAL],
    'a 403'                         => [resp(403, ''), OTHER_CREDENTIAL],
] as $label => [$ping, $message]) {
    managed([PATCHED]);
    $GLOBALS['tw']['ping_response'] = $ping;
    $r = rotate();
    check("$label: rotated, ping_ok false, the message says so; the body needs attention (the failure recorded, no version)", array_keys($r) === ['rotated', 'ping_ok', 'outdated', 'message', 'status_html']
        && $r['ping_ok'] === false && $r['outdated'] === false && $r['message'] === $message && state_of($r['status_html']) === 'failing'
        && pqm_workflow_report()['version'] === 0 && isset(pqm_workflow_report()['failed']));
    check('... the new token stored: pqm_settings written once, then the failure record', token_now() === ROTATED
        && $GLOBALS['tw']['writes'] === ['pqm_settings', 'pqm_workflow_version_seen'] && calls() === [PATCH_WEBHOOK]);
}

echo "Rotation: after\n";
managed([PATCHED]);
rotate();
$GLOBALS['tw']['calls'] = [];
$s = forget();
check('forget key after: rotatable false (the button goes), the token stays as rotated, the map stays', $s['rotatable'] === false && token_now() === ROTATED
    && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $setup_map[PQM_N8n::SETUP_OPTION] && calls() === []);
$r = rotate();
check('... a rotation then: 400 pqm_n8n_not_connected, zero calls', $r instanceof WP_Error && $r->code === 'pqm_n8n_not_connected' && calls() === []);

echo "Rotation: leak scan and the one call\n";
$rotation_calls = array_merge(...array_map(static fn($run) => $run[2], $rotation_runs));
check('every n8n call of the rotations (' . count($rotation_calls) . ') is a PATCH on a map\'s webhook credential id', count($rotation_calls) >= 15
    && !array_filter($rotation_calls, static fn($c) => $c[1]['method'] !== 'PATCH' || !preg_match('#/api/v1/credentials/(c-webhook|c-h1|c%2F\.\.%2Fx%20y)$#', $c[0])));
check('... and no rotation made more than one', !array_filter($rotation_runs, static fn($run) => count($run[2]) > 1));
// Story 67: the reply's status_html is the page's body, whose Webhook disclosure shows the token row the page shows anyway; no reply field carries it.
check('the new token is in no reply field and no error (' . count($rotation_runs) . ' rotations)', !array_filter($rotation_runs, static fn($run) => str_contains(without_body($run[0]), ROTATED)));
check('... in no stored option but pqm_settings[webhook_token]', !array_filter($rotation_runs, static function ($run) {
    $options = $run[1];
    unset($options['pqm_settings']['webhook_token']);
    return str_contains(json_encode($options), ROTATED);
}));
check('... in no outgoing call\'s address or headers (only the PATCH body), and in the ping only as its X-PQM-Token header', !array_filter($rotation_runs, static fn($run) =>
    array_filter($run[2], static fn($c) => str_contains(json_encode([$c[0], $c[1]['headers']]), ROTATED))
    || array_filter($run[3], static fn($p) => str_contains(json_encode([$p[0], $p[1]['body'] ?? '', array_diff_key($p[1]['headers'], ['X-PQM-Token' => 0])]), ROTATED))));
check('... and in no REST reply field of this harness (' . count($replies) . ')', !array_filter($replies, static fn($r) => str_contains(without_body($r), ROTATED)));
preg_match('/function rotate_token\(.*?\n    }\n.*?function managed_blocked\(.*?\n    }\n/s', $src_n8n, $m);
check('the rotation code calls n8n once, a PATCH through request(), and never reads /credentials', ($m[0] ?? '') !== ''
    && !preg_match("/'(GET|POST|PUT|DELETE)'/", $m[0]) && substr_count($m[0], 'self::request(') === 1 && substr_count($m[0], "self::request( 'PATCH'") === 1);
unset($GLOBALS['tw']['uuid']);

/* ─── Entry 9: one click updates the workflow to the bundled template ─ */
echo "Update: route\n";
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/n8n/workflow'] ?? [];
check('/n8n/workflow: POST, gated by check_manage_settings, no arguments', ($route['methods'] ?? null) === 'POST'
    && ($route['permission_callback'] ?? null) === $gate && !array_key_exists('args', $route));

const PUT_WF         = 'PUT ' . BASE . '/api/v1/workflows/wf1';
const UPDATE_STOPPED = 'The plugin could not finish updating the workflow (%s). Try again in a moment.';
const UPDATE_BUTTON  = '<button type="button" class="button button-primary pqm-update-workflow" data-pqm-post="n8n/workflow" data-pqm-busy="Updating the workflow…">Update workflow</button>';
const IN_PLACE       = 'The plugin can update it in place: your credentials and production URL stay.';
const WORKFLOW_GONE  = 'The workflow the plugin manages is no longer in n8n. Set the workflow up again, or let the plugin manage the one at your n8n Webhook URL, then try again.';
define('UPDATED', resp(200, ['id' => 'wf1', 'name' => 'Post Quality — example.com', 'active' => true, 'versionId' => 'v-new']));
$older_report = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => 1700000000]];
$update_runs  = []; // [reply, options, calls, pings] after every update, for the leak scan
function update() {
    $r     = PQM_REST_API::update_n8n_workflow_callback();
    $reply = $r instanceof WP_Error ? json_encode([$r->code, $r->message, $r->data]) : json_encode($r->get_data());
    $GLOBALS['replies'][]     = $reply;
    $GLOBALS['update_runs'][] = [$reply, $GLOBALS['tw']['options'], $GLOBALS['tw']['calls'], $GLOBALS['tw']['pings']];
    return $r instanceof WP_Error ? $r : $r->get_data();
}
/** The Webhook Trigger's path in a sent workflow body. */
function trigger_path(array $workflow): string { return array_column($workflow['nodes'], null, 'name')['Webhook Trigger']['parameters']['path']; }

echo "Update: offered or not — the status block's fix\n";
managed([], $setup_map + $older_report);
$block = PQM_Admin::render_workflow_body();
check('offer: a passing key, a map, the saved URL on the map\'s path, an older report — needs attention (outdated), "this plugin needs N", Update workflow the one primary; no re-import; zero calls',
    state_of($block) === 'outdated' && str_contains($block, 'Your workflow is version ' . (PQM_WORKFLOW_VERSION - 1) . '; this plugin needs ' . PQM_WORKFLOW_VERSION . '.')
    && str_contains($block, '<p class="pqm-workflow-actions">' . UPDATE_BUTTON . ' <button type="button" class="button pqm-test-connection">Test again</button></p>')
    && !str_contains($block, 'pqm-reimport') && one_primary($block) && str_contains($block, IN_PLACE) && !str_contains($block, 'new production URL') && calls() === []);
check('... a managed site: no Set up by hand disclosure; the Webhook disclosure offers Rotate token (a REST action button) beside the token row',
    !str_contains($block, 'id="pqm-by-hand"') && str_contains($block, 'id="pqm-webhook"')
    && str_contains($block, '<button type="button" class="button pqm-rotate-token" data-pqm-post="n8n/token" data-pqm-busy="Rotating the token…">Rotate token</button>')
    && str_contains($block, 'value="' . TOKEN . '"'));
managed([], $adopted_map + $older_report, V5_HOOK);
check('... an adopted map (wizard_created false) on its own v5 path: offered too', str_contains($block = PQM_Admin::render_workflow_body(), UPDATE_BUTTON) && one_primary($block));
managed([], $setup_map + ['pqm_workflow_version_seen' => PQM_WORKFLOW_VERSION - 1]);
check('... a pre-v6 report (a legacy int): still no "new production URL" sentence — the update keeps the URL', str_contains($block = PQM_Admin::render_workflow_body(), UPDATE_BUTTON)
    && !str_contains($block, 'new production URL') && str_contains($block, IN_PLACE));
managed([], $setup_map + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000, 'variant' => 'free']]);
check('... the Free variant on Pro: the Free-variant sentence, the same button', str_contains($block = PQM_Admin::render_workflow_body(), 'Your workflow is the Free variant')
    && state_of($block) === 'outdated' && str_contains($block, UPDATE_BUTTON) && one_primary($block) && !str_contains($block, 'pqm-reimport'));
managed([], $setup_map + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => 1700000000, 'miswired' => SHARED_SENTENCE]]);
$block = PQM_Admin::render_workflow_body();
check('mis-wired first, and older: the sentence, Test again the one primary, Update workflow secondary after it', state_of($block) === 'failing'
    && str_contains($block, 'class="button button-primary pqm-test-connection">Test again</button> <button type="button" class="button pqm-update-workflow"') && one_primary($block));
foreach ($not_offered as $label => $change) {
    managed([], $setup_map + $older_report);
    $change();
    $block = PQM_Admin::render_workflow_body();
    check("not offered ($label): Re-import the workflow as before, no Update workflow, zero calls", $label === 'no URL saved'
        ? !str_contains($block, 'pqm-update-workflow') && one_primary($block) && calls() === [] // no URL: not set up at all
        : str_contains($block, 'class="button button-primary pqm-reimport"') && !str_contains($block, 'pqm-update-workflow') && !str_contains($block, IN_PLACE) && one_primary($block) && calls() === []);
}
managed([], $setup_map + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000]]);
check('up to date, managed: set up, no update button', state_of($block = PQM_Admin::render_workflow_body()) === 'set-up' && !str_contains($block, 'pqm-update-workflow'));

echo "Update: update\n";
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
$r = update();
check('calls: GET /workflows/{the map\'s id}, PUT the same, then the ping\'s diagnosis reads it again (entry 7); every call signed, nothing left unscripted',
    calls() === [READ_WF, PUT_WF, READ_WF] && calls_signed(KEY) && $GLOBALS['tw']['queue'] === []);
$sent = body(1);
check('the PUT body: {name, nodes, connections, settings} only, never `active`; connections and settings the template\'s; JSON',
    array_keys($sent) === ['name', 'nodes', 'connections', 'settings'] && $sent['connections'] === $template['connections'] && $sent['settings'] === $template['settings']
    && $GLOBALS['tw']['calls'][1][1]['headers']['Content-Type'] === 'application/json');
check('... the nodes are the build\'s template, credentials apart', $strip($sent['nodes']) === $strip($template['nodes']));
$links = linked($sent);
check('... all 8 credentialed nodes carry the map\'s id with the name the read workflow carries for it', count($links) === 8
    && $links['Webhook Trigger'] === ['httpHeaderAuth' => WEBHOOK_CRED] && $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => WP_CRED]
    && count(array_filter($links, static fn($c) => $c === ['httpHeaderAuth' => ANTHROPIC_CRED])) === 6);
check('... the trigger on the map\'s path; the workflow\'s name kept (D2)', trigger_path($sent) === 'pqm-execute-example-com' && $sent['name'] === 'Post Quality — example.com');
check('... then one ping of the saved URL, with the token', count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === HOOK
    && $GLOBALS['tw']['pings'][0][1]['headers']['X-PQM-Token'] === TOKEN);
check('... reply {updated, ping_ok, outdated, message, rewrite_ready, status_html}: "Workflow updated." + the ping\'s message', is_array($r)
    && array_keys($r) === ['updated', 'ping_ok', 'outdated', 'message', 'rewrite_ready', 'status_html'] && $r['updated'] === true && $r['ping_ok'] === true
    && $r['outdated'] === false && $r['rewrite_ready'] === true && $r['message'] === 'Workflow updated. ' . PING_OK);
check('... the version recorded by a test; the body is set up with the new version, the message first, no update button', pqm_workflow_report()['version'] === PQM_WORKFLOW_VERSION
    && pqm_workflow_report()['via'] === 'test' && str_contains($r['status_html'], 'Version ' . PQM_WORKFLOW_VERSION . ' · checked just now by a test.')
    && state_of($r['status_html']) === 'set-up' && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">' . $r['message'] . '</p>')
    && !str_contains($r['status_html'], 'pqm-update-workflow') && one_primary($r['status_html']));
check('... the version record the one write: the map, the connection and the settings untouched', $GLOBALS['tw']['writes'] === ['pqm_workflow_version_seen']
    && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $setup_map[PQM_N8n::SETUP_OPTION]);
managed([resp(200, ['name' => 'Reviews for my blog'] + stored_workflow('wf1', wired())), UPDATED, CLEAN_WF], $setup_map + $older_report);
update();
check('a renamed workflow: the PUT carries the name n8n holds, not the template\'s (D2)', body(1)['name'] === 'Reviews for my blog');
managed([resp(200, stored_workflow('wf1', wired(['Post Quality webhook - example.com' => ['id' => 'c-other', 'name' => 'Header Auth account']] + WIRED))), UPDATED, CLEAN_WF], $setup_map + $older_report);
update();
check('a map id the read workflow no longer carries (the trigger was re-pointed in n8n): linked by the map\'s id, with an empty name',
    linked(body(1))['Webhook Trigger'] === ['httpHeaderAuth' => ['id' => 'c-webhook', 'name' => '']]);
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION - 1]);
$r = update();
check('an older workflow still answers the ping (not republished yet): updated, ping_ok and outdated true, a warning; the block offers the update again',
    $r['updated'] === true && $r['ping_ok'] === true && $r['outdated'] === true && str_starts_with($r['message'], 'Workflow updated. Ping OK — this site reaches n8n, but your n8n workflow is an older version')
    && str_contains($r['status_html'], UPDATE_BUTTON));
managed([CLEAN_WF, UPDATED, resp(200, stored_workflow('wf1', $oct13))], $setup_map + $older_report);
$r = update();
check('the ping\'s diagnosis finds the published version mis-wired: the sentence as the message, outdated; the block shows the sentence with Test again as the one primary (the version is current: no update notice)',
    $r['ping_ok'] === true && $r['message'] === 'Workflow updated. ' . SHARED_SENTENCE && $r['outdated'] === true && pqm_workflow_report()['miswired'] === SHARED_SENTENCE
    && str_contains($r['status_html'], 'class="button button-primary pqm-test-connection"') && !str_contains($r['status_html'], 'pqm-update-workflow') && one_primary($r['status_html']));

echo "Update: an adopted workflow (free names, the v5 path, Rewrite unwired)\n";
$hand_read = resp(200, ['name' => 'My blog reviews'] + stored_workflow('wf-hand', with_path(wired(['WordPress - example.com' => null] + HAND_NAMES), 'pqm-execute')));
managed([$hand_read, resp(200, ['id' => 'wf-hand', 'name' => 'My blog reviews']), $hand_read], $adopted_map + $older_report, V5_HOOK);
$r     = update();
$sent  = body(1);
$links = linked($sent);
check('GET then PUT on the adopted id; the trigger keeps pqm-execute (D1: never pqm_webhook_path()); the owner\'s names beside the map\'s ids; the owner\'s workflow name',
    array_slice(calls(), 0, 2) === [READ_HAND, 'PUT ' . BASE . '/api/v1/workflows/wf-hand'] && trigger_path($sent) === 'pqm-execute' && $sent['name'] === 'My blog reviews'
    && $links['Webhook Trigger'] === ['httpHeaderAuth' => HAND_HOOK] && count(array_filter($links, static fn($c) => $c === ['httpHeaderAuth' => HAND_CLAUDE])) === 6);
check('... Rewrite unwired (Pro): Update WP Post (Rewrite) stays name-only; rewrite_ready false and the message says Rewrite stays off', $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => ['name' => 'WordPress - example.com']]
    && $r['rewrite_ready'] === false && $r['ping_ok'] === true && str_starts_with($r['message'], 'Workflow updated. ' . PING_OK)
    && str_ends_with($r['message'], 'Rewrite stays off until a WordPress credential is set in n8n on the Update WP Post (Rewrite) node.'));
check('... the diagnosis after skips that node (6.7\'s D1): the version recorded, the body set up, the message a warning (Rewrite off)', pqm_workflow_report()['version'] === PQM_WORKFLOW_VERSION
    && state_of($r['status_html']) === 'set-up' && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-warning">'));
// The map's path is the trigger's raw string: a `"` in it must survive the template fill (JSON-escaped), not break the decode.
$quoted_read = resp(200, ['name' => 'My blog reviews'] + stored_workflow('wf-hand', with_path(wired(HAND_NAMES), 'pqm-"quoted"')));
managed([$quoted_read, resp(200, ['id' => 'wf-hand']), $quoted_read], [PQM_N8n::SETUP_OPTION => ['path' => 'pqm-"quoted"'] + $adopted_map[PQM_N8n::SETUP_OPTION]] + $older_report, BASE . '/webhook/pqm-"quoted"');
$r = update();
check('an adopted path carrying a `"`: the template fills and decodes, the PUT body\'s trigger path is the raw path, updated', is_array($r) && $r['updated'] === true
    && is_array(body(1)) && trigger_path(body(1)) === 'pqm-"quoted"' && array_slice(calls(), 0, 2) === [READ_HAND, 'PUT ' . BASE . '/api/v1/workflows/wf-hand']);

echo "Update: refused at the gate — zero calls, nothing written\n";
foreach ([
    'no key'                             => ['pqm_n8n_not_connected', 400],
    'a key without a passing check'      => ['pqm_n8n_not_connected', 400],
    'no map'                             => ['pqm_workflow_not_managed', 409],
    'a map without a webhook credential' => ['pqm_workflow_not_managed', 409],
    'no URL saved'                       => ['pqm_webhook_url_changed', 409],
    'the URL on another path'            => ['pqm_webhook_url_changed', 409],
] as $label => [$code, $status]) {
    managed([CLEAN_WF, UPDATED], $setup_map + $older_report);
    $not_offered[$label]();
    $r = update();
    check("$label: $status $code, zero calls, nothing written, no ping", refused($r, $code, $status) && calls() === []);
}
managed([CLEAN_WF, UPDATED], [PQM_N8n::SETUP_OPTION => ['workflow_id' => ''] + $setup_map[PQM_N8n::SETUP_OPTION]] + $older_report);
check('a map without a workflow id: 409 pqm_workflow_not_managed, zero calls; the block offers Re-import, not rotatable either', refused(update(), 'pqm_workflow_not_managed', 409)
    && calls() === [] && str_contains(PQM_Admin::render_workflow_body(), 'pqm-reimport') && PQM_N8n::public_state()['rotatable'] === false);

echo "Update: the workflow is gone\n";
managed([NOT_FOUND, UPDATED], $setup_map + $older_report);
$r = update();
check('a JSON 404 on the GET: 502 pqm_update_failed, the sentence says the workflow is gone and the two ways out; no PUT, nothing written, no ping',
    refused($r, 'pqm_update_failed', 502) && $r->message === WORKFLOW_GONE && calls() === [READ_WF] && $GLOBALS['tw']['queue'] === [UPDATED]);

echo "Update: the read refused — no PUT\n";
foreach ([
    'a 500'                      => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'the key refused (401)'      => [resp(401, ['message' => 'unauthorized']), 'n8n answered HTTP 401'],
    'a proxy\'s non-JSON 404'    => [resp(404, '<html>Not Found</html>'), 'n8n answered HTTP 404'],
    'a proxy\'s non-JSON 200'    => [resp(200, '<html>Just a moment...</html>'), 'n8n did not answer with your workflows'],
    'n8n unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
] as $label => [$answer, $says]) {
    managed([$answer, UPDATED], $setup_map + $older_report);
    $r = update();
    check("$label: 502 pqm_update_failed, could_not('read')'s sentence, n8n's body not repeated; no PUT, nothing written, no ping", refused($r, 'pqm_update_failed', 502)
        && $r->message === "The plugin could not finish reading your workflows ($says). Try again in a moment." && !str_contains($r->message, 'Internal error')
        && calls() === [READ_WF] && $GLOBALS['tw']['queue'] === [UPDATED]);
}

echo "Update: the PUT refused — the version record untouched, no ping\n";
foreach ([
    'a 400 quoting the request'  => [resp(400, ['message' => 'request/body/nodes must match ' . TOKEN]), 'n8n answered HTTP 400'],
    'a 500'                      => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'a JSON 404 (gone meanwhile)' => [NOT_FOUND, 'n8n answered HTTP 404'],
    'a 2xx without JSON'         => [resp(200, '<html>Just a moment...</html>'), 'n8n did not confirm the update'],
    'n8n unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
] as $label => [$answer, $says]) {
    managed([CLEAN_WF, $answer], $setup_map + $older_report);
    $r = update();
    check("$label: 502 pqm_update_failed, stopped('update')'s sentence, n8n's body not repeated; GET then PUT, nothing written, no ping", refused($r, 'pqm_update_failed', 502)
        && $r->message === sprintf(UPDATE_STOPPED, $says) && !str_contains($r->message, 'Internal error') && !str_contains($r->message, 'must match')
        && calls() === [READ_WF, PUT_WF] && $GLOBALS['tw']['options']['pqm_workflow_version_seen'] === $older_report['pqm_workflow_version_seen']);
}
check('... the block still offers the update', str_contains(PQM_Admin::render_workflow_body(), UPDATE_BUTTON));

echo "Update: the ping fails after — updated, no version recorded\n";
foreach ([
    'the URL does not answer (404)' => [resp(404, ['message' => 'The requested webhook is not registered.']), 'The workflow was updated in n8n. n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.'],
    'the webhook unreachable'       => [timed_out(), 'The workflow was updated in n8n. Could not connect to n8n: cURL error 28: timed out'],
    'a 401'                         => [resp(401, ''), 'The workflow was updated in n8n. n8n refused the webhook credential (HTTP 401).'],
] as $label => [$ping, $message]) {
    managed([CLEAN_WF, UPDATED], $setup_map + $older_report);
    $GLOBALS['tw']['ping_response'] = $ping;
    $r = update();
    check("$label: updated, ping_ok false, the message says so; the body needs attention (the failure the one write, the older version kept)", is_array($r)
        && array_keys($r) === ['updated', 'ping_ok', 'outdated', 'message', 'rewrite_ready', 'status_html'] && $r['updated'] === true && $r['ping_ok'] === false && $r['outdated'] === false
        && str_starts_with($r['message'], $message) && $GLOBALS['tw']['writes'] === ['pqm_workflow_version_seen'] && pqm_workflow_report()['version'] === PQM_WORKFLOW_VERSION - 1
        && state_of($r['status_html']) === 'failing' && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-error">' . $r['message'] . '</p>')
        && calls() === [READ_WF, PUT_WF] && count($GLOBALS['tw']['pings']) === 1);
}

echo "Update: after\n";
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
update();
$GLOBALS['tw']['calls'] = [];
forget();
$GLOBALS['tw']['options']['pqm_workflow_version_seen'] = $older_report['pqm_workflow_version_seen'];
check('forget key after: the notice falls back to Re-import the workflow, the map stays', str_contains($block = PQM_Admin::render_workflow_body(), 'pqm-reimport')
    && !str_contains($block, 'pqm-update-workflow') && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] === $setup_map[PQM_N8n::SETUP_OPTION] && calls() === []);
check('... an update then: 400 pqm_n8n_not_connected, zero calls', update()->code === 'pqm_n8n_not_connected' && calls() === []);

echo "Update: leak scan and the two calls\n";
$update_calls = array_merge(...array_map(static fn($run) => array_slice($run[2], 0, 2), $update_runs)); // the update's own calls; a third is the ping's diagnosis
check('every n8n call of the updates (' . count($update_calls) . ') is a GET or a PUT of the map\'s workflow id, never a credential, never a list', count($update_calls) >= 20
    && !array_filter($update_calls, static fn($c) => !in_array($c[1]['method'], ['GET', 'PUT'], true) || !preg_match('#/api/v1/workflows/(wf1|wf-hand)$#', $c[0])));
check('... no update made more than one PUT, and only after a GET of the same workflow', !array_filter($update_runs, static function ($run) {
    $puts = array_values(array_filter($run[2], static fn($c) => $c[1]['method'] === 'PUT'));
    return count($puts) > 1 || ($puts !== [] && ($run[2][0][1]['method'] !== 'GET' || $run[2][0][0] !== $puts[0][0] || $run[2][1] !== $puts[0]));
}));
check('the n8n key, the webhook token and the provider key are in no reply field and no PUT body (credential ids and names only) (' . count($update_runs) . ' updates)',
    !array_filter($update_runs, static fn($run) => str_contains($run[0], KEY) || str_contains(without_body($run[0]), TOKEN) || str_contains($run[0], PROVIDER_KEY)
        || array_filter($run[2], static fn($c) => $c[1]['method'] === 'PUT' && preg_match('/' . preg_quote(KEY, '/') . '|' . preg_quote(TOKEN, '/') . '|' . preg_quote(PROVIDER_KEY, '/') . '/', (string) ($c[1]['body'] ?? '')))));
preg_match('/function update_workflow\(.*?\n    }\n/s', $src_n8n, $m);
check('the update code calls n8n twice through request(), a GET then a PUT, and never reads /credentials or the list', ($m[0] ?? '') !== ''
    && substr_count($m[0], 'self::request(') === 2 && str_contains($m[0], "self::request( 'GET', \$path )") && str_contains($m[0], "self::request( 'PUT', \$path, \$body )")
    && !str_contains($m[0], '/credentials') && !str_contains($m[0], 'ACTIVE_WORKFLOWS') && !preg_match("/'(POST|PATCH|DELETE)'/", $m[0]));
check('every status block the update rows answered: at most one primary, one whenever a notice shows', !array_filter($update_runs, static function ($run) {
    $html = json_decode($run[0], true)['status_html'] ?? null;
    return is_string($html) && !one_primary($html);
}));

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
