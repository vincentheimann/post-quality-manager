<?php
/**
 * Free build harness — story 54 (ADR 0014). Runs bin/build-free.php into a
 * temporary folder, then checks the stripped tree the way a directory
 * reviewer would read it: no premium marker or listed file left, every PHP
 * file still parses, `is_premium` false, and — booted with WP stubs — the
 * registry, the REST enums, the setup state, the dashboard and the Settings
 * and n8n Workflow screens hold nothing paid. No paid command key may be
 * quoted in its PHP. The negative self-test injects each of Freemius's leak
 * patterns into a copy of the source and expects the strip to fail on it.
 * Run: php free-build-harness.php
 */

error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');

$root    = dirname(__DIR__, 2);
$builder = $root . '/bin/build-free.php';
$tmp     = sys_get_temp_dir() . '/pqm-free-build-' . getmypid() . '-' . bin2hex(random_bytes(4));
$free    = $tmp . '/out/post-quality-manager';
const PAID_COMMANDS = ['rewrite', 'sources', 'seo', 'audit'];

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "FAIL  $label\n"; }
}

/** Runs the builder; [exit code, stdout + stderr]. */
function build(string $out, ?string $src = null): array {
    global $builder;
    $cmd = [PHP_BINARY, $builder, $out];
    if ($src !== null) { $cmd[] = $src; }
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($proc), $output];
}

/** Every file under $dir as relative path => absolute path. */
function files_in(string $dir): array {
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        $files[ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($dir)), '/')] = $f->getPathname();
    }
    return $files;
}

/** Copy of the plugin source without vendor/ and admin/build/ (the leak tests need only our code). */
function copy_source(string $to): void {
    global $root;
    foreach (files_in($root . '/post-quality-manager') as $rel => $path) {
        if (str_starts_with($rel, 'vendor/') || str_starts_with($rel, 'admin/build/')) { continue; }
        @mkdir(dirname("$to/$rel"), 0777, true);
        copy($path, "$to/$rel");
    }
}

/** Removes the temporary folder this harness created (that exact path only). */
function remove_tmp(string $dir): void {
    if (!is_dir($dir)) { return; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}
register_shutdown_function('remove_tmp', $tmp);

/* ─── Row: Local strip ───────────────────────────────────────────────── */
echo "Row — php bin/build-free.php <out> builds a clean Free tree\n";
[$code, $output] = build($tmp . '/out');
check('exit 0' . ($code ? " — $output" : ''), $code === 0);
$tree = is_dir($free) ? files_in($free) : [];
$ours = array_filter($tree, static fn($rel) => !str_starts_with($rel, 'vendor/'), ARRAY_FILTER_USE_KEY);
check('the plugin tree is there, SDK included', isset($tree['post-quality-manager.php'], $tree['vendor/freemius/start.php']));
check('no __premium_only left in any file outside vendor/',
    !array_filter($ours, static fn($path) => str_contains((string) file_get_contents($path), '__premium_only')));
check('readme.txt is in the Free build, with freemius added to its Contributors (as Freemius writes it)',
    isset($tree['readme.txt']) && preg_match('/^Contributors: postquality, freemius$/m', (string) file_get_contents($tree['readme.txt'])));
check("the SDK's README.md and composer.json are left out (as Freemius does)",
    !isset($tree['vendor/freemius/README.md'], $tree['vendor/freemius/composer.json']) && isset($tree['vendor/freemius/LICENSE.txt']));
check('the @fs_premium_only files are absent (cron class, Pro template)',
    !isset($tree['includes/class-pqm-cron.php']) && !isset($tree['templates/n8n-workflow.json']));
check("'is_premium' is false", (bool) preg_match("/'is_premium'\\s*=>\\s*false/", (string) @file_get_contents($free . '/post-quality-manager.php')));
$broken = [];
foreach ($ours as $rel => $path) {
    if (str_ends_with($rel, '.php')) {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lint, $lint_code);
        if ($lint_code !== 0) { $broken[] = $rel; }
    }
}
check('every stripped PHP file parses' . ($broken ? ' — ' . implode(', ', $broken) : ''), $ours && !$broken);

