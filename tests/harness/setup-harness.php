<?php
/**
 * Setup dialog harness for story 36 — covers the I/O & Edge-Case Matrix of
 * the setup routes: registration + permission (managers only), the GET
 * state (since entry 6 without the values to copy), the derived
 * completion for sites connected before the dialog, progress validation,
 * the connect step (test URL refused and not stored, production URL stored
 * and pinged, ping failure stored with the cause) and the uninstall list.
 * WP stubs, no WP install. Callbacks are called directly, so the route's
 * own checks (step whitelist) are what is asserted, not WP's arg schema.
 *
 * Story 55 rows: the three configuration steps (state fields, tickable,
 * open on a completed site) and POST /setup/settings — key whitelist,
 * value checks, merge isolation (every other stored key byte-identical),
 * the scheduled-reviews gate (postquality_manage_agents + pro) and a no-change walk.
 *
 * n8n provisioning entry 6 rows: the derived n8n steps and the new state
 * fields per site state (key passing, failed check, connected, provisioned,
 * leftovers, old stored step keys), and POST /setup { manual: true } (a
 * boolean only, kept by every other write); since story 106 `{ manual: false }`
 * removes the choice ("Use the guided setup instead").
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['tw'] = [
    'caps'          => [],
    'options'       => [], // option name => value
    'autoload'      => [], // option name => autoload flag passed to update_option()
    'writes'        => [], // option names written, in order
    'pings'         => [], // wp_remote_post() calls: [url, args]
    'ping_response' => ['response' => ['code' => 200]],
    'ping_callback' => 'received', // story 2: what wp_remote_post() plays as n8n's callback of the ping — received, refused or none
    'app_passwords' => true,
    'scheduled'     => [], // wp_schedule_event() calls: [recurrence, hook]
];
$GLOBALS['pqm_routes'] = [];

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
function plugin_dir_url($f) { return ''; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $tag === 'postquality_ping_callback_wait' ? 0 : $value; } // story 2: the stub plays the callback before the reply, nothing to wait for
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; }
function restore_previous_locale() {}
function wp_next_scheduled($hook) { return false; }
function wp_schedule_event($ts, $recurrence, $hook) { $GLOBALS['tw']['scheduled'][] = [$recurrence, $hook]; return true; }
function wp_unschedule_event(...$a) { return true; }
function is_admin() { return false; }
function home_url($p = '') { return 'https://example.com' . $p; }
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
function wp_remote_post($url, $args) { $GLOBALS['tw']['pings'][] = [$url, $args]; pqm_harness_ping_callback($args, $GLOBALS['tw']['ping_callback']); return $GLOBALS['tw']['ping_response']; }
/* The connect step answers the n8n Workflow page's refreshed body (story 67: the whole page under the H1). */
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') { return $url . '&_wpnonce=stub'; }
define('MINUTE_IN_SECONDS', 60);
function human_time_diff($from, $to = 0) { return 'a while'; }
function wp_get_current_user() { return (object) ['ID' => 1, 'user_login' => 'editor-anna']; }
function wp_date($format, $ts = null) { return gmdate($format, $ts ?? time()); }
function wp_is_application_passwords_available_for_user($user) { return $GLOBALS['tw']['app_passwords']; }
/* Story 65: each call recorded, so a row can prove the license notice is escaped in the plugin's text domain; both
   return what lib/wp-stubs.php's return, so no other row moves. */
function esc_html__($s, $d = null) { $GLOBALS['tw_escaped'][] = ['esc_html__', $s, $d]; return $s; }
function esc_url($s) { $GLOBALS['tw_escaped'][] = ['esc_url', (string) $s]; return (string) $s; }

const PING_PROVEN = 'Ping OK — this site reaches n8n, the webhook credential is right, your workflow is up to date and n8n called this site back with the right token: the round trip works.';

/* ─── Load the plugin bootstrap ──────────────────────────────────────── */
/* Story 2: every read of the pending ping during the wait bypasses the cache with wp_cache_delete(); counted here, so a
   row can tell whether a branch waited at all (declared before the lib, whose own stub yields). */
function wp_cache_delete($k, $group = '') { $GLOBALS['tw_cache_deletes'][$k] = ($GLOBALS['tw_cache_deletes'][$k] ?? 0) + 1; return true; }
/* Bug 90: the main file's WP hooks are recorded (tag, callback, priority), so a row can prove the uninstall hook's registration. */
function add_action($tag, $cb, $priority = 10, $accepted_args = 1) { $GLOBALS['tw']['actions'][] = [$tag, $cb, $priority]; }
require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */
/* Review rows for PostQuality_DB::has_result(): the stub answers its one get_var
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
    $GLOBALS['tw_cache_deletes'] = [];
}
/** Reads of the pending ping since site(): 0 = the branch did not wait for a callback, 1 = it read once (the wait is 0 s here). */
function ping_reads(): int { return $GLOBALS['tw_cache_deletes'][PostQuality_Admin::PING_OPTION] ?? 0; }

function setup_state(): array {
    return PostQuality_REST_API::get_setup_callback()->get_data();
}

function post_setup(array $params) {
    return PostQuality_REST_API::update_setup_callback(new WP_REST_Request($params));
}

function post_settings($json) {
    return PostQuality_REST_API::setup_settings_callback(new WP_REST_Request([], $json));
}

function connect(string $url): array {
    return PostQuality_REST_API::setup_connect_callback(new WP_REST_Request(['webhook_url' => $url]))->get_data();
}

$ns = POSTQUALITY_REST_NAMESPACE;
$manager = ['postquality_manage_settings', 'postquality_manage_agents', 'postquality_view_reviews', 'postquality_run_free_agents'];
$editor  = ['postquality_manage_agents', 'postquality_view_reviews', 'postquality_run_free_agents', 'postquality_run_paid_agents'];
$token   = 'tok-36';
$connected = ['postquality_settings' => ['n8n_webhook_url' => 'https://acme.app.n8n.cloud/webhook/pqm-execute', 'webhook_token' => $token]];
$fresh     = ['postquality_settings' => ['n8n_webhook_url' => '', 'webhook_token' => $token, 'license_key' => 'kept']];

/* Row: registration + permission — managers only, on all three */
echo "Route registration and permission\n";
PostQuality_REST_API::register_routes();
$routes = $GLOBALS['pqm_routes'];
$gate = ['PostQuality_REST_API', 'check_manage_settings'];
check('/setup registered with a GET and a POST endpoint',
    ($routes[$ns . '/setup'][0]['methods'] ?? null) === 'GET' && ($routes[$ns . '/setup'][1]['methods'] ?? null) === 'POST');
check('/setup GET and POST gated by check_manage_settings',
    ($routes[$ns . '/setup'][0]['permission_callback'] ?? null) === $gate && ($routes[$ns . '/setup'][1]['permission_callback'] ?? null) === $gate);
check('/setup/connect is POST, gated by check_manage_settings',
    ($routes[$ns . '/setup/connect']['methods'] ?? null) === 'POST' && ($routes[$ns . '/setup/connect']['permission_callback'] ?? null) === $gate);
check('/setup/settings is POST, gated by check_manage_settings (story 55)',
    ($routes[$ns . '/setup/settings']['methods'] ?? null) === 'POST' && ($routes[$ns . '/setup/settings']['permission_callback'] ?? null) === $gate);
