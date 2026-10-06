<?php
/**
 * CLI harness for story 1.2 (role capabilities) — WP stubs, no WP install.
 * Persists the capability matrix verified in-session on 2026-10-01
 * (story-role-capabilities-replace-binary-checks-plan.md, Implementation
 * Notes + Review Triage Log): the ADR 0003 default map, register/remove
 * semantics, the why_not() role×command×plan matrix, and the
 * hidden/gated/allowed split of the registry. Story 14 appends the
 * registry-as-whitelist rows: score/apply explicit free-tier entries and
 * the fail-closed fallback for unknown commands.
 */

error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');

/* ─── Mutable test world ─────────────────────────────────────────────── */
$GLOBALS['tw'] = [
    'roles'        => [],     // role_name => WP_Role (stub) objects
    'current_role' => null,   // whose capabilities current_user_can() reads
    'license_key'  => '',     // non-empty = pro (PQM_License stub contract)
    'filter_pro'   => null,   // null = pqm_is_pro filter not hooked, bool = override
];

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
}
/* Bug 20: users with direct grants (their own capabilities meta). */
class WP_User {
    public $caps;
    public function __construct($caps = []) { $this->caps = $caps; }
    public function remove_cap($cap) { unset($this->caps[$cap]); }
}
/* Core's capability__in also matches users through a role holding the cap; the
   stub models only the direct-grant match, all that remains once roles are stripped. */
function get_users($args) {
    $GLOBALS['tw']['users_query'] = $args;
    return array_values(array_filter($GLOBALS['tw']['users'] ?? [],
        static fn($user) => array_intersect_key($user->caps, array_flip($args['capability__in'])) !== []));
}
function get_role($name) { return $GLOBALS['tw']['roles'][$name] ?? null; }
function wp_roles() { return (object) ['role_objects' => $GLOBALS['tw']['roles']]; }
function current_user_can($cap) {
    $role = get_role($GLOBALS['tw']['current_role']);
    return $role instanceof WP_Role && !empty($role->capabilities[$cap]);
}
function pqm_get_settings() { return ['license_key' => $GLOBALS['tw']['license_key']]; }
function apply_filters($tag, $value) {
    if ($tag === 'pqm_is_pro' && $GLOBALS['tw']['filter_pro'] !== null) {
        return $GLOBALS['tw']['filter_pro'];
    }
    return $value;
}
function __($s, $d = null) { return $s; }
/* Ticket 12: maybe_upgrade() reads/writes pqm_caps_version; stubs at WP's real arity, writes counted. */
function get_option($option, $default_value = false) {
    return array_key_exists($option, $GLOBALS['tw']['options'] ?? []) ? $GLOBALS['tw']['options'][$option] : $default_value;
}
function update_option($option, $value, $autoload = null) {
    $GLOBALS['tw']['option_writes'] = ($GLOBALS['tw']['option_writes'] ?? 0) + 1;
    $GLOBALS['tw']['options'][$option] = $value;
    return true;
}

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-license.php';
require $base . '/includes/class-pqm-features.php';
require $base . '/includes/class-pqm-activator.php';

/* ─── Tiny assert runner ─────────────────────────────────────────────── */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}
function reset_world($role_names = ['administrator', 'editor', 'author', 'contributor'], $is_pro = false, $current_role = null) {
    $GLOBALS['tw'] = [
        'roles'        => [],
        'current_role' => $current_role,
        'license_key'  => $is_pro ? 'PQM-ANY-KEY' : '',
        'filter_pro'   => null,
    ];
    foreach ($role_names as $name) {
        $GLOBALS['tw']['roles'][$name] = new WP_Role($name);
    }
}

