<?php
/**
 * REST reviews harness for story 5.4 (detail panel + trigger flow) — the
 * I/O & Edge-Case Matrix rows the plugin side owns: GET /reviews/{id}
 * shaping (int score, display date via get_date_from_gmt, 50-row limit,
 * raw result_data on bad JSON) and the `in_flight` derivation behind the
 * list's polling. Bug 31 adds POST /save-review's failure contract and
 * static checks that the n8n template speaks it (behavior per node lives
 * in template-failure-harness.php). WP + wpdb stubs copied from rest-posts-harness.php, no
 * WP install.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('UTC');

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['tw'] = [
    'caps'      => ['pqm_view_reviews'],
    'user_id'   => 42,
    'user_meta' => [],
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
function get_option($k, $d = false) {
    return $GLOBALS['tw']['options'][$k] ?? (['date_format' => 'Y-m-d', 'time_format' => 'H:i', 'pqm_settings' => []][$k] ?? $d);
}
function update_option($k, $v, $autoload = null) { $GLOBALS['tw']['options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['tw']['options'][$k]); return true; }
function get_post($id) { return (int) $id === 7 ? (object) ['ID' => 7] : null; }
function current_time($type, $gmt = 0) { return '2026-10-06 12:00:00'; }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $value; }
function __($s, $d = null) { return $s; }
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; } // already the site locale
function restore_previous_locale() { return false; }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return (string) $s; }
function esc_attr($s) { return (string) $s; }
function esc_url($s) { return (string) $s; }
function sanitize_text_field($s) { return (string) $s; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function absint($n) { return abs((int) $n); }
function is_admin() { return false; }
function home_url($p = '') { return ($GLOBALS['tw']['home'] ?? 'https://example.com') . $p; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function rest_url($p = '') { return 'http://example.test:8080/blog/?rest_route=/' . ltrim($p, '/'); } // plain permalinks, port, subdirectory
/* Story 35: pqm_n8n_payload() runs for real — post 7 is a page; post 8's type is not in REST. */
class WP_Post {
    public function __construct(public $ID = 0, public $post_name = '', public $post_type = 'post') {}
}
function rest_get_route_for_post($post) { return $post->ID === 7 ? '/wp/v2/pages/7' : ''; }
function get_post_field($field, $id) { return '<p>Body text.</p>'; }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function has_excerpt($id) { return false; }
function wp_trim_words($s, $n = 55, $more = null) { return (string) $s; }
function get_the_title($id) { return 'A page'; }
function get_permalink($id) { return 'http://example.test:8080/blog/?page_id=' . $id; }
function get_post_meta($id, $key, $single) { return ''; }
/* WP core polyfills mb_substr (compat.php) when mbstring is missing, as on this CLI. */
if (!function_exists('mb_substr')) { function mb_substr($s, $start, $length = null, $encoding = null) { return substr($s, $start, $length); } }
function get_edit_post_link($id, $context = 'display') { return '/wp-admin/post.php?post=' . $id . '&action=edit'; }
/* Site timezone pinned to Europe/Zurich (UTC+2 in October): a stored GMT value must shift on display. */
function get_date_from_gmt($gmt, $format = 'Y-m-d H:i:s') {
    $d = new DateTime($gmt, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone('Europe/Zurich'));
    return $d->format($format);
}
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }
function get_current_user_id() { return $GLOBALS['tw']['user_id']; }
function get_user_meta($uid, $key, $single) { return $GLOBALS['tw']['user_meta'][$uid][$key] ?? ''; }
function wp_date($format, $ts = null) {
    if ($format === 'Y-m' && $ts === null) { return '2026-10'; }
    return gmdate($format, $ts ?? time());
}
function wp_timezone() { return new DateTimeZone('UTC'); }

/* ─── WP REST / wpdb stubs ───────────────────────────────────────────── */
class WP_REST_Server {
    const READABLE  = 'GET';
    const CREATABLE = 'POST';
}

class WP_REST_Request {
    private $params;
    public function __construct(array $params = []) { $this->params = $params; }
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function get_header($k) { return null; }
}