/* ─── Row: no paid command key quoted in the Free PHP ────────────────── */
echo "Row — no paid command key is quoted in the Free build's PHP\n";
// The one paid word that is not a command: GET /posts carries the post's
// Yoast SEO score under 'seo'. Any other occurrence fails.
$allowed = ['includes/class-pqm-rest-api.php' => ['seo' => 1]];
$found = [];
foreach ($ours as $rel => $path) {
    if (!str_ends_with($rel, '.php')) { continue; }
    foreach (token_get_all((string) file_get_contents($path)) as $tok) {
        if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING && in_array(substr($tok[1], 1, -1), PAID_COMMANDS, true)) {
            $found[$rel][substr($tok[1], 1, -1)][] = $tok[2];
        }
    }
}
$unexpected = [];
foreach ($found as $rel => $keys) {
    foreach ($keys as $key => $lines) {
        if (count($lines) !== ($allowed[$rel][$key] ?? 0)) { $unexpected[] = "$rel:'$key' line " . implode(',', $lines); }
    }
}
check('none outside the allowed Yoast field' . ($unexpected ? ' — ' . implode('; ', $unexpected) : ''), !$unexpected);

/* ─── Negative self-test: each leak pattern fails the strip ──────────── */
echo "Row — Freemius's leak patterns turn the strip red, naming the file\n";
$leaks = [
    'unwrapped call of a suffixed method' => ['includes/class-pqm-features.php',
        "    public static function label( string \$command ): string {\n",
        "    public static function label( string \$command ): string {\n        self::paid_registry__premium_only();\n"],
    'inline condition with the premium check' => ['post-quality-manager.php',
        "if ( pqm_fs()->is__premium_only() ) {\n    require_once",
        "if ( is_admin() && pqm_fs()->is__premium_only() ) {\n    require_once"],
    'assignment from a premium method' => ['includes/class-pqm-blocks.php',
        "        \$keys = [ 'takeaways' => 'rewritten_items' ];\n",
        "        \$keys = [ 'takeaways' => 'rewritten_items' ];\n        \$premium = pqm_fs()->is__premium_only();\n"],
    'an else after a premium block' => ['includes/class-pqm-features.php',
        "            \$registry += self::paid_registry__premium_only();\n        }\n",
        "            \$registry += self::paid_registry__premium_only();\n        } else {\n            \$registry += [];\n        }\n"],
];
$n = 0;
foreach ($leaks as $label => [$file, $search, $replace]) {
    $src = $tmp . '/leak-' . ++$n . '/src';
    copy_source($src);
    $code_src = (string) file_get_contents("$src/$file");
    $injected = substr_count($code_src, $search) === 1;
    file_put_contents("$src/$file", str_replace($search, $replace, $code_src));
    [$code, $output] = build($tmp . '/leak-' . $n . '/out', $src);
    check("$label: injected, exit 1, $file named", $injected && $code === 1 && str_contains($output, $file . ':'));
}
copy_source($tmp . '/leak-0/src');
[$code] = build($tmp . '/leak-0/out', $tmp . '/leak-0/src');
check('control: the same copy without an injection builds (exit 0)', $code === 0);