/* The ADR 0003 access matrix — the shipped default map (story 1.2's contract). */
$default_map = [
    'pqm_view_reviews'    => ['administrator', 'editor', 'author'],
    'pqm_run_free_agents' => ['administrator', 'editor', 'author'],
    'pqm_run_paid_agents' => ['administrator', 'editor'],
    'pqm_manage_agents'   => ['administrator', 'editor'],
    'pqm_manage_settings' => ['administrator'],
];
$registry_commands = ['review', 'takeaways', 'rewrite', 'sources', 'seo', 'audit', 'score', 'apply'];
$paid_commands     = ['rewrite', 'sources', 'seo', 'audit'];

/* ─── Row 1: Default map ─────────────────────────────────────────────── */
echo "Row 1 — Fresh registration grants the ADR 0003 access matrix\n";
reset_world();
check('capabilities() equals the ADR 0003 access matrix', PQM_Features::capabilities() === $default_map);
PQM_Features::register_capabilities();
foreach ($default_map as $cap => $granted_roles) {
    $holders = [];
    foreach ($GLOBALS['tw']['roles'] as $name => $role) {
        if (!empty($role->capabilities[$cap])) { $holders[] = $name; }
    }
    check("$cap granted to exactly its default roles", $holders === $granted_roles);
}
check('contributor holds no pqm_ cap', $GLOBALS['tw']['roles']['contributor']->capabilities === []);
check('registration is idempotent', (function () use ($default_map) {
    $before = [];
    foreach ($GLOBALS['tw']['roles'] as $name => $role) { $before[$name] = $role->capabilities; }
    PQM_Features::register_capabilities();
    foreach ($GLOBALS['tw']['roles'] as $name => $role) {
        if ($role->capabilities !== $before[$name]) { return false; }
    }
    return true;
})());

/* ─── Row 2: Missing role is skipped silently ────────────────────────── */
echo "Row 2 — A role absent on the site is skipped, no error\n";
reset_world(['administrator', 'contributor']); // editor + author removed from this site
PQM_Features::register_capabilities();
check('no error; administrator still gets all five caps',
    array_keys($GLOBALS['tw']['roles']['administrator']->capabilities) === array_keys($default_map));
check('absent roles stay absent', !isset($GLOBALS['tw']['roles']['editor'], $GLOBALS['tw']['roles']['author']));

/* ─── Row 3: Uninstall strip (explicit denies included) ──────────────── */
echo "Row 3 — remove_capabilities() leaves no pqm_* key on any role\n";
reset_world();
PQM_Features::register_capabilities();
/* a role editor stored an explicit deny (cap => false) — must be stripped too (review fix 2026-10-01) */
$GLOBALS['tw']['roles']['contributor']->capabilities['pqm_run_paid_agents'] = false;
$GLOBALS['tw']['roles']['author']->capabilities['pqm_view_reviews'] = false;
/* a non-pqm cap must survive the strip */
$GLOBALS['tw']['roles']['author']->capabilities['edit_posts'] = true;
PQM_Features::remove_capabilities();
$leftover = [];
foreach ($GLOBALS['tw']['roles'] as $name => $role) {
    foreach ($role->capabilities as $cap => $grant) {
        if (strpos($cap, 'pqm_') === 0) { $leftover[] = "$name:$cap"; }
    }
}
check('no pqm_* key on any role (grants and explicit denies)', $leftover === []);
check('non-pqm caps untouched', !empty($GLOBALS['tw']['roles']['author']->capabilities['edit_posts']));

/* ─── Row 3b (bug 20): direct user grants are stripped too ───────────── */
echo "Row 3b — remove_capabilities() leaves no pqm_* key in any user's own capabilities\n";
reset_world();
PQM_Features::register_capabilities();
$GLOBALS['tw']['users'] = [
    'quota-lifted author' => new WP_User(['author' => true, 'pqm_run_paid_agents' => true, 'edit_others_posts' => true]),
    'denied editor'       => new WP_User(['editor' => true, 'pqm_manage_agents' => false]),
];
PQM_Features::remove_capabilities();
check('the user query asks for exactly the five pqm caps',
    $GLOBALS['tw']['users_query']['capability__in'] === array_keys($default_map));
