<?php
/**
 * CLI harness for story 1.2 (role capabilities) — WP stubs, no WP install.
 * Persists the capability matrix verified in-session on 2026-10-01
 * (story-role-capabilities-replace-binary-checks-plan.md, Implementation
 * Notes + Review Triage Log): the ADR 0003 default map, register/remove
 * semantics, the why_not() role×command×plan matrix, and the
 * hidden/gated/allowed split of the registry. Story 14 appends the
 * registry-as-whitelist rows: score/apply explicit free-tier entries and
 * the fail-closed fallback for unknown commands. Story 42 (Roles tab) adds
 * the register rule — a cap's defaults only while no manage_options role
 * holds it, so activation and upgrade never reset a role — and the View as
 * retirement (its harness is gone; uninstall still deletes the meta).
 * Bug 94: Row 8 checks that maybe_upgrade() reloads the current user's
 * capabilities after a grant, and only then.
 */

define('ABSPATH', __DIR__ . '/');

/* ─── Mutable test world ─────────────────────────────────────────────── */
$GLOBALS['tw'] = [
    'roles'        => [],     // role_name => WP_Role (stub) objects
    'current_role' => null,   // whose capabilities current_user_can() reads
    'filter_pro'   => null,   // null = postquality_is_pro filter not hooked, bool = override
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
    public function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}
/* Bug 20: users with direct grants (their own capabilities meta). */
class WP_User {
    public $caps;
    public function __construct($caps = []) { $this->caps = $caps; }
    public function remove_cap($cap) { unset($this->caps[$cap]); }
    /* Bug 94 (memory/freemium-and-roles.md): each call appends the administrator role's capabilities at that moment to tw.reloads. */
    public function for_site() { $GLOBALS['tw']['reloads'][] = get_role('administrator')?->capabilities ?? []; }
}
function wp_get_current_user() { return new WP_User(); }
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
function postquality_get_settings() { return []; }
function apply_filters($tag, $value) {
    if ($tag === 'postquality_is_pro' && $GLOBALS['tw']['filter_pro'] !== null) {
        return $GLOBALS['tw']['filter_pro'];
    }
    return $value;
}
/* Ticket 12: maybe_upgrade() reads/writes postquality_caps_version through the bootstrap's options store; tw.writes counts the writes. */

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php

/* ─── Load plugin classes ────────────────────────────────────────────── */
$base = dirname(__DIR__, 2) . '/post-quality-manager';
require $base . '/includes/class-pqm-license.php';
require $base . '/includes/class-pqm-features.php';
require $base . '/includes/class-pqm-activator.php';
require $base . '/includes/class-pqm-n8n.php'; // bug 73: uninstall() revokes the setup map's Application Password through it

/* ─── Helpers ────────────────────────────────────────────────────────── */
function reset_world($role_names = ['administrator', 'editor', 'author', 'contributor'], $is_pro = false, $current_role = null) {
    $GLOBALS['tw'] = [
        'options'      => ['postquality_names_version' => 1], // story 91: the stored names already moved (the migration itself is stored-names-harness.php's)
        'roles'        => [],
        'current_role' => $current_role,
        'filter_pro'   => null,
    ];
    postquality_fs()->pro = $is_pro; // the SDK stand-in's license (story 44)
    foreach ($role_names as $name) {
        $GLOBALS['tw']['roles'][$name] = new WP_Role($name);
    }
}

/* The ADR 0003 access matrix — the shipped default map (story 1.2's contract). */
$default_map = [
    'postquality_view_reviews'    => ['administrator', 'editor', 'author'],
    'postquality_run_free_agents' => ['administrator', 'editor', 'author'],
    'postquality_run_paid_agents' => ['administrator', 'editor'],
    'postquality_manage_agents'   => ['administrator', 'editor'],
    'postquality_manage_settings' => ['administrator'],
];
$registry_commands = ['review', 'takeaways', 'rewrite', 'sources', 'seo', 'audit', 'score', 'apply'];
$paid_commands     = ['rewrite', 'sources', 'seo', 'audit'];

