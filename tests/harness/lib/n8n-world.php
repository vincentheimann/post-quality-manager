<?php
/**
 * The n8n world shared by the four n8n harnesses (story 78, build 2):
 * n8n-client-harness.php, n8n-provisioning-harness.php,
 * n8n-adoption-harness.php and n8n-managed-harness.php each require it
 * first. Holds the WP stubs the n8n routes need beyond lib/wp-stubs.php
 * (wp_remote_request() answering from a scripted queue, every call
 * recorded; add_option() and the $wpdb stub as bug 82 models them), the
 * plugin load, the route registration, and every constant, builder and
 * helper a section reads. The values a later concern reads ($setup_map,
 * $adopted_map, $older_report, $hand_nodes, $oct13, $template, $strip,
 * $gate, $src_n8n) are literals, built from constants or read from the
 * source, so they are defined once here; nothing that depends on a
 * scenario's run crosses a file boundary, and tw.uuid stays with the
 * Rotation sections that set and unset it. The accumulators the helpers
 * fill ($provision_runs, $diagnosis_calls, $status_blocks,
 * $adoption_calls, $rotation_runs, $update_runs) start empty here, so a
 * helper runs in any file; the leak scans that read them stay in theirs.
 * key_leak_scan() is the "key never leaves the server" section every
 * harness runs last on its own $replies: one function, so the four
 * copies cannot drift. No check runs here; run by hand it loads the
 * plugin and exits 0. Lives in lib/ so CI's `tests/harness/*.php` loop
 * never runs it alone.
 * Bug 82: the lock is claimed at the database (INSERT IGNORE, a
 * compare-and-set UPDATE for a stale one); the stubs below model core's
 * add_option() as a read then an upsert, with a hook in the gap, and the
 * $wpdb stub serializes the lock's two statements, so the same-instant row
 * fails on bug 72's claim() and passes on bug 82's.
 *
 * The answer shapes are the ones n8n 2.42.3 gave the owner's probe
 * (2026-10-13): no version is exposed to an API key; a missing route gets
 * the router's generic `{"message":"not found"}` while an existing one
 * refuses the unknown id with its handler's own message (ROUTE_OK below),
 * and a body-less PATCH answers 415 before the route is known.
 */

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
    'ping_queue'    => [],   // scripted ping answers, in call order; ping_response once empty (bug 80)
    'filters'       => ['pqm_provision_ping_retry_wait' => 0, 'pqm_ping_callback_wait' => 0], // apply_filters() answers: no wait between the retried pings, none for the ping's callback (story 2: the stub plays it before the reply)
    'ping_callback' => 'received', // story 2: what wp_remote_post() plays as n8n's callback of the ping — received, refused or none
    'app_passwords' => true, // wp_is_application_passwords_available_for_user()
    'app_created'   => [],   // Application Passwords created: [user_id, args]
    'app_revoked'   => [],   // Application Passwords deleted: [user_id, uuid]
    'home'          => 'https://example.com', // home_url()
];
$GLOBALS['pqm_routes'] = [];

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
function plugin_dir_url($f) { return ''; }
function is_admin() { return false; }
/* WP core: add_option() writes only a key that does not exist yet (entry 8, adopt()), the way core does it: a read, then an
   INSERT … ON DUPLICATE KEY UPDATE, with a gap between them a racing claim can land in (bug 82: `on_claim` fires there). The
   upsert answers true unless the stored value is byte-identical, as core reads its affected rows. */