$GLOBALS['tw']['caps'] = $editor;
check('non-manager (Editor caps): refused — WP answers 403', PostQuality_REST_API::check_manage_settings() === false);
$GLOBALS['tw']['caps'] = [];
check('no caps: refused', PostQuality_REST_API::check_manage_settings() === false);
$GLOBALS['tw']['caps'] = $manager;
check('manager: allowed', PostQuality_REST_API::check_manage_settings() === true);

/* Row: fresh site — nothing done, the values to copy */
echo "GET /setup — fresh site\n";
site($fresh);
$s = setup_state();
check('seven steps (the key path\'s n8n and provision, the manual path\'s manual, review, then profile, standards, schedule), none done',
    array_keys($s['steps']) === ['n8n', 'provision', 'manual', 'review', 'profile', 'standards', 'schedule']
    && !in_array(true, $s['steps'], true));
check('entry 6 fields: the n8n connection (no key), not manual, nothing provisioned, no leftovers',
    $s['n8n'] === ['base_url' => '', 'key_set' => false, 'key_hint' => '', 'probe' => null, 'connected' => false, 'setup' => null, 'adoptable' => false, 'rotatable' => false] && $s['manual'] === false && $s['manual_reason'] === null
    && $s['provisioned'] === false && $s['leftovers'] === false);
check('not complete, not connected, no result', $s['complete'] === false && $s['connected'] === false && $s['first_result'] === false);
check('per-site webhook path for the URL field placeholder', $s['webhook_path'] === 'pqm-execute-example-com');
check('the REST root n8n sends results to, for the first-review give-up line', $s['rest_url'] === 'https://example.com/wp-json/');
check('no values to copy any more (entry 6): no token, import or download URL, user login, site host or Application Password flag',
    !array_intersect(['token', 'import_url', 'download_url', 'user_login', 'site_host', 'app_passwords'], array_keys($s)));
check('... the webhook token is in no field of the reply', !str_contains(json_encode($s), $token));
check('a GET writes nothing', $GLOBALS['tw']['writes'] === []);

/* Row: legacy connected — complete is derived, no migration */
echo "GET /setup — completion derived for sites connected before the dialog\n";
site($connected, ['needs_revision']);
$s = setup_state();
check('connected + a scored result stored, no progress option, no version ever reported (pre-handshake import): complete, review step done',
    $s['complete'] === true && $s['steps']['review'] === true && $s['first_result'] === true && postquality_setup_complete() === true);
check('... and nothing is written (no migration)', $GLOBALS['tw']['writes'] === [] && !isset($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]));
check('has_result() SQL excludes the placeholder and both failed-run shapes',
    str_contains($wpdb->last_sql, "FROM wp_postquality_reviews WHERE status NOT IN ('', 'processing', 'error') LIMIT 1"));
site($connected);
check('connected, no result yet: not complete', setup_state()['complete'] === false && postquality_setup_complete() === false);
site($connected, ['error', '', 'processing']);
check('connected, only a failed run (error, empty fallback) and a placeholder: not complete',
    setup_state()['complete'] === false && setup_state()['first_result'] === false);
site($connected + ['postquality_workflow_version_seen' => ['version' => POSTQUALITY_WORKFLOW_VERSION, 'via' => 'test', 'at' => time()]], ['error']);
check('version handshake seen (a ping, or a failed row\'s callback) but no real result: not complete', setup_state()['complete'] === false);
site($connected, ['rewritten']);
check('any non-failed result counts (a Rewrite confirmation)', setup_state()['complete'] === true);
site($fresh, ['ship']);
check('a result once, URL emptied since: not complete', setup_state()['complete'] === false);
site($fresh + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => true]]);
check('progress flag complete: complete even while not connected', setup_state()['complete'] === true);

/* Row: progress — tick, untick, resume data; unknown step refused */
echo "POST /setup — progress\n";
site($fresh);
$r = post_setup(['step' => 'profile', 'done' => true]);
check('tick a step: stored once per site, autoload off', ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['profile'] ?? null) === true
    && $GLOBALS['tw']['autoload'][POSTQUALITY_SETUP_OPTION] === false);
check('... the answer is the fresh state (the dialog resumes from it)', $r instanceof WP_REST_Response && $r->get_data()['steps']['profile'] === true
    && $r->get_data()['steps']['standards'] === false);
post_setup(['step' => 'standards', 'done' => true]);
check('a second tick keeps the first', setup_state()['steps'] === ['n8n' => false, 'provision' => false, 'manual' => false, 'review' => false,
    'profile' => true, 'standards' => true, 'schedule' => false]);
post_setup(['step' => 'profile', 'done' => false]);
check('untick', setup_state()['steps']['profile'] === false);
// The n8n steps are derived (entry 6), never ticked; the manual steps before entry 6 are gone.
foreach (['foo', 'n8n', 'provision', 'manual', 'review', 'connect', 'instance', 'import', '', 'PROFILE'] as $step) {
    $before = $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION];
    $r = post_setup(['step' => $step, 'done' => true]);
    check("step \"$step\" refused with 400, nothing stored", $r instanceof WP_Error && ($r->data['status'] ?? null) === 400
        && $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === $before);
}
check('no step and no complete: refused', post_setup([]) instanceof WP_Error);

/* Row: "Set up by hand instead" stores the choice (n8n provisioning entry 6) */
echo "POST /setup { manual: true } — the manual-path choice\n";
check('the route schema takes `manual` as a boolean', ($GLOBALS['pqm_routes'][$ns . '/setup'][1]['args']['manual'] ?? null) === ['required' => false, 'type' => 'boolean']);
site($fresh + [POSTQUALITY_SETUP_OPTION => ['steps' => ['profile' => true], 'complete' => false]]);
$r = post_setup(['manual' => true]);
check('stored beside the progress, autoload off; nothing else written', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => ['profile' => true], 'complete' => false, 'manual' => true]
    && $GLOBALS['tw']['autoload'][POSTQUALITY_SETUP_OPTION] === false && $GLOBALS['tw']['writes'] === [POSTQUALITY_SETUP_OPTION]);
check('... the answer: manual, the reason {kind: choice}, the manual step open', $r instanceof WP_REST_Response && $r->get_data()['manual'] === true
    && $r->get_data()['manual_reason'] === ['kind' => 'choice'] && $r->get_data()['steps']['manual'] === false);
post_setup(['manual' => true]);
check('choosing again changes nothing', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => ['profile' => true], 'complete' => false, 'manual' => true]);
// Callback-level guards: the route's `type: boolean` schema already refuses "yes" and coerces 1 / "true" / "false" / 0 upstream.
foreach (['"yes"' => 'yes', '1' => 1, '"true"' => 'true', '"false"' => 'false', '0' => 0, '[]' => []] as $label => $value) {
    site($fresh + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => false, 'manual' => true]]);
    $r = post_setup(['manual' => $value]);
    check("callback: a non-boolean value ($label) is refused with 400 pqm_setup_bad_manual, nothing stored or removed", $r instanceof WP_Error && $r->get_error_code() === 'pqm_setup_bad_manual'
        && ($r->data['status'] ?? null) === 400 && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['manual'] === true);
}