$leftover = [];
foreach ($GLOBALS['tw']['users'] as $name => $user) {
    foreach (array_keys($user->caps) as $cap) {
        if (strpos($cap, 'pqm_') === 0) { $leftover[] = "$name:$cap"; }
    }
}
check('no pqm_* key on any user (grants and explicit denies)', $leftover === []);
check('non-pqm direct grants and role keys survive',
    $GLOBALS['tw']['users']['quota-lifted author']->caps === ['author' => true, 'edit_others_posts' => true]
    && $GLOBALS['tw']['users']['denied editor']->caps === ['editor' => true]);

/* ─── Row 4: why_not() matrix — role × 8 commands × plan on/off ──────── */
echo "Row 4 — why_not() matrix (role before plan) over the 8 registry commands\n";
/* Story 1.2's verified matrix, cells unchanged. Story 14 made score/apply
 * explicit free-tier entries on pqm_run_free_agents (the cells they had as
 * the free fallback) and swapped apply_takeaways (not sent since story 5.7,
 * now fail-closed, see Row 6) for apply. */
$free_cells = ['review' => null, 'takeaways' => null, 'score' => null, 'apply' => null];
$expected_matrix = [
    'administrator' => [
        'free' => $free_cells + ['rewrite' => 'plan', 'sources' => 'plan', 'seo' => 'plan', 'audit' => 'plan'],
        'pro'  => $free_cells + ['rewrite' => null, 'sources' => null, 'seo' => null, 'audit' => null],
    ],
    'editor' => [
        'free' => $free_cells + ['rewrite' => 'plan', 'sources' => 'plan', 'seo' => 'plan', 'audit' => 'plan'],
        'pro'  => $free_cells + ['rewrite' => null, 'sources' => null, 'seo' => null, 'audit' => null],
    ],
    'author' => [ // lacks pqm_run_paid_agents: role refusal hides paid agents, plan never reached
        'free' => $free_cells + ['rewrite' => 'role', 'sources' => 'role', 'seo' => 'role', 'audit' => 'role'],
        'pro'  => $free_cells + ['rewrite' => 'role', 'sources' => 'role', 'seo' => 'role', 'audit' => 'role'],
    ],
    'contributor' => [ // holds no cap at all
        'free' => array_fill_keys($registry_commands, 'role'),
        'pro'  => array_fill_keys($registry_commands, 'role'),
    ],
];
foreach ($expected_matrix as $role_name => $by_plan) {
    foreach ($by_plan as $plan => $cells) {
        reset_world(['administrator', 'editor', 'author', 'contributor'], $plan === 'pro', $role_name);
        PQM_Features::register_capabilities();
        foreach ($cells as $command => $expected) {
            $actual = PQM_Features::why_not($command);
            check(sprintf('%-13s %-4s why_not(%s) = %s', $role_name, $plan, $command, var_export($expected, true)),
                $actual === $expected);
        }
        /* can() is why_not() === null in every cell */
        foreach ($cells as $command => $expected) {
            check(sprintf('%-13s %-4s can(%s) mirrors why_not()', $role_name, $plan, $command),
                PQM_Features::can($command) === ($expected === null));
        }
    }
}
/* the pqm_is_pro filter override still always wins over the provider */
reset_world(['administrator', 'editor', 'author', 'contributor'], false, 'editor');
PQM_Features::register_capabilities();
$GLOBALS['tw']['filter_pro'] = true;
check("mu-plugin override: why_not('seo') null for editor on an unlicensed site", PQM_Features::why_not('seo') === null);