/* ─── Row: bin/compare-php-trees.php reads code, not formatting ──────── */
echo "Row — compare-php-trees: Freemius's re-printing passes, a changed token fails naming the file\n";
$comparer = $root . '/bin/compare-php-trees.php';
function compare(string $a, string $b): array {
    global $comparer;
    $proc = proc_open([PHP_BINARY, $comparer, $a, $b], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($proc), $output];
}
[$code, $output] = compare($free, $free);
check('the Free tree against itself: exit 0, every file counted', $code === 0 && str_contains($output, 'PHP identical: ' . count(array_filter(array_keys($ours), static fn($r) => str_ends_with($r, '.php')))));
// The same code written the two ways Freemius's output and the source differ.
$pairs = [
    "<?php\n// a comment\nif ( \$a ) {\n    foreach ( \$b as \$c ) {\n        echo \$c ?? 'x\\\\y\\'z';\n    }\n} else {\n    \$z = [ 'k' => 1, 'm' => array_filter( \$b, static fn( \$v ) => \$v > 1 ) ];\n}\nreturn \$z;\n",
    "<?php\nif (\$a):\n    foreach (\$b as \$c):\n        echo ( \$c ?? \"x\\\\y'z\" );\n    endforeach;\nelse:\n    \$z = [\n        'k' => 1,\n        'm' => array_filter(\n            \$b,\n            static fn(\$v) => (\$v > 1)\n        ),\n    ];\nendif;\nreturn \$z;",
];
foreach (['a', 'b'] as $k => $side) {
    @mkdir("$tmp/cmp-$side/includes", 0777, true);
    file_put_contents("$tmp/cmp-$side/includes/sample.php", $pairs[$k]);
    file_put_contents("$tmp/cmp-$side/second.php", "<?php\nreturn 2;\n");
}
[$code, $output] = compare("$tmp/cmp-a", "$tmp/cmp-b");
check('braces vs alternative syntax, parentheses, trailing comma, comments, string escaping: identical', $code === 0 && str_contains($output, 'PHP identical: 2 files'));
file_put_contents("$tmp/cmp-b/includes/sample.php", str_replace("'m' => array_filter", "'n' => array_filter", $pairs[1]));
[$code, $output] = compare("$tmp/cmp-a", "$tmp/cmp-b");
check('one changed key: exit 1, the file and both lines named', $code === 1 && preg_match('/^includes\/sample\.php: differs from token \d+\n.*cmp-a line 8: .*\n.*cmp-b line 9: /m', $output)
    && str_contains($output, '1 PHP difference(s).'));
@mkdir("$tmp/cmp-b/vendor", 0777, true);
file_put_contents("$tmp/cmp-b/vendor/ignored.php", "<?php\nreturn 1;\n");
file_put_contents("$tmp/cmp-b/includes/sample.php", $pairs[1]);
file_put_contents("$tmp/cmp-b/extra.php", "<?php\n");
[$code, $output] = compare("$tmp/cmp-a", "$tmp/cmp-b");
check('a file on one side only: exit 1 naming it; vendor/ ignored', $code === 1 && str_contains($output, "extra.php: only in") && !str_contains($output, 'ignored.php'));