/* ─── Row 1: Default map ─────────────────────────────────────────────── */
echo "Row 1 — Fresh registration grants the ADR 0003 access matrix\n";
reset_world();
check('capabilities() equals the ADR 0003 access matrix', PostQuality_Features::capabilities() === $default_map);
PostQuality_Features::register_capabilities();
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
    PostQuality_Features::register_capabilities();
    foreach ($GLOBALS['tw']['roles'] as $name => $role) {
        if ($role->capabilities !== $before[$name]) { return false; }
    }
    return true;
})());

/* ─── Row 2: Missing role is skipped silently ────────────────────────── */
echo "Row 2 — A role absent on the site is skipped, no error\n";
reset_world(['administrator', 'contributor']); // editor + author removed from this site
PostQuality_Features::register_capabilities();
check('no error; administrator still gets all five caps',
    array_keys($GLOBALS['tw']['roles']['administrator']->capabilities) === array_keys($default_map));
check('absent roles stay absent', !isset($GLOBALS['tw']['roles']['editor'], $GLOBALS['tw']['roles']['author']));

/* ─── Row 3: Uninstall strip (explicit denies included) ──────────────── */
echo "Row 3 — remove_capabilities() leaves no postquality_* key on any role\n";
reset_world();
PostQuality_Features::register_capabilities();
/* a role editor stored an explicit deny (cap => false) — must be stripped too (review fix 2026-10-01) */
$GLOBALS['tw']['roles']['contributor']->capabilities['postquality_run_paid_agents'] = false;
$GLOBALS['tw']['roles']['author']->capabilities['postquality_view_reviews'] = false;
/* a non-pqm cap must survive the strip */
$GLOBALS['tw']['roles']['author']->capabilities['edit_posts'] = true;
PostQuality_Features::remove_capabilities();
$leftover = [];
foreach ($GLOBALS['tw']['roles'] as $name => $role) {
    foreach ($role->capabilities as $cap => $grant) {
        if (strpos($cap, 'postquality_') === 0) { $leftover[] = "$name:$cap"; }
    }
}
check('no postquality_* key on any role (grants and explicit denies)', $leftover === []);
check('non-pqm caps untouched', !empty($GLOBALS['tw']['roles']['author']->capabilities['edit_posts']));

/* ─── Row 3b (bug 20): direct user grants are stripped too ───────────── */
echo "Row 3b — remove_capabilities() leaves no postquality_* key in any user's own capabilities\n";
reset_world();
PostQuality_Features::register_capabilities();
$GLOBALS['tw']['users'] = [
    'quota-lifted author' => new WP_User(['author' => true, 'postquality_run_paid_agents' => true, 'edit_others_posts' => true]),
    'denied editor'       => new WP_User(['editor' => true, 'postquality_manage_agents' => false]),
];
PostQuality_Features::remove_capabilities();
check('the user query asks for exactly the five postquality caps',
    $GLOBALS['tw']['users_query']['capability__in'] === array_keys($default_map));
$leftover = [];
foreach ($GLOBALS['tw']['users'] as $name => $user) {
    foreach (array_keys($user->caps) as $cap) {
        if (strpos($cap, 'postquality_') === 0) { $leftover[] = "$name:$cap"; }
    }
}
check('no postquality_* key on any user (grants and explicit denies)', $leftover === []);
check('non-pqm direct grants and role keys survive',
    $GLOBALS['tw']['users']['quota-lifted author']->caps === ['author' => true, 'edit_others_posts' => true]
    && $GLOBALS['tw']['users']['denied editor']->caps === ['editor' => true]);