class WP_REST_Response {
    public $data;
    public $status;
    public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
    public function header($k, $v) {}
}

class WP_Error {
    public function __construct(public $code = '', public $message = '', public $error_data = null) {}
}

class PQM_Stub_WPDB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $prepared = [];      // every prepare(): ['sql' => ..., 'args' => [...]]
    public $results_queue = []; // rows returned by successive get_results()
    public $results_sql = [];   // SQL of every get_results(), in call order

    public function prepare($sql, ...$args) {
        $this->prepared[] = ['sql' => $sql, 'args' => $args];
        return $sql;
    }
    public function get_results($sql, $output = ARRAY_A) {
        $this->results_sql[] = $sql;
        return array_shift($this->results_queue) ?: [];
    }
    public function get_var($sql) { return 0; }
    public $inserted = [];      // every insert(): ['table' => ..., 'data' => [...]]
    public $insert_id = 0;
    public function insert($table, $data, $format = null) {
        $this->inserted[] = ['table' => $table, 'data' => $data];
        $this->insert_id = count($this->inserted);
        return 1;
    }
}

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

function fresh_wpdb(array $results_queue): PQM_Stub_WPDB {
    global $wpdb;
    $wpdb = new PQM_Stub_WPDB();
    $wpdb->results_queue = $results_queue;
    return $wpdb;
}

/** One stored row as $wpdb returns it (strings, raw JSON). */
function stored_row(int $id, string $command, string $score, string $status, string $result, string $reviewed_at): array {
    return [
        'id'            => (string) $id,
        'post_id'       => '7',
        'command'       => $command,
        'overall_score' => $score,
        'status'        => $status,
        'result_data'   => $result,
        'reviewed_at'   => $reviewed_at,
        'created_at'    => $reviewed_at,
    ];
}

/* Row: registration — /reviews/{id} unchanged: GET, view-reviews gate */
echo "Route registration\n";
PQM_REST_API::register_routes();
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/reviews/(?P<post_id>\d+)'] ?? null;
check('/reviews/{id} registered as GET', $route !== null && $route['methods'] === WP_REST_Server::READABLE);
check('/reviews/{id} gated by check_view_reviews', ($route['permission_callback'] ?? null) === ['PQM_REST_API', 'check_view_reviews']);

/* Row: detail shape — same array as today plus int overall_score + reviewed_at_display */
echo "Detail shape\n";
$wpdb = fresh_wpdb([[
    stored_row(3, 'review', '72', 'ship', '{"structure":{"score":80},"top_edits":["Tighten the intro"]}', '2026-10-05 09:30:00'),
    stored_row(2, 'takeaways', '0', 'processing', '{"summary":"Running asynchronously in n8n..."}', '2026-10-05 09:29:00'),
    stored_row(1, 'review', '41', 'needs_revision', 'not json', '2026-09-01 08:00:00'),
]]);
$res = PQM_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '7']));
check('200 response', $res instanceof WP_REST_Response && $res->get_status() === 200);
$rows = $res->get_data();
check('response is the plain array of rows, newest first', is_array($rows) && count($rows) === 3 && $rows[0]['id'] === '3');
check('row keys: stored columns byte-identical, reviewed_at_display appended',
    array_keys($rows[0]) === ['id', 'post_id', 'command', 'overall_score', 'status', 'result_data', 'reviewed_at', 'created_at', 'reviewed_at_display']);
check('overall_score cast to int (72, 0, 41)',
    $rows[0]['overall_score'] === 72 && $rows[1]['overall_score'] === 0 && $rows[2]['overall_score'] === 41);
check('reviewed_at_display converts the stored GMT value to the site timezone (UTC+2) in the site format',
    $rows[0]['reviewed_at_display'] === '2026-10-05 11:30');
check('reviewed_at kept raw (GMT) beside the display value', $rows[0]['reviewed_at'] === '2026-10-05 09:30:00');
check('valid result_data decoded to an array',
    $rows[0]['result_data'] === ['structure' => ['score' => 80], 'top_edits' => ['Tighten the intro']]);
