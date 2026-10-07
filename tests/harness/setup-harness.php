<?php
/**
 * Setup dialog harness for story 36 — covers the I/O & Edge-Case Matrix of
 * the setup routes: registration + permission (managers only), the GET
 * state (values to copy, Application Password availability), the derived
 * completion for sites connected before the dialog, progress validation,
 * the connect step (test URL refused and not stored, production URL stored
 * and pinged, ping failure stored with the cause) and the uninstall list.
 * WP stubs, no WP install. Callbacks are called directly, so the route's
 * own checks (step whitelist) are what is asserted, not WP's arg schema.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['tw'] = [
    'caps'          => [],
    'options'       => [], // option name => value
    'autoload'      => [], // option name => autoload flag passed to update_option()
    'writes'        => [], // option names written, in order
    'pings'         => [], // wp_remote_post() calls: [url, args]
    'ping_response' => ['response' => ['code' => 200]],
    'app_passwords' => true,
];
$GLOBALS['pqm_routes'] = [];

/* ─── WP stubs ───────────────────────────────────────────────────────── */
function add_action(...$a) {}
function add_filter(...$a) {}
function register_activation_hook(...$a) {}
function register_deactivation_hook(...$a) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return ''; }
function wp_generate_uuid4() { return 'stub-uuid'; }
function get_option($k, $d = false) { return $GLOBALS['tw']['options'][$k] ?? $d; }
function update_option($k, $v, $autoload = null) {
    $GLOBALS['tw']['options'][$k] = $v;
    $GLOBALS['tw']['autoload'][$k] = $autoload;
    $GLOBALS['tw']['writes'][] = $k;
    return true;
}
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $value; }
function __($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_url_raw($s) { return trim((string) $s); }
function sanitize_text_field($s) { return (string) $s; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function absint($n) { return abs((int) $n); }
function is_admin() { return false; }
function home_url($p = '') { return 'https://example.com' . $p; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function rest_url($p = '') { return 'https://example.com/wp-json/' . $p; }
function admin_url($p = '') { return 'https://example.com/wp-admin/' . $p; }
function wp_create_nonce($a = '') { return 'nonce-' . $a; }
function add_query_arg($args, $url = '', $unused = null) {
    if (!is_array($args)) { // add_query_arg( $key, $value, $url )
        [$args, $url] = [[$args => $url], $unused];
    }
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
}
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function wp_remote_post($url, $args) { $GLOBALS['tw']['pings'][] = [$url, $args]; return $GLOBALS['tw']['ping_response']; }
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_get_current_user() { return (object) ['ID' => 1, 'user_login' => 'editor-anna']; }
function wp_is_application_passwords_available_for_user($user) { return $GLOBALS['tw']['app_passwords']; }

class WP_REST_Server {
    const READABLE  = 'GET';
    const CREATABLE = 'POST';
}

class WP_REST_Request {
    public function __construct(private array $params = []) {}
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function get_header($k) { return null; }
}

class WP_REST_Response {
    public function __construct(public $data = null, public $status = 200) {}
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
}

class WP_Error {
    public function __construct(public $code = '', public $message = '', public $data = null) {}
    public function get_error_message() { return $this->message; }
}
function is_wp_error($t) { return $t instanceof WP_Error; }

/* ─── Load the plugin bootstrap ──────────────────────────────────────── */
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Runner ─────────────────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/* Review rows for PQM_DB::has_result(): the stub answers its one get_var
 * from the stored statuses, mirroring the SQL's exclusion list (asserted below). */
$wpdb = new class {
    public $prefix = 'wp_';
    public array $statuses = [];
    public string $last_sql = '';
    public function get_var($sql) {
        $this->last_sql = $sql;
        return array_diff($this->statuses, ['', 'processing', 'error']) ? '1' : null;
    }
    public function query($sql) { return true; }
};

/** Fresh site state: only what the test names is stored (options, review row statuses). */
function site(array $options = [], array $review_statuses = []): void {
    $GLOBALS['wpdb']->statuses = $review_statuses;
    $GLOBALS['tw']['options'] = $options;
    $GLOBALS['tw']['autoload'] = [];
    $GLOBALS['tw']['writes'] = [];
    $GLOBALS['tw']['pings'] = [];
    $GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200]];
}

function setup_state(): array {
    return PQM_REST_API::get_setup_callback()->get_data();
}

function post_setup(array $params) {
    return PQM_REST_API::update_setup_callback(new WP_REST_Request($params));
}

function connect(string $url): array {
    return PQM_REST_API::setup_connect_callback(new WP_REST_Request(['webhook_url' => $url]))->get_data();
}

$ns = PQM_REST_NAMESPACE;
$manager = ['pqm_manage_settings', 'pqm_manage_agents', 'pqm_view_reviews', 'pqm_run_free_agents'];
$editor  = ['pqm_manage_agents', 'pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents'];
$token   = 'tok-36';
$connected = ['pqm_settings' => ['n8n_webhook_url' => 'https://acme.app.n8n.cloud/webhook/pqm-execute', 'webhook_token' => $token]];
$fresh     = ['pqm_settings' => ['n8n_webhook_url' => '', 'webhook_token' => $token, 'license_key' => 'kept']];

/* Row: registration + permission — managers only, on all three */
echo "Route registration and permission\n";
PQM_REST_API::register_routes();
$routes = $GLOBALS['pqm_routes'];
$gate = ['PQM_REST_API', 'check_manage_settings'];
check('/setup registered with a GET and a POST endpoint',
    ($routes[$ns . '/setup'][0]['methods'] ?? null) === 'GET' && ($routes[$ns . '/setup'][1]['methods'] ?? null) === 'POST');
check('/setup GET and POST gated by check_manage_settings',
    ($routes[$ns . '/setup'][0]['permission_callback'] ?? null) === $gate && ($routes[$ns . '/setup'][1]['permission_callback'] ?? null) === $gate);
check('/setup/connect is POST, gated by check_manage_settings',
    ($routes[$ns . '/setup/connect']['methods'] ?? null) === 'POST' && ($routes[$ns . '/setup/connect']['permission_callback'] ?? null) === $gate);
$GLOBALS['tw']['caps'] = $editor;
check('non-manager (Editor caps): refused — WP answers 403', PQM_REST_API::check_manage_settings() === false);
$GLOBALS['tw']['caps'] = [];
check('no caps: refused', PQM_REST_API::check_manage_settings() === false);
$GLOBALS['tw']['caps'] = $manager;
check('manager: allowed', PQM_REST_API::check_manage_settings() === true);

/* Row: fresh site — nothing done, the values to copy */
echo "GET /setup — fresh site\n";
site($fresh);
$s = setup_state();
check('seven steps in dialog order, none done',
    array_keys($s['steps']) === ['instance', 'import', 'credentials', 'link', 'publish', 'connect', 'review'] && !in_array(true, $s['steps'], true));
check('not complete, not connected, no result', $s['complete'] === false && $s['connected'] === false && $s['first_result'] === false);
check('token and import URL (workflow route + token)',
    $s['token'] === $token && $s['import_url'] === 'https://example.com/wp-json/post-quality/v1/workflow?token=tok-36');
check('download URL is raw (no &amp;) with the action and its nonce',
    $s['download_url'] === 'https://example.com/wp-admin/admin-post.php?action=pqm_download_workflow&_wpnonce=nonce-pqm_download_workflow');
check('the signed-in user\'s login for the WordPress credential', $s['user_login'] === 'editor-anna');
check('site host for the template\'s credential names', $s['site_host'] === 'example.com');
check('Application Passwords available (HTTPS)', $s['app_passwords'] === true);
$GLOBALS['tw']['app_passwords'] = false;
check('Application Passwords not available (no HTTPS / not local): reported, the dialog explains', setup_state()['app_passwords'] === false);
$GLOBALS['tw']['app_passwords'] = true;
check('a GET writes nothing', $GLOBALS['tw']['writes'] === []);

/* Row: legacy connected — complete is derived, no migration */
echo "GET /setup — completion derived for sites connected before the dialog\n";
site($connected, ['needs_revision']);
$s = setup_state();
check('connected + a scored result stored, no progress option, no version ever reported (pre-handshake import): complete, review step done',
    $s['complete'] === true && $s['steps']['review'] === true && $s['first_result'] === true && pqm_setup_complete() === true);
check('... and nothing is written (no migration)', $GLOBALS['tw']['writes'] === [] && !isset($GLOBALS['tw']['options'][PQM_SETUP_OPTION]));
check('has_result() SQL excludes the placeholder and both failed-run shapes',
    str_contains($wpdb->last_sql, "FROM wp_pqm_reviews WHERE status NOT IN ('', 'processing', 'error') LIMIT 1"));
site($connected);
check('connected, no result yet: not complete', setup_state()['complete'] === false && pqm_setup_complete() === false);
site($connected, ['error', '', 'processing']);
check('connected, only a failed run (error, empty fallback) and a placeholder: not complete',
    setup_state()['complete'] === false && setup_state()['first_result'] === false);
site($connected + ['pqm_workflow_version_seen' => 4], ['error']);
check('version handshake seen (set on a failed row too) but no real result: not complete', setup_state()['complete'] === false);
site($connected, ['rewritten']);
check('any non-failed result counts (a Rewrite confirmation)', setup_state()['complete'] === true);
site($fresh, ['ship']);
check('a result once, URL emptied since: not complete', setup_state()['complete'] === false);
site($fresh + [PQM_SETUP_OPTION => ['steps' => [], 'complete' => true]]);
check('progress flag complete: complete even while not connected', setup_state()['complete'] === true);

/* Row: progress — tick, untick, resume data; unknown step refused */
echo "POST /setup — progress\n";
site($fresh);
$r = post_setup(['step' => 'import', 'done' => true]);
check('tick a step: stored once per site, autoload off', ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['import'] ?? null) === true
    && $GLOBALS['tw']['autoload'][PQM_SETUP_OPTION] === false);