/* ─── Row 4: why_not() matrix — role × 8 commands × plan on/off ──────── */
echo "Row 4 — why_not() matrix (role before plan) over the 8 registry commands\n";
/* Story 1.2's verified matrix, cells unchanged. Story 14 made score/apply
 * explicit free-tier entries on postquality_run_free_agents (the cells they had as
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
    'author' => [ // lacks postquality_run_paid_agents: role refusal hides paid agents, plan never reached
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
        PostQuality_Features::register_capabilities();
        foreach ($cells as $command => $expected) {
            $actual = PostQuality_Features::why_not($command);
            check(sprintf('%-13s %-4s why_not(%s) = %s', $role_name, $plan, $command, var_export($expected, true)),
                $actual === $expected);
        }
        /* can() is why_not() === null in every cell */
        foreach ($cells as $command => $expected) {
            check(sprintf('%-13s %-4s can(%s) mirrors why_not()', $role_name, $plan, $command),
                PostQuality_Features::can($command) === ($expected === null));
        }
    }
}
/* the postquality_is_pro filter override still always wins over the provider */
reset_world(['administrator', 'editor', 'author', 'contributor'], false, 'editor');
PostQuality_Features::register_capabilities();
$GLOBALS['tw']['filter_pro'] = true;
check("mu-plugin override: why_not('seo') null for editor on an unlicensed site", PostQuality_Features::why_not('seo') === null);

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
    PostQuality_Features::register_capabilities();
    $hidden  = PostQuality_Features::hidden_commands();
    $gated   = PostQuality_Features::gated_commands();
    $allowed = array_values(array_filter($registry_commands, ['PostQuality_Features', 'can']));
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
$free_entry = ['tier' => 'free', 'capability' => 'postquality_run_free_agents'];
$paid_entry = ['tier' => 'paid', 'capability' => 'postquality_run_paid_agents'];
check('registry keys === the 8 commands', array_keys(PostQuality_Features::registry()) === $registry_commands);
check('review, takeaways, score, apply free on postquality_run_free_agents; rewrite, sources, seo, audit paid on postquality_run_paid_agents',
    array_map(fn($entry) => array_intersect_key($entry, $free_entry), PostQuality_Features::registry()) === [
        'review'  => $free_entry, 'takeaways' => $free_entry,
        'rewrite' => $paid_entry, 'sources'   => $paid_entry, 'seo' => $paid_entry, 'audit' => $paid_entry,
        'score'   => $free_entry, 'apply'     => $free_entry,
    ]);
/* Story 11: the registry is the one label source */
check('every registry entry carries a non-empty label string',
    array_filter(PostQuality_Features::registry(), fn($entry) => !is_string($entry['label'] ?? null) || $entry['label'] === '') === []);
check("label('seo') === 'SEO', label('takeaways') === 'Takeaways'",
    PostQuality_Features::label('seo') === 'SEO' && PostQuality_Features::label('takeaways') === 'Takeaways');
check("label('foo') === 'foo' (unknown passes through raw, no ucfirst)", PostQuality_Features::label('foo') === 'foo');
$unknown_commands = ['foo', 'apply_takeaways', 'ping', ''];
foreach (['administrator', 'editor', 'author', 'contributor'] as $role_name) {
    foreach ([false, true] as $is_pro) {
        reset_world(['administrator', 'editor', 'author', 'contributor'], $is_pro, $role_name);
        PostQuality_Features::register_capabilities();
        $plan = $is_pro ? 'pro' : 'free';
        foreach ($unknown_commands as $command) {
            check(sprintf("%-13s %-4s why_not('%s') = 'role', can() false", $role_name, $plan, $command),
                PostQuality_Features::why_not($command) === 'role' && PostQuality_Features::can($command) === false);
            check(sprintf("%-13s %-4s plan_allows('%s') = false", $role_name, $plan, $command),
                PostQuality_Features::plan_allows($command) === false);
        }
    }
}
/* the postquality_is_pro override cannot open an unknown command either (free site, filter flips it pro) */
reset_world(['administrator', 'editor', 'author', 'contributor'], false, 'administrator');
PostQuality_Features::register_capabilities();
$GLOBALS['tw']['filter_pro'] = true;
check("mu-plugin override: why_not('foo') still 'role', plan_allows('foo') still false",
    PostQuality_Features::why_not('foo') === 'role' && PostQuality_Features::plan_allows('foo') === false);