check('processing placeholder decoded too', $rows[1]['result_data'] === ['summary' => 'Running asynchronously in n8n...']);
check('invalid JSON stays the raw string', $rows[2]['result_data'] === 'not json');

/* Row: limit — up to 50 rows, prepared */
echo "Row limit\n";
$p = null;
foreach ($wpdb->prepared as $q) {
    if (strpos($q['sql'], 'ORDER BY reviewed_at DESC LIMIT %d') !== false) { $p = $q; }
}
check('query is FROM %i WHERE post_id = %d ... ORDER BY reviewed_at DESC LIMIT %d', $p !== null && strpos($p['sql'], 'FROM %i WHERE post_id = %d') !== false);
check('prepared with [wp_pqm_reviews, 7, 50]', $p !== null && $p['args'] === ['wp_pqm_reviews', 7, 50]);

/* Row: no reviews — 200 with an empty array */
echo "No reviews\n";
fresh_wpdb([]);
$res = PQM_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '9']));
check('200 with []', $res->get_status() === 200 && $res->get_data() === []);

/* Row: blank reviewed_at → empty display, no PHP notice */
echo "Blank date\n";
fresh_wpdb([[stored_row(5, 'review', '10', 'major_revision', '{}', '0000-00-00 00:00:00')]]);
$rows = PQM_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '7']))->get_data();
check('zero date → empty reviewed_at_display', $rows[0]['reviewed_at_display'] === '');

/* Row: failed run as the shipped template posts it — stored as-is, the client decides */
echo "Failed run rows\n";
fresh_wpdb([[
    stored_row(6, 'review', '0', 'error', '{"error":"Review Agent returned invalid JSON."}', '2026-10-05 10:00:00'),
    stored_row(5, 'seo', '0', '', '{}', '2026-10-05 09:59:00'),
]]);
$rows = PQM_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '7']))->get_data();
check('stored error row keeps status error and its message', $rows[0]['status'] === 'error' && $rows[0]['result_data'] === ['error' => 'Review Agent returned invalid JSON.']);
check('pre-v3 template fallback row: empty status, empty result, score 0', $rows[1]['status'] === '' && $rows[1]['result_data'] === [] && $rows[1]['overall_score'] === 0);

/* ─── POST /save-review: the failure contract (bug 31) ───────────────── */

/** Calls save_review_callback with a body as the template posts it; returns [response, inserted row data]. */
function save_body(array $body): array {
    $wpdb = fresh_wpdb([]);
    $res  = PQM_REST_API::save_review_callback(new WP_REST_Request($body));
    return [$res, $wpdb->inserted[0]['data'] ?? null];
}

/* Row: failed run — error_message non-empty → stored status error + the message */
echo "Save failed run\n";
$GLOBALS['tw']['options'] = [];
[$res, $row] = save_body([
    'post_id' => 7, 'command' => 'review', 'overall_score' => 0, 'status' => '', 'result' => [],
    'workflow_version' => 3,
    'error_message' => 'Review Agent could not get an answer from the model: Your credit balance is too low.',
]);
check('201 response', $res instanceof WP_REST_Response && $res->get_status() === 201);
check('stored status is error', $row['status'] === 'error');
check('stored result carries the message', $row['result_data'] === json_encode(['error' => 'Review Agent could not get an answer from the model: Your credit balance is too low.']));
check('score 0, command kept', $row['overall_score'] === 0 && $row['command'] === 'review');
$seen = $GLOBALS['tw']['options']['pqm_workflow_version_seen'] ?? null;
check('workflow version recorded as {3, via command, at now}', is_array($seen) && $seen['version'] === 3 && $seen['via'] === 'command'
    && abs($seen['at'] - time()) <= 5 && pqm_workflow_report() === $seen);

/* Row: success — error_message '' is ignored, status/result stored as posted */
echo "Save completed run\n";
$result = ['overall_score' => 72, 'structure' => ['score' => 80], 'top_edits' => ['Tighten the intro']];
[, $with]    = save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 3, 'error_message' => '']);
[, $without] = save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 3]);
check('stored status and result as posted', $with['status'] === 'ship' && $with['overall_score'] === 72 && $with['result_data'] === json_encode($result));
check('stored row identical to a body without error_message', $with === $without);