/* ─── Row 5: hidden/gated/allowed partition the registry ─────────────── */
echo "Row 5 — hidden_commands() and gated_commands() are disjoint and complete\n";
$expected_split = [
    //                      [plan,   hidden,          gated]
    ['administrator', false, [],              $paid_commands],
    ['administrator', true,  [],              []],
    ['editor',        false, [],              $paid_commands],
    ['editor',        true,  [],              []],
    ['author',        false, $paid_commands,  []],
    ['author',        true,  $paid_commands,  []],
    ['contributor',   false, $registry_commands, []],
    ['contributor',   true,  $registry_commands, []],
];
foreach ($expected_split as [$role_name, $is_pro, $exp_hidden, $exp_gated]) {
    reset_world(['administrator', 'editor', 'author', 'contributor'], $is_pro, $role_name);
    PQM_Features::register_capabilities();
    $hidden  = PQM_Features::hidden_commands();
    $gated   = PQM_Features::gated_commands();
    $allowed = array_values(array_filter($registry_commands, ['PQM_Features', 'can']));
    $plan    = $is_pro ? 'pro' : 'free';
    check("$role_name $plan hidden = [" . implode(',', $exp_hidden) . ']', $hidden === $exp_hidden);
    check("$role_name $plan gated = [" . implode(',', $exp_gated) . ']', $gated === $exp_gated);
    check("$role_name $plan hidden ∩ gated = ∅", array_intersect($hidden, $gated) === []);
    $union = array_merge($hidden, $gated, $allowed);
    sort($union);
    $all = $registry_commands;
    sort($all);
    check("$role_name $plan hidden + gated + allowed = registry", $union === $all);
}

/* ─── Row 6 (story 14): the registry is the command whitelist ────────── */
echo "Row 6 — registry = the 8 commands with their tiers; unknown commands fail closed\n";
$free_entry = ['tier' => 'free', 'capability' => 'pqm_run_free_agents'];
$paid_entry = ['tier' => 'paid', 'capability' => 'pqm_run_paid_agents'];
check('registry keys === the 8 commands', array_keys(PQM_Features::registry()) === $registry_commands);
check('review, takeaways, score, apply free on pqm_run_free_agents; rewrite, sources, seo, audit paid on pqm_run_paid_agents',
    array_map(fn($entry) => array_intersect_key($entry, $free_entry), PQM_Features::registry()) === [
        'review'  => $free_entry, 'takeaways' => $free_entry,
        'rewrite' => $paid_entry, 'sources'   => $paid_entry, 'seo' => $paid_entry, 'audit' => $paid_entry,
        'score'   => $free_entry, 'apply'     => $free_entry,
    ]);
/* Story 11: the registry is the one label source */
check('every registry entry carries a non-empty label string',
    array_filter(PQM_Features::registry(), fn($entry) => !is_string($entry['label'] ?? null) || $entry['label'] === '') === []);
check("label('seo') === 'SEO', label('takeaways') === 'Takeaways'",
    PQM_Features::label('seo') === 'SEO' && PQM_Features::label('takeaways') === 'Takeaways');
check("label('foo') === 'foo' (unknown passes through raw, no ucfirst)", PQM_Features::label('foo') === 'foo');
$unknown_commands = ['foo', 'apply_takeaways', 'ping', ''];
foreach (['administrator', 'editor', 'author', 'contributor'] as $role_name) {
    foreach ([false, true] as $is_pro) {
        reset_world(['administrator', 'editor', 'author', 'contributor'], $is_pro, $role_name);
        PQM_Features::register_capabilities();
        $plan = $is_pro ? 'pro' : 'free';
        foreach ($unknown_commands as $command) {
            check(sprintf("%-13s %-4s why_not('%s') = 'role', can() false", $role_name, $plan, $command),
                PQM_Features::why_not($command) === 'role' && PQM_Features::can($command) === false);
            check(sprintf("%-13s %-4s plan_allows('%s') = false", $role_name, $plan, $command),
                PQM_Features::plan_allows($command) === false);
        }
    }
}
/* the pqm_is_pro override cannot open an unknown command either (free site, filter flips it pro) */
reset_world(['administrator', 'editor', 'author', 'contributor'], false, 'administrator');
PQM_Features::register_capabilities();
$GLOBALS['tw']['filter_pro'] = true;
check("mu-plugin override: why_not('foo') still 'role', plan_allows('foo') still false",
    PQM_Features::why_not('foo') === 'role' && PQM_Features::plan_allows('foo') === false);

