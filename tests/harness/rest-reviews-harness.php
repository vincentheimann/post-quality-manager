<?php
/**
 * REST reviews harness for story 5.4 (detail panel + trigger flow) — the
 * I/O & Edge-Case Matrix rows the plugin side owns: GET /reviews/{id}
 * shaping (int score, display date via get_date_from_gmt, 50-row limit,
 * raw result_data on bad JSON) and the `in_flight` derivation behind the
 * list's polling. WP + wpdb stubs copied from rest-posts-harness.php, no
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
    return ['date_format' => 'Y-m-d', 'time_format' => 'H:i', 'pqm_settings' => []][$k] ?? $d;
}
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $value; }
function __($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return (string) $s; }
function esc_attr($s) { return (string) $s; }
function esc_url($s) { return (string) $s; }
function sanitize_text_field($s) { return (string) $s; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function absint($n) { return abs((int) $n); }
function is_admin() { return false; }
function home_url($p = '') { return 'https://example.com' . $p; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function rest_url($p = '') { return '/wp-json/' . $p; }
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
}

/* ─── Load the plugin bootstrap ──────────────────────────────────────── */
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
check('query is post_id = %d ... ORDER BY reviewed_at DESC LIMIT %d', $p !== null && strpos($p['sql'], 'WHERE post_id = %d') !== false);
check('prepared with [7, 50]', $p !== null && $p['args'] === [7, 50]);

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
check('template fallback row: empty status, empty result, score 0', $rows[1]['status'] === '' && $rows[1]['result_data'] === [] && $rows[1]['overall_score'] === 0);

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
