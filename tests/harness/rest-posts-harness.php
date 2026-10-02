<?php
/**
 * REST list-route harness for story 5.1 — covers the I/O & Edge-Case Matrix
 * of GET post-quality/v1/posts: registration + permission, response shape,
 * bad-param clamping to the DB whitelist, and the empty site. WP + wpdb
 * stubs, no WP install.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('UTC'); // deterministic latest_reviewed_display assertions

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['tw'] = ['caps' => []];
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
function date_i18n($format, $ts = null) { return gmdate($format, $ts ?? time()); }
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }

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
    public $var_queue = [];     // values returned by successive get_var()
    public $last_var_sql = '';

    public function prepare($sql, ...$args) {
        $this->prepared[] = ['sql' => $sql, 'args' => $args];
        return $sql;
    }
    public function get_results($sql, $output = ARRAY_A) {
        return array_shift($this->results_queue) ?: [];
    }
    public function get_var($sql) {
        $this->last_var_sql = $sql;
        return array_shift($this->var_queue) ?: 0;
    }
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

function fresh_wpdb(array $results_queue, array $var_queue): PQM_Stub_WPDB {
    global $wpdb;
    $wpdb = new PQM_Stub_WPDB();
    $wpdb->results_queue = $results_queue;
    $wpdb->var_queue = $var_queue;
    return $wpdb;
}

/** The prepare() call carrying LIMIT %d OFFSET %d (the items query). */
function limit_args(PQM_Stub_WPDB $wpdb): ?array {
    foreach ($wpdb->prepared as $p) {
        if (strpos($p['sql'], 'LIMIT %d OFFSET %d') !== false) {
            return $p;
        }
    }
    return null;
}

/* Row: registration — /posts exists, GET, view-reviews gate; old routes untouched */
echo "Route registration\n";
PQM_REST_API::register_routes();
$routes = $GLOBALS['pqm_routes'];
$ns = PQM_REST_NAMESPACE;
check('/posts registered', isset($routes[$ns . '/posts']));
check('/posts is read-only GET', ($routes[$ns . '/posts']['methods'] ?? '') === WP_REST_Server::READABLE);
check('/posts gated by check_view_reviews', ($routes[$ns . '/posts']['permission_callback'] ?? null) === ['PQM_REST_API', 'check_view_reviews']);
foreach (['/post-score', '/save-review', '/reviews/(?P<post_id>\d+)', '/trigger-review', '/workflow'] as $existing) {
    check("existing route still registered: $existing", isset($routes[$ns . $existing]));
}

/* Row: no capability — 403 via the shared permission callback */
echo "No capability\n";
$GLOBALS['tw']['caps'] = [];
check('check_view_reviews refuses without pqm_view_reviews', PQM_REST_API::check_view_reviews() === false);
$GLOBALS['tw']['caps'] = ['pqm_view_reviews'];
check('check_view_reviews passes with pqm_view_reviews', PQM_REST_API::check_view_reviews() === true);

/* Row: list happy path — 200 {items,total,pages}, full item shape + commands */
echo "List happy path\n";
$wpdb = fresh_wpdb([
    [
        ['post_id' => '7', 'title' => 'Hello World', 'slug' => 'hello', 'post_type' => 'post', 'wp_post_status' => 'publish', 'latest_score' => '83', 'latest_status' => 'ship', 'latest_reviewed' => '2026-09-30 10:00:00', 'latest_command' => 'review'],
        ['post_id' => '9', 'title' => 'Untouched', 'slug' => 'untouched', 'post_type' => 'post', 'wp_post_status' => 'publish', 'latest_score' => null, 'latest_status' => null, 'latest_reviewed' => null, 'latest_command' => null],
    ],
    [
        ['post_id' => '7', 'command' => 'review'],
        ['post_id' => '7', 'command' => 'seo'],
    ],
], [2]);
$res = PQM_REST_API::list_posts_callback(new WP_REST_Request());
check('200 response', $res instanceof WP_REST_Response && $res->get_status() === 200);
$data = $res->get_data();
check('shape is {items,total,pages}', array_keys($data) === ['items', 'total', 'pages']);
check('total 2, pages 1', $data['total'] === 2 && $data['pages'] === 1);
$item = $data['items'][0];
check('item carries post_id/title/edit_link/latest_* /commands',
    $item['post_id'] === 7
    && $item['title'] === 'Hello World'
    && $item['edit_link'] === '/wp-admin/post.php?post=7&action=edit'
    && $item['latest_score'] === 83
    && $item['latest_status'] === 'ship'
    && $item['latest_reviewed'] === '2026-09-30 10:00:00'
    && $item['commands'] === ['review', 'seo']);
check('reviewed date formatted for display', $item['latest_reviewed_display'] === '2026-09-30 10:00');
$never = $data['items'][1];
check('unreviewed post: null score, empty status/date, no commands',
    $never['latest_score'] === null
    && $never['latest_status'] === ''
    && $never['latest_reviewed'] === ''
    && $never['latest_reviewed_display'] === ''
    && $never['commands'] === []);
$limit = limit_args($wpdb);
check('defaults reach the DB: per_page 20, offset 0', $limit !== null && $limit['args'] === [20, 0]);
check('default order: latest_reviewed DESC', strpos($limit['sql'], 'ORDER BY latest_reviewed DESC') !== false);

/* Row: bad params — clamped/whitelisted, silently sanitized, still 200 */
echo "Bad params\n";
$wpdb = fresh_wpdb([[]], [0]);
$res = PQM_REST_API::list_posts_callback(new WP_REST_Request([
    'per_page' => 999,
    'page'     => 0,
    'orderby'  => 'evil',
    'order'    => 'sideways',
    'filter'   => 'bogus',
]));
check('still 200', $res instanceof WP_REST_Response && $res->get_status() === 200);
$limit = limit_args($wpdb);
check('per_page 999 clamped to 100', $limit !== null && $limit['args'][0] === 100);
check('page 0 clamped to 1 (offset 0)', $limit !== null && $limit['args'][1] === 0);
check('orderby evil falls back to latest_reviewed', strpos($limit['sql'], 'ORDER BY latest_reviewed') !== false);
check('order sideways falls back to DESC', strpos($limit['sql'], 'DESC') !== false);
check('filter bogus treated as all (no HAVING)', strpos($wpdb->last_var_sql, 'HAVING') === false);

/* Row: whitelisted values pass through untouched */
echo "Whitelisted params\n";
$wpdb = fresh_wpdb([[]], [0]);
PQM_REST_API::list_posts_callback(new WP_REST_Request([
    'per_page' => 5,
    'page'     => 3,
    'orderby'  => 'score',
    'order'    => 'asc',
    'filter'   => 'needs_review',
]));
$limit = limit_args($wpdb);
check('per_page 5 / page 3 → offset 10', $limit !== null && $limit['args'] === [5, 10]);
check('orderby score ASC honored', strpos($limit['sql'], 'ORDER BY latest_score ASC') !== false);
check('needs_review filter reaches the query', strpos($wpdb->last_var_sql, 'HAVING') !== false);

/* Row: empty site — 200 with items: [] */
echo "Empty site\n";
$wpdb = fresh_wpdb([], []);
$res = PQM_REST_API::list_posts_callback(new WP_REST_Request());
$data = $res->get_data();
check('200 with empty items', $res->get_status() === 200 && $data['items'] === [] && $data['total'] === 0 && $data['pages'] === 0);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