function add_option($k, $v, $deprecated = '', $autoload = null) {
    if (array_key_exists($k, $GLOBALS['tw']['options'])) { return false; }
    race('on_claim');
    if (array_key_exists($k, $GLOBALS['tw']['options']) && maybe_serialize($GLOBALS['tw']['options'][$k]) === maybe_serialize($v)) { return false; }
    $GLOBALS['tw']['options'][$k] = $v;
    $GLOBALS['tw']['autoload'][$k] = $autoload;
    $GLOBALS['tw']['writes'][] = $k;
    return true;
}
/* Bug 82: a racing request's statement reaching the database first. Runs once (unset before it runs), so a claim it makes does not recurse. */
function race(string $hook): void {
    $fn = $GLOBALS['tw'][$hook] ?? null;
    if ($fn === null) { return; }
    $GLOBALS['tw'][$hook] = null;
    $fn();
}
/* The Options API cache a raw write brings in line (bug 82); delete_option() leaves it, so a row can read what the claim cached. */
function wp_cache_get($k, $group = '') { return $GLOBALS['tw']['cache'][$group][$k] ?? false; }
function wp_cache_set($k, $v, $group = '') { $GLOBALS['tw']['cache'][$group][$k] = $v; return true; }
function wp_cache_delete($k, $group = '') { unset($GLOBALS['tw']['cache'][$group][$k]); return true; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }
/* A scripted answer may be a closure: it runs when its call is made, and returns the answer. */
function wp_remote_request($url, $args) {
    $answer = array_shift($GLOBALS['tw']['queue']) ?? new WP_Error('harness', "unscripted call: {$args['method']} $url");
    $answer = $answer instanceof Closure ? $answer() : $answer;
    $GLOBALS['tw']['calls'][] = [$url, $args, $answer];
    return $answer;
}
function wp_remote_get($url, $args) { return wp_remote_request($url, $args + ['method' => 'GET']); }
function wp_remote_post($url, $args) {
    $GLOBALS['tw']['pings'][] = [$url, $args];
    pqm_harness_ping_callback($args, $GLOBALS['tw']['ping_callback']); // story 2: n8n calls the site back before the plugin reads the reply
    return $GLOBALS['tw']['ping_queue'] !== [] ? array_shift($GLOBALS['tw']['ping_queue']) : $GLOBALS['tw']['ping_response'];
}
/* Entry 10 rotates to a distinct token through tw.uuid, which the bootstrap's wp_generate_uuid4() reads. */
function apply_filters($tag, $value) { return $GLOBALS['tw']['filters'][$tag] ?? $value; }
function home_url($p = '') { return $GLOBALS['tw']['home'] . $p; }
function wp_get_current_user() { return (object) ['ID' => 7, 'user_login' => 'admin-anna', 'display_name' => 'Anna Admin']; }
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
/* A ping answers the page's refreshed body (entry 6; story 67: the whole page under the H1). */
define('MINUTE_IN_SECONDS', 60);
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') { return $url . '&_wpnonce=stub'; }
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
/* $wpdb: PQM_DB's read answers nothing. The provisioning lock's two statements (bug 82) are serialized against the options
   store as the database serializes them: INSERT IGNORE affects 1 row only while the key is free, the compare-and-set UPDATE
   only while the stored value is byte-identical to the one read; `db_refuses` answers false. Writes are recorded as
   update_option() records them, so untouched()/lock_free() and the writes[0] assertions hold. `on_claim` fires before the
   insert lands and `on_takeover` before the update (each once, see race()). prepare() returns the statement with its arguments. */
$wpdb = new class {
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $rows_affected = 0;
    public function get_var($sql) { return null; }
    public function prepare($sql, ...$args) { return [$sql, $args]; }
    public function query($prepared) {
        [$sql, $args] = $prepared;
        if ($GLOBALS['tw']['db_refuses'] ?? false) { return false; }
        $store = &$GLOBALS['tw']['options'];
        if (preg_match("/^INSERT IGNORE INTO wp_options \\(option_name, option_value, autoload\\) VALUES \\(%s, %s, '(\\w+)'\\)$/", $sql, $m)) {
            [$name, $value] = $args;
            race('on_claim');
            if (array_key_exists($name, $store)) { return $this->rows_affected = 0; }
            $store[$name] = maybe_unserialize($value);
            $GLOBALS['tw']['autoload'][$name] = $m[1];
            $GLOBALS['tw']['writes'][] = $name;
            return $this->rows_affected = 1;
        }
        if ($sql === 'UPDATE wp_options SET option_value = %s WHERE option_name = %s AND option_value = %s') {
            [$value, $name, $expected] = $args;
            race('on_takeover');
            if (!array_key_exists($name, $store) || maybe_serialize($store[$name]) !== $expected) { return $this->rows_affected = 0; }
            $store[$name] = maybe_unserialize($value);
            $GLOBALS['tw']['writes'][] = $name;
            return $this->rows_affected = 1;
        }
        throw new RuntimeException("unstubbed query: $sql");
    }
};

require __DIR__ . '/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php

/* ─── Load the plugin bootstrap ──────────────────────────────────────── */
require dirname(__DIR__, 3) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */
const KEY    = 'n8n_api_key-SECRET-0123456789';
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

/* ─── The routes, the right, the gate every route section reads ──────── */
PQM_REST_API::register_routes();
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
$gate  = ['PQM_REST_API', 'check_manage_settings'];

/* ─── Provisioning (entry 4): constants and builders ─────────────────── */
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
    $GLOBALS['tw']['ping_queue']    = [];
    $GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION]);
    $GLOBALS['tw']['app_passwords'] = true;
    $GLOBALS['tw']['app_created']   = [];
    $GLOBALS['tw']['app_revoked']   = [];
    $GLOBALS['tw']['on_claim']      = null;  // bug 82: no racing statement at the claim ...
    $GLOBALS['tw']['on_takeover']   = null;  // ... nor at the takeover
    $GLOBALS['tw']['db_refuses']    = false; // the database answers
    $GLOBALS['tw']['cache']         = [];
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