check('... the answer is the fresh state (the dialog resumes from it)', $r instanceof WP_REST_Response && $r->get_data()['steps']['import'] === true
    && $r->get_data()['steps']['instance'] === false);
post_setup(['step' => 'instance', 'done' => true]);
check('a second tick keeps the first', setup_state()['steps'] === ['instance' => true, 'import' => true, 'credentials' => false, 'link' => false, 'publish' => false, 'connect' => false, 'review' => false]);
post_setup(['step' => 'import', 'done' => false]);
check('untick', setup_state()['steps']['import'] === false);
foreach (['foo', 'connect', 'review', '', 'INSTANCE'] as $step) {
    $before = $GLOBALS['tw']['options'][PQM_SETUP_OPTION];
    $r = post_setup(['step' => $step, 'done' => true]);
    check("step \"$step\" refused with 400, nothing stored", $r instanceof WP_Error && ($r->data['status'] ?? null) === 400
        && $GLOBALS['tw']['options'][PQM_SETUP_OPTION] === $before);
}
check('no step and no complete: refused', post_setup([]) instanceof WP_Error);

/* Row: first review closes the setup — settings untouched */
echo "POST /setup { complete: true }\n";
site($fresh);
$r = post_setup(['complete' => true]);
check('complete stored, state complete', ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['complete'] ?? null) === true && $r->get_data()['complete'] === true);
check('only the progress option was written (settings untouched)', $GLOBALS['tw']['writes'] === [PQM_SETUP_OPTION]
    && $GLOBALS['tw']['options']['pqm_settings'] === $fresh['pqm_settings']);