/* Row (story 106): "Use the guided setup instead" removes the choice */
echo "POST /setup { manual: false } — the way back to the guided setup (story 106)\n";
site($fresh + [POSTQUALITY_SETUP_OPTION => ['steps' => ['profile' => true], 'complete' => false, 'manual' => true]]);
$r = post_setup(['manual' => false]);
check('manual: false removes the key; the rest of the progress kept, autoload off; nothing else written', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => ['profile' => true], 'complete' => false]
    && $GLOBALS['tw']['autoload'][POSTQUALITY_SETUP_OPTION] === false && $GLOBALS['tw']['writes'] === [POSTQUALITY_SETUP_OPTION]);
check('... the answer: the key path (not manual, no reason), Connect your n8n open', $r instanceof WP_REST_Response && $r->get_data()['manual'] === false
    && $r->get_data()['manual_reason'] === null && $r->get_data()['steps']['n8n'] === false && postquality_manual_reason() === null);
post_setup(['manual' => false]);
check('... again, with nothing stored: no `manual` key, the progress unchanged', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => ['profile' => true], 'complete' => false]);
$r = post_setup(['manual' => true]);
check('... choosing by hand again stores it again: the manual path, the reason {kind: choice}', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => ['profile' => true], 'complete' => false, 'manual' => true]
    && $r->get_data()['manual'] === true && $r->get_data()['manual_reason'] === ['kind' => 'choice']);
$key_ok = [PostQuality_N8n::OPTION => ['base_url' => 'https://n8n.example.com', 'api_key' => 'n8n-secret-key-ab12', 'probe' => ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => 1760000000]]];
site($fresh + $key_ok + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => false, 'manual' => true]]);
$r = post_setup(['manual' => false]);
check('a passing key with the choice stored: removed, the key kept; the answer: the key path, Connect your n8n done (Set up the workflow next)', !array_key_exists('manual', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION])
    && $GLOBALS['tw']['options'][PostQuality_N8n::OPTION] === $key_ok[PostQuality_N8n::OPTION] && $GLOBALS['tw']['writes'] === [POSTQUALITY_SETUP_OPTION]
    && $r->get_data()['manual'] === false && $r->get_data()['steps']['n8n'] === true && $r->get_data()['steps']['provision'] === false);
$probe_failed = [PostQuality_N8n::OPTION => ['base_url' => 'https://n8n.example.com', 'failed' => ['cause' => 'api_disabled', 'message' => 'The public API is turned off on this instance.', 'at' => 1760000000]]];
site($fresh + $probe_failed + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => false, 'manual' => true]]);
$r = post_setup(['manual' => false]);
check('a failed check and the choice: the choice removed, the failed check still the reason (no way back from it here), its record untouched',
    !array_key_exists('manual', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]) && $GLOBALS['tw']['options'][PostQuality_N8n::OPTION] === $probe_failed[PostQuality_N8n::OPTION]
    && $r->get_data()['manual'] === true && ($r->get_data()['manual_reason']['kind'] ?? '') === 'probe');

/* Row: the choice survives every progress write */
echo "POST /setup — the manual-path choice is kept\n";
site($fresh + [POSTQUALITY_SETUP_OPTION => ['steps' => ['profile' => true], 'complete' => false, 'manual' => true]]);
post_setup(['step' => 'standards', 'done' => true]);
check('a step tick keeps `manual`', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => ['profile' => true, 'standards' => true], 'complete' => false, 'manual' => true]);
post_setup(['complete' => true]);
check('completing keeps `manual`', ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['manual'] ?? null) === true);
connect('https://n8n.example.com/webhook/pqm-execute-example-com');
check('the connect route keeps `manual`', ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['manual'] ?? null) === true);
check('postquality_manual_reason() reads it: {kind: choice}', postquality_manual_reason() === ['kind' => 'choice']);
site($fresh);
post_setup(['step' => 'profile', 'done' => true]);
check('no choice stored: no `manual` key written', !array_key_exists('manual', $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]));

/* Row: the n8n steps derived per site state (entry 6), as the dialog reads them */
echo "GET /setup — the n8n steps per site state\n";
$passing = ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => 1760000000];
$key     = [PostQuality_N8n::OPTION => ['base_url' => 'https://n8n.example.com', 'api_key' => 'n8n-secret-key-ab12', 'probe' => $passing]];
$failed  = [PostQuality_N8n::OPTION => ['base_url' => 'https://n8n.example.com', 'failed' => ['cause' => 'api_disabled', 'message' => 'The public API is turned off on this instance.', 'at' => 1760000000]]];
$n8n_steps = static fn(array $s): array => array_slice($s['steps'], 0, 3, true);
site($fresh + $key);
$s = setup_state();
check('key stored with a passing check: Connect your n8n done, Set up the workflow open, the key path',
    $n8n_steps($s) === ['n8n' => true, 'provision' => false, 'manual' => false] && $s['manual'] === false
    && $s['n8n'] === ['base_url' => 'https://n8n.example.com', 'key_set' => true, 'key_hint' => 'ab12', 'probe' => $passing, 'connected' => true, 'setup' => null, 'adoptable' => false, 'rotatable' => false]);
// Story 74: a refusal of the stored key replaces its probe; every reader asks the one predicate.
$refused_key = [PostQuality_N8n::OPTION => ['probe' => ['ok' => false, 'cause' => 'key', 'message' => 'n8n refused this API key. Create a new one in n8n under Settings → n8n API, then try again.', 'at' => 1760000500]] + $key[PostQuality_N8n::OPTION]];
site($fresh + $refused_key);
$s = setup_state();
check('story 74, a stored refusal (key): n8n.connected false, Connect your n8n open, no manual reason (the key path, a new key is the fix)',
    $s['n8n']['connected'] === false && $s['n8n']['key_set'] === true && $s['n8n']['probe']['cause'] === 'key'
    && $n8n_steps($s) === ['n8n' => false, 'provision' => false, 'manual' => false] && $s['manual'] === false && $s['manual_reason'] === null
    && PostQuality_N8n::connected() === false && postquality_manual_reason() === null);
$scoped_reason  = 'This API key cannot create credentials or publish workflows: n8n limits what it may do. In n8n under Settings → n8n API, create a key without scope limits, then try again.';
$refused_scoped = [PostQuality_N8n::OPTION => ['probe' => ['ok' => false, 'cause' => 'scoped', 'message' => $scoped_reason . ' Or set up the workflow by hand with the Workflow File section.', 'at' => 1760000500],
    'failed' => ['cause' => 'scoped', 'message' => $scoped_reason, 'at' => 1760000500]] + $key[PostQuality_N8n::OPTION]];
site($fresh + $refused_scoped);
$s = setup_state();
check('... a stored refusal (scoped): n8n.connected false, the n8n step open, the manual path with its reason (the probe kind, the cause sentence)',
    $s['n8n']['connected'] === false && $n8n_steps($s)['n8n'] === false && $s['manual'] === true
    && $s['manual_reason'] === ['kind' => 'probe', 'message' => $scoped_reason, 'at' => wp_date('F j, Y', 1760000500)]);
check('... the n8n API key is in no field of the reply, only its last 4 characters', !str_contains(json_encode($s), 'n8n-secret-key'));
site($fresh + $key + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => false, 'manual' => true]]);
check('key path wins: a passing key with the choice stored reads not manual', setup_state()['manual'] === false && setup_state()['manual_reason'] === null);
site($fresh + $failed);
$s = setup_state();
check('a failed check that offered the manual path: manual, with its date and cause; no step done',
    $s['manual'] === true && $s['manual_reason'] === ['kind' => 'probe', 'message' => 'The public API is turned off on this instance.', 'at' => wp_date('F j, Y', 1760000000)]
    && !in_array(true, $n8n_steps($s), true));