/** Nothing written but the lock's claim and release (bug 72), the lock gone, nothing pinged, no Application Password created or revoked. */
function untouched(): bool {
    return array_values(array_diff($GLOBALS['tw']['writes'], [PQM_N8n::LOCK_OPTION, '-' . PQM_N8n::LOCK_OPTION])) === [] && lock_free()
        && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === [] && $GLOBALS['tw']['app_revoked'] === [];
}

/** The provisioning lock is not held (bug 72): absent, or never written. */
function lock_free(): bool {
    return !isset($GLOBALS['tw']['options'][PQM_N8n::LOCK_OPTION]);
}

/** The setup map a passing Pro run stores (bug 73: with the Application Password it created), `at` as stored. */
function pro_map(?int $at): array {
    return ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic', 'wordpress' => 'c-wp'],
        'path' => 'pqm-execute-example-com', 'base_url' => BASE, 'wizard_created' => true, 'at' => $at, 'application_password' => ['uuid' => 'app-uuid', 'user_id' => 7]];
}

$template = json_decode((string) pqm_workflow_json(), true);
$strip    = static fn(array $nodes) => array_map(static function ($n) { unset($n['credentials']); return $n; }, $nodes);

/* ─── Rollback (entry 5): constants and the instance model ───────────── */
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

