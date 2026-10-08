<?php
/**
 * CLI harness for story 34 ("View as") — WP stubs, no WP install.
 * Covers every row of the plan's I/O & Edge-Case Matrix
 * (story-admin-views-plugin-as-lower-role-plan.md): the user_has_cap
 * filter only ever removes pqm_* caps, the toolbar nodes, the switch
 * handler (nonce, 403, redirects, bad input = Exit) and the uninstall line.
 *
 * current_user_can() models core's WP_User::has_cap(): the user's caps
 * (roles merged with direct grants) run through the filter under test.
 */

error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');

/* ─── WP stubs ───────────────────────────────────────────────────────── */
class WP_Role {
    public $name;
    public $capabilities = [];
    public function __construct($name, $capabilities = []) {
        $this->name = $name;
        $this->capabilities = $capabilities;
    }
    public function add_cap($cap, $grant = true) { $this->capabilities[$cap] = $grant; }
    public function remove_cap($cap) { unset($this->capabilities[$cap]); }
    public function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}
class WP_User {
    public $ID;
    public $roles;
    public function __construct($id, $roles) { $this->ID = $id; $this->roles = $roles; }
}
class WP_Admin_Bar {
    public $nodes = [];
    public function add_node($args) { $this->nodes[$args['id']] = $args; }
}
class PQM_Test_Die extends Exception {}
class PQM_Test_Redirect extends Exception {}

function get_role($name) { return $GLOBALS['tw']['roles'][$name] ?? null; }
function wp_roles() {
    $names = [];
    foreach ($GLOBALS['tw']['roles'] as $key => $role) { $names[$key] = ucfirst($key); }
    return (object) ['role_objects' => $GLOBALS['tw']['roles'], 'role_names' => $names];
}
function translate_user_role($name) { return "[$name]"; } // marks the translated label
function get_current_user_id() { return $GLOBALS['tw']['user']->ID; }
function get_user_meta($user_id, $key = '', $single = false) { return $GLOBALS['tw']['meta'][$user_id][$key] ?? ''; }
function update_user_meta($user_id, $key, $value, $prev = '') { $GLOBALS['tw']['meta'][$user_id][$key] = $value; return true; }
function delete_user_meta($user_id, $key, $value = '') { unset($GLOBALS['tw']['meta'][$user_id][$key]); return true; }
/** The user's own caps before any filter: each role's caps merged. */
function base_caps($user) {
    $caps = [];
    foreach ($user->roles as $role_name) {
        $role = get_role($role_name);
        if ($role) { $caps = array_merge($caps, $role->capabilities); }
    }
    return $caps;
}
function caps_of($user) { return PQM_View_As::filter_caps(base_caps($user), [], [], $user); }
function current_user_can($cap) {
    $user = $GLOBALS['tw']['user'];
    $allcaps = PQM_View_As::filter_caps(base_caps($user), [$cap], [$cap, $user->ID], $user);
    return !empty($allcaps[$cap]);
}
function __($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_html__($s, $d = null) { return esc_html($s); }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_url($url, $action = -1) { return $url . '&_wpnonce=nonce-' . $action; }
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function wp_unslash($v) { return $v; }
function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
    if (($_REQUEST[$query_arg] ?? '') !== 'nonce-' . $action) {
        throw new PQM_Test_Die('check_admin_referer');
    }
    return 1;
}
function wp_die($message = '', $title = '', $args = []) {
    $GLOBALS['tw']['die_status'] = $args['response'] ?? 500;
    throw new PQM_Test_Die((string) $message);
}
/* Throws so the handler's exit is never reached. */
function wp_safe_redirect($location, $status = 302) { throw new PQM_Test_Redirect($location); }
function wp_get_referer() { return $GLOBALS['tw']['referer']; }

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-features.php';
require $base . '/includes/class-pqm-view-as.php';