/* ─── Row 7 (ticket 16): the README tells the same truth ─────────────── */
echo "Row 7 — README capability table === capabilities(); single-site stated\n";
$readme = file_get_contents(dirname(__DIR__, 2) . '/README.md');
/* Rows "| `postquality_x` | Administrator, Editor |" — the capability table only
   (the page table's rows start with a page name, not a capability). */
preg_match_all('/^\| `(postquality_[a-z_]+)` \| ([^|]+) \|\s*$/m', $readme, $rows, PREG_SET_ORDER);
$readme_caps = array_column($rows, 1);
$readme_cells = array_column($rows, 2, 1);
/* The README cell exactly as written: role keys ucfirst, joined by ", ". */
$expected_cells = array_map(static fn($roles) => implode(', ', array_map('ucfirst', $roles)), PostQuality_Features::capabilities());
check('README capability table: each capability exactly once, no extra pqm_ row, in capabilities() order',
    $readme_caps === array_keys($expected_cells));
check('README role cells === capabilities() written out exactly (case and order)',
    array_map('trim', $readme_cells) === $expected_cells);
check('README states single-site support (multisite unsupported)',
    str_contains($readme, '**Single-site only.**') && str_contains($readme, 'Multisite networks are not supported'));

/* ─── Row 8 (ticket 12): maybe_upgrade() grants only when the stored version is behind ── */
echo "Row 8 — PostQuality_Activator::maybe_upgrade() against postquality_caps_version\n";
/** Each role's capabilities array, as stored. */
function role_caps_snapshot() {
    return array_map(static fn($role) => $role->capabilities, $GLOBALS['tw']['roles']);
}
/** Role name list holding $cap. */
function holders_of($cap) {
    return array_keys(array_filter($GLOBALS['tw']['roles'], static fn($role) => !empty($role->capabilities[$cap])));
}

reset_world();
PostQuality_Activator::maybe_upgrade();
foreach ($default_map as $cap => $granted_roles) {
    check("option absent: $cap granted to exactly its default roles", holders_of($cap) === $granted_roles);
}
check('option absent: postquality_caps_version stored as CAPS_VERSION, one write',
    ($GLOBALS['tw']['options']['postquality_caps_version'] ?? null) === PostQuality_Features::CAPS_VERSION && count($GLOBALS['tw']['writes'] ?? []) === 1);
check('option absent: the current user is reloaded once, after the grant: the administrator role then holds all five caps (bug 94)',
    count($GLOBALS['tw']['reloads'] ?? []) === 1 && array_keys(array_filter($GLOBALS['tw']['reloads'][0])) === array_keys($default_map));

reset_world();
PostQuality_Features::register_capabilities();
$GLOBALS['tw']['options'] = ['postquality_names_version' => 1, 'postquality_caps_version' => (string) PostQuality_Features::CAPS_VERSION]; // real get_option returns the stored scalar as a string
$GLOBALS['tw']['writes'] = [];
unset($GLOBALS['tw']['roles']['author']->capabilities['postquality_view_reviews']); // an admin removed it by hand
$before = role_caps_snapshot();
PostQuality_Activator::maybe_upgrade();
check('equal version: no grant — every role byte-identical (hand edit kept)', role_caps_snapshot() === $before);
check('equal version: no option write', count($GLOBALS['tw']['writes'] ?? []) === 0);
check('equal version: no reload of the current user (bug 94)', ($GLOBALS['tw']['reloads'] ?? []) === []);

