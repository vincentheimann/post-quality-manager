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
 *
 * Story 55 rows: the three configuration steps (state fields, tickable,
 * open on a completed site) and POST /setup/settings — key whitelist,
 * value checks, merge isolation (every other stored key byte-identical),
 * the scheduled-reviews gate (pqm_manage_agents + pro) and a no-change walk.
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
    'scheduled'     => [], // wp_schedule_event() calls: [recurrence, hook]
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
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; }
function restore_previous_locale() {}
function wp_next_scheduled($hook) { return false; }
function wp_schedule_event($ts, $recurrence, $hook) { $GLOBALS['tw']['scheduled'][] = [$recurrence, $hook]; return true; }
function wp_unschedule_event(...$a) { return true; }
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
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }
/* The connect step answers the n8n Workflow page's refreshed status block. */
function esc_html($s) { return (string) $s; }
function esc_html_e($s, $d = null) { echo $s; }
define('MINUTE_IN_SECONDS', 60);
function human_time_diff($from, $to = 0) { return 'a while'; }
function wp_get_current_user() { return (object) ['ID' => 1, 'user_login' => 'editor-anna']; }
function wp_is_application_passwords_available_for_user($user) { return $GLOBALS['tw']['app_passwords']; }

class WP_REST_Server {
    const READABLE  = 'GET';
    const CREATABLE = 'POST';
}

class WP_REST_Request {
    public function __construct(private array $params = [], private $json = null) {}
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function get_json_params() { return $this->json; }
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
require __DIR__ . '/fs-stub.php'; // the Freemius SDK stand-in (story 44)
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
    public function prepare($sql, ...$args) { return $sql; }
};

/** Fresh site state: only what the test names is stored (options, review row statuses). */
function site(array $options = [], array $review_statuses = []): void {
    $GLOBALS['wpdb']->statuses = $review_statuses;
    $GLOBALS['tw']['options'] = $options;
    $GLOBALS['tw']['autoload'] = [];
    $GLOBALS['tw']['writes'] = [];
    $GLOBALS['tw']['pings'] = [];
    $GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200]];
    $GLOBALS['tw']['scheduled'] = [];
}

function setup_state(): array {
    return PQM_REST_API::get_setup_callback()->get_data();
}

function post_setup(array $params) {
    return PQM_REST_API::update_setup_callback(new WP_REST_Request($params));
}

function post_settings($json) {
    return PQM_REST_API::setup_settings_callback(new WP_REST_Request([], $json));
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
check('/setup/settings is POST, gated by check_manage_settings (story 55)',
    ($routes[$ns . '/setup/settings']['methods'] ?? null) === 'POST' && ($routes[$ns . '/setup/settings']['permission_callback'] ?? null) === $gate);
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
check('ten steps in dialog order (the seven n8n steps, then profile, standards, schedule), none done',
    array_keys($s['steps']) === ['instance', 'import', 'credentials', 'link', 'publish', 'connect', 'review', 'profile', 'standards', 'schedule']
    && !in_array(true, $s['steps'], true));
check('not complete, not connected, no result', $s['complete'] === false && $s['connected'] === false && $s['first_result'] === false);
check('token and import URL (workflow route + token)',
    $s['token'] === $token && $s['import_url'] === 'https://example.com/wp-json/post-quality/v1/workflow?token=tok-36');
check('download URL is raw (no &amp;) with the action and its nonce',
    $s['download_url'] === 'https://example.com/wp-admin/admin-post.php?action=pqm_download_workflow&_wpnonce=nonce-pqm_download_workflow');
check('the signed-in user\'s login for the WordPress credential', $s['user_login'] === 'editor-anna');
check('site host for the template\'s credential names', $s['site_host'] === 'example.com');
check('per-site webhook path for the URL field placeholder', $s['webhook_path'] === 'pqm-execute-example-com');
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
site($connected + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()]], ['error']);
check('version handshake seen (a ping, or a failed row\'s callback) but no real result: not complete', setup_state()['complete'] === false);
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
check('a second tick keeps the first', setup_state()['steps'] === ['instance' => true, 'import' => true, 'credentials' => false, 'link' => false, 'publish' => false, 'connect' => false, 'review' => false,
    'profile' => false, 'standards' => false, 'schedule' => false]);
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
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200], 'body' => '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . '}'];
$r = connect($prod);
check('production URL, n8n 2xx from a v5 workflow: stored, pinged, says what the ping proved',
    $r['saved'] === true && $r['ping_ok'] === true && $r['outdated'] === false
    && str_contains($r['message'], 'this site reaches n8n, the webhook credential is right and your workflow is up to date'));