// Only what the plugin did ticks a step (owner, 2026-10-15): a saved URL follows how its workflow was made.
$map = [PostQuality_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => [], 'path' => 'pqm-execute-example-com', 'wizard_created' => true, 'at' => 1]];
site($connected);
$s = setup_state();
check('hand-made: a URL saved without the setup map, no key (legacy, Settings, no ping record): the manual path, Set up by hand done, no reason stored',
    $s['manual'] === true && $s['manual_reason'] === null && $s['provisioned'] === false
    && $n8n_steps($s) === ['n8n' => false, 'provision' => false, 'manual' => true]);
site($connected + $key);
$s = setup_state();
check('hand-made, a passing key: still the manual path (the key waits for adoption, 6.8); Connect your n8n reads the key, Set up the workflow never ticked',
    $s['manual'] === true && $s['manual_reason'] === null && $n8n_steps($s) === ['n8n' => true, 'provision' => false, 'manual' => true]);
site($connected + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => true]]);
$s = setup_state();
check('hand-made, complete: the manual path, complete', $s['manual'] === true && $s['complete'] === true && $s['steps']['review'] === true);
site($connected + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => false, 'manual' => true]]);
$s = setup_state();
check('hand-made with the choice stored: the manual path, the choice as its reason, Set up by hand done',
    $s['manual'] === true && $s['manual_reason'] === ['kind' => 'choice'] && $n8n_steps($s) === ['n8n' => false, 'provision' => false, 'manual' => true]);
site($connected + [POSTQUALITY_SETUP_OPTION => ['steps' => ['connect' => true], 'complete' => false]]);
check('hand-made, its last dialog ping passed: Set up by hand done, nothing else',
    $n8n_steps(setup_state()) === ['n8n' => false, 'provision' => false, 'manual' => true] && setup_state()['manual'] === true);
site($connected + [POSTQUALITY_SETUP_OPTION => ['steps' => ['connect' => false], 'complete' => false]]);
check('hand-made, the ping after a by-hand paste failed: the manual path, Set up by hand open, the record not listed',
    $n8n_steps(setup_state()) === ['n8n' => false, 'provision' => false, 'manual' => false] && setup_state()['manual'] === true
    && !array_key_exists('connect', setup_state()['steps']));
site($connected + $key + $map);
$s = setup_state();
check('provisioned: the setup map, a URL, a passing key: the key path, both n8n steps done',
    $s['manual'] === false && $s['provisioned'] === true && $n8n_steps($s) === ['n8n' => true, 'provision' => true, 'manual' => true]);
$adopted = [PostQuality_N8n::SETUP_OPTION => ['workflow_id' => 'wf-hand', 'credentials' => ['webhook' => 'c-h1', 'anthropic' => 'c-k1'], 'path' => 'pqm-execute', 'wizard_created' => false, 'at' => 1]];
site($connected + $key + $adopted);
$s = setup_state();
check('adopted (entry 8): a map with wizard_created false reads as provisioned — the key path, both n8n steps done; the island\'s setup line, not adoptable',
    $s['manual'] === false && $s['provisioned'] === true && $n8n_steps($s) === ['n8n' => true, 'provision' => true, 'manual' => true]
    && $s['n8n']['setup'] === ['wizard_created' => false, 'at' => 1, 'host' => '', 'same_instance' => true] && $s['n8n']['adoptable'] === false);
site($connected + $key);
check('hand-made with a passing key, before adoption: the island offers it (adoptable)', setup_state()['n8n']['adoptable'] === true && setup_state()['n8n']['setup'] === null);
site($connected + $map + [PostQuality_N8n::OPTION => ['base_url' => 'https://n8n.example.com']]);
$s = setup_state();
check('provisioned, key forgotten: the key path, Connect your n8n open, Set up the workflow done',
    $s['manual'] === false && $s['manual_reason'] === null && $n8n_steps($s) === ['n8n' => false, 'provision' => true, 'manual' => true]);
site($connected + $key + $map + [POSTQUALITY_SETUP_OPTION => ['steps' => ['connect' => false], 'complete' => false]]);
check('provisioned, the ping failed: the key path, Set up the workflow open, the record not listed',
    $n8n_steps(setup_state()) === ['n8n' => true, 'provision' => false, 'manual' => false] && setup_state()['manual'] === false
    && !array_key_exists('connect', setup_state()['steps']));
site($fresh + [POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => false, 'manual' => true]]);
$s = setup_state();
check('the choice stored, no key, no URL: the manual path, Set up by hand open (unchanged)',
    $s['manual'] === true && $s['manual_reason'] === ['kind' => 'choice'] && !in_array(true, $n8n_steps($s), true));
site($fresh + $key + $map);
$s = setup_state();
check('provisioned, URL missing: provisioned, the key path, Set up the workflow open', $s['provisioned'] === true && $s['manual'] === false
    && $s['steps']['provision'] === false && $s['connected'] === false);
site($fresh + $key + [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://n8n.example.com', 'credentials' => ['c1'], 'at' => 1]]);
check('leftovers waiting: leftovers', setup_state()['leftovers'] === true && setup_state()['provisioned'] === false);
site($fresh + [POSTQUALITY_SETUP_OPTION => ['steps' => ['instance' => true, 'import' => true, 'credentials' => true, 'link' => true, 'publish' => true, 'connect' => true], 'complete' => false]]);
$s = setup_state();
check('old stored step keys (the manual steps before entry 6) ignored: none in the reply, nothing done',
    array_keys($s['steps']) === ['n8n', 'provision', 'manual', 'review', 'profile', 'standards', 'schedule'] && !in_array(true, $s['steps'], true)
    && $GLOBALS['tw']['writes'] === []);

/* Row: first review closes the setup — settings untouched */
echo "POST /setup { complete: true }\n";
site($fresh);
$r = post_setup(['complete' => true]);
check('complete stored, state complete', ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['complete'] ?? null) === true && $r->get_data()['complete'] === true);
check('only the progress option was written (settings untouched)', $GLOBALS['tw']['writes'] === [POSTQUALITY_SETUP_OPTION]
    && $GLOBALS['tw']['options']['postquality_settings'] === $fresh['postquality_settings']);
post_setup(['complete' => true]);
check('completing again changes nothing else', $GLOBALS['tw']['writes'] === [POSTQUALITY_SETUP_OPTION, POSTQUALITY_SETUP_OPTION]
    && $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => [], 'complete' => true]);
$r = post_setup(['complete' => false]);
check('complete:false is no reset (refused: no step given)', $r instanceof WP_Error && setup_state()['complete'] === true);