/* ─── Row 7 (ticket 16): the README tells the same truth ─────────────── */
echo "Row 7 — README capability table === capabilities(); single-site stated\n";
$readme = file_get_contents(dirname(__DIR__, 2) . '/README.md');
/* Rows "| `pqm_x` | Administrator, Editor |" — the capability table only
   (the page table's rows start with a page name, not a capability). */
preg_match_all('/^\| `(pqm_[a-z_]+)` \| ([^|]+) \|\s*$/m', $readme, $rows, PREG_SET_ORDER);
$readme_caps = array_column($rows, 1);
$readme_cells = array_column($rows, 2, 1);
/* The README cell exactly as written: role keys ucfirst, joined by ", ". */
$expected_cells = array_map(static fn($roles) => implode(', ', array_map('ucfirst', $roles)), PQM_Features::capabilities());
check('README capability table: each capability exactly once, no extra pqm_ row, in capabilities() order',
    $readme_caps === array_keys($expected_cells));
check('README role cells === capabilities() written out exactly (case and order)',
    array_map('trim', $readme_cells) === $expected_cells);
check('README states single-site support (multisite unsupported)',
    str_contains($readme, '**Single-site only.**') && str_contains($readme, 'Multisite networks are not supported'));

/* ─── Row 8 (ticket 12): maybe_upgrade() grants only when the stored version is behind ── */
echo "Row 8 — PQM_Activator::maybe_upgrade() against pqm_caps_version\n";
/** Each role's capabilities array, as stored. */
function role_caps_snapshot() {
    return array_map(static fn($role) => $role->capabilities, $GLOBALS['tw']['roles']);
}
/** Role name list holding $cap. */
function holders_of($cap) {
    return array_keys(array_filter($GLOBALS['tw']['roles'], static fn($role) => !empty($role->capabilities[$cap])));
}

reset_world();
PQM_Activator::maybe_upgrade();
foreach ($default_map as $cap => $granted_roles) {
    check("option absent: $cap granted to exactly its default roles", holders_of($cap) === $granted_roles);
}
check('option absent: pqm_caps_version stored as CAPS_VERSION, one write',
    ($GLOBALS['tw']['options']['pqm_caps_version'] ?? null) === PQM_Features::CAPS_VERSION && $GLOBALS['tw']['option_writes'] === 1);

reset_world();
PQM_Features::register_capabilities();
$GLOBALS['tw']['options'] = ['pqm_caps_version' => (string) PQM_Features::CAPS_VERSION]; // real get_option returns the stored scalar as a string
$GLOBALS['tw']['option_writes'] = 0;
unset($GLOBALS['tw']['roles']['author']->capabilities['pqm_view_reviews']); // an admin removed it by hand
$before = role_caps_snapshot();
PQM_Activator::maybe_upgrade();
check('equal version: no grant — every role byte-identical (hand edit kept)', role_caps_snapshot() === $before);
check('equal version: no option write', $GLOBALS['tw']['option_writes'] === 0);

reset_world();
PQM_Features::register_capabilities();
/* Ceiling: with CAPS_VERSION = 1 this stores 0 — distinct from 'absent' only once CAPS_VERSION > 1. */
$GLOBALS['tw']['options'] = ['pqm_caps_version' => (string) (PQM_Features::CAPS_VERSION - 1)];
$GLOBALS['tw']['option_writes'] = 0;
unset($GLOBALS['tw']['roles']['author']->capabilities['pqm_view_reviews']); // a cap the older map lacked
PQM_Activator::maybe_upgrade();
check('lower version: re-grants (author holds pqm_view_reviews again)', holders_of('pqm_view_reviews') === $default_map['pqm_view_reviews']);
check('lower version: stores CAPS_VERSION, one write',
    $GLOBALS['tw']['options']['pqm_caps_version'] === PQM_Features::CAPS_VERSION && $GLOBALS['tw']['option_writes'] === 1);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
