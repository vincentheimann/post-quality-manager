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

date_default_timezone_set('UTC');

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['tw'] = [
    'caps'      => ['postquality_view_reviews'],
    'user_id'   => 42,
    'user_meta' => [],
    'options'   => site_options(),
];
$GLOBALS['pqm_routes'] = [];

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
function plugin_dir_url($f) { return ''; }
/* The site's options every row starts from (the bootstrap's store); a row replaces tw.options through this. */
function site_options(array $extra = []): array { return $extra + ['date_format' => 'Y-m-d', 'time_format' => 'H:i', 'postquality_settings' => []]; }
function get_post($id) { return (int) $id === 7 ? (object) ['ID' => 7] : null; }
function current_time($type, $gmt = 0) { return '2026-10-06 12:00:00'; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $value; }
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; } // already the site locale
function restore_previous_locale() { return false; }
function is_admin() { return false; }
function home_url($p = '') { return ($GLOBALS['tw']['home'] ?? 'https://example.com') . $p; }
function rest_url($p = '') { return 'http://example.test:8080/blog/?rest_route=/' . ltrim($p, '/'); } // plain permalinks, port, subdirectory
/* Story 35: postquality_n8n_payload() runs for real — post 7 is a page; post 8's type is not in REST. */
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

/* ─── wpdb stub ──────────────────────────────────────────────────────── */
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
require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */
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
PostQuality_REST_API::register_routes();
$route = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/reviews/(?P<post_id>\d+)'] ?? null;
check('/reviews/{id} registered as GET', $route !== null && $route['methods'] === WP_REST_Server::READABLE);
check('/reviews/{id} gated by check_view_reviews', ($route['permission_callback'] ?? null) === ['PostQuality_REST_API', 'check_view_reviews']);

/* Row: detail shape — same array as today plus int overall_score + reviewed_at_display */
echo "Detail shape\n";
$wpdb = fresh_wpdb([[
    stored_row(3, 'review', '72', 'ship', '{"structure":{"score":80},"top_edits":["Tighten the intro"]}', '2026-10-05 09:30:00'),
    stored_row(2, 'takeaways', '0', 'processing', '{"summary":"Running asynchronously in n8n..."}', '2026-10-05 09:29:00'),
    stored_row(1, 'review', '41', 'needs_revision', 'not json', '2026-09-01 08:00:00'),
]]);
$res = PostQuality_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '7']));
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
check('prepared with [wp_postquality_reviews, 7, 50]', $p !== null && $p['args'] === ['wp_postquality_reviews', 7, 50]);

/* Row: no reviews — 200 with an empty array */
echo "No reviews\n";
fresh_wpdb([]);
$res = PostQuality_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '9']));
check('200 with []', $res->get_status() === 200 && $res->get_data() === []);

/* Row: blank reviewed_at → empty display, no PHP notice */
echo "Blank date\n";
fresh_wpdb([[stored_row(5, 'review', '10', 'major_revision', '{}', '0000-00-00 00:00:00')]]);
$rows = PostQuality_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '7']))->get_data();
check('zero date → empty reviewed_at_display', $rows[0]['reviewed_at_display'] === '');

/* Row: failed run as the shipped template posts it — stored as-is, the client decides */
echo "Failed run rows\n";
fresh_wpdb([[
    stored_row(6, 'review', '0', 'error', '{"error":"Review Agent returned invalid JSON."}', '2026-10-05 10:00:00'),
    stored_row(5, 'seo', '0', '', '{}', '2026-10-05 09:59:00'),
]]);
$rows = PostQuality_REST_API::get_reviews_callback(new WP_REST_Request(['post_id' => '7']))->get_data();
check('stored error row keeps status error and its message', $rows[0]['status'] === 'error' && $rows[0]['result_data'] === ['error' => 'Review Agent returned invalid JSON.']);
check('pre-v3 template fallback row: empty status, empty result, score 0', $rows[1]['status'] === '' && $rows[1]['result_data'] === [] && $rows[1]['overall_score'] === 0);

/* ─── POST /save-review: the failure contract (bug 31) ───────────────── */

/** Calls save_review_callback with a body as the template posts it; returns [response, inserted row data]. */
function save_body(array $body): array {
    $wpdb = fresh_wpdb([]);
    $res  = PostQuality_REST_API::save_review_callback(new WP_REST_Request($body));
    return [$res, $wpdb->inserted[0]['data'] ?? null];
}