/* Row: connect — test URL refused, production stored + pinged, ping failure stored */
echo "POST /setup/connect\n";
/* Story 67: every reply carries status_html, the page's whole body with the message as its first line. */
$outcome_of = static fn(string $html): string => preg_match('/<p class="pqm-workflow-outcome (is-[a-z]+)">([^<]*)<\/p>/', $html, $m) ? "$m[1]: $m[2]" : '';
$state_of   = static fn(string $html): string => preg_match('/<div class="pqm-workflow-status" data-state="([a-z-]+)">/', $html, $m) ? $m[1] : '';
foreach (['https://acme.app.n8n.cloud/webhook-test/pqm-execute', 'https://n8n.example.com/webhook-test/pqm-execute'] as $url) {
    site($fresh);
    $r = connect($url);
    check("test URL $url: refused with the story 35 warning, nothing stored, nothing sent; the body with the refusal as its outcome line",
        array_keys($r) === ['saved', 'ping_ok', 'message', 'status_html'] && $r['saved'] === false && $r['ping_ok'] === false
        && $r['message'] === postquality_webhook_url_warning($url) && $outcome_of($r['status_html']) === 'is-error: ' . $r['message']
        && $state_of($r['status_html']) === 'not-set-up' && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === []);
}
site($fresh);
$r = connect('   ');
check('empty URL: refused, nothing stored, the refusal in the body\'s outcome line', $r['saved'] === false && $r['message'] === 'Enter your n8n webhook URL first.'
    && $GLOBALS['tw']['writes'] === [] && $outcome_of($r['status_html']) === 'is-error: Enter your n8n webhook URL first.');

$prod = 'https://acme.app.n8n.cloud/webhook/pqm-execute';
/* delete_option() in this harness only records (the uninstall rows' stub, hoisted): a deleted pending ping shows in tw_deleted, not as a gone option. */
$pings_deleted = static fn(): int => count(array_keys($GLOBALS['tw_deleted'] ?? [], 'postquality_ping', true));
site($fresh);
$deleted_before = $pings_deleted();
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200], 'body' => '{"ok":true,"workflow_version":' . POSTQUALITY_WORKFLOW_VERSION . '}'];
$r = connect($prod);
check('production URL, n8n 2xx from a current workflow, the callback received: stored, pinged, says what the ping proved — the round trip (story 2)',
    $r['saved'] === true && $r['ping_ok'] === true && $r['outdated'] === false && $r['message'] === PING_PROVEN);
$ping_body = json_decode($GLOBALS['tw']['pings'][0][1]['body'], true);
check('... the ping body carries callback_url = this site\'s /ping/{id} (20 characters) and auth_token = the webhook token, as a review payload does',
    array_keys($ping_body) === ['command', 'source', 'callback_url', 'auth_token'] && $ping_body['command'] === 'ping'
    && (bool) preg_match('#^https://example\.com/wp-json/post-quality/v1/ping/[A-Za-z0-9]{20}$#', $ping_body['callback_url']) && $ping_body['auth_token'] === $token);
check('... the pending ping written after the URL save (autoload off), claimed by the callback, deleted after the request; the branch waited (one cache-bypassed read)',
    array_slice($GLOBALS['tw']['writes'], 0, 3) === ['postquality_settings', 'postquality_ping', 'postquality_ping'] && $GLOBALS['tw']['autoload']['postquality_ping'] === false && $pings_deleted() === $deleted_before + 1
    && ping_reads() === 1);
$report = $GLOBALS['tw']['options']['postquality_workflow_version_seen'] ?? null;
check('... the replied version is recorded {5, via test, at now} (the URL was just saved)',
    is_array($report) && $report['version'] === POSTQUALITY_WORKFLOW_VERSION && $report['via'] === 'test' && abs($report['at'] - time()) <= 5);
check('... status_html = the page\'s renderer with the message first: set up, "Your workflow answers.", version 5, checked just now by a test',
    $r['status_html'] === PostQuality_Admin::render_workflow_body($r['message'], 'success') && $state_of($r['status_html']) === 'set-up'
    && $outcome_of($r['status_html']) === 'is-success: ' . $r['message'] && str_contains($r['status_html'], 'Your workflow answers.')
    && str_contains($r['status_html'], 'Version ' . POSTQUALITY_WORKFLOW_VERSION . ' · checked just now by a test.'));
check('... merge-save: only the URL changed in postquality_settings',
    $GLOBALS['tw']['options']['postquality_settings'] === ['n8n_webhook_url' => $prod, 'webhook_token' => $token, 'license_key' => 'kept']);
check('... one ping to that URL with the X-PQM-Token header',
    count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === $prod
    && $GLOBALS['tw']['pings'][0][1]['headers'] === ['Content-Type' => 'application/json', 'X-PQM-Token' => $token]);
check('... the ping recorded (autoload off): Set up by hand reads done, on the manual path (no setup map: made by hand); Set up the workflow never',
    ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] ?? null) === true && $GLOBALS['tw']['autoload'][POSTQUALITY_SETUP_OPTION] === false
    && setup_state()['steps']['manual'] === true && setup_state()['steps']['provision'] === false && setup_state()['manual'] === true
    && setup_state()['connected'] === true);

site($fresh);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200], 'body' => '{"message":"Workflow was started"}'];
$r = connect($prod);
check('production URL, n8n 2xx without a version (v1–v4 import): connected, outdated (the dialog warns), older-version message, {0, via test}, the body needs attention',
    $r['ping_ok'] === true && $r['outdated'] === true && str_contains($r['message'], 'your n8n workflow is an older version')
    && ($GLOBALS['tw']['options']['postquality_workflow_version_seen']['version'] ?? null) === 0
    && $state_of($r['status_html']) === 'outdated' && $outcome_of($r['status_html']) === 'is-warning: ' . $r['message']
    && str_contains($r['status_html'], 'an older version that does not report its number; this plugin needs ' . POSTQUALITY_WORKFLOW_VERSION . '.')
    && setup_state()['steps']['manual'] === true);

$before = ['version' => POSTQUALITY_WORKFLOW_VERSION, 'via' => 'command', 'at' => 1700000000];
site($fresh + ['postquality_workflow_version_seen' => $before, POSTQUALITY_SETUP_OPTION => ['steps' => ['profile' => true], 'complete' => false]]);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 404]];
$r = connect($prod);
check('ping fails: the failure recorded on the report (story 67), the version kept; the body needs attention, the message its outcome line',
    postquality_workflow_report() === $before + ['failed' => ['message' => 'n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.', 'at' => postquality_workflow_report()['failed']['at'] ?? 0]]
    && abs(postquality_workflow_report()['failed']['at'] - time()) <= 5 && $state_of($r['status_html']) === 'failing'
    && $outcome_of($r['status_html']) === 'is-error: ' . $r['message'] && str_contains($r['status_html'], 'The last test just now did not pass: n8n responded with 404.'));
check('production URL, n8n 404: stored, message names the cause and the next step',
    $r['saved'] === true && $r['ping_ok'] === false
    && $r['message'] === 'URL saved, but the test did not pass. n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.'
    && $GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url'] === $prod);
check('... the failed ping recorded, other ticks kept: the URL steps stay open (the dialog offers the paste-URL body again)',
    $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION] === ['steps' => ['profile' => true, 'connect' => false], 'complete' => false]
    && setup_state()['steps']['provision'] === false && setup_state()['steps']['manual'] === false && setup_state()['steps']['n8n'] === false);

site($fresh);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 403]];
$r = connect($prod);
check('production URL, n8n 403 (credential refused): stored, message names the credential and its fields',
    $r['saved'] === true && $r['ping_ok'] === false
    && str_ends_with($r['message'], "n8n refused the webhook credential (HTTP 403). In n8n, open the Webhook Trigger's Header Auth credential: its Name must be X-PQM-Token and its Value this site's webhook token. Then try again."));