/* Story 42 rewrote this case: a lower version grants only a capability no
 * manage_options role holds yet (one new in this version); a cap an
 * Administrator removed from a role by hand or on the Roles tab stays removed. */
reset_world();
$GLOBALS['tw']['roles']['administrator']->add_cap('manage_options');
PostQuality_Features::register_capabilities();
/* Ceiling: with CAPS_VERSION = 1 this stores 0 — distinct from 'absent' only once CAPS_VERSION > 1. */
$GLOBALS['tw']['options'] = ['postquality_names_version' => 1, 'postquality_caps_version' => (string) (PostQuality_Features::CAPS_VERSION - 1)];
$GLOBALS['tw']['writes'] = [];
unset($GLOBALS['tw']['roles']['author']->capabilities['postquality_view_reviews']); // set on the Roles tab
foreach ($GLOBALS['tw']['roles'] as $role) { $role->remove_cap('postquality_manage_agents'); } // as if new in this version
$before = role_caps_snapshot();
PostQuality_Activator::maybe_upgrade();
check('lower version: the hand-removed cap stays removed (author lacks postquality_view_reviews)',
    holders_of('postquality_view_reviews') === ['administrator', 'editor']);
check('lower version: a cap no manage_options role holds is granted to exactly its default roles',
    holders_of('postquality_manage_agents') === $default_map['postquality_manage_agents']);
$before['administrator']['postquality_manage_agents'] = $before['editor']['postquality_manage_agents'] = true;
check('lower version: nothing else changes on any role', role_caps_snapshot() == $before);
check('lower version: stores CAPS_VERSION, one write',
    $GLOBALS['tw']['options']['postquality_caps_version'] === PostQuality_Features::CAPS_VERSION && count($GLOBALS['tw']['writes'] ?? []) === 1);
check('lower version: the current user is reloaded once, after the grant: the administrator role then holds postquality_manage_agents (bug 94)',
    count($GLOBALS['tw']['reloads'] ?? []) === 1 && !empty($GLOBALS['tw']['reloads'][0]['postquality_manage_agents']));

/* ─── Row 9 (story 42): activation and upgrade keep the roles as set ─── */
echo "Row 9 — register_capabilities() grants a cap's defaults only while no manage_options role holds it\n";
/** A site as WordPress ships it: core caps, administrator holds manage_options, a custom role. */
function reset_real_site() {
    reset_world(['administrator', 'editor', 'author', 'contributor', 'writer']);
    $GLOBALS['tw']['roles']['administrator']->add_cap('manage_options');
    foreach (['administrator', 'editor', 'author', 'contributor', 'writer'] as $name) {
        $GLOBALS['tw']['roles'][$name]->add_cap('edit_posts');
    }
}
/* activate()'s capability step (create_table() needs a WP install); PHP 8.1+ invokes a private method as is. */
$activate_caps = new ReflectionMethod('PostQuality_Activator', 'register_capabilities');

reset_real_site();
$activate_caps->invoke(null);
foreach ($default_map as $cap => $granted_roles) {
    check("fresh install: $cap granted to exactly its default roles", holders_of($cap) === $granted_roles);
}

/* Roles set on the tab: Author stripped of View reviews, Editor given
 * Manage settings, the custom role given Run Review and Key Takeaways. */