/* Row: failed run — error_message non-empty → stored status error + the message */
echo "Save failed run\n";
$GLOBALS['tw']['options'] = site_options();
[$res, $row] = save_body([
    'post_id' => 7, 'command' => 'review', 'overall_score' => 0, 'status' => '', 'result' => [],
    'workflow_version' => 3,
    'error_message' => 'Review Agent could not get an answer from the model: Your credit balance is too low.',
]);
check('201 response', $res instanceof WP_REST_Response && $res->get_status() === 201);
check('stored status is error', $row['status'] === 'error');
check('stored result carries the message', $row['result_data'] === json_encode(['error' => 'Review Agent could not get an answer from the model: Your credit balance is too low.']));
check('score 0, command kept', $row['overall_score'] === 0 && $row['command'] === 'review');
$seen = $GLOBALS['tw']['options']['postquality_workflow_version_seen'] ?? null;
check('workflow version recorded as {3, via command, at now}', is_array($seen) && $seen['version'] === 3 && $seen['via'] === 'command'
    && abs($seen['at'] - time()) <= 5 && postquality_workflow_report() === $seen);

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
$GLOBALS['tw']['options'] = site_options(['postquality_workflow_version_seen' => $ping_report]);
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result]);
check('callback without workflow_version (0): last report untouched', $GLOBALS['tw']['options']['postquality_workflow_version_seen'] === $ping_report);
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5]);
check('callback with workflow_version 5: replaces a ping\'s report, via command',
    $GLOBALS['tw']['options']['postquality_workflow_version_seen']['via'] === 'command' && postquality_workflow_report()['version'] === 5);
/* Story 54: the callback states the template variant; absent = Pro */
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5, 'workflow_variant' => 'free']);
check('callback from the Free template: recorded with variant free', ($GLOBALS['tw']['options']['postquality_workflow_version_seen']['variant'] ?? null) === 'free'
    && postquality_workflow_report()['variant'] === 'free');
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5, 'workflow_variant' => 'pro']);
check('callback stating pro: recorded without a variant (absent = Pro)', !array_key_exists('variant', $GLOBALS['tw']['options']['postquality_workflow_version_seen'])
    && !array_key_exists('variant', postquality_workflow_report()));
$variant_arg = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/save-review']['args']['workflow_variant'] ?? [];
check('save-review workflow_variant: optional string, enum free/pro (400 on others), default pro',
    ($variant_arg['required'] ?? null) === false && ($variant_arg['type'] ?? null) === 'string'
    && ($variant_arg['enum'] ?? null) === ['free', 'pro'] && ($variant_arg['default'] ?? null) === 'pro');
foreach ([4, '4'] as $legacy) { // WP returns a stored scalar as a string
    $GLOBALS['tw']['options'] = site_options(['postquality_workflow_version_seen' => $legacy]);
    check('legacy int option (' . gettype($legacy) . ') read as {4, via command, at 0}', postquality_workflow_report() === ['version' => 4, 'via' => 'command', 'at' => 0]);
}
$GLOBALS['tw']['options'] = site_options();
check('option absent: no report', postquality_workflow_report() === null);
/* n8n provisioning entry 7: a test's mis-wiring sentence rides with the report; only a test clears it */
$sentence = 'In n8n, no credential is set on Update WP Post (Rewrite): add one there, publish the workflow, then check again.';
$GLOBALS['tw']['options'] = site_options(['postquality_workflow_version_seen' => $ping_report + ['miswired' => $sentence]]);
check('a stored sentence: the report carries miswired', postquality_workflow_report() === $ping_report + ['miswired' => $sentence]);
save_body(['post_id' => 7, 'command' => 'review', 'overall_score' => 72, 'status' => 'ship', 'result' => $result, 'workflow_version' => 5]);
check('a command\'s callback while a sentence is stored: via command, the sentence kept', postquality_workflow_report()['via'] === 'command'
    && postquality_workflow_report()['miswired'] === $sentence);