site($fresh);
$deleted_before = $pings_deleted();
$GLOBALS['tw']['ping_response'] = new WP_Error('http_request_failed', 'cURL error 6: Could not resolve host');
$r = connect($prod);
check('production URL, n8n unreachable: stored, message carries the connection error',
    $r['saved'] === true && $r['ping_ok'] === false && str_ends_with($r['message'], 'Could not connect to n8n: cURL error 6: Could not resolve host'));
check('... no pending ping left behind by a failed request', $pings_deleted() === $deleted_before + 1);

/* Story 2: the callback refused (a wrong token) or missing — the connect step stays open as after any failed ping.
   The callback comes from n8n, not a logged-in user: the manager's own postquality_run_free_agents must not vouch for it. */
$current = ['response' => ['code' => 200], 'body' => '{"ok":true,"workflow_version":' . POSTQUALITY_WORKFLOW_VERSION . '}'];
$GLOBALS['tw']['caps'] = ['postquality_manage_settings'];
site($fresh);
$deleted_before = $pings_deleted();
$GLOBALS['tw']['ping_response'] = $current;
$GLOBALS['tw']['ping_callback'] = 'refused';
$r = connect($prod);
check('current workflow, the callback refused (wrong token): stored, ping_ok false, the message names the token and the node to fix',
    $r['saved'] === true && $r['ping_ok'] === false && array_keys($r) === ['saved', 'ping_ok', 'message', 'status_html']
    && $r['message'] === 'URL saved, but the test did not pass. n8n reached this site, but the callback was refused: it carried a token that is not this site\'s webhook token. In n8n, open the POST Result to WordPress node and set its X-PQM-Token header to {{ $json.auth_token }}, then test again.');
check('... the failure on record, the body needs attention, the step open, no pending ping left', isset(postquality_workflow_report()['failed']) && $state_of($r['status_html']) === 'failing'
    && str_contains($r['status_html'], 'The last test just now did not pass: n8n reached this site, but the callback was refused')
    && $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] === false && $pings_deleted() === $deleted_before + 1);
site($fresh);
$deleted_before = $pings_deleted();
$GLOBALS['tw']['ping_response'] = $current;
$GLOBALS['tw']['ping_callback'] = 'none';
$r = connect($prod);
check('current workflow, no callback within the wait: stored, ping_ok false, the message names the wait, this site\'s REST address and the n8n execution',
    $r['saved'] === true && $r['ping_ok'] === false
    && $r['message'] === 'URL saved, but the test did not pass. n8n accepted the ping but did not call this site back within 0 seconds. n8n must reach https://example.com/wp-json/ (this site\'s REST API): a local, private or password-protected site cannot be called back. Check the ping\'s execution in n8n, then test again.');
check('... the failure on record, the step open, no pending ping left', isset(postquality_workflow_report()['failed']) && $state_of($r['status_html']) === 'failing'
    && $GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] === false && $pings_deleted() === $deleted_before + 1);
site($fresh);
$deleted_before = $pings_deleted();
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200], 'body' => '{"ok":true,"workflow_version":' . (POSTQUALITY_WORKFLOW_VERSION - 1) . '}'];
$r = connect($prod);
check('an older workflow, no callback: the older-version warning as before, ping_ok true, outdated, no wait (zero reads of the pending ping), no pending ping left',
    $r['ping_ok'] === true && $r['outdated'] === true && str_contains($r['message'], 'your n8n workflow is an older version') && !str_contains($r['message'], 'call this site back')
    && ping_reads() === 0 && $pings_deleted() === $deleted_before + 1);
site($fresh);
$GLOBALS['tw']['ping_response'] = ['response' => ['code' => 200], 'body' => '{"ok":true,"workflow_version":' . POSTQUALITY_WORKFLOW_VERSION . ',"workflow_variant":"free"}'];
$r = connect($prod);
check('a current Free template on the Pro build: the Free-variant warning as before, ping_ok true, outdated — and the branch waited (one read), so the Free callback claims its id instead of meeting a 404',
    $r['ping_ok'] === true && $r['outdated'] === true && str_contains($r['message'], 'your n8n workflow is the Free variant') && !str_contains($r['message'], 'call this site back')
    && ping_reads() === 1);
$GLOBALS['tw']['ping_callback'] = 'received';
$GLOBALS['tw']['caps'] = $manager;

/* ─── Story 55: the configuration steps ─────────────────────────────── */

/* Row: state carries the stored choices, the presets and the two capability flags */
echo "GET /setup — configuration step values (story 55)\n";
$GLOBALS['tw']['caps'] = $manager;
postquality_fs()->pro = false;
site($fresh);
$s = setup_state();
check('fresh site: defaults (Custom profile and standards, empty texts)',
    $s['blog_profile'] === 'custom' && $s['website_context'] === '' && $s['brand_voice_preset'] === 'custom' && $s['brand_voice'] === '');
check('profiles: every Blog Profile in Settings order, with its label and preset text (Custom has none)',
    array_column($s['profiles'], 'value') === array_keys(PostQuality_Admin::blog_profiles())
    && $s['profiles'][0] === ['value' => 'marketing', 'label' => 'Marketing Blog', 'text' => PostQuality_Admin::blog_profiles()['marketing']['website_context']]
    && end($s['profiles']) === ['value' => 'custom', 'label' => 'Custom', 'text' => '']);
check('voice presets: every Editorial Standards preset, then Custom',
    array_column($s['voice_presets'], 'value') === array_merge(array_keys(postquality_voice_presets()), ['custom'])
    && $s['voice_presets'][0]['text'] === postquality_voice_presets()['professional']['instruction']
    && end($s['voice_presets']) === ['value' => 'custom', 'label' => 'Custom', 'text' => '']);
check('cron knobs: the stored (default) values the schedule step names',
    $s['cron_enabled'] === false && $s['cron_frequency'] === 'daily' && $s['cron_batch_size'] === 5
    && $s['cron_min_score'] === 60 && $s['cron_max_age_days'] === 30 && $s['cron_command'] === 'review');
check('capability flags: manager with postquality_manage_agents on a free site', $s['can_manage_agents'] === true && $s['is_pro'] === false);
check('build: pro (story 54; the harness runs the Pro build, licensed or not)', $s['build'] === 'pro');
postquality_fs()->pro = true;
check('... pro site: is_pro', setup_state()['is_pro'] === true);
$GLOBALS['tw']['caps'] = ['postquality_manage_settings'];
check('... manager without postquality_manage_agents: can_manage_agents false', setup_state()['can_manage_agents'] === false);
$GLOBALS['tw']['caps'] = $manager;
postquality_fs()->pro = false;
site(['postquality_settings' => $fresh['postquality_settings'] + ['blog_profile' => 'food', 'website_context' => 'My old words', 'brand_voice_preset' => 'casual']]);
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
        && $GLOBALS['tw']['writes'] === [POSTQUALITY_SETUP_OPTION] && $GLOBALS['tw']['options']['postquality_settings'] === $fresh['postquality_settings']);
}

/* A configured site with every key the Settings form stores, nested agent prompts included. */
$configured = postquality_default_settings();
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
    $after = $GLOBALS['tw']['options']['postquality_settings'];
    return serialize($after) === serialize(array_merge($before, $expect)) && array_keys($after) === array_keys($before);
}