$set_on_tab = static function () {
    reset_real_site();
    PostQuality_Features::register_capabilities();
    $GLOBALS['tw']['roles']['author']->remove_cap('postquality_view_reviews');
    $GLOBALS['tw']['roles']['editor']->add_cap('postquality_manage_settings');
    $GLOBALS['tw']['roles']['writer']->add_cap('postquality_run_free_agents');
    $GLOBALS['tw']['users'] = ['direct' => new WP_User(['author' => true, 'postquality_run_paid_agents' => true])];
    unset($GLOBALS['tw']['users_query']);
};
$set_on_tab();
$before = role_caps_snapshot();
$activate_caps->invoke(null);
check('re-activation: every role byte-identical (Author still lacks View reviews)', role_caps_snapshot() === $before);
$activate_caps->invoke(null);
check('second re-activation: still byte-identical', role_caps_snapshot() === $before);
foreach (['absent' => null, 'lower' => (string) (PostQuality_Features::CAPS_VERSION - 1)] as $label => $stored) {
    $set_on_tab();
    $GLOBALS['tw']['options'] = ['postquality_names_version' => 1] + ($stored === null ? [] : ['postquality_caps_version' => $stored]);
    $before = role_caps_snapshot();
    PostQuality_Activator::maybe_upgrade();
    check("upgrade, stored version $label: every role byte-identical", role_caps_snapshot() === $before);
}
check('no per-user grant read or touched (no user query)',
    !isset($GLOBALS['tw']['users_query']) && $GLOBALS['tw']['users']['direct']->caps === ['author' => true, 'postquality_run_paid_agents' => true]);

/* Capability-keyed, never a role name: any manage_options role counts. */
reset_real_site();
$GLOBALS['tw']['roles']['writer']->add_cap('manage_options');
$GLOBALS['tw']['roles']['writer']->add_cap('postquality_view_reviews');
PostQuality_Features::register_capabilities();
check('a custom manage_options role holding a cap keeps its defaults from being granted',
    holders_of('postquality_view_reviews') === ['writer'] && holders_of('postquality_run_free_agents') === ['administrator', 'editor', 'author']);
/* Documented ceiling: a cap stripped from every manage_options role is granted again. */
reset_real_site();
PostQuality_Features::register_capabilities();
$GLOBALS['tw']['roles']['administrator']->remove_cap('postquality_manage_settings');
PostQuality_Features::register_capabilities();
check('ceiling: stripped from every manage_options role, the defaults come back', holders_of('postquality_manage_settings') === ['administrator']);

/* ─── Row 10 (story 42): View as is retired ──────────────────────────── */
echo "Row 10 — no View as left in the plugin; uninstall (the SDK hook since story 44) removes everything\n";
$plugin_php = '';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() === 'php') { $plugin_php .= file_get_contents($file->getPathname()); }
}
check('class file gone', !file_exists($base . '/includes/class-pqm-view-as.php'));
/* View as identifiers only — never generic hook names (user_has_cap,
 * admin_bar_menu), which a future feature may legitimately use. */
check('no PQM_View_As, class-pqm-view-as, filter_caps or pqm_view_as switch action in any plugin PHP file',
    !str_contains($plugin_php, 'PQM_View_As') && !str_contains($plugin_php, 'class-pqm-view-as')
    && !str_contains($plugin_php, 'filter_caps') && !str_contains($plugin_php, 'action=pqm_view_as')
    && !str_contains($plugin_php, 'admin_post_pqm_view_as'));
$GLOBALS['tw_metadata_deletes'] = [];
$GLOBALS['tw_option_deletes'] = [];
$GLOBALS['tw_cleared_hooks'] = [];
$GLOBALS['wpdb'] = new class {
    public $prefix = 'wp_';
    public $queries = [];
    public function query($q) { $this->queries[] = $q; return true; }
    public function prepare($q, ...$a) { return vsprintf(str_replace('%i', '`%s`', $q), $a); }
};
function delete_option($option) { $GLOBALS['tw_option_deletes'][] = $option; return true; }
function delete_metadata($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false) {
    $GLOBALS['tw_metadata_deletes'][] = [$meta_type, $object_id, $meta_key, $meta_value, $delete_all];
    return true;
}
function wp_clear_scheduled_hook($hook, $args = [], $wp_error = false) { $GLOBALS['tw_cleared_hooks'][] = $hook; return 0; }
/* Bug 73: the Application Password the setup map names is revoked before the map's option goes. */
$GLOBALS['tw_app_revoked'] = [];
class WP_Application_Passwords {
    public static function delete_application_password($user_id, $uuid) {
        $GLOBALS['tw_app_revoked'][] = [$user_id, $uuid, 'deleted so far' => $GLOBALS['tw_option_deletes']];
        return true;
    }
}
reset_world();
$GLOBALS['tw']['options']['postquality_n8n_setup'] = ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c1', 'anthropic' => 'c2', 'wordpress' => 'c3'],
    'path' => 'pqm-execute-example-com', 'wizard_created' => true, 'at' => 1, 'application_password' => ['uuid' => 'app-uuid', 'user_id' => 7]];
