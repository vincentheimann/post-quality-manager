<?php
/**
 * REST list-route harness for story 5.1 — covers the I/O & Edge-Case Matrix
 * of GET post-quality/v1/posts: registration + permission, response shape,
 * bad-param clamping to the DB whitelist, and the empty site. WP + wpdb
 * stubs, no WP install. Story 5.3 appends the filter/sort/counts rows.
 *
 * wpdb call order per list request: get_var (count) → get_results (items)
 * → get_results (commands) → get_results (in_flight, story 5.4) — both
 * only when items exist — → get_results (counts).
 *
 * Story 5.6 appends `orderby=priority` (Fix-next queue) and GET /dashboard:
 * registration, flag matrix and the score-trend walk on known rows (one
 * get_results per request).
 *
 * Story 5.10 appends `wp_post_status`, `yoast` and `yoast_active`: Yoast
 * off, then WPSEO_VERSION defined (it cannot be undefined again, so those
 * rows run last) with scored, unanalyzed and half-scored posts.
 *
 * Story 14 appends: the /trigger-review and /save-review `command` enums
 * equal the registry keys (the one command whitelist), and the viewer's
 * hidden list covers all 8 registry commands.
 *
 * Ticket 32 appends the viewer's `connected` / `can_manage_settings` flags
 * (webhook URL set or empty × admin or author).
 */

date_default_timezone_set('UTC'); // deterministic latest_reviewed_display assertions

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('WEEK_IN_SECONDS', 7 * 86400);

$GLOBALS['tw'] = [
    'caps'      => [],
    'user_id'   => 42,
    'user_meta' => [], // user_id => [key => value]
    'options'   => site_options(), // the site's options + overrides (story 5.6: pqm_settings, pqm_cron_nudge_dismissed)
    'post_meta'   => [], // post_id => [key => value] (story 5.10: Yoast scores)
    'meta_reads'  => 0,  // get_post_meta() calls
    'meta_primes' => [], // update_postmeta_cache() calls, each its id list
];
$GLOBALS['pqm_routes'] = [];

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
function plugin_dir_url($f) { return ''; }
/* The site's options every row starts from (the bootstrap's store); a row replaces tw.options through this. */
function site_options(array $extra = []): array { return $extra + ['date_format' => 'Y-m-d', 'time_format' => 'H:i', 'pqm_settings' => []]; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $value; }
function sanitize_title($title, $fallback_title = '', $context = 'save') { return strtolower((string) $title); } // WP's real arity
function is_admin() { return false; }
function home_url($p = '') { return 'https://example.com' . $p; }
function rest_url($p = '') { return '/wp-json/' . $p; }
function get_edit_post_link($id, $context = 'display') { return '/wp-admin/post.php?post=' . $id . '&action=edit'; }
/* Stored dates are GMT; the site timezone is pinned to UTC here, so display == stored (story 5.4 fix). */
function get_date_from_gmt($gmt, $format = 'Y-m-d H:i:s') { return gmdate($format, strtotime($gmt . ' UTC')); }
function admin_url($p = '') { return '/wp-admin/' . $p; }
function wp_create_nonce($a = '') { return 'nonce-' . $a; }
function add_query_arg(array $args, string $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$ns . $route] = $args; }
/* Viewer-block stubs (story 5.2): quota_state() reads user meta + site clock. */
function get_current_user_id() { return $GLOBALS['tw']['user_id']; }
function get_user_meta($uid, $key, $single) { return $GLOBALS['tw']['user_meta'][$uid][$key] ?? ''; }
/* Feedback card stubs (story 79): the per-user flag and the environment lines. */
function update_user_meta($uid, $key, $value) { $GLOBALS['tw']['user_meta'][$uid][$key] = $value; return true; }
function delete_user_meta($uid, $key) { unset($GLOBALS['tw']['user_meta'][$uid][$key]); return true; }
function get_bloginfo($show = '') { return $show === 'version' ? '6.8' : ''; }
function wp_get_theme() { return new class { public function get($header) { return $header === 'Name' ? 'Twenty Twenty-Five' : ''; } }; }
function wp_date($format, $ts = null) {
    if ($format === 'Y-m' && $ts === null) { return '2026-10'; } // pinned current month
    return gmdate($format, $ts ?? time());
}
function wp_timezone() { return new DateTimeZone('UTC'); }
/* Post-meta stubs (story 5.10), at WP's real arity. A missing single meta is ''. */
function get_post_meta($post_id, $key = '', $single = false) {
    $GLOBALS['tw']['meta_reads']++;
    return $GLOBALS['tw']['post_meta'][$post_id][$key] ?? '';
}
function update_postmeta_cache($post_ids) { $GLOBALS['tw']['meta_primes'][] = $post_ids; return []; }

/* ─── wpdb stub ──────────────────────────────────────────────────────── */
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
    public $results_sql = [];   // SQL of every get_results(), in call order
    public function get_results($sql, $output = ARRAY_A) {
        $this->results_sql[] = $sql;
        return array_shift($this->results_queue) ?: [];
    }
    public function get_var($sql) {
        $this->last_var_sql = $sql;
        return array_shift($this->var_queue) ?: 0;
    }
}

/* ─── Load the plugin bootstrap ──────────────────────────────────────── */
require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */

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
PostQuality_REST_API::register_routes();
$routes = $GLOBALS['pqm_routes'];
$ns = POSTQUALITY_REST_NAMESPACE;
check('/posts registered', isset($routes[$ns . '/posts']));
check('/posts is read-only GET', ($routes[$ns . '/posts']['methods'] ?? '') === WP_REST_Server::READABLE);
check('/posts gated by check_view_reviews', ($routes[$ns . '/posts']['permission_callback'] ?? null) === ['PostQuality_REST_API', 'check_view_reviews']);
foreach (['/post-score', '/save-review', '/reviews/(?P<post_id>\d+)', '/trigger-review', '/workflow'] as $existing) {
    check("existing route still registered: $existing", isset($routes[$ns . $existing]));
}

/* Story 14: the registry is the one command whitelist — both command enums are its keys,
   so WP's arg validation answers 400 rest_invalid_param for anything else. */
