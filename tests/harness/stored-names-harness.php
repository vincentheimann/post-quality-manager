<?php
/**
 * CLI harness for story 91 — every stored name carries the postquality
 * prefix, and PostQuality_Activator::migrate_stored_names() carries an
 * existing site's data to the new names once. WP stubs, no WP install: the
 * options store from lib/wp-stubs.php, plus user meta, roles and users,
 * WP-Cron and a $wpdb stand-in that answers SHOW TABLES, COUNT, RENAME and
 * DROP for the reviews table. One row per line of the plan's matrix:
 * fresh install, upgrade, interrupted, activation on an old site, low-cap
 * first load, deletion.
 */

define('ABSPATH', __DIR__ . '/lib/'); // create_table() requires ABSPATH . 'wp-admin/includes/upgrade.php' (the dbDelta stub)
define('POSTQUALITY_VERSION', '3.10.0');
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);

/* ─── Stubs declared before the shared world (PHP hoists them, lib's guarded twins yield) ── */

/* Filters: a registry, so the Settings API's sanitizer can sit on sanitize_option_<name> as register_setting() puts it. */
function add_filter($tag, $cb, $priority = 10, $accepted_args = 1) { $GLOBALS['tw']['filters'][$tag][] = [$cb, $priority]; return true; }
function has_filter($tag, $cb = false) {
    foreach ($GLOBALS['tw']['filters'][$tag] ?? [] as [$registered, $priority]) { if ($cb === false || $registered == $cb) { return $priority; } }
    return false;
}
function remove_filter($tag, $cb, $priority = 10) {
    $GLOBALS['tw']['filters'][$tag] = array_values(array_filter($GLOBALS['tw']['filters'][$tag] ?? [], static fn($f) => $f[0] !== $cb)); // identity: a closure is removed only by its own instance, as core's unique id
    return true;
}
function apply_filters($tag, $value, ...$args) {
    foreach ($GLOBALS['tw']['filters'][$tag] ?? [] as [$cb]) { $value = call_user_func($cb, $value, ...$args); }
    return $value;
}
/* Options: as core, add_option() and update_option() pass the value through sanitize_option_<name>. */
function update_option($k, $v, $autoload = null) {
    $GLOBALS['tw']['options'][$k] = apply_filters("sanitize_option_$k", $v, $k);
    $GLOBALS['tw']['autoload'][$k] = $autoload;
    $GLOBALS['tw']['writes'][] = $k;
    return true;
}
function add_option($k, $v, $deprecated = '', $autoload = null) {
    if (array_key_exists($k, $GLOBALS['tw']['options'] ?? [])) { return false; }
    return update_option($k, $v, $autoload);
}
function wp_load_alloptions() {
    return array_filter($GLOBALS['tw']['options'] ?? [], static fn($k) => ($GLOBALS['tw']['autoload'][$k] ?? true) !== false, ARRAY_FILTER_USE_KEY);
}

/* Roles and users */
class WP_Role {
    public $name;
    public $capabilities = [];
    public function __construct($name, $capabilities = []) { $this->name = $name; $this->capabilities = $capabilities; }
    public function add_cap($cap, $grant = true) { $this->capabilities[$cap] = $grant; }
    public function remove_cap($cap) { unset($this->capabilities[$cap]); }
    public function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}
class WP_User {
    public $ID;
    public $caps;
    public function __construct($id, $caps = []) { $this->ID = $id; $this->caps = $caps; }
    public function add_cap($cap, $grant = true) { $this->caps[$cap] = $grant; }
    public function remove_cap($cap) { unset($this->caps[$cap]); }
}
function wp_roles() { return (object) ['role_objects' => $GLOBALS['tw']['roles']]; }
function get_role($name) { return $GLOBALS['tw']['roles'][$name] ?? null; }
function current_user_can($cap) { return !empty($GLOBALS['tw']['current_caps'][$cap]); }
function get_current_user_id() { return $GLOBALS['tw']['current_user'] ?? 0; }
/* capability__in: with the roles already stripped only a user's own grants match (as core, see caps-harness);
   meta_key: the users holding that meta, as ids. Every call is recorded. */
function get_users($args) {
    $GLOBALS['tw']['users_queries'][] = $args;
    if (isset($args['meta_key'])) {
        return array_keys(array_filter($GLOBALS['tw']['user_meta'] ?? [], static fn($meta) => array_key_exists($args['meta_key'], $meta)));
    }
    return array_values(array_filter($GLOBALS['tw']['users'] ?? [],
        static fn($user) => array_intersect_key($user->caps, array_flip($args['capability__in'])) !== []));
}