/* Rows: the workflow version report — callback writer, legacy read, forget on serve */
echo "Workflow version report\n";
$ping_report = ['version' => 5, 'via' => 'test', 'at' => 1700000000];
$GLOBALS['tw']['options'] = ['pqm_workflow_version_seen' => $ping_report];
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result]);
check('callback without workflow_version (0): last report untouched', $GLOBALS['tw']['options']['pqm_workflow_version_seen'] === $ping_report);
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5]);
check('callback with workflow_version 5: replaces a ping\'s report, via command',
    $GLOBALS['tw']['options']['pqm_workflow_version_seen']['via'] === 'command' && pqm_workflow_report()['version'] === 5);
/* Story 54: the callback states the template variant; absent = Pro */
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5, 'workflow_variant' => 'free']);
check('callback from the Free template: recorded with variant free', ($GLOBALS['tw']['options']['pqm_workflow_version_seen']['variant'] ?? null) === 'free'
    && pqm_workflow_report()['variant'] === 'free');
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5, 'workflow_variant' => 'pro']);
check('callback stating pro: recorded without a variant (absent = Pro)', !array_key_exists('variant', $GLOBALS['tw']['options']['pqm_workflow_version_seen'])
    && !array_key_exists('variant', pqm_workflow_report()));
$variant_arg = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/save-review']['args']['workflow_variant'] ?? [];
check('save-review workflow_variant: optional string, enum free/pro (400 on others), default pro',
    ($variant_arg['required'] ?? null) === false && ($variant_arg['type'] ?? null) === 'string'
    && ($variant_arg['enum'] ?? null) === ['free', 'pro'] && ($variant_arg['default'] ?? null) === 'pro');
foreach ([4, '4'] as $legacy) { // WP returns a stored scalar as a string
    $GLOBALS['tw']['options'] = ['pqm_workflow_version_seen' => $legacy];
    check('legacy int option (' . gettype($legacy) . ') read as {4, via command, at 0}', pqm_workflow_report() === ['version' => 4, 'via' => 'command', 'at' => 0]);
}
$GLOBALS['tw']['options'] = [];
check('option absent: no report', pqm_workflow_report() === null);
/* n8n provisioning entry 7: a test's mis-wiring sentence rides with the report; only a test clears it */
$sentence = 'In n8n, no credential is set on Update WP Post (Rewrite): add one there, publish the workflow, then check again.';
$GLOBALS['tw']['options'] = ['pqm_workflow_version_seen' => $ping_report + ['miswired' => $sentence]];
check('a stored sentence: the report carries miswired', pqm_workflow_report() === $ping_report + ['miswired' => $sentence]);
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5]);
check('a command\'s callback while a sentence is stored: via command, the sentence kept', pqm_workflow_report()['via'] === 'command'
    && pqm_workflow_report()['miswired'] === $sentence);
$GLOBALS['tw']['options'] = ['pqm_workflow_version_seen' => $ping_report + ['miswired' => '']];
check('an empty sentence: the report carries no miswired key', pqm_workflow_report() === $ping_report);
$GLOBALS['tw']['options'] = ['pqm_workflow_version_seen' => $ping_report + ['miswired' => $sentence]];
PQM_REST_API::workflow_callback();
check('GET /workflow (a serve) forgets the report, the sentence with it', pqm_workflow_report() === null);
$GLOBALS['tw']['options'] = ['pqm_workflow_version_seen' => $ping_report];
$res = PQM_REST_API::workflow_callback();
check('GET /workflow (n8n Import from URL): serves the template and forgets the report',
    $res instanceof WP_REST_Response && isset($res->get_data()->nodes) && !isset($GLOBALS['tw']['options']['pqm_workflow_version_seen'])
    && pqm_workflow_report() === null);