$report = $GLOBALS['tw']['options']['pqm_workflow_version_seen'] ?? null;
check('... the replied version is recorded {5, via test, at now} (the URL was just saved)',
    is_array($report) && $report['version'] === PQM_WORKFLOW_VERSION && $report['via'] === 'test' && abs($report['at'] - time()) <= 5);
check('... status_html = the page\'s renderer: version 5, checked just now by a test ping, no notice',
    $r['status_html'] === PQM_Admin::render_workflow_status() && str_contains($r['status_html'], '<div class="pqm-workflow-status">')
    && str_contains($r['status_html'], 'version ' . PQM_WORKFLOW_VERSION . ', checked just now by a test ping.')
    && !str_contains($r['status_html'], 'Workflow update available.'));
check('... merge-save: only the URL changed in pqm_settings',
    $GLOBALS['tw']['options']['pqm_settings'] === ['n8n_webhook_url' => $prod, 'webhook_token' => $token, 'license_key' => 'kept']);
check('... one ping to that URL with the X-PQM-Token header',
    count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === $prod
    && $GLOBALS['tw']['pings'][0][1]['headers'] === ['Content-Type' => 'application/json', 'X-PQM-Token' => $token]);
check('... the connect step is done', setup_state()['steps']['connect'] === true && setup_state()['connected'] === true);

site($fresh);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200], 'body' => '{"message":"Workflow was started"}'];
$r = connect($prod);
check('production URL, n8n 2xx without a version (v1–v4 import): connected, outdated (the dialog warns), older-version message, {0, via test}, notice in status_html',
    $r['ping_ok'] === true && $r['outdated'] === true && str_contains($r['message'], 'your n8n workflow is an older version')
    && ($GLOBALS['tw']['options']['pqm_workflow_version_seen']['version'] ?? null) === 0
    && str_contains($r['status_html'], 'Workflow update available.') && setup_state()['steps']['connect'] === true);

$before = ['version' => PQM_WORKFLOW_VERSION, 'via' => 'command', 'at' => 1700000000];
site($fresh + ['pqm_workflow_version_seen' => $before, PQM_SETUP_OPTION => ['steps' => ['connect' => true, 'import' => true], 'complete' => false]]);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 404]];
$r = connect($prod);
check('ping fails: nothing recorded, no status_html', $GLOBALS['tw']['options']['pqm_workflow_version_seen'] === $before && !isset($r['status_html']));
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

/* ─── Story 55: the configuration steps ─────────────────────────────── */

/* Row: state carries the stored choices, the presets and the two capability flags */
echo "GET /setup — configuration step values (story 55)\n";
$GLOBALS['tw']['caps'] = $manager;
pqm_fs()->pro = false;
site($fresh);
$s = setup_state();
check('fresh site: defaults (Custom profile and standards, empty texts)',
    $s['blog_profile'] === 'custom' && $s['website_context'] === '' && $s['brand_voice_preset'] === 'custom' && $s['brand_voice'] === '');
check('profiles: every Blog Profile in Settings order, with its label and preset text (Custom has none)',
    array_column($s['profiles'], 'value') === array_keys(PQM_Admin::blog_profiles())
    && $s['profiles'][0] === ['value' => 'marketing', 'label' => 'Marketing Blog', 'text' => PQM_Admin::blog_profiles()['marketing']['website_context']]
    && end($s['profiles']) === ['value' => 'custom', 'label' => 'Custom', 'text' => '']);