echo "Command enums = registry keys\n";
$registry_keys = array_keys(PostQuality_Features::registry());
check('registry keys are the 8 commands',
    $registry_keys === ['review', 'takeaways', 'rewrite', 'sources', 'seo', 'audit', 'score', 'apply']);
foreach (['/trigger-review', '/save-review'] as $route) {
    $enum = $routes[$ns . $route]['args']['command']['enum'] ?? null;
    check("$route command enum === array_keys(registry())", $enum === $registry_keys);
    check("$route command enum leaves out unknown commands (foo, apply_takeaways)",
        is_array($enum) && !in_array('foo', $enum, true) && !in_array('apply_takeaways', $enum, true));
}
/* The n8n template keeps its own list (Parse Input's validCommands): a registry-only
   addition would pass WP and be rejected by n8n, so the two sets must match too. */
$template = json_decode(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json'), true);
$template_commands = null;
foreach ($template['nodes'] ?? [] as $node) {
    if (($node['name'] ?? '') === 'Parse Input'
        && preg_match('/const validCommands = \[([^\]]*)\]/', $node['parameters']['jsCode'] ?? '', $m)) {
        preg_match_all("/'([a-z_]+)'/", $m[1], $names);
        $template_commands = $names[1];
    }
}
$sorted_registry = $registry_keys;
sort($sorted_registry);
if (is_array($template_commands)) { sort($template_commands); }
check("n8n template Parse Input validCommands = registry keys (as a set)", $template_commands === $sorted_registry);

/* Every arg's sanitize_callback survives WP's real call shape — (value, request, param).
   PHP internals with fixed arity (intval, trim…) throw ArgumentCountError there. */
echo "Sanitize callbacks take WP's three arguments
";
foreach ($routes as $route => $def) {
    foreach ((isset($def['methods']) ? [$def] : $def) as $endpoint) {
        foreach (($endpoint['args'] ?? []) as $param => $arg) {
            if (!isset($arg['sanitize_callback'])) { continue; }
            try {
                call_user_func($arg['sanitize_callback'], '41', new WP_REST_Request(), $param);
                $ok = true;
            } catch (Throwable $e) {
                $ok = false;
            }
            check("$route $param: sanitize_callback accepts (value, request, param)", $ok);
        }
    }
}

/* Row: no capability — 403 via the shared permission callback */
echo "No capability\n";
$GLOBALS['tw']['caps'] = [];
check('check_view_reviews refuses without pqm_view_reviews', PostQuality_REST_API::check_view_reviews() === false);
$GLOBALS['tw']['caps'] = ['pqm_view_reviews'];
check('check_view_reviews passes with pqm_view_reviews', PostQuality_REST_API::check_view_reviews() === true);
/* Ticket 12: an edit_posts-only user (WP Author/Contributor without pqm caps) cannot read reviews */
$GLOBALS['tw']['caps'] = ['edit_posts'];
check('check_view_reviews refuses an edit_posts-only user', PostQuality_REST_API::check_view_reviews() === false);

/* Ticket 12: the trigger route lets through any registry capability, nothing else */
echo "Trigger route permission\n";
check('/trigger-review gated by check_can_trigger', ($routes[$ns . '/trigger-review']['permission_callback'] ?? null) === ['PostQuality_REST_API', 'check_can_trigger']);
foreach ([
    'free cap [pqm_run_free_agents]' => [['pqm_run_free_agents'], true],
    'paid cap [pqm_run_paid_agents]' => [['pqm_run_paid_agents'], true],
    'capless []'                     => [[], false],
    'edit_posts only [edit_posts]'   => [['edit_posts'], false],
] as $label => [$caps, $expected]) {
    $GLOBALS['tw']['caps'] = $caps;
    check("check_can_trigger, $label → " . var_export($expected, true), PostQuality_REST_API::check_can_trigger() === $expected);
}
$GLOBALS['tw']['caps'] = ['pqm_view_reviews'];

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
    [ // in_flight query (story 5.4): post 7's latest seo row is still processing
        ['post_id' => '7', 'command' => 'seo'],
    ],
    [ // counts query (story 5.3)
        ['status' => 'ship', 'cnt' => '1'],
        ['status' => null, 'cnt' => '1'],
    ],
], [2]);
$res = PostQuality_REST_API::list_posts_callback(new WP_REST_Request());
check('200 response', $res instanceof WP_REST_Response && $res->get_status() === 200);
$data = $res->get_data();
check('shape is {items,total,pages,counts,viewer,yoast_active} — counts (5.3), viewer (5.2), yoast_active (5.10) additive, first three untouched',
    array_keys($data) === ['items', 'total', 'pages', 'counts', 'viewer', 'yoast_active']);
check('total 2, pages 1', $data['total'] === 2 && $data['pages'] === 1);
check('counts reach the response after the in_flight query (ship 1, none 1)',
    $data['counts']['ship'] === 1 && $data['counts']['none'] === 1 && $data['counts']['all'] === 2);
$item = $data['items'][0];
check('item keys: 5.1–5.3 keys byte-identical, in_flight appended (5.4), wp_post_status + yoast appended (5.10)',
    array_keys($item) === ['post_id', 'title', 'edit_link', 'latest_score', 'latest_status', 'latest_reviewed', 'latest_reviewed_display', 'commands', 'in_flight', 'wp_post_status', 'yoast']);
check('item carries post_id/title/edit_link/latest_* /commands',
    $item['post_id'] === 7
    && $item['title'] === 'Hello World'
    && $item['edit_link'] === '/wp-admin/post.php?post=7&action=edit'
    && $item['latest_score'] === 83
    && $item['latest_status'] === 'ship'
    && $item['latest_reviewed'] === '2026-09-30 10:00:00'
    && $item['commands'] === ['review', 'seo']);
check('in_flight lists the processing command; commands unchanged', $item['in_flight'] === ['seo'] && $item['commands'] === ['review', 'seo']);
check('reviewed date formatted for display via get_date_from_gmt (stored value is GMT)', $item['latest_reviewed_display'] === '2026-09-30 10:00');
$never = $data['items'][1];
check('unreviewed post: null score, empty status/date, no commands, nothing in flight',
    $never['latest_score'] === null
    && $never['latest_status'] === ''
    && $never['latest_reviewed'] === ''
    && $never['latest_reviewed_display'] === ''
    && $never['commands'] === []
    && $never['in_flight'] === []);