/** The options written, without the pending test ping (story 2: PQM_Admin::PING_OPTION, written before every ping and deleted after it). */
function writes(): array {
    return array_values(array_diff($GLOBALS['tw']['writes'], [PQM_Admin::PING_OPTION, '-' . PQM_Admin::PING_OPTION]));
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

/* ─── Bug 75, the leftovers, bug 80: sentences and answers ───────────── */
const HELD_PATH = 'The n8n setup stopped while activating the workflow: another active workflow on this n8n instance already listens on this site\'s webhook path.';
const WAYS_OUT  = 'If that workflow is this site\'s, paste its production URL as the n8n Webhook URL and choose Let the plugin manage this workflow on the n8n Workflow page; otherwise remove it in n8n, then try again.';
const OLD_OBJECTS = ['credentials/c-old1', 'credentials/c-old2', 'credentials/c-old3', 'workflows/wf-old'];
define('HOOK_404', resp(404, ['code' => 404, 'message' => 'The requested webhook is not registered.']));
const EARLY_404 = 'Your workflow is set up and active in n8n, but its webhook did not answer yet (n8n responded with 404). Give n8n a moment, then press Test again on the n8n Workflow page. If it keeps answering 404, your n8n may serve webhooks on another address than its editor: copy the production URL from the Webhook Trigger in n8n and paste it as the n8n Webhook URL.';

/* ─── Diagnosis (entry 7): constants, maps and helpers ───────────────── */
const LIST_READ = 'GET ' . BASE . '/api/v1/workflows?active=true&limit=250&excludePinnedData=true';
const V5_HOOK   = BASE . '/webhook/pqm-execute';
const SHARED    = ['id' => 'c-hdr', 'name' => 'Header Auth account']; // n8n's default name for a Header Auth credential
const SHARED_SENTENCE = 'In n8n, the webhook credential “Header Auth account” is set on Claude - Review Agent, Claude - Takeaways Agent, Claude - Sources Agent, Claude - SEO Agent, Claude - Audit Agent, and Claude - Rewrite Agent instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again.';
const MISSING_SENTENCE = 'In n8n, no credential is set on Update WP Post (Rewrite): add one there, publish the workflow, then test again.';
const PING_OK  = 'Ping OK — this site reaches n8n, the webhook credential is right, your workflow is up to date and n8n called this site back with the right token: the round trip works.';
$diagnosis_calls = []; // every call the rows below made, for the GET-only check
$status_blocks   = []; // every status block a connect ping below answered
$setup_map = [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic', 'wordpress' => 'c-wp'], 'path' => 'pqm-execute-example-com', 'base_url' => BASE, 'wizard_created' => true, 'at' => 1]];
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
// The 2026-10-13 shape (the first diagnosis row; Update reads it again): the v5 path, the trigger's Header Auth credential spread to every Claude node.
$oct13 = with_path(wired(['Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + WIRED), 'pqm-execute');

/* ─── Adoption (entry 8): the hand-made setup and its helpers ────────── */
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
/** A refusal: this code and status, zero writes, no ping. The page's body a refused Rotate or Update carries (story 74) is checked in its own rows. */
function refused($r, string $code, int $status): bool {
    return $r instanceof WP_Error && $r->code === $code && array_diff_key((array) $r->data, ['status_html' => 1]) === ['status' => $status]
        && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === [];
}
/** An error as the leak scans record it: its data without the page's body, which shows the page's own token row (story 74, as without_body() for a reply). */
function error_json(WP_Error $r): string {
    return json_encode([$r->code, $r->message, array_diff_key((array) $r->data, ['status_html' => 1])]);
}
$hand_nodes = wired(HAND_NAMES);

/* ─── Rotation (entry 10): constants, the adopted map, managed() ─────── */
const ROTATED          = 'rotated-token-SECRET';
const PATCH_WEBHOOK    = 'PATCH ' . BASE . '/api/v1/credentials/c-webhook';
const ROTATION_STOPPED = 'The plugin could not finish updating the webhook credential (%s). Try again in a moment.';
const ROTATED_PREFIX   = 'The token was rotated in n8n and saved here.';
const OTHER_CREDENTIAL = ROTATED_PREFIX . ' n8n still refused the test ping: your Webhook Trigger uses another credential than the one the plugin manages. In n8n, pick that credential on the Webhook Trigger, publish the workflow, then send a test ping.';
define('PATCHED', resp(200, ['id' => 'c-webhook', 'name' => 'Post Quality webhook - example.com', 'type' => 'httpHeaderAuth', 'updatedAt' => '2026-10-16T10:00:00.000Z']));
$rotation_runs = []; // [reply, options, calls, pings] after every rotation, for the leak scan
$adopted_map   = [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'wf-hand', 'credentials' => ['webhook' => 'c-h1', 'anthropic' => 'c-k1'], 'path' => 'pqm-execute', 'base_url' => BASE, 'wizard_created' => false, 'at' => HAND_MAP_AT]];

/** A managed site: a passing key, a setup map (the provisioned one by default), the saved URL on its path (D3). */
function managed(array $queue, ?array $map = null, string $url = HOOK): void {
    connected($queue, $map ?? $GLOBALS['setup_map']);
    $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] = $url;
    $GLOBALS['tw']['writes'] = [];
}
function rotate() {
    $r     = PQM_REST_API::rotate_n8n_token_callback();
    $reply = $r instanceof WP_Error ? error_json($r) : json_encode($r->get_data());
    $GLOBALS['replies'][]       = $reply;
    $GLOBALS['rotation_runs'][] = [$reply, $GLOBALS['tw']['options'], $GLOBALS['tw']['calls'], $GLOBALS['tw']['pings']];
    return $r instanceof WP_Error ? $r : $r->get_data();
}
/** The stored webhook token. */
function token_now(): ?string { return $GLOBALS['tw']['options']['pqm_settings']['webhook_token'] ?? null; }

/* ─── Update (entry 9): constants and update() ───────────────────────── */
const PUT_WF         = 'PUT ' . BASE . '/api/v1/workflows/wf1';
const UPDATE_STOPPED = 'The plugin could not finish updating the workflow (%s). Try again in a moment.';
const UPDATE_BUTTON  = '<button type="button" class="button button-primary pqm-update-workflow" data-pqm-post="n8n/workflow" data-pqm-busy="Updating the workflow…">Update workflow</button>';
const IN_PLACE       = 'The plugin can update it in place: the workflow\'s nodes are replaced; its credentials, webhook path and name stay.'; // story 76 (D3)
const WORKFLOW_GONE  = 'The workflow the plugin manages is no longer in n8n. Set the workflow up again, or let the plugin manage the one at your n8n Webhook URL, then try again. Choose Start over in the n8n connection section to set it up again.';
define('UPDATED', resp(200, ['id' => 'wf1', 'name' => 'Post Quality — example.com', 'active' => true, 'versionId' => 'v-new']));
$older_report = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => 1700000000]];
$update_runs  = []; // [reply, options, calls, pings] after every update, for the leak scan
function update() {
    $r     = PQM_REST_API::update_n8n_workflow_callback();
    $reply = $r instanceof WP_Error ? error_json($r) : json_encode($r->get_data());
    $GLOBALS['replies'][]     = $reply;
    $GLOBALS['update_runs'][] = [$reply, $GLOBALS['tw']['options'], $GLOBALS['tw']['calls'], $GLOBALS['tw']['pings']];
    return $r instanceof WP_Error ? $r : $r->get_data();
}
/** The Webhook Trigger's path in a sent workflow body. */
function trigger_path(array $workflow): string { return array_column($workflow['nodes'], null, 'name')['Webhook Trigger']['parameters']['path']; }

/* ─── Start over (story 71): sentences, maps, start_over() ───────────── */
const REMOVED_BOTH      = 'The setup was removed here and in n8n. Set n8n up again when you are ready.';
const REMOVED_HERE      = 'The setup was removed here. Your workflow and credentials stay in n8n.';
const REMOVED_LEFT      = 'The setup was removed here. Some of it is still in n8n: the next setup removes it first.';
const OTHER_INSTANCE    = 'https://other.example.com';
const OTHER_BLOCKED     = 'The plugin set this workflow up on other.example.com, not on the n8n you are connected to now. Connect other.example.com again, or choose Start over in the n8n connection section.';
const START_OVER_CLAUSE = 'Choose Start over in the n8n connection section to set it up again.';
const GONE_PING         = 'The workflow the plugin set up is no longer in n8n. Choose Start over in the n8n connection section, then set n8n up again.';
const PLAIN_404         = 'n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.';
$state_keys = ['base_url', 'key_set', 'key_hint', 'probe', 'connected', 'setup', 'adoptable', 'rotatable'];
$report     = ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000]];
$elsewhere  = [PQM_N8n::SETUP_OPTION => array_replace(pro_map(1), ['base_url' => OTHER_INSTANCE])];
function start_over(bool $remove) {
    $r = PQM_REST_API::start_over_n8n_callback(new WP_REST_Request(['remove_from_n8n' => $remove]));
    $GLOBALS['replies'][] = $r instanceof WP_Error ? json_encode([$r->code, $r->message, $r->data]) : json_encode($r->get_data());
    return $r instanceof WP_Error ? $r : $r->get_data();
}
/** The site after a start over: no map, no report, the URL '' and the token kept, the key stored. */
function started_over(): bool {
    $o = $GLOBALS['tw']['options'];
    return !isset($o[PQM_N8n::SETUP_OPTION]) && !isset($o['pqm_workflow_version_seen']) && ($o['pqm_settings']['n8n_webhook_url'] ?? null) === ''
        && ($o['pqm_settings']['webhook_token'] ?? null) === TOKEN && ($o[PQM_N8n::OPTION]['api_key'] ?? null) === KEY;
}