post_setup(['complete' => true]);
check('completing again changes nothing else', $GLOBALS['tw']['writes'] === [PQM_SETUP_OPTION, PQM_SETUP_OPTION]
    && $GLOBALS['tw']['options'][PQM_SETUP_OPTION] === ['steps' => [], 'complete' => true]);
$r = post_setup(['complete' => false]);
check('complete:false is no reset (refused: no step given)', $r instanceof WP_Error && setup_state()['complete'] === true);

/* Row: connect — test URL refused, production stored + pinged, ping failure stored */
echo "POST /setup/connect\n";
foreach (['https://acme.app.n8n.cloud/webhook-test/pqm-execute', 'https://n8n.example.com/webhook-test/pqm-execute'] as $url) {
    site($fresh);
    $r = connect($url);
    check("test URL $url: refused with the story 35 warning, nothing stored, nothing sent",
        $r === ['saved' => false, 'ping_ok' => false, 'message' => pqm_webhook_url_warning($url)]
        && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === []);
}
site($fresh);
$r = connect('   ');
check('empty URL: refused, nothing stored', $r['saved'] === false && $r['message'] === 'Enter your n8n webhook URL first.' && $GLOBALS['tw']['writes'] === []);

$prod = 'https://acme.app.n8n.cloud/webhook/pqm-execute';
site($fresh);
$r = connect($prod);
check('production URL, n8n 2xx: stored, pinged, says what the ping proved',
    $r['saved'] === true && $r['ping_ok'] === true
    && str_contains($r['message'], 'this site reaches n8n and the webhook credential is right'));