$GLOBALS['tw']['options'] = site_options(['postquality_workflow_version_seen' => $ping_report + ['miswired' => '']]);
check('an empty sentence: the report carries no miswired key', postquality_workflow_report() === $ping_report);
$GLOBALS['tw']['options'] = site_options(['postquality_workflow_version_seen' => $ping_report + ['miswired' => $sentence]]);
PostQuality_REST_API::workflow_callback();
check('GET /workflow (a serve) forgets the report, the sentence with it', postquality_workflow_report() === null);
$GLOBALS['tw']['options'] = site_options(['postquality_workflow_version_seen' => $ping_report]);
$res = PostQuality_REST_API::workflow_callback();
check('GET /workflow (n8n Import from URL): serves the template and forgets the report',
    $res instanceof WP_REST_Response && isset($res->get_data()->nodes) && !isset($GLOBALS['tw']['options']['postquality_workflow_version_seen'])
    && postquality_workflow_report() === null);

/* Provisioning entry 3: the per-site webhook path */
$served  = json_decode(json_encode($res->get_data()), true);
$trigger = array_column($served['nodes'], null, 'name')['Webhook Trigger'];
check('GET /workflow: the trigger path is pqm-execute-example-com, no __PQM_ placeholder left',
    $trigger['parameters']['path'] === 'pqm-execute-example-com' && postquality_webhook_path() === 'pqm-execute-example-com'
    && !str_contains(json_encode($served), '__PQM_'));
check('GET /workflow: the trigger replies and Parse Input stamps POSTQUALITY_WORKFLOW_VERSION',
    json_decode($trigger['parameters']['options']['responseData'], true) === ['ok' => true, 'workflow_version' => POSTQUALITY_WORKFLOW_VERSION]
    && str_contains(array_column($served['nodes'], null, 'name')['Parse Input']['parameters']['jsCode'], 'workflow_version: ' . POSTQUALITY_WORKFLOW_VERSION . ','));
foreach ([
    'https://Blog.Example.com'      => 'pqm-execute-blog-example-com', // capitals
    'https://my_blog.example.com/x' => 'pqm-execute-my-blog-example-com', // a non-slug char, the path ignored
    'https://.example.com.'         => 'pqm-execute-example-com', // no leading/trailing dash
    '/'                             => 'pqm-execute-my-site', // host unknown, as the credential names
] as $home => $want) {
    $GLOBALS['tw']['home'] = $home;
    check("webhook path for home $home: $want", postquality_webhook_path() === $want);
}
unset($GLOBALS['tw']['home']);
$admin_src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php');
check('Download forgets the report only once the template is found (after the not-found wp_die)',
    (bool) preg_match("/function download_workflow\(\).*?postquality_workflow_json\(\).*?wp_die\(.*?delete_option\('postquality_workflow_version_seen'\).*?echo \\\$json/s", $admin_src));
$GLOBALS['tw']['options'] = site_options();

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
preg_match_all('/workflow_version: (\d+)/', $nodes['Parse Input']['parameters']['jsCode'], $m);
check('every Parse Input stamp === POSTQUALITY_WORKFLOW_VERSION (the command item\'s and, story 2, the ping item\'s)', count($m[1]) === 2 && array_unique(array_map('intval', $m[1])) === [POSTQUALITY_WORKFLOW_VERSION]);

/* ─── Story 35: the n8n connection is secured and host-agnostic ──────── */
echo "Secured, host-agnostic connection (story 35)\n";
check('POSTQUALITY_WORKFLOW_VERSION is 9', POSTQUALITY_WORKFLOW_VERSION === 9);
$trigger = $nodes['Webhook Trigger'];
check('Webhook Trigger answers on receipt with {"ok":true,"workflow_version":POSTQUALITY_WORKFLOW_VERSION} (the ping handshake)',
    !isset($trigger['parameters']['responseMode']) // n8n default: onReceived, the mode that shows responseData
    && json_decode($trigger['parameters']['options']['responseData'] ?? '', true) === ['ok' => true, 'workflow_version' => POSTQUALITY_WORKFLOW_VERSION]);
check('Webhook Trigger checks Header Auth with the per-site credential',
    ($trigger['parameters']['authentication'] ?? null) === 'headerAuth'
    && ($trigger['credentials'] ?? null) === ['httpHeaderAuth' => ['name' => 'Post Quality webhook - __PQM_SITE_HOST__']]);
$rewrite_url = $nodes['Update WP Post (Rewrite)']['parameters']['url'];
check('rewrite PATCHes the payload\'s post_rest_url as-is (no scheme, host or /wp-json assembled)',
    $rewrite_url === "={{ \$('Parse Input').first().json.post_rest_url }}");