check('voice presets: every Editorial Standards preset, then Custom',
    array_column($s['voice_presets'], 'value') === array_merge(array_keys(pqm_voice_presets()), ['custom'])
    && $s['voice_presets'][0]['text'] === pqm_voice_presets()['professional']['instruction']
    && end($s['voice_presets']) === ['value' => 'custom', 'label' => 'Custom', 'text' => '']);
check('cron knobs: the stored (default) values the schedule step names',
    $s['cron_enabled'] === false && $s['cron_frequency'] === 'daily' && $s['cron_batch_size'] === 5
    && $s['cron_min_score'] === 60 && $s['cron_max_age_days'] === 30 && $s['cron_command'] === 'review');
check('capability flags: manager with pqm_manage_agents on a free site', $s['can_manage_agents'] === true && $s['is_pro'] === false);
check('build: pro (story 54; the harness runs the Pro build, licensed or not)', $s['build'] === 'pro');
pqm_fs()->pro = true;
check('... pro site: is_pro', setup_state()['is_pro'] === true);
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
check('... manager without pqm_manage_agents: can_manage_agents false', setup_state()['can_manage_agents'] === false);
$GLOBALS['tw']['caps'] = $manager;
pqm_fs()->pro = false;
site(['pqm_settings' => $fresh['pqm_settings'] + ['blog_profile' => 'food', 'website_context' => 'My old words', 'brand_voice_preset' => 'casual']]);
$s = setup_state();
check('stored choices prefill: preset key, and the stored custom text kept beside it',
    $s['blog_profile'] === 'food' && $s['website_context'] === 'My old words' && $s['brand_voice_preset'] === 'casual');
check('a GET writes nothing', $GLOBALS['tw']['writes'] === []);

/* Row: legacy complete site — configuration steps open, nothing auto-written */
site($connected, ['ship']);
$s = setup_state();
check('complete site: the configuration steps read as open (completion never depended on them)',
    $s['complete'] === true && $s['steps']['profile'] === false && $s['steps']['standards'] === false && $s['steps']['schedule'] === false
    && $GLOBALS['tw']['writes'] === []);

/* Row: skip — the step is ticked, no setting written */
echo "POST /setup — configuration steps tick (Skip)\n";
foreach (['profile', 'standards', 'schedule'] as $step) {
    site($fresh);
    $r = post_setup(['step' => $step, 'done' => true]);
    check("skip \"$step\": ticked, only the progress option written, settings untouched",
        $r instanceof WP_REST_Response && $r->get_data()['steps'][$step] === true
        && $GLOBALS['tw']['writes'] === [PQM_SETUP_OPTION] && $GLOBALS['tw']['options']['pqm_settings'] === $fresh['pqm_settings']);
}

/* A configured site with every key the Settings form stores, nested agent prompts included. */
$configured = pqm_default_settings();
$configured['n8n_webhook_url'] = 'https://acme.app.n8n.cloud/webhook/pqm-execute';
$configured['webhook_token'] = $token;
$configured['blog_profile'] = 'technical';
$configured['website_context'] = 'Kept custom context';
$configured['brand_voice_preset'] = 'custom';
$configured['brand_voice'] = 'Kept custom voice';
$configured['cron_frequency'] = 'weekly';
$configured['cron_batch_size'] = 12;
$configured['agent_prompts']['review']['instructions'] = 'Be strict.';
$configured['author_quota'] = 7;

/** Every stored key but those in $expect is byte-identical (serialized) to $before, key order kept. */
function merged_only(array $before, array $expect): bool {
    $after = $GLOBALS['tw']['options']['pqm_settings'];
    return serialize($after) === serialize(array_merge($before, $expect)) && array_keys($after) === array_keys($before);
}