/* ─── Tiny assert runner ─────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

$pqm_caps = array_keys(PQM_Features::capabilities());

/** Default site: the four core roles with the shipped pqm map, plus core caps. */
function reset_world($user_roles = ['administrator'], $view = null) {
    $GLOBALS['tw'] = ['roles' => [], 'meta' => [], 'referer' => false, 'die_status' => null];
    $core = [
        'administrator' => ['manage_options' => true, 'edit_others_posts' => true, 'edit_posts' => true],
        'editor'        => ['edit_others_posts' => true, 'edit_posts' => true],
        'author'        => ['edit_posts' => true],
        'contributor'   => ['edit_posts' => true],
    ];
    foreach ($core as $name => $caps) {
        $GLOBALS['tw']['roles'][$name] = new WP_Role($name, $caps);
    }
    PQM_Features::register_capabilities();
    $GLOBALS['tw']['user'] = new WP_User(1, $user_roles);
    if ($view !== null) {
        $GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY] = $view;
    }
}
/** pqm caps the current user effectively holds. */
function held_pqm_caps() {
    global $pqm_caps;
    return array_values(array_filter($pqm_caps, 'current_user_can'));
}
function toolbar() {
    $bar = new WP_Admin_Bar();
    PQM_View_As::admin_bar($bar);
    return $bar->nodes;
}
function switch_href($role) {
    return 'https://example.test/wp-admin/admin-post.php?action=pqm_view_as&role=' . $role . '&_wpnonce=nonce-pqm_view_as';
}
/** Runs the handler with $query as the request; returns ['redirect' => url] or ['die' => message]. */
function run_switch($query) {
    $_GET = $_REQUEST = $query;
    try {
        PQM_View_As::handle_switch();
        return ['returned' => true];
    } catch (PQM_Test_Redirect $e) {
        return ['redirect' => $e->getMessage()];
    } catch (PQM_Test_Die $e) {
        return ['die' => $e->getMessage()];
    }
}
$nonce = ['_wpnonce' => 'nonce-pqm_view_as'];

/* ─── Row: No view ───────────────────────────────────────────────────── */
echo "No view — caps unchanged; toolbar offers the three roles\n";
reset_world();
$admin = $GLOBALS['tw']['user'];
check('filter returns the caps untouched', caps_of($admin) === base_caps($admin));
check('all five pqm caps held', held_pqm_caps() === $pqm_caps);
$nodes = toolbar();
check('top node "View as", no link', ($nodes['pqm-view-as']['title'] ?? null) === 'View as' && !isset($nodes['pqm-view-as']['href']));
check('top node keyboard-focusable (tabindex 0)', ($nodes['pqm-view-as']['meta']['tabindex'] ?? null) === 0);
check('children: editor, author, contributor, in that order', array_keys($nodes) === ['pqm-view-as', 'pqm-view-as-editor', 'pqm-view-as-author', 'pqm-view-as-contributor']);
check('children hang under pqm-view-as, translated labels, handler links with nonce',
    $nodes['pqm-view-as-author']['parent'] === 'pqm-view-as'
    && $nodes['pqm-view-as-author']['title'] === '[Author]'
    && $nodes['pqm-view-as-author']['href'] === switch_href('author'));
check('no Exit node', !isset($nodes['pqm-view-as-exit']));
reset_world();
unset($GLOBALS['tw']['roles']['editor']);
check('a role missing on the site is not listed', !isset(toolbar()['pqm-view-as-editor']));

/* ─── Row: Author view ───────────────────────────────────────────────── */
echo "Author view — default map\n";
reset_world(['administrator'], 'author');
check('only pqm_view_reviews + pqm_run_free_agents kept', held_pqm_caps() === ['pqm_view_reviews', 'pqm_run_free_agents']);
check('manage_options, edit_others_posts unchanged', current_user_can('manage_options') && current_user_can('edit_others_posts'));
$admin = $GLOBALS['tw']['user'];
$non_pqm = static fn($caps) => array_diff_key($caps, array_flip($GLOBALS['pqm_caps']));
check('every non-pqm cap byte-identical', $non_pqm(caps_of($admin)) === $non_pqm(base_caps($admin)));
check('no role written: administrator role still holds all five pqm caps',
    array_values(array_filter($pqm_caps, [$GLOBALS['tw']['roles']['administrator'], 'has_cap'])) === $pqm_caps);
$nodes = toolbar();
check('top node "Viewing as [Author]"', $nodes['pqm-view-as']['title'] === 'Viewing as [Author]');
check('children: the other roles only', array_keys(array_filter($nodes, fn($n) => ($n['parent'] ?? '') === 'pqm-view-as')) === ['pqm-view-as-editor', 'pqm-view-as-contributor']);

/* ─── Row: Contributor view + Exit ───────────────────────────────────── */
echo "Contributor view — nothing held; Exit from the toolbar\n";
reset_world(['administrator'], 'contributor');
check('all five pqm caps false', held_pqm_caps() === []);
$nodes = toolbar();
check('top node "Viewing as [Contributor]"', $nodes['pqm-view-as']['title'] === 'Viewing as [Contributor]');
check('top node keyboard-focusable while a view is on (tabindex 0)', ($nodes['pqm-view-as']['meta']['tabindex'] ?? null) === 0);
check('Exit is a top-level node linking to the handler with role=""',
    isset($nodes['pqm-view-as-exit']) && !isset($nodes['pqm-view-as-exit']['parent'])
    && $nodes['pqm-view-as-exit']['title'] === 'Exit' && $nodes['pqm-view-as-exit']['href'] === switch_href(''));