$in_flight_sql = $wpdb->results_sql[2];
check('in_flight query: latest row per post+command (MAX(reviewed_at) join) with status processing, one query',
    strpos($in_flight_sql, 'MAX(reviewed_at)') !== false
    && strpos($in_flight_sql, 'GROUP BY post_id, command') !== false
    && strpos($in_flight_sql, "r1.status = 'processing'") !== false
    && count($wpdb->results_sql) === 4);
$limit = limit_args($wpdb);
check('defaults reach the DB: per_page 20, offset 0', $limit !== null && $limit['args'] === [20, 0]);
check('default order: latest_reviewed DESC', strpos($limit['sql'], 'ORDER BY latest_reviewed DESC') !== false);
check('latest-review subquery excludes the processing placeholder (5.4: a running review never moves the post)',
    strpos($limit['sql'], "WHERE command = 'review' AND status <> 'processing'") !== false
    && strpos($wpdb->last_var_sql, "WHERE command = 'review' AND status <> 'processing'") !== false);

/* Row: bad params — clamped/whitelisted, silently sanitized, still 200 */
echo "Bad params\n";
$wpdb = fresh_wpdb([[], []], [0]);
$res = PostQuality_REST_API::list_posts_callback(new WP_REST_Request([
    'per_page'  => 999,
    'page'      => 0,
    'orderby'   => 'evil',
    'order'     => 'sideways',
    'filter'    => 'bogus',
    'status'    => 'weird',
    'min_score' => -5,
    'max_score' => 500,
]));
check('still 200', $res instanceof WP_REST_Response && $res->get_status() === 200);
$limit = limit_args($wpdb);
check('per_page 999 clamped to 100', $limit !== null && $limit['args'][0] === 100);
check('page 0 clamped to 1 (offset 0)', $limit !== null && $limit['args'][1] === 0);
check('orderby evil falls back to latest_reviewed', strpos($limit['sql'], 'ORDER BY latest_reviewed') !== false);
check('order sideways falls back to DESC', strpos($limit['sql'], 'DESC') !== false);
check('filter bogus treated as all (no needs_review HAVING)', strpos($wpdb->last_var_sql, 'latest_score IS NULL') === false);
check('status weird ignored (no lr.status clause)', strpos($limit['sql'], 'lr.status =') === false && strpos($limit['sql'], 'lr.status IS NULL') === false);
$band = prepared_with($wpdb, 'latest_score BETWEEN');
check('scores clamped to 0–100 (min -5 → 0, max 500 → 100)', $band !== null && $band['args'] === [0, 100]);