/* Row: a pick lands alone */
echo "POST /setup/settings — merge-save (story 55)\n";
site(['pqm_settings' => $configured], ['ship']);
$r = post_settings(['blog_profile' => 'marketing']);
check('preset pick: blog_profile saved alone; every other key byte-identical (custom text kept beside it)',
    $r instanceof WP_REST_Response && merged_only($configured, ['blog_profile' => 'marketing']));
check('... only pqm_settings written, no cron scheduled, the answer is the fresh state',
    $GLOBALS['tw']['writes'] === ['pqm_settings'] && $GLOBALS['tw']['scheduled'] === [] && $r->get_data()['blog_profile'] === 'marketing');
check('... the agents now get the preset text (pqm_resolved_context)',
    pqm_resolved_context(pqm_get_settings()) === PQM_Admin::blog_profiles()['marketing']['website_context']);

site(['pqm_settings' => $configured]);
post_settings(['blog_profile' => 'custom', 'website_context' => "  <b>Home automation</b> for tinkerers.  "]);
check('Custom: key and its text saved, the text sanitized like the Settings form (tags stripped, trimmed)',
    merged_only($configured, ['blog_profile' => 'custom', 'website_context' => 'Home automation for tinkerers.']));
site(['pqm_settings' => $configured]);
post_settings(['brand_voice_preset' => 'direct']);
check('Editorial Standards preset: saved alone, the stored custom voice kept', merged_only($configured, ['brand_voice_preset' => 'direct']));
site(['pqm_settings' => $configured]);
post_settings(['brand_voice_preset' => 'custom', 'brand_voice' => 'Short sentences.']);
check('Editorial Standards Custom: key and text saved', merged_only($configured, ['brand_voice_preset' => 'custom', 'brand_voice' => 'Short sentences.']));

/* Row: refusals — 400, nothing stored */
$refused = [
    'a stray key'                              => ['author_quota' => 0],
    'a valid key beside a stray one'           => ['blog_profile' => 'food', 'n8n_webhook_url' => 'https://evil.example/webhook/x'],
    'the webhook token'                        => ['webhook_token' => 'mine'],
    'a cron knob (only the switch is offered)' => ['cron_frequency' => 'daily'],
    'an unknown Blog Profile'                  => ['blog_profile' => 'neuroscience'],
    'an unknown Editorial Standards preset'    => ['brand_voice_preset' => 'shouty'],
    'a non-text Custom text'                   => ['website_context' => ['x']],
    'an empty body'                            => [],
    'no JSON body'                             => null,
    'a list instead of an object'              => ['marketing'],
];
foreach ($refused as $label => $json) {
    site(['pqm_settings' => $configured]);
    $r = post_settings($json);
    check("$label: refused with 400, nothing stored", $r instanceof WP_Error && ($r->data['status'] ?? null) === 400
        && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['options']['pqm_settings'] === $configured);
}

/* Row: scheduled reviews — pqm_manage_agents on a pro site only */
echo "POST /setup/settings — scheduled reviews gate (story 55)\n";
$cron_cases = [
    'free site, manager with pqm_manage_agents'    => [$manager, false],
    'pro site, manager without pqm_manage_agents'  => [['pqm_manage_settings'], true],
    'free site, manager without pqm_manage_agents' => [['pqm_manage_settings'], false],
];
foreach ($cron_cases as $label => [$caps, $pro]) {
    $GLOBALS['tw']['caps'] = $caps;
    pqm_fs()->pro = $pro;
    site(['pqm_settings' => $configured]);
    $r = post_settings(['cron_enabled' => true]);
    check("$label: refused with 403, nothing stored or scheduled", $r instanceof WP_Error && ($r->data['status'] ?? null) === 403
        && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['scheduled'] === []);
    site(['pqm_settings' => $configured]);
    $r = post_settings(['blog_profile' => 'food', 'cron_enabled' => true]);
    check("... with a profile beside it: still 403, the profile not stored either", $r instanceof WP_Error && $GLOBALS['tw']['writes'] === []);
}
$GLOBALS['tw']['caps'] = $manager;
pqm_fs()->pro = true;
site(['pqm_settings' => $configured]);
$r = post_settings(['cron_enabled' => true]);
check('pro site + pqm_manage_agents: enabled with the stored knobs, every other key byte-identical',
    $r instanceof WP_REST_Response && merged_only($configured, ['cron_enabled' => true]) && $r->get_data()['cron_enabled'] === true);