PostQuality_Features::register_capabilities();
PostQuality_Activator::uninstall(); // story 44: the SDK's after_uninstall hook calls it
check('uninstall revokes the Application Password the setup map names (user id and uuid from the map), before postquality_n8n_setup is deleted (bug 73)',
    count($GLOBALS['tw_app_revoked']) === 1 && $GLOBALS['tw_app_revoked'][0][0] === 7 && $GLOBALS['tw_app_revoked'][0][1] === 'app-uuid'
    && !in_array('postquality_n8n_setup', $GLOBALS['tw_app_revoked'][0]['deleted so far'], true));
check('uninstall deletes the leftover pqm_view_as meta for all users',
    in_array(['user', 0, 'pqm_view_as', '', true], $GLOBALS['tw_metadata_deletes'], true));
/* Story 44: the cleanup moved from uninstall.php into PostQuality_Activator::uninstall(), unchanged. */
check('uninstall drops the reviews table under both names (story 91)', $GLOBALS['wpdb']->queries === ['DROP TABLE IF EXISTS `wp_postquality_reviews`', 'DROP TABLE IF EXISTS `wp_pqm_reviews`']);
check('uninstall deletes every option under both prefixes, new name then old, and the names-version flag (story 91)',
    $GLOBALS['tw_option_deletes'] === array_merge(...array_map(static fn($o) => [$o, str_replace('postquality_', 'pqm_', $o)], ['postquality_settings', 'postquality_db_version', 'postquality_workflow_version_seen',
        'postquality_cron_nudge_dismissed', 'postquality_caps_version', 'postquality_setup_progress', 'postquality_n8n_api', 'postquality_n8n_setup', 'postquality_n8n_leftovers', 'postquality_n8n_provisioning', 'postquality_ping'])) + [22 => 'postquality_names_version']);
$GLOBALS['tw_option_deletes'] = [];
$GLOBALS['tw_app_revoked'] = [];
$GLOBALS['tw_cleared_hooks'] = []; // the cron check below counts this second run's clear
unset($GLOBALS['tw']['options']['postquality_n8n_setup']);
PostQuality_Activator::uninstall();
check('uninstall without a setup map: no Application Password revoked, every option still deleted', $GLOBALS['tw_app_revoked'] === []
    && in_array('postquality_n8n_setup', $GLOBALS['tw_option_deletes'], true) && in_array('postquality_n8n_provisioning', $GLOBALS['tw_option_deletes'], true));
check('uninstall deletes the postquality_monthly_usage meta for all users',
    in_array(['user', 0, 'postquality_monthly_usage', '', true], $GLOBALS['tw_metadata_deletes'], true));
check('uninstall strips every cap of either prefix from every role', array_filter(array_map(
    static fn($role) => array_filter(array_keys($role->capabilities), static fn($c) => preg_match('/^(pqm|postquality)_/', $c)),
    $GLOBALS['tw']['roles'])) === []);
check('uninstall clears the review cron', $GLOBALS['tw_cleared_hooks'] === ['postquality_cron_review_posts', 'pqm_cron_review_posts']);
check('no uninstall.php left (WordPress would run it instead of the SDK hook)', !file_exists($base . '/uninstall.php'));

harness_exit();