/* User meta: tw.user_meta[user id][key] = value */
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['tw']['user_meta'][$id] ?? []); }
function get_user_meta($id, $key = '', $single = false) {
    if (!metadata_exists('user', $id, $key)) { return $single ? '' : []; }
    $v = $GLOBALS['tw']['user_meta'][$id][$key];
    return $single ? $v : [$v];
}
function add_user_meta($id, $key, $value, $unique = false) {
    if ($unique && metadata_exists('user', $id, $key)) { return false; }
    $GLOBALS['tw']['user_meta'][$id][$key] = $value;
    $GLOBALS['tw']['meta_writes'][] = $key;
    return 1;
}
function update_user_meta($id, $key, $value) { $GLOBALS['tw']['user_meta'][$id][$key] = $value; $GLOBALS['tw']['meta_writes'][] = $key; return true; }
function delete_user_meta($id, $key) { unset($GLOBALS['tw']['user_meta'][$id][$key]); return true; }
function delete_metadata($type, $id, $key, $value = '', $delete_all = false) {
    foreach ($GLOBALS['tw']['user_meta'] ?? [] as $uid => $meta) { unset($GLOBALS['tw']['user_meta'][$uid][$key]); }
    return true;
}

/* WP-Cron: tw.cron[hook] = [timestamp, schedule, interval]; the schedules this build knows (tw.schedules) */
function wp_get_schedules() { return apply_filters('cron_schedules', $GLOBALS['tw']['schedules']); } // as core: the registered schedules through the cron_schedules filter
function wp_get_scheduled_event($hook, $args = [], $timestamp = null) {
    $e = $GLOBALS['tw']['cron'][$hook] ?? null;
    return $e ? (object) ($e + ['hook' => $hook, 'args' => []]) : false;
}
function wp_next_scheduled($hook, $args = []) { return $GLOBALS['tw']['cron'][$hook]['timestamp'] ?? false; }
function wp_schedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false) {
    $schedules = wp_get_schedules();
    if (!isset($schedules[$recurrence])) { return false; }
    $GLOBALS['tw']['cron'][$hook] = ['timestamp' => $timestamp, 'schedule' => $recurrence, 'interval' => $schedules[$recurrence]['interval']];
    return true;
}
function wp_unschedule_event($timestamp, $hook, $args = [], $wp_error = false) { unset($GLOBALS['tw']['cron'][$hook]); return true; }
function wp_clear_scheduled_hook($hook, $args = [], $wp_error = false) { $n = isset($GLOBALS['tw']['cron'][$hook]) ? 1 : 0; unset($GLOBALS['tw']['cron'][$hook]); return $n; }

/* The reviews table: tw-independent $wpdb stand-in with named tables holding rows. */
class Harness_WPDB {
    public $prefix = 'wp_';
    public $tables = [];   // table name => rows
    public $queries = [];  // every statement as prepared
    public $ddl = [];      // tables dbDelta() created, in order
    public $fail_rename = false;
    public $fail_count = false;  // a COUNT(*) that fails answers null, as $wpdb->get_var() does
    public function esc_like($s) { return addcslashes($s, '_%\\'); }
    public function get_charset_collate() { return ''; }
    public function prepare($q, ...$a) { return vsprintf(str_replace(['%s', '%i'], ["'%s'", '`%s`'], $q), $a); } // %s first: the %i stand-in must not be quoted again
    public function get_var($q) {
        $this->queries[] = $q;
        if (preg_match("/^SHOW TABLES LIKE '(.+)'$/", $q, $m)) { $name = stripcslashes($m[1]); return isset($this->tables[$name]) ? $name : null; }
        if (preg_match('/^SELECT COUNT\(\*\) FROM `(.+)`$/', $q, $m)) { return $this->fail_count ? null : (string) count($this->tables[$m[1]] ?? []); }
        return null;
    }
    public function query($q) {
        $this->queries[] = $q;
        if (preg_match('/^RENAME TABLE `(.+)` TO `(.+)`$/', $q, $m)) {
            if ($this->fail_rename || !isset($this->tables[$m[1]]) || isset($this->tables[$m[2]])) { return false; }
            $this->tables[$m[2]] = $this->tables[$m[1]];
            unset($this->tables[$m[1]]);
            return 0;
        }
        if (preg_match('/^DROP TABLE IF EXISTS `(.+)`$/', $q, $m)) { unset($this->tables[$m[1]]); return 0; }
        return false;
    }
}

/* The Settings API's sanitizer, as register_setting() attaches it on admin_init: a stand-in with the carry
   rule's effect — an admin-only key comes from the stored settings (none here) unless the saver holds the cap. */