check('... the schedule is set right away, at the stored frequency', $GLOBALS['tw']['scheduled'] === [['pqm_weekly', PQM_Cron::HOOK]]);
site(['pqm_settings' => $configured]);
$r = post_settings(['cron_enabled' => 'yes']);
check('... a non-boolean switch: refused with 400', $r instanceof WP_Error && ($r->data['status'] ?? null) === 400 && $GLOBALS['tw']['writes'] === []);
pqm_fs()->pro = false;

/* Row: re-run without a pick — settings, URL and token byte-identical */
echo "Re-run, every configuration step walked without a pick (story 55)\n";
site(['pqm_settings' => $configured, PQM_SETUP_OPTION => ['steps' => [], 'complete' => true]], ['ship']);
$before = serialize($GLOBALS['tw']['options']['pqm_settings']);
setup_state();
foreach (['profile', 'standards', 'schedule'] as $step) {
    post_setup(['step' => $step, 'done' => true]);
}
$s = setup_state();
check('pqm_settings byte-identical (webhook URL and token included); only progress written',
    serialize($GLOBALS['tw']['options']['pqm_settings']) === $before && array_unique($GLOBALS['tw']['writes']) === [PQM_SETUP_OPTION]);
check('... the three steps done, the setup still complete', $s['complete'] === true
    && $s['steps']['profile'] === true && $s['steps']['standards'] === true && $s['steps']['schedule'] === true);

/* Row: one home — the AJAX handlers wrap the same two helpers */
echo "One home for save + ping; the unused AJAX save removed\n";
$src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php');
check('the unused AJAX save is gone (no handler, no hook)', !str_contains($src, 'ajax_save_webhook_url')
    && !str_contains(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php'), 'pqm_save_webhook_url'));
check('ajax_test_connection() calls ping_webhook()', (bool) preg_match('/function ajax_test_connection\(\).*?self::ping_webhook\(/s', $src));
check('wp_remote_post appears once in the admin class (the ping helper)', substr_count($src, 'wp_remote_post(') === 1);

/* Row: uninstall — the progress and n8n connection options are removed, through the SDK hook (story 44) */
echo "Uninstall\n";
check('the SDK after_uninstall hook calls PQM_Activator::uninstall()',
    (pqm_fs()->actions['after_uninstall'] ?? []) === [['PQM_Activator', 'uninstall']]);
$icon = (pqm_fs()->filters['plugin_icon'] ?? [null])[0];
check('the SDK plugin_icon filter returns a bundled icon file (no remote fetch)',
    is_callable($icon) && is_file($icon()));
$GLOBALS['tw_deleted'] = [];
function delete_option($k) { $GLOBALS['tw_deleted'][] = $k; return true; }
function delete_metadata(...$a) { return true; }
function wp_roles() { return (object) ['role_objects' => []]; }
function get_users($args) { return []; }
function wp_clear_scheduled_hook(...$a) {}
PQM_Activator::uninstall();
check('uninstall deletes the setup progress option (PQM_SETUP_OPTION)', in_array(PQM_SETUP_OPTION, $GLOBALS['tw_deleted'], true));
check('uninstall deletes the n8n connection option (PQM_N8n::OPTION: base URL and API key)', PQM_N8n::OPTION === 'pqm_n8n_api'
    && in_array(PQM_N8n::OPTION, $GLOBALS['tw_deleted'], true));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
