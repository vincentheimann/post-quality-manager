<?php
/**
 * n8n API client harness — n8n provisioning, entry 1 (connect by key). Runs
 * every row of the plan's I/O & Edge-Case Matrix through GET|POST|DELETE
 * /n8n/connection and PQM_N8n, with wp_remote_request() answering from a
 * scripted queue. Every recorded call is checked for the plugin User-Agent,
 * no redirect following, and the key header; every reply is checked for the
 * key string. WP stubs, no WP install; callbacks are called directly.
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
    'queue'    => [], // scripted wp_remote_request() answers, in call order
    'calls'    => [], // wp_remote_request() calls: [url, args]
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
function wp_remote_request($url, $args) {
    $GLOBALS['tw']['calls'][] = [$url, $args];
    return array_shift($GLOBALS['tw']['queue']) ?? new WP_Error('harness', "unscripted call: {$args['method']} $url");
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }

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
check('the router\'s generic 404 "not found" on activate = missing endpoint, nothing stored', $r['probe']['cause'] === 'missing_endpoint'
    && $r['probe']['message'] === 'Your n8n lacks an API endpoint the setup needs. Update n8n to 2.42.3 or later. Or set up the workflow by hand with the Workflow File section.'
    && !isset($GLOBALS['tw']['options'][PQM_N8n::OPTION]));
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
    check('... the manual path offered', $cause === 'key' || str_contains($r['probe']['message'], 'set up the workflow by hand with the Workflow File section'));
    check('... stored URL and key as they were; the reply says so', unchanged($stored_conn)
        && $r['base_url'] === 'https://old.example.com' && $r['key_set'] === true);
    check('... every call signed, nothing left unscripted', calls_signed(KEY) && $GLOBALS['tw']['queue'] === []);
}
site([], [LIST_OK, resp(405, '')]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('Missing endpoint: a 405 without a body, nothing stored', $r['probe']['cause'] === 'missing_endpoint' && !isset($GLOBALS['tw']['options'][PQM_N8n::OPTION]));

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

/* ─── The key never leaves the server ────────────────────────────────── */
echo "The key never leaves the server\n";
check('no REST reply above carries either key (' . count($replies) . ' replies)', $replies !== []
    && !array_filter($replies, static fn($r) => str_contains($r, KEY) || str_contains($r, OLD_KEY)));
$src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-n8n.php');
check('the client logs nothing (no error_log, print_r, var_dump)', !preg_match('/\b(error_log|print_r|var_dump|var_export)\s*\(/', $src));
check('the client sends through wp_remote_request() in one place', substr_count($src, 'wp_remote_request(') === 1 && !str_contains($src, 'wp_remote_post(') && !str_contains($src, 'wp_remote_get('));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