/* Row: a pick lands alone */
echo "POST /setup/settings — merge-save (story 55)\n";
site(['postquality_settings' => $configured], ['ship']);
$r = post_settings(['blog_profile' => 'marketing']);
check('preset pick: blog_profile saved alone; every other key byte-identical (custom text kept beside it)',
    $r instanceof WP_REST_Response && merged_only($configured, ['blog_profile' => 'marketing']));
check('... only postquality_settings written, no cron scheduled, the answer is the fresh state',
    $GLOBALS['tw']['writes'] === ['postquality_settings'] && $GLOBALS['tw']['scheduled'] === [] && $r->get_data()['blog_profile'] === 'marketing');
check('... the agents now get the preset text (postquality_resolved_context)',
    postquality_resolved_context(postquality_get_settings()) === PostQuality_Admin::blog_profiles()['marketing']['website_context']);

site(['postquality_settings' => $configured]);
post_settings(['blog_profile' => 'custom', 'website_context' => "  <b>Home automation</b> for tinkerers.  "]);
check('Custom: key and its text saved, the text sanitized like the Settings form (tags stripped, trimmed)',
    merged_only($configured, ['blog_profile' => 'custom', 'website_context' => 'Home automation for tinkerers.']));
site(['postquality_settings' => $configured]);
post_settings(['brand_voice_preset' => 'direct']);
check('Editorial Standards preset: saved alone, the stored custom voice kept', merged_only($configured, ['brand_voice_preset' => 'direct']));
site(['postquality_settings' => $configured]);
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
    site(['postquality_settings' => $configured]);
    $r = post_settings($json);
    check("$label: refused with 400, nothing stored", $r instanceof WP_Error && ($r->data['status'] ?? null) === 400
        && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['options']['postquality_settings'] === $configured);
}

/* Row: scheduled reviews — postquality_manage_agents on a pro site only */
echo "POST /setup/settings — scheduled reviews gate (story 55)\n";
$cron_cases = [
    'free site, manager with postquality_manage_agents'    => [$manager, false],
    'pro site, manager without postquality_manage_agents'  => [['postquality_manage_settings'], true],
    'free site, manager without postquality_manage_agents' => [['postquality_manage_settings'], false],
];
foreach ($cron_cases as $label => [$caps, $pro]) {
    $GLOBALS['tw']['caps'] = $caps;
    postquality_fs()->pro = $pro;
    site(['postquality_settings' => $configured]);
    $r = post_settings(['cron_enabled' => true]);
    check("$label: refused with 403, nothing stored or scheduled", $r instanceof WP_Error && ($r->data['status'] ?? null) === 403
        && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['scheduled'] === []);
    site(['postquality_settings' => $configured]);
    $r = post_settings(['blog_profile' => 'food', 'cron_enabled' => true]);
    check("... with a profile beside it: still 403, the profile not stored either", $r instanceof WP_Error && $GLOBALS['tw']['writes'] === []);
}
$GLOBALS['tw']['caps'] = $manager;
postquality_fs()->pro = true;
site(['postquality_settings' => $configured]);
$r = post_settings(['cron_enabled' => true]);
check('pro site + postquality_manage_agents: enabled with the stored knobs, every other key byte-identical',
    $r instanceof WP_REST_Response && merged_only($configured, ['cron_enabled' => true]) && $r->get_data()['cron_enabled'] === true);
check('... the schedule is set right away, at the stored frequency', $GLOBALS['tw']['scheduled'] === [['postquality_weekly', PostQuality_Cron::HOOK]]);
site(['postquality_settings' => $configured]);
$r = post_settings(['cron_enabled' => 'yes']);
check('... a non-boolean switch: refused with 400', $r instanceof WP_Error && ($r->data['status'] ?? null) === 400 && $GLOBALS['tw']['writes'] === []);
postquality_fs()->pro = false;

/* Row: re-run without a pick — settings, URL and token byte-identical */
echo "Re-run, every configuration step walked without a pick (story 55)\n";
site(['postquality_settings' => $configured, POSTQUALITY_SETUP_OPTION => ['steps' => [], 'complete' => true]], ['ship']);
$before = serialize($GLOBALS['tw']['options']['postquality_settings']);
setup_state();
foreach (['profile', 'standards', 'schedule'] as $step) {
    post_setup(['step' => $step, 'done' => true]);
}
$s = setup_state();
check('postquality_settings byte-identical (webhook URL and token included); only progress written',
    serialize($GLOBALS['tw']['options']['postquality_settings']) === $before && array_unique($GLOBALS['tw']['writes']) === [POSTQUALITY_SETUP_OPTION]);
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
check('the SDK after_uninstall hook calls PostQuality_Activator::uninstall()',
    (postquality_fs()->actions['after_uninstall'] ?? []) === [['PostQuality_Activator', 'uninstall']]);
$icon = (postquality_fs()->filters['plugin_icon'] ?? [null])[0];
check('the SDK plugin_icon filter returns a bundled icon file (no remote fetch)',
    is_callable($icon) && is_file($icon()));
$GLOBALS['tw_deleted'] = [];
function delete_option($k) { $GLOBALS['tw_deleted'][] = $k; return true; }
function delete_metadata(...$a) { return true; }
function wp_roles() { return (object) ['role_objects' => []]; }
function get_users($args) { return []; }
function wp_clear_scheduled_hook(...$a) {}
$GLOBALS['tw']['options']['postquality_names_version'] = 1; // story 91: the names already moved (the migration itself is stored-names-harness.php's)
PostQuality_Activator::uninstall();
check('uninstall deletes the setup progress option (POSTQUALITY_SETUP_OPTION)', in_array(POSTQUALITY_SETUP_OPTION, $GLOBALS['tw_deleted'], true));
check('uninstall deletes the n8n connection option (PostQuality_N8n::OPTION: base URL and API key)', PostQuality_N8n::OPTION === 'postquality_n8n_api'
    && in_array(PostQuality_N8n::OPTION, $GLOBALS['tw_deleted'], true));
check('uninstall deletes the provisioning lock option (PostQuality_N8n::LOCK_OPTION, bug 72)', PostQuality_N8n::LOCK_OPTION === 'postquality_n8n_provisioning'
    && in_array(PostQuality_N8n::LOCK_OPTION, $GLOBALS['tw_deleted'], true));
check('uninstall deletes the pending test ping option (PostQuality_Admin::PING_OPTION, story 2)', PostQuality_Admin::PING_OPTION === 'postquality_ping'
    && in_array(PostQuality_Admin::PING_OPTION, $GLOBALS['tw_deleted'], true));

/* Rows (bug 90): the deactivation feedback form opens only on a site that shares usage data now
 * (registered and tracking allowed), so a site that skipped the opt-in, or opted out since, stores no
 * reason. The SDK passes true and, once a filter is registered, no longer reads its own snooze — the
 * callback must keep it. Every row fails (null) when no callback is registered. */
echo "Deactivation feedback form (bug 90)\n";
$form_cb = (postquality_fs()->filters['show_deactivation_feedback_form'] ?? [null])[0];
$form_shows = static function (bool $registered, bool $tracking, bool $snoozed, bool $pro = false) use ($form_cb) {
    postquality_fs()->registered = $registered;
    postquality_fs()->tracking = $tracking;
    postquality_fs()->pro = $pro;
    PQM_FS_Stub::$snoozed = $snoozed;
    return is_callable($form_cb) ? $form_cb(true) : null;
};
check('skipped site (not registered, tracking not allowed): the filter answers false — no form, no reason stored, nothing sent at uninstall',
    $form_shows(false, false, false) === false);