check('Parse Input passes post_rest_url through',
    str_contains($nodes['Parse Input']['parameters']['jsCode'], "post_rest_url: body.post_rest_url || ''"));

/* ─── Story 2: the test ping's callback — the template's way back and the route it lands on ── */
echo "Round-trip ping (story 2)\n";
check('Ping OK → Result Ready → POST Result to WordPress, passing the item through with the result fields a review carries',
    ($tpl['connections']['Ping OK']['main'][0][0]['node'] ?? null) === 'Result Ready'
    && ($tpl['connections']['Result Ready']['main'][0][0]['node'] ?? null) === 'POST Result to WordPress'
    && str_contains($nodes['Ping OK']['parameters']['jsCode'], "return [{ json: { ...d, overall_score: 0, status: '', result: '{}' } }];"));
check('Parse Input\'s ping item carries callback_url, auth_token and the stamp (one line)',
    (bool) preg_match("/^  return \[\{ json: \{ command: 'ping', ping: true, error: false, callback_url: callbackUrl, auth_token: authToken, notification_url: '', workflow_version: " . POSTQUALITY_WORKFLOW_VERSION . " \} \}\];$/m", $nodes['Parse Input']['parameters']['jsCode']));
$ping_route = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/ping/(?P<id>[A-Za-z0-9]{20})'] ?? null;
check('POST /ping/{id} registered: a 20-character id, check_ping_token, ping_callback', is_array($ping_route) && $ping_route['methods'] === 'POST'
    && $ping_route['permission_callback'] === ['PostQuality_REST_API', 'check_ping_token'] && $ping_route['callback'] === ['PostQuality_REST_API', 'ping_callback']);
check('the pending ping lives in PostQuality_Admin::PING_OPTION = postquality_ping, claimable for PING_LIVE = 60 s, waited for PING_WAIT = 8 s',
    PostQuality_Admin::PING_OPTION === 'postquality_ping' && PostQuality_Admin::PING_LIVE === 60 && PostQuality_Admin::PING_WAIT === 8);
$GLOBALS['tw']['options'] = site_options(['postquality_settings' => ['webhook_token' => 'tok-2']]);
$ping_id   = 'abcdefghij0123456789';
$pending   = ['id' => $ping_id, 'at' => time()];
$callback  = static function (string $id, string $token) use ($ping_route) {
    $request = new WP_REST_Request(['id' => $id], null, ['X-PQM-Token' => $token]);
    $GLOBALS['tw']['writes'] = [];
    if (!call_user_func($ping_route['permission_callback'], $request)) { return 'refused'; }
    return call_user_func($ping_route['callback'], $request);
};
$GLOBALS['tw']['options']['postquality_ping'] = $pending;
$res = $callback($ping_id, 'tok-2');
check('right token, the pending id: 200 {ok: true}, the pending ping marked received (id and at kept)', $res instanceof WP_REST_Response && $res->get_status() === 200
    && $res->get_data() === ['ok' => true] && $GLOBALS['tw']['options']['postquality_ping'] === ['state' => 'received'] + $pending && $GLOBALS['tw']['writes'] === ['postquality_ping']);
$GLOBALS['tw']['options']['postquality_ping'] = $pending;
$res = $callback($ping_id, 'not-this-sites-token');
check('wrong token, the pending id: refused, the pending ping marked refused (the waiting test names the token)', $res === 'refused'
    && $GLOBALS['tw']['options']['postquality_ping'] === ['state' => 'refused'] + $pending && $GLOBALS['tw']['writes'] === ['postquality_ping']);
$GLOBALS['tw']['options']['postquality_ping'] = $pending;
$res = $callback('zzzzzzzzzzzzzzzzzzzz', 'not-this-sites-token');
check('wrong token, another id: refused, nothing written', $res === 'refused' && $GLOBALS['tw']['options']['postquality_ping'] === $pending && $GLOBALS['tw']['writes'] === []);
$res = $callback('zzzzzzzzzzzzzzzzzzzz', 'tok-2');
check('right token, an unknown id: 404 pqm_ping_unknown, nothing written', $res instanceof WP_Error && $res->get_error_code() === 'pqm_ping_unknown'
    && $res->get_error_data() === ['status' => 404] && $GLOBALS['tw']['options']['postquality_ping'] === $pending && $GLOBALS['tw']['writes'] === []);