$GLOBALS['tw']['referer'] = 'https://example.test/wp-admin/index.php';
$result = run_switch(['action' => 'pqm_view_as', 'role' => ''] + $nonce);
check('Exit accepted: meta deleted', !isset($GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY]));
check('Exit returns to the referer', ($result['redirect'] ?? null) === 'https://example.test/wp-admin/index.php');
check('after Exit all five pqm caps back', held_pqm_caps() === $pqm_caps);
reset_world(['administrator'], 'contributor');
$result = run_switch(['role' => ''] + $nonce);
check('Exit without a referer lands on admin_url()', ($result['redirect'] ?? null) === 'https://example.test/wp-admin/');

/* ─── Row: Entering a view — redirect target ─────────────────────────── */
echo "Entering a view — stored, lands where the view is allowed\n";
reset_world();
$result = run_switch(['role' => 'author'] + $nonce);
check('author stored', ($GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY] ?? null) === 'author');
check('author view lands on the plugin Dashboard', ($result['redirect'] ?? null) === 'https://example.test/wp-admin/admin.php?page=pqm-dashboard');
$result = run_switch(['role' => 'contributor'] + $nonce);
check('contributor stored (switch between views)', ($GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY] ?? null) === 'contributor');
check('contributor view lands on the wp-admin Dashboard', ($result['redirect'] ?? null) === 'https://example.test/wp-admin/');

/* ─── Row: Role edited ───────────────────────────────────────────────── */
echo "Role edited — the view follows the role as stored now\n";
reset_world(['administrator'], 'author');
$GLOBALS['tw']['roles']['author']->add_cap('pqm_manage_agents');
check('author view keeps pqm_manage_agents', current_user_can('pqm_manage_agents'));
$GLOBALS['tw']['roles']['author']->remove_cap('pqm_view_reviews');
check('a cap removed from the role is gone from the view', !current_user_can('pqm_view_reviews'));

/* ─── Row: Never adds ────────────────────────────────────────────────── */
echo "Never adds — the view cannot grant what the Administrator lacks\n";
reset_world(['administrator'], 'editor');
$GLOBALS['tw']['roles']['administrator']->remove_cap('pqm_run_paid_agents');
check('editor holds pqm_run_paid_agents', $GLOBALS['tw']['roles']['editor']->has_cap('pqm_run_paid_agents'));
check('editor view: pqm_run_paid_agents stays false', !current_user_can('pqm_run_paid_agents'));
check('editor view: the rest of the editor map', held_pqm_caps() === ['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_manage_agents']);
/* exhaustive: for every view and every role map, filtered ⊆ base */
$never_adds = true;
foreach (PQM_View_As::ROLES as $view) {
    foreach ($pqm_caps as $cap) {
        reset_world(['administrator'], $view);
        $GLOBALS['tw']['roles']['administrator']->remove_cap($cap);
        $GLOBALS['tw']['roles'][$view]->add_cap($cap);
        $admin = $GLOBALS['tw']['user'];
        foreach (caps_of($admin) as $key => $grant) {
            if ($grant && empty(base_caps($admin)[$key])) { $never_adds = false; }
        }
    }
}
check('no view ever turns a cap on (3 views × 5 caps)', $never_adds);

/* ─── Row: Role deleted ──────────────────────────────────────────────── */
echo "Role deleted — fail closed, Exit still offered\n";
reset_world(['administrator'], 'author');
unset($GLOBALS['tw']['roles']['author']);
check('all five pqm caps false', held_pqm_caps() === []);
$nodes = toolbar();
check('bar names the view with the raw key', $nodes['pqm-view-as']['title'] === 'Viewing as author');
check('Exit offered', isset($nodes['pqm-view-as-exit']));
check('the deleted role is not offered as a child', !isset($nodes['pqm-view-as-author']));

/* ─── Row: Not an Administrator ──────────────────────────────────────── */
echo "Not an Administrator — forged meta ignored, no toolbar, handler 403\n";
reset_world(['editor'], 'author');
$editor = $GLOBALS['tw']['user'];
check('caps unchanged', caps_of($editor) === base_caps($editor));
check('editor keeps pqm_run_paid_agents + pqm_manage_agents', current_user_can('pqm_run_paid_agents') && current_user_can('pqm_manage_agents'));
check('no toolbar node', toolbar() === []);
$result = run_switch(['role' => 'contributor'] + $nonce);
check('handler dies with 403', isset($result['die']) && $GLOBALS['tw']['die_status'] === 403);
check('nothing stored', $GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY] === 'author');
reset_world(['editor'], 'author');
run_switch(['role' => ''] + $nonce);
check('Exit refused too (meta untouched)', $GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY] === 'author');