/* ─── Story 74: sentences, the stored connections, refusal_stored() ──── */
const KEY_SENTENCE    = 'n8n refused this API key. Create a new one in n8n under Settings → n8n API, then try again.';
const SCOPED_SENTENCE = 'This API key cannot create credentials or publish workflows: n8n limits what it may do. In n8n under Settings → n8n API, create a key without scope limits, then try again.';
const REFUSED_FIX     = 'n8n refused the API key. Create a new one in n8n under Settings → n8n API, connect again, then try again.';
define('UNAUTHORIZED', resp(401, ['message' => 'unauthorized']));
$passing_probe = ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => 1700000000];
$passing_conn  = [PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => $passing_probe]];
$refused_conn  = [PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => ['ok' => false, 'cause' => 'key', 'message' => KEY_SENTENCE, 'at' => 1700000500]]];
/** The stored probe is a fresh refusal of this cause, {ok, cause, message, at}; the URL and key kept; not connected. */
function refusal_stored(string $cause): bool {
    $c = $GLOBALS['tw']['options'][PQM_N8n::OPTION] ?? [];
    return ($c['base_url'] ?? '') === BASE && ($c['api_key'] ?? '') === KEY && is_array($c['probe'] ?? null)
        && array_keys($c['probe']) === ['ok', 'cause', 'message', 'at'] && $c['probe']['ok'] === false && $c['probe']['cause'] === $cause
        && abs($c['probe']['at'] - time()) <= 5 && PQM_N8n::connected() === false;
}

/* ─── The key never leaves the server ────────────────────────────────── */
// The client's source text, read once for the source pins (adoption, rotation, update, start over; story 74 reads it again).
$src_n8n = file_get_contents(dirname(__DIR__, 3) . '/post-quality-manager/includes/class-pqm-n8n.php');
/** The replies scan every n8n file runs last (owner decision 2026-10-21: one scan per file); the two source pins run once, in the client harness. */
function key_leak_scan(array $replies): void {
    echo "The key never leaves the server\n";
    check('no REST reply above carries either n8n key, the provider key, the Application Password or its uuid (' . count($replies) . ' replies)', $replies !== []
        && !array_filter($replies, static fn($r) => str_contains($r, KEY) || str_contains($r, OLD_KEY) || str_contains($r, PROVIDER_KEY) || str_contains($r, APP_PASSWORD) || str_contains($r, 'app-uuid')));
}