/* Row: whitelisted values pass through untouched */
echo "Whitelisted params\n";
$wpdb = fresh_wpdb([[], []], [0]);
PostQuality_REST_API::list_posts_callback(new WP_REST_Request([
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
check('needs_review clause parenthesised (safe to AND with a band)',
    strpos($wpdb->last_var_sql, 'HAVING (latest_score IS NULL OR latest_score < 71)') !== false);
check('no status / band clauses by default',
    strpos($limit['sql'], 'lr.status =') === false && strpos($limit['sql'], 'lr.status IS NULL') === false
    && strpos($limit['sql'], 'latest_score BETWEEN') === false
    && strpos($limit['sql'], 'latest_score >=') === false && strpos($limit['sql'], 'latest_score <=') === false);

/* Row: empty site — 200 with items: [] */
echo "Empty site\n";
$wpdb = fresh_wpdb([], []);
$res = PostQuality_REST_API::list_posts_callback(new WP_REST_Request());
$data = $res->get_data();
check('200 with empty items', $res->get_status() === 200 && $data['items'] === [] && $data['total'] === 0 && $data['pages'] === 0);
check('counts all zero on an empty site',
    $data['counts'] === ['all' => 0, 'ship' => 0, 'needs_revision' => 0, 'major_revision' => 0, 'none' => 0]);

/* ─── Filters, sort and counts (story 5.3) ───────────────────────────── */

/** The prepare() call whose SQL contains $needle, or null. */
function prepared_with(PQM_Stub_WPDB $wpdb, string $needle): ?array {
    foreach ($wpdb->prepared as $p) {
        if (strpos($p['sql'], $needle) !== false) {
            return $p;
        }
    }
    return null;
}

/** Run one list request against an empty result set and return the stub. */
function list_with(array $params): PQM_Stub_WPDB {
    $wpdb = fresh_wpdb([[], []], [0]);
    PostQuality_REST_API::list_posts_callback(new WP_REST_Request($params));
    return $wpdb;
}

/* Row: status filter — lr.status = %s, prepared */
echo "Status filter\n";
$wpdb = list_with(['status' => 'needs_revision']);
$p = prepared_with($wpdb, 'lr.status = %s');
check('status=needs_revision → prepared lr.status = %s', $p !== null && $p['args'] === ['needs_revision']);
$limit = limit_args($wpdb);
check('clause sits in the WHERE of the items query', $limit !== null && strpos($limit['sql'], 'lr.status = %s') !== false);
check('clause sits in the count query too (total counts the filtered set)', strpos($wpdb->last_var_sql, 'lr.status = %s') !== false);

/* Row: not analyzed — status=none → lr.status IS NULL */
echo "Not analyzed\n";
$wpdb = list_with(['status' => 'none']);
$limit = limit_args($wpdb);
check('status=none → lr.status IS NULL in items query', $limit !== null && strpos($limit['sql'], 'lr.status IS NULL') !== false);
check('status=none → lr.status IS NULL in count query', strpos($wpdb->last_var_sql, 'lr.status IS NULL') !== false);
check("status=none also matches an empty status ('' = failed-run fallback, counted as none) in items + count queries",
    $limit !== null
    && strpos($limit['sql'], "(lr.status IS NULL OR lr.status = '')") !== false
    && strpos($wpdb->last_var_sql, "(lr.status IS NULL OR lr.status = '')") !== false);
check('no lr.status = %s prepared', prepared_with($wpdb, 'lr.status = %s') === null);

/* Row: unknown status — ignored, treated as no filter */
echo "Unknown status\n";
$wpdb = list_with(['status' => 'weird']);
$limit = limit_args($wpdb);
check('status=weird → no lr.status clause at all', $limit !== null
    && strpos($limit['sql'], 'lr.status =') === false && strpos($limit['sql'], 'lr.status IS NULL') === false);

/* Row: score band — HAVING latest_score BETWEEN prepared ints */
echo "Score band\n";
$wpdb = list_with(['min_score' => 41, 'max_score' => 70]);
$p = prepared_with($wpdb, 'latest_score BETWEEN %d AND %d');
check('min 41 / max 70 → prepared BETWEEN %d AND %d with [41, 70]', $p !== null && $p['args'] === [41, 70]);
$limit = limit_args($wpdb);
check('band lives in HAVING of the items query', $limit !== null && strpos($limit['sql'], 'HAVING latest_score BETWEEN %d AND %d') !== false);
check('band lives in HAVING of the count query', strpos($wpdb->last_var_sql, 'HAVING latest_score BETWEEN %d AND %d') !== false);

/* Row: score band, open-ended — min only */
echo "Score band, open-ended\n";
$wpdb = list_with(['min_score' => 71]);
$p = prepared_with($wpdb, 'latest_score >= %d');
check('min 71 alone → prepared latest_score >= %d with [71]', $p !== null && $p['args'] === [71]);
check('no BETWEEN / <= clause', prepared_with($wpdb, 'BETWEEN') === null && prepared_with($wpdb, 'latest_score <=') === null);
$wpdb = list_with(['max_score' => 40]);
$p = prepared_with($wpdb, 'latest_score <= %d');
check('max 40 alone → prepared latest_score <= %d with [40]', $p !== null && $p['args'] === [40]);

/* Row: band + legacy needs_review combine with AND, parenthesised */
echo "Band + needs_review\n";
$wpdb = list_with(['filter' => 'needs_review', 'min_score' => 41, 'max_score' => 70]);
$limit = limit_args($wpdb);
check('HAVING (needs_review) AND band', $limit !== null
    && strpos($limit['sql'], 'HAVING (latest_score IS NULL OR latest_score < 71) AND latest_score BETWEEN %d AND %d') !== false);

/* Row: status sort — severity FIELD() order, NULLs last asc, desc reverses */
echo "Status sort\n";
$expr = PostQuality_DB::status_order_expression();
check('expression ranks by FIELD(major_revision, needs_revision, ship, processing)',
    $expr === "IFNULL(NULLIF(FIELD(lr.status, 'major_revision','needs_revision','ship','processing'), 0), 99)");
$wpdb = list_with(['orderby' => 'status', 'order' => 'asc']);
$limit = limit_args($wpdb);
check('orderby=status asc → ORDER BY <severity expr> ASC', $limit !== null && strpos($limit['sql'], "ORDER BY {$expr} ASC") !== false);
$wpdb = list_with(['orderby' => 'status', 'order' => 'desc']);
$limit = limit_args($wpdb);
check('orderby=status desc → ORDER BY <severity expr> DESC', $limit !== null && strpos($limit['sql'], "ORDER BY {$expr} DESC") !== false);

/* Row: counts — fixed shape, filter-independent, ints; processing folds into all only */
echo "Counts\n";
$wpdb = fresh_wpdb([
    [],
    [
        ['status' => 'ship',           'cnt' => '28'],
        ['status' => 'needs_revision', 'cnt' => '9'],
        ['status' => 'major_revision', 'cnt' => '6'],
        ['status' => 'processing',     'cnt' => '2'],
        ['status' => null,             'cnt' => '15'],
    ],
], [0]);
$data = PostQuality_REST_API::list_posts_callback(new WP_REST_Request(['status' => 'ship', 'min_score' => 71]))->get_data();
check('counts keys are {all,ship,needs_revision,major_revision,none}',
    array_keys($data['counts']) === ['all', 'ship', 'needs_revision', 'major_revision', 'none']);
check('counts are ints: ship 28, needs_revision 9, major_revision 6, none 15',
    $data['counts']['ship'] === 28 && $data['counts']['needs_revision'] === 9
    && $data['counts']['major_revision'] === 6 && $data['counts']['none'] === 15);
check('all = every listable post incl. processing (60)', $data['counts']['all'] === 60);
$counts_sql = end($wpdb->results_sql);
check('counts query is one GROUP BY lr.status over listable posts',
    strpos($counts_sql, 'GROUP BY lr.status') !== false && strpos($counts_sql, "p.post_status IN ('publish', 'pending')") !== false);
check('counts query ignores the active status / band filters',
    strpos($counts_sql, 'lr.status =') === false && strpos($counts_sql, 'HAVING') === false);
check('counts query shares the placeholder exclusion (chips never count a running review)',
    strpos($counts_sql, "WHERE command = 'review' AND status <> 'processing'") !== false);

/* ─── Viewer block (story 5.2) — read-only mirror of PostQuality_Features ────── */

/** Fetch just the viewer block for the current test-world user. */
function viewer_block(): array {
    fresh_wpdb([[]], [0]);
    return PostQuality_REST_API::list_posts_callback(new WP_REST_Request())->get_data()['viewer'];
}

/* Row: free-tier author — quota applies, paid commands hidden by role */
echo "Viewer: free-tier author\n";
$GLOBALS['tw']['caps'] = ['pqm_view_reviews', 'pqm_run_free_agents'];
$GLOBALS['tw']['user_meta'] = [42 => ['pqm_monthly_usage' => ['month' => '2026-10', 'count' => 6]]];
$v = viewer_block();
check('viewer keys are {can_trigger,quota,gated_commands,hidden_commands} + connected, can_manage_settings appended (ticket 32)',
    array_keys($v) === ['can_trigger', 'quota', 'gated_commands', 'hidden_commands', 'connected', 'can_manage_settings']);
check('can_trigger true', $v['can_trigger'] === true);
check('quota: limit 20, used 6, remaining 14',
    is_array($v['quota'])
    && $v['quota']['limit'] === 20
    && $v['quota']['used'] === 6
    && $v['quota']['remaining'] === 14);
check('quota carries a non-empty resets date', is_string($v['quota']['resets'] ?? null) && $v['quota']['resets'] !== '');
check('quota equals PostQuality_Features::quota_state()', $v['quota'] === PostQuality_Features::quota_state());
check('gated_commands empty', $v['gated_commands'] === []);
check('hidden_commands = paid commands', $v['hidden_commands'] === ['rewrite', 'sources', 'seo', 'audit']);
check('partition equals PostQuality_Features outputs',
    $v['gated_commands'] === PostQuality_Features::gated_commands()
    && $v['hidden_commands'] === PostQuality_Features::hidden_commands());

/* Row: admin on free plan — exempt from quota, paid commands gated by plan */
echo "Viewer: admin on free plan\n";
$GLOBALS['tw']['caps'] = ['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents', 'pqm_manage_settings'];
$GLOBALS['tw']['user_meta'] = [];
$v = viewer_block();
check('can_trigger true', $v['can_trigger'] === true);
check('quota null (exempt via pqm_run_paid_agents)', $v['quota'] === null);
check('gated_commands = paid commands', $v['gated_commands'] === ['rewrite', 'sources', 'seo', 'audit']);
check('hidden_commands empty', $v['hidden_commands'] === []);
check('partition equals PostQuality_Features outputs',
    $v['gated_commands'] === PostQuality_Features::gated_commands()
    && $v['hidden_commands'] === PostQuality_Features::hidden_commands());

/* Row: cannot trigger — view-only user: quota null even though a limit exists */
echo "Viewer: cannot trigger\n";
$GLOBALS['tw']['caps'] = ['pqm_view_reviews'];
$GLOBALS['tw']['user_meta'] = [];
$v = viewer_block();
check('can_trigger false', $v['can_trigger'] === false);
check('quota null despite an applicable limit', $v['quota'] === null && PostQuality_Features::quota_state() !== null);
check('gated_commands empty', $v['gated_commands'] === []);
check('all 8 registry commands hidden', $v['hidden_commands'] === ['review', 'takeaways', 'rewrite', 'sources', 'seo', 'audit', 'score', 'apply']);
check('hidden equals PostQuality_Features::hidden_commands()', $v['hidden_commands'] === PostQuality_Features::hidden_commands());

/* Ticket 32: connected / can_manage_settings — the GET /dashboard flags, on the list's viewer */
echo "Viewer: connected and can_manage_settings\n";
$viewer_admin  = ['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents', 'pqm_manage_settings'];
$viewer_author = ['pqm_view_reviews', 'pqm_run_free_agents'];
foreach ([
    'URL set, admin'    => ['https://n8n.example/webhook', $viewer_admin,  true,  true],
    'URL empty, admin'  => ['',                            $viewer_admin,  false, true],
    'URL empty, author' => ['',                            $viewer_author, false, false],
    'URL set, author'   => ['https://n8n.example/webhook', $viewer_author, true,  false],
] as $label => [$url, $caps, $connected, $can_manage]) {
    $GLOBALS['tw']['caps'] = $caps;
    $GLOBALS['tw']['user_meta'] = [];
    $GLOBALS['tw']['options'] = site_options(['pqm_settings' => ['n8n_webhook_url' => $url]]);
    $v = viewer_block();
    check("$label → connected " . var_export($connected, true) . ', can_manage_settings ' . var_export($can_manage, true),
        $v['connected'] === $connected && $v['can_manage_settings'] === $can_manage);
    check("$label → existing viewer keys keep their order", array_slice(array_keys($v), 0, 4) === ['can_trigger', 'quota', 'gated_commands', 'hidden_commands']);
}
$GLOBALS['tw']['options'] = site_options();

/* ─── Fix-next queue: orderby=priority (story 5.6) ───────────────────── */

/* Row: queue order — rank, score ASC, reviewed ASC, post date ASC, ID ASC; `order` ignored */
echo "Queue order (orderby=priority)\n";
$priority = PostQuality_DB::priority_order_sql();
check('priority SQL: rank (major, needs, none — NULL/empty status read as none), then score, staleness, post date, ID',
    $priority === "IFNULL(NULLIF(FIELD(IFNULL(NULLIF(lr.status, ''), 'none'), 'major_revision','needs_revision','none'), 0), 99) ASC, latest_score ASC, latest_reviewed ASC, p.post_date ASC, p.ID ASC");
$wpdb = list_with(['orderby' => 'priority', 'order' => 'desc', 'per_page' => 5]);
$limit = limit_args($wpdb);
check('orderby=priority passes the whitelist → ORDER BY <priority list>', $limit !== null && strpos($limit['sql'], "ORDER BY {$priority}") !== false);
check('order=desc ignored (no fallback to latest_reviewed, no DESC)', $limit !== null
    && strpos($limit['sql'], 'ORDER BY latest_reviewed') === false && strpos($limit['sql'], 'DESC') === false);
check('per_page 5 reaches the DB (queue of 5)', $limit !== null && $limit['args'] === [5, 0]);
check('ship is not a priority rank (ranks after; the client drops ship rows)', !in_array('ship', PostQuality_DB::PRIORITY, true));

/* ─── GET /dashboard (story 5.6) ─────────────────────────────────────── */

/* Row: registration + permission — read-only, view-reviews gate */
echo "Dashboard route\n";
PostQuality_REST_API::register_routes();
$routes = $GLOBALS['pqm_routes'];
check('/dashboard registered', isset($routes[$ns . '/dashboard']));
check('/dashboard is read-only GET', ($routes[$ns . '/dashboard']['methods'] ?? '') === WP_REST_Server::READABLE);
check('/dashboard gated by check_view_reviews (no pqm_view_reviews → 401/403)',
    ($routes[$ns . '/dashboard']['permission_callback'] ?? null) === ['PostQuality_REST_API', 'check_view_reviews']);

/**
 * One /dashboard request: $rows feed the trend query; $settings become
 * pqm_settings; $dismissed sets pqm_cron_nudge_dismissed; $pro is the license.
 */
function dashboard_with(array $caps, array $settings, array $rows, bool $dismissed = false, bool $pro = true): array {
    $GLOBALS['tw']['caps'] = $caps;
    postquality_fs()->pro = $pro; // the SDK stand-in's license (story 44)
    $GLOBALS['tw']['options'] = site_options(['pqm_settings' => $settings, 'pqm_cron_nudge_dismissed' => $dismissed ? 1 : false]);
    fresh_wpdb([$rows], []);
    $res = PostQuality_REST_API::dashboard_callback();
    return ['status' => $res->get_status(), 'data' => $res->get_data()];
}

/** GMT 'Y-m-d H:i:s', $weeks weeks + $days days before now. */
function ago(int $weeks, int $days = 0): string {
    return gmdate('Y-m-d H:i:s', time() - $weeks * WEEK_IN_SECONDS - $days * 86400);
}

$admin = ['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents', 'pqm_manage_settings'];
$connected_pro = ['n8n_webhook_url' => 'https://n8n.example/webhook', 'cron_enabled' => false];
$one_scored = [['post_id' => '1', 'overall_score' => '70', 'status' => 'ship', 'reviewed_at' => ago(0, 1)]];

/* Row: trend on known rows — each point = average of latest results as of that week's end */
echo "Trend walk on known rows\n";
// Week ends E0 = now − 7 weeks … E7 = now. Rows ascending, as the SQL orders them.
$rows = [
    ['post_id' => '1', 'overall_score' => '40', 'status' => 'major_revision', 'reviewed_at' => ago(7, 1)], // before E0
    ['post_id' => '2', 'overall_score' => '80', 'status' => 'ship',           'reviewed_at' => ago(5, 1)], // before E2
    ['post_id' => '1', 'overall_score' => '60', 'status' => 'needs_revision', 'reviewed_at' => ago(3, 1)], // before E4: supersedes 40
    ['post_id' => '2', 'overall_score' => '0',  'status' => '',               'reviewed_at' => ago(1, 1)], // before E6: failed latest, post 2 drops out
    ['post_id' => '3', 'overall_score' => '0',  'status' => 'error',          'reviewed_at' => ago(0, 1)], // before E7: failed, never counts
];
$r = dashboard_with($admin, $connected_pro, $rows);
$d = $r['data'];
check('200 response', $r['status'] === 200);
check('shape: average_score, trend, connected, can_manage_settings, cron_nudge (+ dismiss url when the nudge shows), feedback (story 79)',
    array_keys($d) === ['average_score', 'trend', 'connected', 'can_manage_settings', 'cron_nudge', 'cron_nudge_dismiss_url', 'feedback']);
check('8 points, each {label, average}', count($d['trend']) === 8 && array_keys($d['trend'][0]) === ['label', 'average']);
check('as-of walk: 40, 40, 60, 60, 70, 70, 60, 60 (supersede, failed-latest exclusion)',
    array_column($d['trend'], 'average') === [40, 40, 60, 60, 70, 70, 60, 60]);
check('last point equals average_score', $d['average_score'] === 60 && end($d['trend'])['average'] === $d['average_score']);
check('point label = week-end date in the site date format (no time)', end($d['trend'])['label'] === gmdate('Y-m-d'));
check('first label is seven weeks back', $d['trend'][0]['label'] === gmdate('Y-m-d', time() - 7 * WEEK_IN_SECONDS));
$trend_sql = $wpdb->results_sql[0] ?? '';
check('trend: one query of non-processing review rows for listable posts, ascending',
    count($wpdb->results_sql) === 1
    && strpos($trend_sql, "r.command = 'review' AND r.status <> 'processing'") !== false
    && strpos($trend_sql, "p.post_status IN ('publish', 'pending') AND p.post_type = 'post'") !== false
    && strpos($trend_sql, 'ORDER BY r.reviewed_at ASC') !== false);
$r = dashboard_with($admin, $connected_pro, [
    ['post_id' => '1', 'overall_score' => '40', 'status' => 'needs_revision', 'reviewed_at' => ago(0, 2)],
    ['post_id' => '2', 'overall_score' => '81', 'status' => 'ship',           'reviewed_at' => ago(0, 1)],
]);
check('average rounds to an int (60.5 → 61); earlier points without history are null',
    $r['data']['average_score'] === 61 && array_column($r['data']['trend'], 'average') === [null, null, null, null, null, null, null, 61]);

/* Row: no history — average null, all points null */
echo "No history\n";
$r = dashboard_with($admin, $connected_pro, []);
check('average_score null', $r['data']['average_score'] === null);
check('8 points, all null', count($r['data']['trend']) === 8 && array_unique(array_column($r['data']['trend'], 'average')) === [null]);

/* Row: not connected — connected false; can_manage_settings mirrors the cap */
echo "Not connected\n";
$r = dashboard_with($admin, ['n8n_webhook_url' => ''] + $connected_pro, $one_scored);
check('empty webhook URL → connected false', $r['data']['connected'] === false);
check('admin → can_manage_settings true', $r['data']['can_manage_settings'] === true);
$r = dashboard_with(['pqm_view_reviews', 'pqm_run_free_agents'], ['n8n_webhook_url' => ''], $one_scored);
check('author → can_manage_settings false', $r['data']['can_manage_settings'] === false);
$r = dashboard_with($admin, $connected_pro, $one_scored);
check('webhook URL set → connected true', $r['data']['connected'] === true);

/* Row: cron nudge — pro, admin, cron off, ≥1 analyzed, not dismissed */
echo "Cron nudge\n";
$r = dashboard_with($admin, $connected_pro, $one_scored);
$url = $r['data']['cron_nudge_dismiss_url'] ?? '';
check('all conditions met → cron_nudge true', $r['data']['cron_nudge'] === true);
check('dismiss URL targets the Dashboard and carries a nonce for postquality_dismiss_cron_nudge',
    strpos($url, '/wp-admin/admin.php?page=postquality-dashboard') === 0
    && strpos($url, 'postquality_dismiss_cron_nudge=1') !== false
    && strpos($url, '_wpnonce=nonce-postquality_dismiss_cron_nudge') !== false);
check('dismiss URL is raw (no &amp;) — it travels as JSON into an href', strpos($url, '&amp;') === false);
/* Ticket 16: the nudge follows pqm_manage_agents, the cap of the section it links to */
$r = dashboard_with(['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents'], $connected_pro, $one_scored);
check('editor (pqm_manage_agents, no pqm_manage_settings) on pro → cron_nudge true + dismiss url',
    $r['data']['cron_nudge'] === true && isset($r['data']['cron_nudge_dismiss_url']));
check('…while can_manage_settings stays the settings cap (false)', $r['data']['can_manage_settings'] === false);
$src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php');
check('dismiss handler persists only for pqm_manage_agents (static)',
    (bool) preg_match("/'postquality_dismiss_cron_nudge'\\)\\s*&& current_user_can\\('pqm_manage_agents'\\)/", $src));
$cases = [
    'free plan'            => dashboard_with($admin, $connected_pro, $one_scored, false, false),
    'settings-only role (cannot see the cron section)' => dashboard_with(['pqm_view_reviews', 'pqm_manage_settings'], $connected_pro, $one_scored),
    'author (no pqm_manage_agents)' => dashboard_with(['pqm_view_reviews', 'pqm_run_free_agents'], $connected_pro, $one_scored),
    'cron already on'      => dashboard_with($admin, ['cron_enabled' => true] + $connected_pro, $one_scored),
    'nothing analyzed'     => dashboard_with($admin, $connected_pro, []),
    'only a failed latest' => dashboard_with($admin, $connected_pro, [['post_id' => '1', 'overall_score' => '0', 'status' => 'error', 'reviewed_at' => ago(0, 1)]]),
    'dismissed'            => dashboard_with($admin, $connected_pro, $one_scored, true),
];
foreach ($cases as $label => $r) {
    check("$label → cron_nudge false, no dismiss url",
        $r['data']['cron_nudge'] === false && !array_key_exists('cron_nudge_dismiss_url', $r['data']));
}
$GLOBALS['tw']['options'] = site_options();
postquality_fs()->pro = false;

/* ─── Feedback card (story 79) ───────────────────────────────────────── */

/* Row: GET /dashboard carries `feedback` — the viewer's flag and the five environment lines */
echo "Feedback card: the feedback key\n";
$r  = dashboard_with($admin, $connected_pro, $one_scored);
$fb = $r['data']['feedback'] ?? null;
check('feedback is the last key: {dismissed, environment}',
    array_key_last($r['data']) === 'feedback' && is_array($fb) && array_keys($fb) === ['dismissed', 'environment']);
check('not dismissed by default', $fb['dismissed'] === false);
check('environment: plugin + build (Pro here), WordPress, PHP, workflow not seen yet, theme — five lines, nothing else',
    $fb['environment'] === [
        'Post Quality Manager ' . POSTQUALITY_VERSION . ' (Pro)',
        'WordPress 6.8',
        'PHP ' . PHP_VERSION,
        'Workflow version: not seen yet',
        'Theme: Twenty Twenty-Five',
    ]);
$GLOBALS['tw']['options']['pqm_workflow_version_seen'] = ['version' => 7, 'via' => 'test', 'at' => 1760000000];
fresh_wpdb([$one_scored], []);
$env = PostQuality_REST_API::dashboard_callback()->get_data()['feedback']['environment'];
check('a workflow seen → "Workflow version 7"', $env[3] === 'Workflow version 7');
check('no URL, host, email, token or setting in any line', !preg_grep('~https?:|example\.com|@|webhook|token|key~i', $env));
// A failure alone, as postquality_record_workflow_failure() stores it on an empty report: no version, no time.
$GLOBALS['tw']['options']['pqm_workflow_version_seen'] = ['failed' => ['message' => 'n8n responded with 404.', 'at' => 1760000000]];
fresh_wpdb([$one_scored], []);
check('a failed test, no version → "not seen yet"',
    PostQuality_REST_API::dashboard_callback()->get_data()['feedback']['environment'][3] === 'Workflow version: not seen yet');
// Version 0 with a time: a v1–v4 workflow answered without its number (the n8n Workflow page's "older version").
$GLOBALS['tw']['options']['pqm_workflow_version_seen'] = ['version' => 0, 'via' => 'test', 'at' => 1760000000];
fresh_wpdb([$one_scored], []);
check('an older workflow that answered without its number → said so, never "not seen yet"',
    PostQuality_REST_API::dashboard_callback()->get_data()['feedback']['environment'][3] === 'Workflow version: an older one that does not report its number');
$GLOBALS['tw']['options'] = site_options(['pqm_settings' => $connected_pro]);

/* Row: Other user — the flag is per user */
echo "Feedback card: per-user dismissal\n";
$GLOBALS['tw']['user_meta'] = [42 => ['pqm_feedback_dismissed' => 1]];
fresh_wpdb([$one_scored], []);
check('user 42 dismissed → dismissed true', PostQuality_REST_API::dashboard_callback()->get_data()['feedback']['dismissed'] === true);
$GLOBALS['tw']['user_id'] = 43;
fresh_wpdb([$one_scored], []);
check('user 43 still sees the card → dismissed false', PostQuality_REST_API::dashboard_callback()->get_data()['feedback']['dismissed'] === false);
$GLOBALS['tw']['user_id'] = 42;

/* Row: POST /dashboard/feedback — registration, the one boolean arg, true stores, false deletes, the gate */
echo "Feedback card: POST /dashboard/feedback\n";
$route = $routes[$ns . '/dashboard/feedback'] ?? [];
check('registered as POST, gated by check_view_reviews (the Dashboard\'s own gate)',
    ($route['methods'] ?? '') === WP_REST_Server::CREATABLE
    && ($route['permission_callback'] ?? null) === ['PostQuality_REST_API', 'check_view_reviews']);
check('dismissed: a required boolean coerced by the schema type, no sanitize_callback (never intval, lessons 2026-10-05)',
    ($route['args']['dismissed'] ?? null) === ['required' => true, 'type' => 'boolean']);
$GLOBALS['tw']['user_meta'] = [];
$res = PostQuality_REST_API::dashboard_feedback_callback(new WP_REST_Request(['dismissed' => true]));
check('true → 200 {dismissed: true}, the meta set for user 42 only',
    $res instanceof WP_REST_Response && $res->get_status() === 200 && $res->get_data() === ['dismissed' => true]
    && $GLOBALS['tw']['user_meta'] === [42 => ['pqm_feedback_dismissed' => 1]]);
$res = PostQuality_REST_API::dashboard_feedback_callback(new WP_REST_Request(['dismissed' => false]));
check('false → 200 {dismissed: false}, the meta deleted',
    $res->get_status() === 200 && $res->get_data() === ['dismissed' => false]
    && !isset($GLOBALS['tw']['user_meta'][42]['pqm_feedback_dismissed']));
$GLOBALS['tw']['caps'] = ['edit_posts'];
check('without pqm_view_reviews the gate refuses (WP answers 403)', PostQuality_REST_API::check_view_reviews() === false);
$GLOBALS['tw']['caps'] = [];
$GLOBALS['tw']['user_meta'] = [];
$GLOBALS['tw']['options'] = site_options();

/* Row: Uninstall — the meta goes for every user */
$activator = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-activator.php');
check('uninstall deletes pqm_feedback_dismissed for all users (static)',
    str_contains($activator, "delete_metadata( 'user', 0, 'pqm_feedback_dismissed', '', true );"));

/* ─── Yoast column and Pending marker (story 5.10) ───────────────────── */

/** One list request over $rows (no commands, nothing in flight); returns the response data. */
function list_rows(array $rows): array {
    fresh_wpdb([$rows, [], [], []], [count($rows)]);
    $GLOBALS['tw']['meta_reads']  = 0;
    $GLOBALS['tw']['meta_primes'] = [];
    return PostQuality_REST_API::list_posts_callback(new WP_REST_Request())->get_data();
}

/** A never-reviewed DB row with the given WordPress post status. */
function post_row(string $id, ?string $wp_status): array {
    return ['post_id' => $id, 'title' => "Post $id", 'slug' => "p$id", 'post_type' => 'post', 'wp_post_status' => $wp_status,
            'latest_score' => null, 'latest_status' => null, 'latest_reviewed' => null, 'latest_command' => null];
}

$GLOBALS['tw']['caps'] = ['pqm_view_reviews'];
$GLOBALS['tw']['post_meta'] = [
    11 => ['_yoast_wpseo_linkdex' => '72', '_yoast_wpseo_content_score' => '45'], // scored
    12 => ['_yoast_wpseo_linkdex' => '',   '_yoast_wpseo_content_score' => ''],   // unanalyzed
    13 => ['_yoast_wpseo_linkdex' => '80', '_yoast_wpseo_content_score' => ''],   // one score missing
];
$rows = [post_row('11', 'publish'), post_row('12', 'pending'), post_row('13', 'publish')];

/* Row: Yoast inactive — field null, flag false, no meta read, no cache prime */
echo "Yoast inactive\n";
$data = list_rows($rows);
check('yoast_active false', $data['yoast_active'] === false);
check('every item: yoast null', array_column($data['items'], 'yoast') === [null, null, null]);
check('no post meta read, no cache prime', $GLOBALS['tw']['meta_reads'] === 0 && $GLOBALS['tw']['meta_primes'] === []);

/* Row: pending / published post — wp_post_status passes through */
echo "Pending marker field\n";
check('wp_post_status passes through (publish, pending, publish)',
    array_column($data['items'], 'wp_post_status') === ['publish', 'pending', 'publish']);
$data = list_rows([post_row('14', null)]);
check('missing wp_post_status reads as an empty string', $data['items'][0]['wp_post_status'] === '');

/* Row: Yoast active — scored, unanalyzed, one missing; one cache prime */
define('WPSEO_VERSION', '99.0');
echo "Yoast active\n";
$data = list_rows($rows);
check('yoast_active true, still the last top-level key',
    $data['yoast_active'] === true && array_key_last($data) === 'yoast_active');
check('scored post → {seo: 72, readability: 45}', $data['items'][0]['yoast'] === ['seo' => 72, 'readability' => 45]);
check("unanalyzed post ('' metas) → {seo: null, readability: null}", $data['items'][1]['yoast'] === ['seo' => null, 'readability' => null]);
check('one score missing → {seo: 80, readability: null}', $data['items'][2]['yoast'] === ['seo' => 80, 'readability' => null]);
check("cache primed once with the page's ids", $GLOBALS['tw']['meta_primes'] === [[11, 12, 13]]);
check('pending passes through with Yoast on too', $data['items'][1]['wp_post_status'] === 'pending');

/* Row: Yoast active, empty list — layout input only, no cache prime */
echo "Yoast active, empty list\n";
$data = list_rows([]);
check('items [] with yoast_active true, no cache prime',
    $data['items'] === [] && $data['yoast_active'] === true && $GLOBALS['tw']['meta_primes'] === []);
$GLOBALS['tw']['post_meta'] = [];

/* ─── Ticket 3: cron selection per command — PostQuality_DB::get_posts_needing_review()
       prepares the command as %s in the latest-row subquery and the r1 join ── */
echo "Cron selection per command (ticket 3)\n";
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);
foreach (['audit' => [70, 14, 4, 'audit'], 'review (default)' => [70, 14, 4]] as $what => $call) {
    $wpdb = fresh_wpdb([], []);
    PostQuality_DB::get_posts_needing_review(...$call);
    $cmd = $call[3] ?? 'review';
    $p   = prepared_with($wpdb, 'MAX(reviewed_at)');
    $sql = preg_replace('/\s+/', ' ', $p['sql'] ?? '');
    check("$what: no hard-coded command literal", $p !== null && strpos($sql, "command = '") === false);
    check("$what: command is %s in the subquery and the r1 join",
        strpos($sql, 'WHERE command = %s GROUP BY post_id') !== false
        && strpos($sql, 'r1.reviewed_at = r2.max_date AND r1.command = %s') !== false);
    check("$what: args in placeholder order (command, command, min_score, cutoff, limit)",
        count($p['args']) === 5 && $p['args'][0] === $cmd && $p['args'][1] === $cmd
        && $p['args'][2] === 70 && $p['args'][4] === 4
        && $p['args'][3] === gmdate('Y-m-d H:i:s', strtotime($p['args'][3])) && strtotime($p['args'][3]) < time() - 13 * 86400);
    check("$what: placeholder count matches args", substr_count($p['sql'], '%') === count($p['args']));
}

harness_exit();