/* Provisioning entry 3: the per-site webhook path */
$served  = json_decode(json_encode($res->get_data()), true);
$trigger = array_column($served['nodes'], null, 'name')['Webhook Trigger'];
check('GET /workflow: the trigger path is pqm-execute-example-com, no __PQM_ placeholder left',
    $trigger['parameters']['path'] === 'pqm-execute-example-com' && pqm_webhook_path() === 'pqm-execute-example-com'
    && !str_contains(json_encode($served), '__PQM_'));
check('GET /workflow: the trigger replies and Parse Input stamps PQM_WORKFLOW_VERSION',
    json_decode($trigger['parameters']['options']['responseData'], true) === ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION]
    && str_contains(array_column($served['nodes'], null, 'name')['Parse Input']['parameters']['jsCode'], 'workflow_version: ' . PQM_WORKFLOW_VERSION . ','));
foreach ([
    'https://Blog.Example.com'      => 'pqm-execute-blog-example-com', // capitals
    'https://my_blog.example.com/x' => 'pqm-execute-my-blog-example-com', // a non-slug char, the path ignored
    'https://.example.com.'         => 'pqm-execute-example-com', // no leading/trailing dash
    '/'                             => 'pqm-execute-my-site', // host unknown, as the credential names
] as $home => $want) {
    $GLOBALS['tw']['home'] = $home;
    check("webhook path for home $home: $want", pqm_webhook_path() === $want);
}
unset($GLOBALS['tw']['home']);
$admin_src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php');
check('Download forgets the report only once the template is found (after the not-found wp_die)',
    (bool) preg_match("/function download_workflow\(\).*?pqm_workflow_json\(\).*?wp_die\(.*?delete_option\('pqm_workflow_version_seen'\).*?echo \\\$json/s", $admin_src));
$GLOBALS['tw']['options'] = [];

/* ─── Template contract (static checks on templates/n8n-workflow.json) ── */
echo "Template contract\n";
$tpl   = json_decode(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json'), true);
$nodes = array_column($tpl['nodes'], null, 'name');
check('template parses', is_array($tpl) && isset($nodes['POST Result to WordPress']));
check('POST body forwards error_message only for failure items',
    strpos($nodes['POST Result to WordPress']['parameters']['body'], "error_message: \$json.error ? (\$json.message ||") !== false);
$missing = [];
foreach ($nodes as $name => $n) {
    foreach (explode("\n", $n['parameters']['jsCode'] ?? '') as $line) {
        if (strpos($line, 'error: true') !== false && strpos($line, 'message') === false) { $missing[] = $name; }
    }
}
check('every Code node line containing "error: true" also mentions message', $missing === []);
$into_update = [];
foreach ($tpl['connections'] as $from => $c) {
    foreach ($c['main'] as $out => $targets) {
        foreach ($targets as $t) {
            if ($t['node'] === 'Update WP Post (Rewrite)') { $into_update[] = "$from#$out"; }
        }
    }
}
check('Update WP Post (Rewrite) reached only through Rewrite Parsed? (true output)', $into_update === ['Rewrite Parsed?#0']);
preg_match('/workflow_version: (\d+),/', $nodes['Parse Input']['parameters']['jsCode'], $m);
check('Parse Input stamp === PQM_WORKFLOW_VERSION', isset($m[1]) && (int) $m[1] === PQM_WORKFLOW_VERSION);

/* ─── Story 35: the n8n connection is secured and host-agnostic ──────── */
echo "Secured, host-agnostic connection (story 35)\n";
check('PQM_WORKFLOW_VERSION is 6', PQM_WORKFLOW_VERSION === 6);
$trigger = $nodes['Webhook Trigger'];
check('Webhook Trigger answers on receipt with {"ok":true,"workflow_version":PQM_WORKFLOW_VERSION} (the ping handshake)',
    !isset($trigger['parameters']['responseMode']) // n8n default: onReceived, the mode that shows responseData
    && json_decode($trigger['parameters']['options']['responseData'] ?? '', true) === ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION]);