$stale = ['id' => $ping_id, 'at' => time() - 61];
$GLOBALS['tw']['options']['postquality_ping'] = $stale;
$res = $callback($ping_id, 'tok-2');
check('right token, the id of a ping older than 60 s: 404, nothing written', $res instanceof WP_Error && $res->get_error_code() === 'pqm_ping_unknown'
    && $GLOBALS['tw']['options']['postquality_ping'] === $stale && $GLOBALS['tw']['writes'] === []);
$res = $callback($ping_id, 'not-this-sites-token');
check('wrong token, the id of a stale ping: refused, nothing written', $res === 'refused' && $GLOBALS['tw']['options']['postquality_ping'] === $stale && $GLOBALS['tw']['writes'] === []);
unset($GLOBALS['tw']['options']['postquality_ping']);
$res = $callback($ping_id, 'tok-2');
check('no pending ping at all: 404, nothing written', $res instanceof WP_Error && $res->get_error_code() === 'pqm_ping_unknown' && $GLOBALS['tw']['writes'] === []);
$GLOBALS['tw']['options']['postquality_ping'] = $pending;
$GLOBALS['tw']['caps'][] = 'postquality_run_free_agents';
$res = $callback($ping_id, '');
check('a logged-in user who may run agents passes without the header, as on /save-review', $res instanceof WP_REST_Response && $res->get_status() === 200);
$GLOBALS['tw']['caps'] = ['postquality_view_reviews'];
check('/ping/{id} ignores workflow_variant and every other field: no args schema', !isset($ping_route['args']));
check('uninstall removes the pending ping option', str_contains(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-activator.php'), "'postquality_ping'") && str_contains(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-activator.php'), 'delete_option( $new );')); // story 91: every mapped option, new and old
$GLOBALS['tw']['options'] = site_options();

/* Payload: the post's own REST address, '' when its type is not in REST */
$payload = postquality_n8n_payload(new WP_Post(7, 'a-page', 'page'), 'rewrite', postquality_get_settings());
check('payload post_rest_url = rest_url() of the post\'s own route (page, plain permalinks, port, subdirectory)',
    $payload['post_rest_url'] === 'http://example.test:8080/blog/?rest_route=/wp/v2/pages/7');
$payload = postquality_n8n_payload(new WP_Post(8, 'hidden', 'pqm_private_type'), 'rewrite', postquality_get_settings());
check('post type not in REST → post_rest_url \'\'', $payload['post_rest_url'] === '');

/* Headers: one home for the token header, used by Run, cron and ping */
check('postquality_n8n_headers(): JSON content type + X-PQM-Token = the webhook token',
    postquality_n8n_headers(['webhook_token' => 'tok-35']) === ['Content-Type' => 'application/json', 'X-PQM-Token' => 'tok-35']);
$sources = implode('', array_map(static fn($f) => file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/' . $f),
    ['includes/class-pqm-rest-api.php', 'includes/class-pqm-cron.php', 'admin/class-pqm-admin.php']));
check('every wp_remote_post to n8n sends postquality_n8n_headers() (Run, cron, ping)',
    substr_count($sources, 'wp_remote_post(') === 3 && substr_count($sources, 'postquality_n8n_headers(') === 3
    && !str_contains($sources, "'headers' => ['Content-Type'") && !str_contains($sources, "'headers' => [ 'Content-Type'"));

/* ─── in_flight derivation (PostQuality_DB::get_in_flight_for_posts) ─────────── */

/* Row: list in-flight — latest row per post+command is processing → in_flight */
echo "In flight\n";
$wpdb = fresh_wpdb([[
    ['post_id' => '7', 'command' => 'takeaways'],
    ['post_id' => '7', 'command' => 'review'],
    ['post_id' => '9', 'command' => 'seo'],
]]);
$map = PostQuality_DB::get_in_flight_for_posts([7, 9, 11]);
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
check('[] → [] without touching wpdb', PostQuality_DB::get_in_flight_for_posts([]) === [] && $wpdb->results_sql === []);

/* Row: nothing processing — empty map */
echo "In flight, none\n";
fresh_wpdb([[]]);
check('no processing rows → []', PostQuality_DB::get_in_flight_for_posts([7]) === []);

harness_exit();