check('... merge-save: only the URL changed in pqm_settings',
    $GLOBALS['tw']['options']['pqm_settings'] === ['n8n_webhook_url' => $prod, 'webhook_token' => $token, 'license_key' => 'kept']);
check('... one ping to that URL with the X-PQM-Token header',
    count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === $prod
    && $GLOBALS['tw']['pings'][0][1]['headers'] === ['Content-Type' => 'application/json', 'X-PQM-Token' => $token]);
check('... the connect step is done', setup_state()['steps']['connect'] === true && setup_state()['connected'] === true);

site($fresh + [PQM_SETUP_OPTION => ['steps' => ['connect' => true, 'import' => true], 'complete' => false]]);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 404]];
$r = connect($prod);
check('production URL, n8n 404: stored, message names the cause and the next step',
    $r['saved'] === true && $r['ping_ok'] === false
    && $r['message'] === 'URL saved, but the ping did not get through. n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.'
    && $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] === $prod);
check('... the connect step is not done, other ticks kept', setup_state()['steps']['connect'] === false && setup_state()['steps']['import'] === true);

site($fresh);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 403]];
$r = connect($prod);
check('production URL, n8n 403 (credential refused): stored, message names the credential and its fields',
    $r['saved'] === true && $r['ping_ok'] === false
    && str_ends_with($r['message'], "n8n refused the webhook credential (HTTP 403). In n8n, open the Webhook Trigger's Header Auth credential: its Name must be X-PQM-Token and its Value this site's webhook token. Then try again."));

site($fresh);
$GLOBALS['tw']['ping_response'] = new WP_Error('http_request_failed', 'cURL error 6: Could not resolve host');
$r = connect($prod);
check('production URL, n8n unreachable: stored, message carries the connection error',
    $r['saved'] === true && $r['ping_ok'] === false && str_ends_with($r['message'], 'Could not connect to n8n: cURL error 6: Could not resolve host'));

/* Row: one home — the AJAX handlers wrap the same two helpers */
echo "One home for save + ping; the unused AJAX save removed\n";
$src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php');
check('the unused AJAX save is gone (no handler, no hook)', !str_contains($src, 'ajax_save_webhook_url')
    && !str_contains(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php'), 'pqm_save_webhook_url'));
check('ajax_test_connection() calls ping_webhook()', (bool) preg_match('/function ajax_test_connection\(\).*?self::ping_webhook\(/s', $src));
check('wp_remote_post appears once in the admin class (the ping helper)', substr_count($src, 'wp_remote_post(') === 1);

/* Row: uninstall — the progress option is removed */
echo "Uninstall\n";
define('WP_UNINSTALL_PLUGIN', true);
$GLOBALS['tw_deleted'] = [];
function delete_option($k) { $GLOBALS['tw_deleted'][] = $k; return true; }
function delete_metadata(...$a) { return true; }
function wp_roles() { return (object) ['role_objects' => []]; }
function get_users($args) { return []; }
function wp_clear_scheduled_hook(...$a) {}
require dirname(__DIR__, 2) . '/post-quality-manager/uninstall.php';
check('uninstall deletes the setup progress option (PQM_SETUP_OPTION)', in_array(PQM_SETUP_OPTION, $GLOBALS['tw_deleted'], true));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