class PostQuality_Admin {
    public static function sanitize_settings($input) {
        if (!current_user_can('postquality_manage_settings')) {
            unset($input['n8n_webhook_url'], $input['webhook_token'], $input['author_quota']);
        }
        return $input;
    }
}
function postquality_get_settings() { return get_option('postquality_settings', []); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function wp_date($format, $ts = null) { return gmdate($format, $ts ?? time()); }

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php

$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-license.php';
require $base . '/includes/class-pqm-features.php';
require $base . '/includes/class-pqm-activator.php';
require $base . '/includes/class-pqm-n8n.php'; // uninstall() revokes the setup map's Application Password through it
require $base . '/includes/class-pqm-db.php';
require $base . '/includes/class-pqm-cron.php';

/* ─── The names ──────────────────────────────────────────────────────── */

const OLD_OPTIONS = ['pqm_settings', 'pqm_db_version', 'pqm_workflow_version_seen', 'pqm_cron_nudge_dismissed', 'pqm_caps_version',
    'pqm_setup_progress', 'pqm_n8n_api', 'pqm_n8n_setup', 'pqm_n8n_leftovers', 'pqm_n8n_provisioning', 'pqm_ping'];
const OLD_META = ['pqm_monthly_usage', 'pqm_feedback_dismissed'];
const OLD_CAPS = ['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents', 'pqm_manage_settings'];
const OLD_HOOK = 'pqm_cron_review_posts';
const NEW_HOOK = 'postquality_cron_review_posts';
const FLAG = 'postquality_names_version';
function new_name(string $old): string { return str_replace('pqm_', 'postquality_', $old); }

/** The admin-only settings a low-capability save must not lose. */
$settings = ['n8n_webhook_url' => 'https://n8n.example.com/webhook/pqm-execute-example-com', 'webhook_token' => 'tok-1', 'author_quota' => 7,
    'cron_enabled' => true, 'cron_frequency' => 'weekly', 'blog_profile' => 'tech'];
/** One distinct value per old option (the real shapes: arrays, scalars, a string version). */
$old_values = [
    'pqm_settings'              => $settings,
    'pqm_db_version'            => '3.9.0',
    'pqm_workflow_version_seen' => ['version' => 7, 'at' => 1700000000, 'status' => 'ok'],
    'pqm_cron_nudge_dismissed'  => 1,
    'pqm_caps_version'          => (string) PostQuality_Features::CAPS_VERSION,
    'pqm_setup_progress'        => ['steps' => ['profile' => true], 'complete' => true],
    'pqm_n8n_api'               => ['base_url' => 'https://n8n.example.com', 'api_key' => 'n8n-key'],
    'pqm_n8n_setup'             => ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c1'], 'path' => 'pqm-execute-example-com'],
    'pqm_n8n_leftovers'         => ['workflow_id' => 'wf0'],
    'pqm_n8n_provisioning'      => 1700000001,
    'pqm_ping'                  => ['id' => 'abcdefghijklmnopqrst', 'at' => 1700000002],
];
$not_autoloaded = ['pqm_workflow_version_seen', 'pqm_cron_nudge_dismissed'];

/* ─── World ──────────────────────────────────────────────────────────── */

/** An empty site on the Pro build: core roles, no plugin data at all. */
function reset_world(bool $pro_schedules = true): void {
    $GLOBALS['tw'] = [
        'options' => [], 'autoload' => [], 'writes' => [], 'filters' => [],
        'roles' => [], 'users' => [], 'users_queries' => [], 'user_meta' => [], 'meta_writes' => [],
        'cron' => [], 'current_caps' => [], 'current_user' => 0,
        'schedules' => ['daily' => ['interval' => DAY_IN_SECONDS]] + ($pro_schedules ? ['postquality_weekly' => ['interval' => WEEK_IN_SECONDS]] : []),
    ];
    foreach (['administrator', 'editor', 'author', 'contributor', 'writer'] as $name) { $GLOBALS['tw']['roles'][$name] = new WP_Role($name, ['edit_posts' => true]); }
    $GLOBALS['tw']['roles']['administrator']->add_cap('manage_options');
    $GLOBALS['wpdb'] = new Harness_WPDB();
    postquality_fs()->pro = true;
}

/** A site on the previous release: a value under every old name, roles customised on the Roles tab, a user's own grant and deny, a weekly event, three reviews. */
function old_site(): void {
    global $old_values, $not_autoloaded;
    reset_world();
    foreach ($old_values as $k => $v) { $GLOBALS['tw']['options'][$k] = $v; $GLOBALS['tw']['autoload'][$k] = !in_array($k, $not_autoloaded, true); }
    $r = $GLOBALS['tw']['roles'];
    foreach (OLD_CAPS as $cap) { $r['administrator']->add_cap($cap); }
    foreach (['pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents', 'pqm_manage_settings'] as $cap) { $r['editor']->add_cap($cap); } // Editor given Manage settings on the tab
    $r['author']->add_cap('pqm_run_free_agents');       // Author stripped of View reviews on the tab
    $r['contributor']->add_cap('pqm_run_paid_agents', false); // a role editor's explicit deny
    $r['writer']->add_cap('pqm_run_free_agents');       // a custom role given Run Review and Key Takeaways
    $GLOBALS['tw']['users'] = [
        7 => new WP_User(7, ['author' => true, 'pqm_run_paid_agents' => true]),   // quota lifted by a direct grant
        8 => new WP_User(8, ['editor' => true, 'pqm_manage_agents' => false]),    // a per-user deny
        9 => new WP_User(9, ['author' => true]),
    ];
    $GLOBALS['tw']['user_meta'] = [
        7 => ['pqm_monthly_usage' => ['month' => '2026-10', 'count' => 3], 'pqm_view_as' => 'author', 'nickname' => 'seven'],
        9 => ['pqm_monthly_usage' => ['month' => '2026-09', 'count' => 20], 'pqm_feedback_dismissed' => 1],
    ];
    $GLOBALS['tw']['cron'][OLD_HOOK] = ['timestamp' => 1700600000, 'schedule' => 'pqm_weekly', 'interval' => WEEK_IN_SECONDS];
    $GLOBALS['wpdb']->tables['wp_pqm_reviews'] = [['id' => 1, 'post_id' => 10], ['id' => 2, 'post_id' => 11], ['id' => 3, 'post_id' => 10]];
    $GLOBALS['tw']['writes'] = [];
}

/** Every name starting with pqm_ still stored anywhere: options, meta, role and user caps, cron hooks, tables. */
function pqm_leftovers(): array {
    $out = [];
    foreach (array_keys($GLOBALS['tw']['options']) as $k) { if (str_starts_with($k, 'pqm_')) { $out[] = "option $k"; } }
    foreach ($GLOBALS['tw']['user_meta'] as $uid => $meta) { foreach (array_keys($meta) as $k) { if (str_starts_with($k, 'pqm_')) { $out[] = "meta $uid:$k"; } } }
    foreach ($GLOBALS['tw']['roles'] as $name => $role) { foreach (array_keys($role->capabilities) as $c) { if (str_starts_with($c, 'pqm_')) { $out[] = "role $name:$c"; } } }
    foreach ($GLOBALS['tw']['users'] as $uid => $user) { foreach (array_keys($user->caps) as $c) { if (str_starts_with($c, 'pqm_')) { $out[] = "user $uid:$c"; } } }
    foreach (array_keys($GLOBALS['tw']['cron']) as $h) { if (str_starts_with($h, 'pqm_')) { $out[] = "cron $h"; } }
    foreach ($GLOBALS['tw']['cron'] as $h => $e) { if (str_starts_with($e['schedule'], 'pqm_')) { $out[] = "schedule $h:{$e['schedule']}"; } }
    foreach (array_keys($GLOBALS['wpdb']->tables) as $t) { if (str_contains($t, 'pqm_')) { $out[] = "table $t"; } }
    return $out;
}
/** Everything stored under either prefix (deletion must leave none). */
function any_leftovers(): array {
    $out = [];
    foreach (array_keys($GLOBALS['tw']['options']) as $k) { if (preg_match('/^(pqm|postquality)_/', $k)) { $out[] = "option $k"; } }
    foreach ($GLOBALS['tw']['user_meta'] as $uid => $meta) { foreach (array_keys($meta) as $k) { if (preg_match('/^(pqm|postquality)_/', $k)) { $out[] = "meta $uid:$k"; } } }
    foreach ($GLOBALS['tw']['roles'] as $name => $role) { foreach (array_keys($role->capabilities) as $c) { if (preg_match('/^(pqm|postquality)_/', $c)) { $out[] = "role $name:$c"; } } }
    foreach ($GLOBALS['tw']['users'] as $uid => $user) { foreach (array_keys($user->caps) as $c) { if (preg_match('/^(pqm|postquality)_/', $c)) { $out[] = "user $uid:$c"; } } }
    foreach (array_keys($GLOBALS['tw']['cron']) as $h) { $out[] = "cron $h"; }
    foreach (array_keys($GLOBALS['wpdb']->tables) as $t) { if (preg_match('/_(pqm|postquality)_/', $t)) { $out[] = "table $t"; } }
    return $out;
}
function describe(array $items): string { return $items ? ' — found: ' . implode(', ', $items) : ''; }
/** Every role's and user's capabilities, keyed by holder. */
function caps_snapshot(): array {
    $out = [];
    foreach ($GLOBALS['tw']['roles'] as $name => $role) { $out["role $name"] = $role->capabilities; }
    foreach ($GLOBALS['tw']['users'] as $uid => $user) { $out["user $uid"] = $user->caps; }
    return $out;
}
/** The same holders with every pqm_ key renamed in place (what the migration must produce: same values, same order). */
function renamed(array $snapshot): array {
    foreach ($snapshot as $holder => $caps) {
        $renamed = [];
        foreach ($caps as $cap => $grant) { $renamed[str_starts_with($cap, 'pqm_') ? new_name($cap) : $cap] = $grant; }
        $snapshot[$holder] = $renamed;
    }
    return $snapshot;
}
$migrate = static fn() => method_exists('PostQuality_Activator', 'migrate_stored_names') ? PostQuality_Activator::migrate_stored_names() : null;

/* ─── Row 1: Fresh install ───────────────────────────────────────────── */
echo "Row 1 — Fresh install: activation, setup, a review and a cron run write only postquality names; the finished flag is set\n";
reset_world();
PostQuality_Activator::activate();
check('activation writes only postquality_ options (the flag, the table version, the caps version, the settings with its token)',
    array_filter($GLOBALS['tw']['writes'], static fn($k) => !str_starts_with($k, 'postquality_')) === []
    && array_values(array_unique($GLOBALS['tw']['writes'])) == [FLAG, 'postquality_db_version', 'postquality_caps_version', 'postquality_settings']);
check('the finished flag is set to 1 and autoloaded', ($GLOBALS['tw']['options'][FLAG] ?? null) === 1 && ($GLOBALS['tw']['autoload'][FLAG] ?? null) === true);
check('the table created is wp_postquality_reviews, and PostQuality_DB::table_name() reads it',
    $GLOBALS['wpdb']->ddl === ['wp_postquality_reviews'] && PostQuality_DB::table_name() === 'wp_postquality_reviews');
check('the five capabilities granted are postquality_ and the default map (capabilities() keys)',
    array_keys(PostQuality_Features::capabilities()) === array_map('new_name', OLD_CAPS)
    && array_keys($GLOBALS['tw']['roles']['author']->capabilities) === ['edit_posts', 'postquality_view_reviews', 'postquality_run_free_agents']);
check('the constants every write goes through carry the prefix: the usage meta, the four n8n options, the cron hook',
    PostQuality_Features::USAGE_META_KEY === 'postquality_monthly_usage' && PostQuality_N8n::OPTION === 'postquality_n8n_api'
    && PostQuality_N8n::SETUP_OPTION === 'postquality_n8n_setup' && PostQuality_N8n::LEFTOVERS_OPTION === 'postquality_n8n_leftovers'
    && PostQuality_N8n::LOCK_OPTION === 'postquality_n8n_provisioning' && PostQuality_Cron::HOOK === NEW_HOOK);
update_option('postquality_settings', ['cron_enabled' => true, 'cron_frequency' => 'weekly', 'author_quota' => 20] + (array) get_option('postquality_settings', []));
PostQuality_Cron::schedule();
check('a weekly schedule runs under postquality_cron_review_posts with the postquality_weekly recurrence',
    array_keys($GLOBALS['tw']['cron']) === [NEW_HOOK] && $GLOBALS['tw']['cron'][NEW_HOOK]['schedule'] === 'postquality_weekly');
check('the cron_schedules filter adds postquality_weekly and nothing pqm', array_keys(PostQuality_Cron::add_custom_schedules([])) === ['postquality_weekly']);
$GLOBALS['tw']['current_user'] = 9;
$GLOBALS['tw']['users'][9] = new WP_User(9, ['author' => true]);
PostQuality_Features::consume_quota();
check('a review consumes the quota under postquality_monthly_usage', array_keys($GLOBALS['tw']['user_meta'][9] ?? []) === ['postquality_monthly_usage']);
check('nothing under pqm_ anywhere' . describe($l = pqm_leftovers()), $l === []);

/* ─── Row 2: Upgrade ─────────────────────────────────────────────────── */
echo "Row 2 — Upgrade: every value under its new name, old names gone, roles and users exact, the event and the rows kept\n";
old_site();
$caps_before = caps_snapshot();
PostQuality_Activator::maybe_upgrade();
$moved = [];
foreach ($old_values as $old => $value) {
    if (array_key_exists($old, $GLOBALS['tw']['options']) || serialize($GLOBALS['tw']['options'][new_name($old)] ?? null) !== serialize($value)) { $moved[] = $old; }
}
check('each of the 11 options sits under its new name with the identical value, and the old name is gone' . describe($moved), $moved === []);
check('the autoload flag travels with the option (the version report and the dismissed nudge stay unautoloaded)',
    $GLOBALS['tw']['autoload']['postquality_workflow_version_seen'] === false && $GLOBALS['tw']['autoload']['postquality_cron_nudge_dismissed'] === false
    && $GLOBALS['tw']['autoload']['postquality_settings'] === true);
check('each role and user holds the new caps exactly as it held the old ones (denies included), nothing else changed',
    caps_snapshot() === renamed($caps_before));
check('the roles were not reset to the defaults: Author still lacks View reviews, the Editor keeps Manage settings, the custom role keeps its grant',
    !isset($GLOBALS['tw']['roles']['author']->capabilities['postquality_view_reviews'])
    && $GLOBALS['tw']['roles']['editor']->capabilities['postquality_manage_settings'] === true
    && $GLOBALS['tw']['roles']['writer']->capabilities['postquality_run_free_agents'] === true
    && $GLOBALS['tw']['roles']['contributor']->capabilities['postquality_run_paid_agents'] === false);
check('the users were found by their own grants of the old caps, after the roles moved',
    in_array(['capability__in' => OLD_CAPS], $GLOBALS['tw']['users_queries'], true)
    && $GLOBALS['tw']['users'][7]->caps === ['author' => true, 'postquality_run_paid_agents' => true]
    && $GLOBALS['tw']['users'][8]->caps === ['editor' => true, 'postquality_manage_agents' => false]);
check('the usage counters and the dismissed card moved per user, unchanged; the retired pqm_view_as meta is deleted; other meta untouched',
    $GLOBALS['tw']['user_meta'][7] === ['nickname' => 'seven', 'postquality_monthly_usage' => ['month' => '2026-10', 'count' => 3]]
    && $GLOBALS['tw']['user_meta'][9] === ['postquality_monthly_usage' => ['month' => '2026-09', 'count' => 20], 'postquality_feedback_dismissed' => 1]);
check('the event runs under the new hook and the postquality_weekly schedule at the same time and interval; the old hook is gone',
    $GLOBALS['tw']['cron'] === [NEW_HOOK => ['timestamp' => 1700600000, 'schedule' => 'postquality_weekly', 'interval' => WEEK_IN_SECONDS]]);
check('the table is renamed with every row kept',
    array_keys($GLOBALS['wpdb']->tables) === ['wp_postquality_reviews'] && count($GLOBALS['wpdb']->tables['wp_postquality_reviews']) === 3);
check('the capability version check found the moved version and granted nothing (postquality_caps_version = CAPS_VERSION, written once by the move)',
    $GLOBALS['tw']['options']['postquality_caps_version'] === (string) PostQuality_Features::CAPS_VERSION
    && array_count_values($GLOBALS['tw']['writes'])['postquality_caps_version'] === 1);
check('the finished flag is set last', end($GLOBALS['tw']['writes']) === FLAG && $GLOBALS['tw']['options'][FLAG] === 1);
check('nothing under pqm_ anywhere' . describe($l = pqm_leftovers()), $l === []);
$state = serialize([$GLOBALS['tw']['options'], caps_snapshot(), $GLOBALS['tw']['user_meta'], $GLOBALS['tw']['cron'], $GLOBALS['wpdb']->tables]);
$GLOBALS['tw']['writes'] = []; $GLOBALS['tw']['users_queries'] = []; $GLOBALS['wpdb']->queries = [];
PostQuality_Activator::maybe_upgrade();
check('the next load skips the migration: no write, no user query, no table query, state identical',
    $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['users_queries'] === [] && $GLOBALS['wpdb']->queries === []
    && serialize([$GLOBALS['tw']['options'], caps_snapshot(), $GLOBALS['tw']['user_meta'], $GLOBALS['tw']['cron'], $GLOBALS['wpdb']->tables]) === $state);

/* A daily schedule keeps its core recurrence; on the Free build (no postquality_weekly registered) the weekly event still moves, at its time and interval. */
old_site();
$GLOBALS['tw']['cron'][OLD_HOOK] = ['timestamp' => 1700600000, 'schedule' => 'daily', 'interval' => DAY_IN_SECONDS];
$migrate();
check('a daily event moves with its daily recurrence', $GLOBALS['tw']['cron'] === [NEW_HOOK => ['timestamp' => 1700600000, 'schedule' => 'daily', 'interval' => DAY_IN_SECONDS]]);
reset_world(false);
$GLOBALS['tw']['cron'][OLD_HOOK] = ['timestamp' => 1700600000, 'schedule' => 'pqm_weekly', 'interval' => WEEK_IN_SECONDS];
$migrate();
check('Free build, no weekly schedule registered: the event still moves to the new hook and the postquality_weekly key at the same time and interval; the migration finishes',
    $GLOBALS['tw']['cron'] === [NEW_HOOK => ['timestamp' => 1700600000, 'schedule' => 'postquality_weekly', 'interval' => WEEK_IN_SECONDS]] && ($GLOBALS['tw']['options'][FLAG] ?? null) === 1);
check('... the schedule offered for that one call is not left on cron_schedules', !isset(wp_get_schedules()['postquality_weekly']) && ($GLOBALS['tw']['filters']['cron_schedules'] ?? []) === []);

/* ─── Row 3: Interrupted ─────────────────────────────────────────────── */
echo "Row 3 — Interrupted: the remaining steps finish, an existing new value is kept and the old one removed; later loads skip\n";
old_site();
$GLOBALS['tw']['options']['postquality_settings'] = ['n8n_webhook_url' => 'https://kept.example.com/'] + $settings; // the new option already beside the old one
$GLOBALS['tw']['options']['postquality_n8n_api'] = ['base_url' => 'https://kept.example.com', 'api_key' => 'kept'];
$GLOBALS['tw']['roles']['author']->add_cap('postquality_run_free_agents', false); // the new cap already beside the old one (a different grant, to see which wins)
$GLOBALS['tw']['users'][7]->add_cap('postquality_run_paid_agents', false);
$GLOBALS['tw']['user_meta'][9]['postquality_monthly_usage'] = ['month' => '2026-10', 'count' => 1];
$GLOBALS['tw']['cron'][NEW_HOOK] = ['timestamp' => 1700700000, 'schedule' => 'postquality_weekly', 'interval' => WEEK_IN_SECONDS];
$migrate();
check('an option already under its new name is kept and the old one removed',
    $GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url'] === 'https://kept.example.com/' && !isset($GLOBALS['tw']['options']['pqm_settings'])
    && $GLOBALS['tw']['options']['postquality_n8n_api']['api_key'] === 'kept' && !isset($GLOBALS['tw']['options']['pqm_n8n_api']));
check('the other options still moved', $GLOBALS['tw']['options']['postquality_ping'] === $old_values['pqm_ping'] && !isset($GLOBALS['tw']['options']['pqm_ping']));
check('a cap already under its new name keeps its grant; the old key goes (role and user)',
    $GLOBALS['tw']['roles']['author']->capabilities === ['edit_posts' => true, 'postquality_run_free_agents' => false]
    && $GLOBALS['tw']['users'][7]->caps === ['author' => true, 'postquality_run_paid_agents' => false]);
check('meta already under its new name is kept; the old key goes',
    $GLOBALS['tw']['user_meta'][9]['postquality_monthly_usage'] === ['month' => '2026-10', 'count' => 1] && !isset($GLOBALS['tw']['user_meta'][9]['pqm_monthly_usage']));
check('an event already under the new hook is kept; the old hook is cleared',
    $GLOBALS['tw']['cron'] === [NEW_HOOK => ['timestamp' => 1700700000, 'schedule' => 'postquality_weekly', 'interval' => WEEK_IN_SECONDS]]);
check('the old table, still present, is renamed; the flag is set' . describe($l = pqm_leftovers()),
    $l === [] && array_keys($GLOBALS['wpdb']->tables) === ['wp_postquality_reviews'] && ($GLOBALS['tw']['options'][FLAG] ?? null) === 1);

old_site();
$GLOBALS['wpdb']->fail_rename = true;
$migrate();
check('a failed table rename leaves the flag unset, the old table in place, everything else moved',
    !isset($GLOBALS['tw']['options'][FLAG]) && array_keys($GLOBALS['wpdb']->tables) === ['wp_pqm_reviews'] && pqm_leftovers() === ['table wp_pqm_reviews']);
$GLOBALS['wpdb']->fail_rename = false;
$GLOBALS['tw']['writes'] = [];
$migrate();
check('the next load retries: the table renamed, the flag set, nothing else rewritten' . describe($l = pqm_leftovers()),
    $l === [] && $GLOBALS['tw']['writes'] === [FLAG] && count($GLOBALS['wpdb']->tables['wp_postquality_reviews']) === 3);

/* ─── Row 4: Activation on an old site ───────────────────────────────── */
echo "Row 4 — Activation on an old site: the migration runs before the table is created, so no empty new table shadows the old one\n";
old_site();
PostQuality_Activator::activate();
check('the old table was renamed before dbDelta ran on the new name; the three rows are there',
    array_search('RENAME TABLE `wp_pqm_reviews` TO `wp_postquality_reviews`', $GLOBALS['wpdb']->queries, true) !== false
    && $GLOBALS['wpdb']->ddl === ['wp_postquality_reviews'] && count($GLOBALS['wpdb']->tables['wp_postquality_reviews']) === 3);
check('the roles kept their customised grants through activation (Author still lacks View reviews, the deny stays)',
    !isset($GLOBALS['tw']['roles']['author']->capabilities['postquality_view_reviews'])
    && $GLOBALS['tw']['roles']['contributor']->capabilities['postquality_run_paid_agents'] === false);
check('the webhook token was kept, not regenerated', $GLOBALS['tw']['options']['postquality_settings']['webhook_token'] === 'tok-1');
check('nothing under pqm_ anywhere' . describe($l = pqm_leftovers()), $l === []);

old_site();
$GLOBALS['wpdb']->tables['wp_postquality_reviews'] = []; // an earlier activation created the new table empty beside the old one
PostQuality_Activator::activate();
check('both tables, the new one empty: it is dropped and the old one renamed, rows kept',
    array_keys($GLOBALS['wpdb']->tables) === ['wp_postquality_reviews'] && count($GLOBALS['wpdb']->tables['wp_postquality_reviews']) === 3
    && in_array('DROP TABLE IF EXISTS `wp_postquality_reviews`', $GLOBALS['wpdb']->queries, true));

old_site();
$GLOBALS['wpdb']->tables['wp_postquality_reviews'] = [['id' => 1, 'post_id' => 99]];
$GLOBALS['wpdb']->fail_count = true;
PostQuality_Activator::activate();
check('both tables, the count of the new one failed (null): the new table survives, nothing dropped, the old one kept too',
    ($GLOBALS['wpdb']->tables['wp_postquality_reviews'] ?? null) === [['id' => 1, 'post_id' => 99]] && count($GLOBALS['wpdb']->tables['wp_pqm_reviews'] ?? []) === 3
    && !array_filter($GLOBALS['wpdb']->queries, static fn($q) => str_starts_with($q, 'DROP')));

old_site();
$GLOBALS['wpdb']->tables['wp_postquality_reviews'] = [['id' => 1, 'post_id' => 99]];
PostQuality_Activator::activate();
check('both tables with rows in the new one: both kept, nothing overwritten, the migration finishes',
    ($GLOBALS['wpdb']->tables['wp_postquality_reviews'] ?? null) === [['id' => 1, 'post_id' => 99]] && count($GLOBALS['wpdb']->tables['wp_pqm_reviews'] ?? []) === 3
    && !in_array('DROP TABLE IF EXISTS `wp_postquality_reviews`', $GLOBALS['wpdb']->queries, true) && ($GLOBALS['tw']['options'][FLAG] ?? null) === 1);

/* ─── Row 5: Low-cap first load ──────────────────────────────────────── */
echo "Row 5 — Low-cap first load: a cron or REST request, or an Author, triggers the migration; every admin-only setting arrives intact\n";
foreach (['no user (cron, REST)' => [], 'an Author' => ['postquality_run_free_agents' => true, 'pqm_run_free_agents' => true]] as $who => $caps) {
    old_site();
    $GLOBALS['tw']['current_caps'] = $caps;
    add_filter('sanitize_option_postquality_settings', ['PostQuality_Admin', 'sanitize_settings'], 10, 2); // as register_setting() attached it
    $migrate();
    check("$who: the settings arrive byte-identical, admin-only keys included",
        serialize($GLOBALS['tw']['options']['postquality_settings'] ?? null) === serialize($settings));
    check("$who: the sanitizer is attached again afterwards", has_filter('sanitize_option_postquality_settings', ['PostQuality_Admin', 'sanitize_settings']) === 10);
}
old_site();
$migrate();
check('without the sanitizer attached (init runs before admin_init) the settings arrive the same', serialize($GLOBALS['tw']['options']['postquality_settings'] ?? null) === serialize($settings));

/* ─── Row 6: Deletion ────────────────────────────────────────────────── */
echo "Row 6 — Deletion: no option, user meta, capability, cron event or table with either prefix remains\n";
old_site();
PostQuality_Activator::maybe_upgrade();
$GLOBALS['tw']['users'][7]->add_cap('postquality_manage_settings');
PostQuality_Activator::uninstall();
check('a migrated site: nothing with either prefix is left' . describe($l = any_leftovers()), $l === []);
check('other data survives: core caps, other meta, other tables untouched',
    $GLOBALS['tw']['roles']['administrator']->capabilities === ['edit_posts' => true, 'manage_options' => true]
    && $GLOBALS['tw']['users'][7]->caps === ['author' => true] && $GLOBALS['tw']['user_meta'][7] === ['nickname' => 'seven']);

old_site();
$GLOBALS['tw']['options']['postquality_ping'] = ['id' => 'x']; // and one new name beside the old ones (an interrupted site)
$GLOBALS['wpdb']->tables['wp_postquality_reviews'] = [];
$GLOBALS['tw']['cron'][NEW_HOOK] = ['timestamp' => 1, 'schedule' => 'daily', 'interval' => DAY_IN_SECONDS];
PostQuality_Activator::uninstall();
check('a site that still holds old names: nothing with either prefix is left' . describe($l = any_leftovers()), $l === []);
check('uninstall() queries users for the capabilities of both prefixes',
    array_filter($GLOBALS['tw']['users_queries'], static fn($q) => array_intersect(array_map('new_name', OLD_CAPS), $q['capability__in'] ?? []) !== []
        && array_intersect(OLD_CAPS, $q['capability__in'] ?? []) !== []) !== []);

/* A site that never ran the migration (deactivated on 3.9.0, updated, deleted: init has fired, maybe_upgrade() never ran): uninstall()
   moves the names first, so the revoke (bug 73) finds the setup map under its new name and the Application Password it names. */
class WP_Application_Passwords {
    public static function delete_application_password($user_id, $uuid) { $GLOBALS['tw']['app_revoked'][] = [$user_id, $uuid, 'setup option then' => get_option('postquality_n8n_setup')]; return true; }
}
old_site();
$GLOBALS['tw']['options']['pqm_n8n_setup']['application_password'] = ['uuid' => 'app-uuid', 'user_id' => 7];
$GLOBALS['tw']['app_revoked'] = [];
PostQuality_Activator::uninstall();
check('never migrated, deleted: the Application Password of the old setup map is revoked (user id and uuid from the map), the map read under its new name',
    $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid', 'setup option then' => $old_values['pqm_n8n_setup'] + ['application_password' => ['uuid' => 'app-uuid', 'user_id' => 7]]]]
    && array_filter($GLOBALS['wpdb']->queries, static fn($q) => str_starts_with($q, 'RENAME')) !== []);
check('... and nothing with either prefix is left' . describe($l = any_leftovers()), $l === []);

harness_exit();