/* ─── Boot the stripped tree (WP stubs, no SDK) ──────────────────────── */
$GLOBALS['tw'] = ['caps' => [], 'options' => []];
$GLOBALS['pqm_routes'] = [];
function add_action(...$a) {}
function add_filter(...$a) {}
function register_activation_hook(...$a) {}
function register_deactivation_hook(...$a) {}
function register_setting(...$a) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return '/wp-content/plugins/post-quality-manager/'; }
function plugin_basename($f) { return basename($f); }
function load_plugin_textdomain(...$a) {}
function is_admin() { return true; }
function wp_generate_uuid4() { return 'stub-uuid'; }
function get_option($k, $d = false) { return $GLOBALS['tw']['options'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['tw']['options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['tw']['options'][$k]); return true; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function current_user_can($cap) { return in_array($cap, $GLOBALS['tw']['caps'], true); }
function apply_filters($tag, $value) { return $value; }
function __($s, $d = null) { return $s; }
function _e($s, $d = null) { echo $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_html_e($s, $d = null) { echo $s; }
function esc_attr__($s, $d = null) { return $s; }
function esc_attr_e($s, $d = null) { echo $s; }
function esc_html($s) { return (string) $s; }
function esc_attr($s) { return (string) $s; }
function esc_url($s) { return (string) $s; }
function esc_url_raw($s) { return (string) $s; }
function esc_textarea($s) { return (string) $s; }
function wp_kses($s, $allowed_html, $allowed_protocols = []) { return (string) $s; }
function wp_print_inline_script_tag($data, $attributes = []) { echo "<script>$data</script>"; }
function settings_fields($g) {}
function settings_errors(...$a) {}
function submit_button() { echo '<input type="submit" />'; }
function checked($a, $b = true) { if ((string) $a === (string) $b) { echo ' checked'; } }
function selected($a, $b = true) { if ((string) $a === (string) $b) { echo ' selected'; } }
function disabled($a, $b = true) { if ($a == $b) { echo ' disabled'; } }
function sanitize_key($s) { return strtolower((string) $s); }
function sanitize_text_field($s) { return (string) $s; }
function sanitize_textarea_field($s) { return (string) $s; }
function absint($n) { return abs((int) $n); }
function get_locale() { return 'en_US'; }
function switch_to_locale($l) { return false; }
function restore_previous_locale() { return false; }
function home_url($p = '') { return 'https://example.com' . $p; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function rest_url($p = '') { return 'https://example.com/wp-json/' . $p; }
function admin_url($p = '') { return '/wp-admin/' . $p; }
function wp_create_nonce($a = '') { return 'nonce'; }
function wp_nonce_url($url, $action = -1) { return $url . '&_wpnonce=nonce'; }
function add_query_arg($args, $url = '') { return $url . '?' . http_build_query((array) $args); }
function register_rest_route($ns, $route, $args) { $GLOBALS['pqm_routes'][$route] = $args; }
function wp_get_current_user() { return (object) ['ID' => 1, 'user_login' => 'admin']; }
function wp_is_application_passwords_available_for_user($u) { return true; }
function wp_remote_post($url, $args) { return ['response' => ['code' => 200], 'body' => $GLOBALS['tw']['ping_body']]; }
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function is_wp_error($thing) { return $thing instanceof WP_Error; }
define('MINUTE_IN_SECONDS', 60);
define('ARRAY_A', 'ARRAY_A');
define('WEEK_IN_SECONDS', 604800);
function human_time_diff($from, $to = 0) { return 'a while'; }
function get_date_from_gmt($gmt, $format = '') { return $gmt; }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; const EDITABLE = 'POST, PUT, PATCH'; }
class WP_Error {
    public function __construct(public $code = '', public $message = '', public $data = null) {}
}
class WP_REST_Request {
    public function __construct(private $json = null) {}
    public function get_json_params() { return $this->json; }
}
class WP_REST_Response {
    public function __construct(public $data = null, public $status = 200) {}
    public function get_data() { return $this->data; }
}
$wpdb = new class {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql, $output = null) { return []; }
    public function get_var($sql) { return null; }
};

require __DIR__ . '/fs-stub.php'; // pqm_fs() without the SDK; no premium check is left to read it
if (!is_file($free . '/post-quality-manager.php')) {
    echo "\n$pass passed, " . ($fail + 1) . " failed (no Free tree to boot)\n";
    exit(1);
}
require $free . '/post-quality-manager.php';

/* ─── Row: Free build registry and defaults ──────────────────────────── */
echo "Row — the Free build's registry, defaults and insert keys are free only\n";
$registry = PQM_Features::registry();
check('registry = review, takeaways, score, apply', array_keys($registry) === ['review', 'takeaways', 'score', 'apply']);
check('every entry is tier free', array_unique(array_column($registry, 'tier')) === ['free']);
check('agent defaults = review, takeaways', array_keys(pqm_default_settings()['agent_prompts']) === ['review', 'takeaways']);
check('output budgets = review, takeaways', array_keys(pqm_agent_max_tokens()) === ['review', 'takeaways']);
check('Insert into Post keys = takeaways', array_keys(PQM_Blocks::item_keys()) === ['takeaways']);
check('no cron class is loaded, no cron hook registered (a stale schedule runs nothing)', !class_exists('PQM_Cron', false)
    && !str_contains((string) file_get_contents($free . '/post-quality-manager.php'), 'PQM_Cron'));
$GLOBALS['tw']['caps'] = ['pqm_run_free_agents'];
check('Post Reviews viewer: nothing locked, nothing paid hidden', PQM_Features::gated_commands() === []
    && !array_intersect(PQM_Features::hidden_commands(), PAID_COMMANDS));

/* ─── Row: REST enums come from the free registry ────────────────────── */
echo "Row — paid commands are refused by the route schema (400), never reach the gate\n";
PQM_REST_API::register_routes();
$enum = static fn(string $route) => $GLOBALS['pqm_routes'][$route]['args']['command']['enum'] ?? null;
check('trigger-review command enum = the free registry', $enum('/trigger-review') === array_keys($registry));
check('save-review command enum = the free registry', $enum('/save-review') === array_keys($registry));
check('insert-block command enum = takeaways', $enum('/insert-block') === ['takeaways']);
check('the n8n connection routes are there, as on Pro (provisioning is free): GET, POST, DELETE, each gated by check_manage_settings',
    array_column($GLOBALS['pqm_routes']['/n8n/connection'] ?? [], 'methods') === ['GET', 'POST', 'DELETE']
    && array_unique(array_column($GLOBALS['pqm_routes']['/n8n/connection'], 'permission_callback'), SORT_REGULAR) === [['PQM_REST_API', 'check_manage_settings']]);

/* ─── Row: setup dialog and dashboard ────────────────────────────────── */
echo "Row — setup state and dashboard carry no schedule\n";
$GLOBALS['tw']['caps'] = ['pqm_manage_settings', 'pqm_manage_agents'];
$state = PQM_REST_API::get_setup_callback()->get_data();
check('setup state has no cron_* key', !preg_grep('/^cron_/', array_keys($state)));
check("setup state names the build: 'free'", ($state['build'] ?? null) === 'free');
$refused = PQM_REST_API::setup_settings_callback(new WP_REST_Request(['cron_enabled' => true]));
check('the setup guide cannot switch scheduled reviews on (400, unknown setting)', $refused instanceof WP_Error
    && $refused->code === 'pqm_setup_bad_setting' && ($refused->data['status'] ?? null) === 400);
$dash = PQM_REST_API::dashboard_callback()->get_data();
check('dashboard has no cron_nudge key', !array_key_exists('cron_nudge', $dash) && !array_key_exists('cron_nudge_dismiss_url', $dash));

/* ─── Row: the handshake on the Free build ───────────────────────────── */
echo "Row — the Free build takes either template variant as current\n";
$free_notice = 'Your workflow is the Free variant';
foreach (['Free template' => ',"workflow_variant":"free"', 'Pro template' => ''] as $label => $variant_json) {
    $GLOBALS['tw']['ping_body'] = '{"ok":true,"workflow_version":' . PQM_WORKFLOW_VERSION . $variant_json . '}';
    $ping = PQM_Admin::ping_webhook('https://n8n.example/webhook/pqm-execute');
    pqm_record_workflow_version($ping['version'], 'test', $ping['variant']);
    check("$label answers: up to date, no variant notice", $ping['ok'] === true && $ping['outdated'] === false
        && !str_contains(PQM_Admin::render_workflow_status(), $free_notice));
}
check('pqm_workflow_json() serves the Free template, filled with the host',
    ($json = json_decode((string) pqm_workflow_json(), true)) !== null && $json['name'] === 'Post Quality — example.com'
    && !in_array('Build Rewrite Body', array_column($json['nodes'], 'name'), true));
$free_trigger = array_column($json['nodes'], null, 'name')['Webhook Trigger']['parameters'];
check('the Free template carries the per-site trigger path and the version, no __PQM_ placeholder left',
    $free_trigger['path'] === 'pqm-execute-example-com' && !str_contains((string) pqm_workflow_json(), '__PQM_')
    && json_decode($free_trigger['options']['responseData'], true) === ['ok' => true, 'workflow_version' => PQM_WORKFLOW_VERSION, 'workflow_variant' => 'free']);

/* ─── Row: the screens an Administrator opens ────────────────────────── */
echo "Row — Settings and n8n Workflow: nothing locked, one Get Pro, no paid agent named\n";
$GLOBALS['tw']['caps'] = ['manage_options', 'pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents', 'pqm_manage_settings'];
ob_start();
PQM_Admin::render_settings();
$settings_html = ob_get_clean();
preg_match_all('/class="nav-tab(?: nav-tab-active)?">([^<]+)<\/a>/', $settings_html, $m);
check('tabs: Connection, AI, Usage & Limits', $m[1] === ['Connection', 'AI', 'Usage & Limits']);
check('no locked section, no disabled control', !str_contains($settings_html, 'pqm-locked') && !str_contains($settings_html, ' disabled'));
check('"Pro" appears once, in the one Get Pro link', preg_match_all('/\bPro\b/', strip_tags($settings_html)) === 1
    && substr_count($settings_html, '>Get Pro</a>') === 1);
$GLOBALS['tw']['options']['pqm_n8n_api'] = ['base_url' => 'https://n8n.example', 'api_key' => 'free-build-n8n-key-secret', 'probe' => null];
ob_start();
PQM_Admin::render_workflow();
$workflow_html = ob_get_clean();
check('n8n Workflow page asks for two credentials', str_contains($workflow_html, 'connect these two credentials')
    && substr_count($workflow_html, '<li>') === 2);
check('n8n Workflow page carries the n8n Instance section, as on Pro; a stored n8n API key is not in its source',
    str_contains($workflow_html, '<h2>n8n Instance</h2>')
    && (str_contains($workflow_html, 'id="pqm-n8n-connection-root"') || !is_file($free . '/admin/build/index.asset.php'))
    && !str_contains($workflow_html, 'free-build-n8n-key-secret'));
check('request preview lists Review and Key Takeaways only', preg_match_all('/<td>([^<]+ Agent)<\/td>/', $workflow_html, $agents) === 2
    && $agents[1] === ['Review Agent', 'Key Takeaways Agent']);
$visible = strip_tags($settings_html . $workflow_html);
check('no paid agent is named on either screen', !preg_match('/\b(Rewrite|Sources|SEO|Audit)\b/', $visible));

/* ─── Row: Settings access and the License section per plan state ────── */
echo "Row — Settings needs pqm_manage_settings (or core manage_options); a license on the Free build points to the Pro download\n";
$GLOBALS['tw']['caps'] = ['pqm_manage_agents'];
check('pqm_manage_agents alone: no Settings page (core manage_options)', PQM_Admin::settings_page_capability() === 'manage_options');
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
check('pqm_manage_settings: the Settings page', PQM_Admin::settings_page_capability() === 'pqm_manage_settings');
pqm_fs()->pro = true;
ob_start();
PQM_Admin::render_settings();
$licensed_html = ob_get_clean();
pqm_fs()->pro = false;
check('license active on the Free build: "install the Pro version" with the Account link, no plan-Pro line, no Get Pro',
    str_contains($licensed_html, 'Your Pro license is active. Install the Pro version to use it: <a href="' . pqm_fs()->get_account_url() . '">')
    && !str_contains($licensed_html, 'Current plan: Pro') && !str_contains($licensed_html, 'Get Pro'));

/* ─── Row: both builds installed (Freemius dual activation) ──────────── */
echo "Row — the other build's main file loaded beside a live SDK: set_basename, nothing redeclared\n";
$probe = $tmp . '/dual-activation-probe.php';
file_put_contents($probe, <<<'PHP'
<?php
// A live SDK of the build loaded first (is_premium from argv[2]); then the other build's main file (argv[1]).
define('ABSPATH', __DIR__ . '/');
class Freemius {
    public $calls = [];
    public function __construct(public bool $premium) {}
    public function is_premium() { return $this->premium; }
    public function set_basename($is_premium, $file) { $this->calls[] = [$is_premium, basename($file)]; }
}
function pqm_fs() { static $fs; return $fs ??= new Freemius($GLOBALS['argv'][2] === '1'); }
$r = require $GLOBALS['argv'][1];
echo json_encode(['calls' => pqm_fs()->calls, 'functions' => function_exists('pqm_default_settings'), 'returned' => $r]);
PHP);
foreach ([
    'Free active, the Pro file loads'  => [$root . '/post-quality-manager/post-quality-manager.php', '0', true],
    'Pro active, the Free file loads'  => [$free . '/post-quality-manager.php', '1', false],
] as $label => [$file, $loaded_is_premium, $want_premium]) {
    $proc = proc_open([PHP_BINARY, $probe, $file, $loaded_is_premium], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = json_decode(stream_get_contents($pipes[1]), true);
    $err = stream_get_contents($pipes[2]);
    proc_close($proc);
    check("$label: set_basename(" . var_export($want_premium, true) . ", its file), no function declared, no output" . ($err ? " — $err" : ''),
        is_array($out) && $out['calls'] === [[$want_premium, 'post-quality-manager.php']] && $out['functions'] === false && $err === '');
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