/* ─── Row: Bad meta / bad request ────────────────────────────────────── */
echo "Bad meta / bad request — read as no view; treated as Exit\n";
foreach (['administrator', 'shop_manager', ['author'], 'Author'] as $bad) {
    reset_world(['administrator'], $bad);
    $label = is_array($bad) ? 'array' : $bad;
    check("meta '$label' reads as no view", PQM_View_As::current(1) === '' && held_pqm_caps() === $pqm_caps);
    check("meta '$label': toolbar shows \"View as\", no Exit", toolbar()['pqm-view-as']['title'] === 'View as' && !isset(toolbar()['pqm-view-as-exit']));
}
foreach (['administrator', 'shop_manager', ''] as $bad) {
    reset_world(['administrator'], 'author');
    $result = run_switch(['role' => $bad] + $nonce);
    check("request role='$bad' treated as Exit (meta deleted)", !isset($GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY]) && isset($result['redirect']));
}
reset_world(['administrator'], 'author');
$result = run_switch($nonce);
check('request without role treated as Exit', !isset($GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY]) && isset($result['redirect']));

/* ─── Row: No nonce ──────────────────────────────────────────────────── */
echo "No nonce — refused, nothing stored\n";
foreach (['missing' => [], 'wrong' => ['_wpnonce' => 'nonce-pqm_download_workflow']] as $label => $nonce_arg) {
    reset_world();
    $result = run_switch(['role' => 'author'] + $nonce_arg);
    check("$label nonce: check_admin_referer refusal", ($result['die'] ?? null) === 'check_admin_referer');
    check("$label nonce: nothing stored", !isset($GLOBALS['tw']['meta'][1]));
    reset_world(['administrator'], 'contributor');
    run_switch(['role' => ''] + $nonce_arg);
    check("$label nonce: Exit refused, view kept", $GLOBALS['tw']['meta'][1][PQM_View_As::META_KEY] === 'contributor');
}

/* ─── Row: Wiring ────────────────────────────────────────────────────── */
echo "Wiring — filter global with 4 args; toolbar + handler in wp-admin\n";
$main = file_get_contents($base . '/post-quality-manager.php');
$admin_block_at = strpos($main, 'if (is_admin()) {');
$admin_block_end = strpos($main, "\n}", $admin_block_at); // the block's closing brace (column 0)
$filter_at = strpos($main, "add_filter('user_has_cap', ['PQM_View_As', 'filter_caps'], 10, 4);");
$in_admin_block = static fn($pos) => $pos !== false && $pos > $admin_block_at && $pos < $admin_block_end;
check('class required', str_contains($main, "require_once PQM_PLUGIN_DIR . 'includes/class-pqm-view-as.php';"));
check('user_has_cap filter hooked with 4 args, outside the is_admin() block (REST too)', $filter_at !== false && !$in_admin_block($filter_at));
check('admin_bar_menu + admin_post_pqm_view_as hooked inside the is_admin() block (between its braces)',
    $admin_block_at !== false && $admin_block_end !== false
    && $in_admin_block(strpos($main, "add_action('admin_bar_menu', ['PQM_View_As', 'admin_bar']"))
    && $in_admin_block(strpos($main, "add_action('admin_post_pqm_view_as', ['PQM_View_As', 'handle_switch']);")));
check('handler action matches PQM_View_As::ACTION', PQM_View_As::ACTION === 'pqm_view_as');

/* ─── Row: Uninstall ─────────────────────────────────────────────────── */
echo "Uninstall — no pqm_view_as meta remains\n";
define('WP_UNINSTALL_PLUGIN', true);
$GLOBALS['tw_metadata_deletes'] = [];
$GLOBALS['wpdb'] = new class { public $prefix = 'wp_'; public function query($q) { return true; } public function prepare($q, ...$a) { return $q; } };
function delete_option($k) { return true; }
function delete_metadata(...$a) { $GLOBALS['tw_metadata_deletes'][] = $a; return true; }
function get_users($args) { return []; }
function wp_clear_scheduled_hook(...$a) {}
reset_world();
require $base . '/uninstall.php';
check('uninstall deletes META_KEY for all users (delete_all)',
    in_array(['user', 0, PQM_View_As::META_KEY, '', true], $GLOBALS['tw_metadata_deletes'], true));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