check('opted-out site (registered, tracking not allowed, not snoozed): the filter answers false — no form, no reason stored',
    $form_shows(true, false, false) === false);
check('site sharing usage data, not snoozed: the filter answers true — the form shows as before', $form_shows(true, true, false) === true);
check('site sharing usage data, snoozed: the filter answers false, as the SDK does without the filter', $form_shows(true, true, true) === false);
check('licensed Pro site sharing usage data: still the form — the plan does not change the answer', $form_shows(true, true, false, true) === true);

/* Rows (bug 90): at deletion, a stored feedback answer is removed through the SDK's storage before its
 * uninstall step reads it, unless the site shares usage data now; the hook runs at priority 9 (the
 * SDK's own callback sits at 10) on the plugin's uninstall tag. */
$uninstall_cb = null;
foreach ($GLOBALS['tw']['actions'] as [$tag, $cb, $priority]) {
    if ($tag === 'uninstall_post-quality-manager.php' && $priority === 9) { $uninstall_cb = $cb; }
}
$forget = static function (bool $registered, bool $tracking) use ($form_shows, $uninstall_cb) {
    $form_shows($registered, $tracking, false);
    postquality_fs()->storage->removed = [];
    if (!is_callable($uninstall_cb)) { return null; } // every deletion row fails when the hook is not registered
    $uninstall_cb();
    return postquality_fs()->storage->removed;
};
check('deletion on a site that opted out (registered, tracking not allowed): the stored answer is removed — no reason, name or email sent',
    $forget(true, false) === ['uninstall_reason']);
check('deletion on a site not registered (a skipped site holding a pre-fix answer): the stored answer is removed',
    $forget(false, false) === ['uninstall_reason']);
check('deletion on a site sharing usage data: the answer is kept and goes with the deletion update, as agreed',
    $forget(true, true) === []);
check('the removal is hooked on uninstall_<plugin file> at priority 9, before the SDK\'s own uninstall step',
    in_array(['uninstall_post-quality-manager.php', ['PostQuality_License', 'forget_feedback_unless_sharing'], 9], $GLOBALS['tw']['actions'], true));
$form_shows(false, false, false); // back to the harness default: a free site that never opted in

/* Rows (story 65): on the update path the SDK adds its opt-in sticky while Pro waits for its license, and passes
 * the text through sticky_message_connect_account; the Pro build answers with the license copy and the SDK's
 * activation URL (the license screen), escaped in the plugin's text domain. The Free build has no such filter
 * (stripped, free-build-harness.php). Every row fails when no callback is registered. */
echo "License notice instead of the opt-in sticky (story 65)\n";
$notice_cb = (postquality_fs()->filters['sticky_message_connect_account'] ?? [null])[0];
$sdk_text = 'We made a few tweaks to the plugin, <b><a href="' . postquality_fs()->get_activation_url() . '">Opt in to make "Post Quality Manager" better!</a></b>';
$GLOBALS['tw_escaped'] = [];
$notice = is_callable($notice_cb) ? $notice_cb($sdk_text) : null;
check('Pro waiting for its license: the notice reads the approved copy, its bold link the SDK\'s activation URL (the license screen)',
    $notice === 'Pro is installed. Enter your license key to unlock it. <b><a href="' . postquality_fs()->get_activation_url() . '">Activate your license</a></b>');
check('... both texts through esc_html__() in the plugin\'s text domain, the URL through esc_url()', $notice !== null
    && in_array(['esc_html__', 'Pro is installed. Enter your license key to unlock it.', 'post-quality-manager'], $GLOBALS['tw_escaped'], true)
    && in_array(['esc_html__', 'Activate your license', 'post-quality-manager'], $GLOBALS['tw_escaped'], true)
    && in_array(['esc_url', postquality_fs()->get_activation_url()], $GLOBALS['tw_escaped'], true));

/* Rows (story 65): the SDK never removes its opt-in sticky on a license activation, so a closure on WordPress's
 * admin_init removes it whenever the SDK waits for nothing (licensed, opted in or skipped); while it waits for an
 * opt-in or a license the notice stays. No build check: the Free tree's copy is free-build-harness.php's. It sits
 * outside the is_admin() block, which this harness never enters. Every row fails when no closure is registered. */
echo "A leftover opt-in sticky goes once nothing is awaited (story 65)\n";
$clear_hooks = array_values(array_filter($GLOBALS['tw']['actions'], static fn($a) => $a[0] === 'admin_init'));
$clear_cb = count($clear_hooks) === 1 ? $clear_hooks[0][1] : null;
$clears = static function (bool $activation_mode) use ($clear_cb) {
    postquality_fs()->activation_mode = $activation_mode;
    postquality_fs()->removed_stickies = [];
    if (!is_callable($clear_cb)) { return null; }
    $clear_cb();
    return postquality_fs()->removed_stickies;
};
check('one closure on admin_init, at priority 10', count($clear_hooks) === 1 && $clear_cb instanceof Closure && $clear_hooks[0][2] === 10);
check('not in activation mode (licensed, opted in or skipped): connect_account removed', $clears(false) === ['connect_account']);
check('in activation mode (still waiting for an opt-in or a license): nothing removed, the notice stays', $clears(true) === []);
postquality_fs()->activation_mode = false;
postquality_fs()->removed_stickies = [];

/* Rows (bug 93): the Pro build hooks the SDK's three license hooks (the lapse record) and its expired-license
 * notice (the renew link). All four are registered at load, outside the is_admin() block this harness never
 * enters, so the SDK's daily sync from cron records the lapse and words the notice it stores. What the record does
 * is license-harness.php's and render-matrix-harness.php's; the Free build has none of the four
 * (free-build-harness.php). Every row fails when nothing is registered. */
echo "Lapsed license: the record and the renew link on the SDK's expired notice (bug 93)\n";
check('after_license_deactivation notes the release, after_license_change records or ends the lapse, after_license_activation ends it (PostQuality_License callbacks)',
    (postquality_fs()->actions['after_license_deactivation'] ?? []) === [['PostQuality_License', 'note_license_deactivation__premium_only']]
    && (postquality_fs()->actions['after_license_change'] ?? []) === [['PostQuality_License', 'record_lapse__premium_only']]
    && (postquality_fs()->actions['after_license_activation'] ?? []) === [['PostQuality_License', 'forget_lapse__premium_only']]);
$expired_cb = (postquality_fs()->filters['sticky_message_license_expired'] ?? [null])[0];
$sdk_expired = 'Your license has expired. You can still continue using the free plugin forever.'; // SDK 2.13.5, the product has a free plan
$GLOBALS['tw_escaped'] = [];
$expired = is_callable($expired_cb) ? $expired_cb($sdk_expired) : null;
check('the SDK\'s expired-license notice: its own text, then a "Renew Pro" link to the pricing page',
    $expired === $sdk_expired . ' <a href="' . POSTQUALITY_PRICING_URL . '">Renew Pro</a>');
check('... the link text through esc_html__() in the plugin\'s text domain, the URL through esc_url()', $expired !== null
    && in_array(['esc_html__', 'Renew Pro', 'post-quality-manager'], $GLOBALS['tw_escaped'], true)
    && in_array(['esc_url', POSTQUALITY_PRICING_URL], $GLOBALS['tw_escaped'], true));

harness_exit();