check('Webhook Trigger checks Header Auth with the per-site credential',
    ($trigger['parameters']['authentication'] ?? null) === 'headerAuth'
    && ($trigger['credentials'] ?? null) === ['httpHeaderAuth' => ['name' => 'Post Quality webhook - __PQM_SITE_HOST__']]);
$rewrite_url = $nodes['Update WP Post (Rewrite)']['parameters']['url'];
check('rewrite PATCHes the payload\'s post_rest_url as-is (no scheme, host or /wp-json assembled)',
    $rewrite_url === "={{ \$('Parse Input').first().json.post_rest_url }}");
check('Parse Input passes post_rest_url through',
    str_contains($nodes['Parse Input']['parameters']['jsCode'], "post_rest_url: body.post_rest_url || ''"));

/* Payload: the post's own REST address, '' when its type is not in REST */
$payload = pqm_n8n_payload(new WP_Post(7, 'a-page', 'page'), 'rewrite', pqm_get_settings());
check('payload post_rest_url = rest_url() of the post\'s own route (page, plain permalinks, port, subdirectory)',
    $payload['post_rest_url'] === 'http://example.test:8080/blog/?rest_route=/wp/v2/pages/7');
$payload = pqm_n8n_payload(new WP_Post(8, 'hidden', 'pqm_private_type'), 'rewrite', pqm_get_settings());
check('post type not in REST → post_rest_url \'\'', $payload['post_rest_url'] === '');

/* Headers: one home for the token header, used by Run, cron and ping */
check('pqm_n8n_headers(): JSON content type + X-PQM-Token = the webhook token',
    pqm_n8n_headers(['webhook_token' => 'tok-35']) === ['Content-Type' => 'application/json', 'X-PQM-Token' => 'tok-35']);
$sources = implode('', array_map(static fn($f) => file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/' . $f),
    ['includes/class-pqm-rest-api.php', 'includes/class-pqm-cron.php', 'admin/class-pqm-admin.php']));
check('every wp_remote_post to n8n sends pqm_n8n_headers() (Run, cron, ping)',
    substr_count($sources, 'wp_remote_post(') === 3 && substr_count($sources, 'pqm_n8n_headers(') === 3
    && !str_contains($sources, "'headers' => ['Content-Type'") && !str_contains($sources, "'headers' => [ 'Content-Type'"));

/* ─── in_flight derivation (PQM_DB::get_in_flight_for_posts) ─────────── */

/* Row: list in-flight — latest row per post+command is processing → in_flight */
echo "In flight\n";
$wpdb = fresh_wpdb([[
    ['post_id' => '7', 'command' => 'takeaways'],
    ['post_id' => '7', 'command' => 'review'],
    ['post_id' => '9', 'command' => 'seo'],
]]);
$map = PQM_DB::get_in_flight_for_posts([7, 9, 11]);
check('map keyed by post_id → commands, posts with nothing in flight absent',
    $map === [7 => ['takeaways', 'review'], 9 => ['seo']]);
$sql = $wpdb->results_sql[0];
check('one query', count($wpdb->results_sql) === 1);
check('joins the latest row per post+command on MAX(reviewed_at)',
    strpos($sql, 'MAX(reviewed_at) AS max_date') !== false
    && strpos($sql, 'GROUP BY post_id, command') !== false
    && strpos($sql, 'r1.command = r2.command AND r1.reviewed_at = r2.max_date') !== false);
check('filters on the processing status of that latest row', strpos($sql, "WHERE r1.status = 'processing'") !== false);
check('post ids prepared as %d placeholders',
    $wpdb->prepared[0]['args'] === [7, 9, 11] && strpos($wpdb->prepared[0]['sql'], 'IN (%d,%d,%d)') !== false);

/* Row: empty id list — no query at all */
echo "In flight, no posts\n";
$wpdb = fresh_wpdb([]);
check('[] → [] without touching wpdb', PQM_DB::get_in_flight_for_posts([]) === [] && $wpdb->results_sql === []);

/* Row: nothing processing — empty map */
echo "In flight, none\n";
fresh_wpdb([[]]);
check('no processing rows → []', PQM_DB::get_in_flight_for_posts([7]) === []);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
