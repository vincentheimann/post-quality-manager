<?php
/**
 * Prefix harness for backlog story 88 — every name the plugin defines in
 * code carries the postquality prefix (WordPress.org refuses prefixes of two
 * or three letters). No WP install, no plugin code loaded: the harness reads
 * the plugin's PHP (token_get_all) and JS (regexes) and enumerates the names
 * the plugin *defines*: classes, free functions, constants, globals, the
 * hooks it fires, its AJAX / admin-post actions, nonce actions, the option
 * group, script and style handles, the localized object and window globals,
 * admin page slugs (menu, Freemius, every `admin.php?page=` link), query
 * args and the custom DOM events the admin script and the islands exchange.
 * Each must start with the prefix of its kind and carry no `pqm` in any
 * case; a failure names the offender.
 *
 * Stored names (options, user meta, capabilities, the cron hook and
 * schedule, the table, the form field names) moved to `postquality_` with
 * story 91 (2026-10-26) and a one-time upgrade: Row 10 proves each new name
 * is in the tree and that every old `pqm_` name lives only in the legacy
 * map of PostQuality_Activator (a stray old name would read an empty option
 * or a capability nobody holds after the upgrade). One list stays `pqm`:
 * the permanent exceptions (owner, 2026-10-26: the webhook header, the REST
 * namespace, the template placeholders, the block marker, the per-site
 * webhook path prefix, the REST error codes) in Row 11, the same way.
 * PERMANENT_LITERALS never changes.
 */

require __DIR__ . '/lib/wp-stubs.php'; // the runner (check(), harness_exit()); no plugin code is loaded here

const PREFIX_CLASS    = 'PostQuality_';
const PREFIX_FUNCTION = 'postquality_';
const PREFIX_CONSTANT = 'POSTQUALITY_';
const PREFIX_SLUG     = 'postquality-';
const PREFIX_JS       = 'postquality';

/** Core hooks the plugin applies on its own content (not hooks it defines). */
const CORE_HOOKS_APPLIED = ['the_content'];
/** Core nonce actions and script handles the plugin uses as they are. */
const CORE_NONCE_ACTIONS = ['wp_rest'];
const CORE_HANDLES       = ['wp-components'];
/** Core globals the plugin reads with `global`; its own is the SDK accessor's. */
const CORE_GLOBALS = ['$wpdb', '$post'];
/** Query args WordPress itself sets on the plugin's pages. */
const CORE_QUERY_ARGS = ['page', 'action', '_wpnonce', '_wp_http_referer', 'settings-updated', 'tab'];

/** Stored names since story 91 (the new names the plugin writes; the one-time upgrade's own flag included). */
const STORED_LITERALS = [
    // options
    'postquality_settings', 'postquality_db_version', 'postquality_workflow_version_seen', 'postquality_cron_nudge_dismissed', 'postquality_caps_version',
    'postquality_setup_progress', 'postquality_n8n_api', 'postquality_n8n_setup', 'postquality_n8n_leftovers', 'postquality_n8n_provisioning', 'postquality_ping',
    'postquality_names_version',
    // user meta
    'postquality_monthly_usage', 'postquality_feedback_dismissed',
    // capabilities
    'postquality_view_reviews', 'postquality_run_free_agents', 'postquality_run_paid_agents', 'postquality_manage_agents', 'postquality_manage_settings',
    // cron hook and schedule, table, form field names
    'postquality_cron_review_posts', 'postquality_weekly', 'postquality_reviews', 'postquality_settings[',
];
/** The stored names of release 3.9.0 (main 9b8ac0f): read only by the upgrade and the uninstall, from PostQuality_Activator::LEGACY_NAMES. */
const LEGACY_LITERALS = [
    'pqm_settings', 'pqm_db_version', 'pqm_workflow_version_seen', 'pqm_cron_nudge_dismissed', 'pqm_caps_version',
    'pqm_setup_progress', 'pqm_n8n_api', 'pqm_n8n_setup', 'pqm_n8n_leftovers', 'pqm_n8n_provisioning', 'pqm_ping',
    'pqm_monthly_usage', 'pqm_feedback_dismissed', 'pqm_view_as',
    'pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents', 'pqm_manage_agents', 'pqm_manage_settings',
    'pqm_cron_review_posts', 'pqm_weekly', 'pqm_reviews',
];
/** Names kept as they are (owner, 2026-10-26): the wire and what sites and workflows already hold. */
const PERMANENT_LITERALS = [
    // webhook header, REST namespace, template placeholders, block marker in post content, per-site webhook path prefix
    'X-PQM-Token', 'post-quality/v1', '__PQM_SITE_HOST__', '__PQM_WEBHOOK_PATH__', 'pqmInsert', 'pqm-execute-',
    // WP_Error codes in REST responses (the workflow and the islands switch on them)
    'pqm_forbidden', 'pqm_feature_gated', 'pqm_quota_exceeded', 'pqm_not_configured', 'pqm_not_found', 'pqm_invalid_post',
];

$root   = dirname(__DIR__, 2);
$plugin = $root . '/post-quality-manager';

/** Plugin PHP files outside vendor/ and the git-ignored build. */
$php_files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS)) as $f) {
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($plugin) + 1));
    if ($f->getExtension() === 'php' && !str_starts_with($rel, 'vendor/') && !str_starts_with($rel, 'admin/build/')) {
        $php_files[$rel] = (string) file_get_contents($f->getPathname());
    }
}
ksort($php_files);
/** Plugin JS: the hand-written admin script and the React sources the bundle is built from. */
$js_files = [];
foreach (array_merge(glob($plugin . '/admin/js/*.js') ?: [], rglob($root . '/src', 'js')) as $f) {
    $js_files[str_replace('\\', '/', substr($f, strlen($root) + 1))] = (string) file_get_contents($f);
}
ksort($js_files);
$php_all = implode("\n", $php_files);
$js_all  = implode("\n", $js_files);

function rglob(string $dir, string $ext): array {
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->getExtension() === $ext) { $out[] = $f->getPathname(); }
    }
    sort($out);
    return $out;
}

/* ─── PHP: names defined by declaration ──────────────────────────────── */

/**
 * Classes, free functions (declared outside any class body, at any brace
 * depth: postquality_fs() sits inside a function_exists guard), constants (define()
 * and a top-level const), and globals — per file.
 */
function php_declared(string $src): array {
    $names = ['class' => [], 'function' => [], 'const' => [], 'global' => []];
    $t = token_get_all($src);
    $n = count($t);
    $sig = static function (int $i) use ($t, $n): int {
        for ($j = $i + 1; $j < $n; $j++) {
            if (!is_array($t[$j]) || !in_array($t[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { return $j; }
        }
        return $n - 1;
    };
    $stack = []; // 'class' | 'block' per open brace
    $pending_class = false;
    $prev = null; // previous significant token
    for ($i = 0; $i < $n; $i++) {
        $tok = $t[$i];
        if (is_array($tok)) {
            if (in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
            switch ($tok[0]) {
                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                case T_ENUM:
                    if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) { break; } // Foo::class
                    $pending_class = true;
                    $j = $sig($i);
                    if (is_array($t[$j]) && $t[$j][0] === T_STRING) { $names['class'][] = $t[$j][1]; }
                    break;
                case T_FUNCTION:
                    $j = $sig($i);
                    if ($t[$j] === '&') { $j = $sig($j); }
                    if (is_array($t[$j]) && $t[$j][0] === T_STRING && !in_array('class', $stack, true)) { $names['function'][] = $t[$j][1]; }
                    break;
                case T_CONST:
                    $j = $sig($i);
                    if (is_array($t[$j]) && $t[$j][0] === T_STRING && !in_array('class', $stack, true)) { $names['const'][] = $t[$j][1]; }
                    break;
                case T_GLOBAL:
                    for ($j = $i + 1; $j < $n && $t[$j] !== ';'; $j++) {
                        if (is_array($t[$j]) && $t[$j][0] === T_VARIABLE) { $names['global'][] = $t[$j][1]; }
                    }
                    break;
                case T_STRING:
                    if ($tok[1] === 'define' && $t[$sig($i)] === '(') {
                        $k = $sig($sig($i));
                        if (is_array($t[$k]) && $t[$k][0] === T_CONSTANT_ENCAPSED_STRING) { $names['const'][] = trim($t[$k][1], '\'"'); }
                    }
                    break;
                case T_CURLY_OPEN:
                case T_DOLLAR_OPEN_CURLY_BRACES:
                    $stack[] = 'block';
                    break;
            }
        } elseif ($tok === '{') {
            $stack[] = $pending_class ? 'class' : 'block';
            $pending_class = false;
        } elseif ($tok === '}') {
            array_pop($stack);
        }
        $prev = $tok;
    }
    return $names;
}

/**
 * Every call of $fn in $src as its argument list: each argument is the
 * unquoted string when it is one plain literal, else null. Nested calls
 * and arrays are skipped by depth, so __('Label', 'domain') is one argument.
 */
function php_calls(string $src, string $fn): array {
    $calls = [];
    $t = token_get_all($src);
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_STRING || $t[$i][1] !== $fn) { continue; }
        $prev = $i - 1;
        while ($prev >= 0 && is_array($t[$prev]) && $t[$prev][0] === T_WHITESPACE) { $prev--; }
        if ($prev >= 0 && is_array($t[$prev]) && in_array($t[$prev][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) { continue; } // a method or a declaration
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && $t[$j][0] === T_WHITESPACE) { $j++; }
        if ($j >= $n || $t[$j] !== '(') { continue; }
        $depth = 1; $args = []; $parts = [];
        for ($k = $j + 1; $k < $n && $depth > 0; $k++) {
            $tok = $t[$k];
            if ($tok === '(' || $tok === '[' || $tok === '{' || (is_array($tok) && in_array($tok[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($tok === ')' || $tok === ']' || $tok === '}') { $depth--; }
            if ($depth === 0 || ($depth === 1 && $tok === ',')) {
                $args[] = count($parts) === 1 && is_array($parts[0]) && $parts[0][0] === T_CONSTANT_ENCAPSED_STRING ? trim($parts[0][1], '\'"') : null;
                $parts = [];
                continue;
            }
            if (!(is_array($tok) && in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) { $parts[] = $tok; }
        }
        $calls[] = $args;
    }
    return $calls;
}

/** Unique, sorted. */
function uniq(array $names): array { $names = array_values(array_unique($names)); sort($names); return $names; }

/** The names of $names that do not start with $prefix, as "name" strings for the failure line. */
function offenders(array $names, string $prefix): array {
    return array_values(array_filter($names, static fn(string $x) => !str_starts_with($x, $prefix)));
}
function describe(array $offenders): string { return $offenders ? ' — offenders: ' . implode(', ', $offenders) : ''; }

/* ─── Collect ────────────────────────────────────────────────────────── */

$classes = $functions = $constants = $globals = [];
foreach ($php_files as $rel => $src) {
    $d = php_declared($src);
    $classes   = array_merge($classes, $d['class']);
    $functions = array_merge($functions, $d['function']);
    $constants = array_merge($constants, $d['const']);
    $globals   = array_merge($globals, $d['global']);
}
$classes = uniq($classes); $functions = uniq($functions); $constants = uniq($constants); $globals = uniq(array_diff($globals, CORE_GLOBALS));

$hooks = [];
foreach (['do_action', 'apply_filters'] as $fn) {
    foreach (php_calls($php_all, $fn) as $args) { if (($args[0] ?? null) !== null) { $hooks[] = $args[0]; } }
}
$hooks = uniq(array_diff($hooks, CORE_HOOKS_APPLIED));

$actions = [];
foreach (['add_action', 'add_filter'] as $fn) {
    foreach (php_calls($php_all, $fn) as $args) {
        if (preg_match('/^(wp_ajax_nopriv_|wp_ajax_|admin_post_nopriv_|admin_post_)(.+)$/', (string) ($args[0] ?? ''), $m)) { $actions[] = $m[2]; }
    }
}
preg_match_all('/admin-post\.php\?action=([A-Za-z0-9_-]+)/', $php_all, $m);
$actions = array_merge($actions, $m[1]);
preg_match_all("/'action',\s*'([A-Za-z0-9_-]+)'/", $js_all, $m); // form.append('action', '…') in the admin script
$actions = uniq(array_merge($actions, $m[1]));

$nonces = [];
foreach (['wp_create_nonce', 'check_admin_referer', 'check_ajax_referer', 'wp_nonce_field'] as $fn) {
    foreach (php_calls($php_all, $fn) as $args) { if (($args[0] ?? null) !== null) { $nonces[] = $args[0]; } }
}
foreach (['wp_verify_nonce', 'wp_nonce_url'] as $fn) {
    foreach (php_calls($php_all, $fn) as $args) { if (($args[1] ?? null) !== null) { $nonces[] = $args[1]; } }
}
$nonces = uniq(array_diff($nonces, CORE_NONCE_ACTIONS));

$groups = [];
foreach (['register_setting', 'settings_fields'] as $fn) {
    foreach (php_calls($php_all, $fn) as $args) { if (($args[0] ?? null) !== null) { $groups[] = $args[0]; } }
}
foreach (php_calls($php_all, 'add_filter') as $args) {
    if (preg_match('/^option_page_capability_(.+)$/', (string) ($args[0] ?? ''), $m)) { $groups[] = $m[1]; }
}
$groups = uniq($groups);

$handles = [];
foreach (['wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style', 'wp_localize_script', 'wp_set_script_translations', 'wp_style_add_data', 'wp_add_inline_script', 'wp_script_add_data'] as $fn) {
    foreach (php_calls($php_all, $fn) as $args) { if (($args[0] ?? null) !== null) { $handles[] = $args[0]; } }
}
$handles = uniq(array_diff($handles, CORE_HANDLES));

$js_globals = [];
foreach (php_calls($php_all, 'wp_localize_script') as $args) { if (($args[1] ?? null) !== null) { $js_globals[] = $args[1]; } }
preg_match_all('/window\.([A-Za-z_$][A-Za-z0-9_$]*)\s*=[^=]/', $php_all . "\n" . $js_all, $m);
$js_globals = uniq(array_merge($js_globals, $m[1]));
$localized = uniq(array_filter(array_map(static fn($a) => $a[1] ?? null, php_calls($php_all, 'wp_localize_script'))));

$slugs = [];
foreach (php_calls($php_all, 'add_menu_page') as $args) { if (($args[3] ?? null) !== null) { $slugs[] = $args[3]; } }
foreach (php_calls($php_all, 'add_submenu_page') as $args) {
    if (($args[0] ?? null) !== null) { $slugs[] = $args[0]; }
    if (($args[4] ?? null) !== null) { $slugs[] = $args[4]; }
}
preg_match_all("/'menu'\s*=>\s*\[\s*'slug'\s*=>\s*'([^']+)'/", $php_all, $m); // the Freemius menu slug (the Account page hangs under it)
$slugs = array_merge($slugs, $m[1]);
preg_match_all('/admin\.php\?page=([A-Za-z0-9_-]+)/', $php_all . "\n" . $js_all, $m);
$slugs = array_merge($slugs, $m[1]);
preg_match_all("/\bpage:\s*'([A-Za-z0-9_-]+)'/", $js_all, $m); // addQueryArgs( 'admin.php', { page: '…' } )
$slugs = uniq(array_merge($slugs, $m[1]));

$query_args = [];
preg_match_all("/\\\$_GET\[\s*'([^']+)'\s*\]/", $php_all, $m);
$query_args = array_merge($query_args, $m[1]);
preg_match_all("/const\s+[A-Z_]*QUERY_ARG\s*=\s*'([^']+)'/", $php_all, $m);
$php_setup_arg = $m[1];
preg_match_all("/const\s+[A-Z_]*_ARG\s*=\s*'([^']+)'/", $js_all, $m); // src/setup/index.js OPEN_ARG, the welcome notice's link read on the client
$js_open_arg = $m[1];
$query_args = uniq(array_diff(array_merge($query_args, $php_setup_arg, $js_open_arg), CORE_QUERY_ARGS));

/** Custom DOM events (`name:detail`) the admin script, the islands and the tests dispatch or listen to. */
preg_match_all("/(?:new\s+CustomEvent|new\s+Event|addEventListener|removeEventListener|dispatchEvent)\s*\(\s*'([A-Za-z0-9_-]+:[A-Za-z0-9_-]+)'/", $js_all, $m);
preg_match_all("/\[\s*((?:'[A-Za-z0-9_-]+:[A-Za-z0-9_-]+'\s*,?\s*)+)\]\s*\.forEach/", $js_all, $lists); // ['a:b', 'c:d'].forEach(type => addEventListener(type…))
preg_match_all("/'([A-Za-z0-9_-]+:[A-Za-z0-9_-]+)'/", implode(' ', $lists[1]), $listed);
$events = uniq(array_merge($m[1], $listed[1]));

/* ─── Rows ───────────────────────────────────────────────────────────── */

echo "Row 1 — names declared in PHP (" . count($php_files) . " files)\n";
check(count($classes) . ' classes start with ' . PREFIX_CLASS . describe($o = offenders($classes, PREFIX_CLASS)), !$o);
check(count($functions) . ' free functions start with ' . PREFIX_FUNCTION . describe($o = offenders($functions, PREFIX_FUNCTION)), !$o);
check(count($constants) . ' constants start with ' . PREFIX_CONSTANT . describe($o = offenders($constants, PREFIX_CONSTANT)), !$o);
check(count($globals) . ' globals start with $' . PREFIX_FUNCTION . ' (core: ' . implode(', ', CORE_GLOBALS) . ')' . describe($o = offenders($globals, '$' . PREFIX_FUNCTION)), !$o);
check('the inventory is not empty (the parser read the plugin)', count($classes) >= 9 && count($functions) >= 20 && count($constants) >= 8 && count($globals) >= 1);

echo "Row 2 — hooks the plugin fires (do_action, apply_filters; core hooks it applies: " . implode(', ', CORE_HOOKS_APPLIED) . ")\n";
check(count($hooks) . ' hooks start with ' . PREFIX_FUNCTION . describe($o = offenders($hooks, PREFIX_FUNCTION)), !$o && count($hooks) >= 4);

echo "Row 3 — AJAX and admin-post actions (hook suffix, admin-post.php?action=, the admin script's action values)\n";
check(count($actions) . ' actions start with ' . PREFIX_FUNCTION . describe($o = offenders($actions, PREFIX_FUNCTION)), !$o && count($actions) >= 3);

echo "Row 4 — nonce actions (core: " . implode(', ', CORE_NONCE_ACTIONS) . ")\n";
check(count($nonces) . ' nonce actions start with ' . PREFIX_FUNCTION . describe($o = offenders($nonces, PREFIX_FUNCTION)), !$o && count($nonces) >= 3);

echo "Row 5 — the settings group (register_setting, settings_fields, option_page_capability_*)\n";
check(count($groups) . ' option group names start with ' . PREFIX_FUNCTION . describe($o = offenders($groups, PREFIX_FUNCTION)), !$o && count($groups) === 1);

echo "Row 6 — script and style handles, the localized object, window globals (" . count($js_files) . " JS files)\n";
check(count($handles) . ' handles start with ' . PREFIX_SLUG . describe($o = offenders($handles, PREFIX_SLUG)), !$o && count($handles) >= 2);
check(count($js_globals) . ' JS globals start with ' . PREFIX_JS . describe($o = offenders($js_globals, PREFIX_JS)), !$o && count($js_globals) >= 2);
check('the admin script reads the localized object by the name PHP localizes (' . implode(', ', $localized) . ')',
    $localized !== [] && !array_filter($localized, static fn(string $name) => !str_contains($js_files['post-quality-manager/admin/js/pqm-admin.js'] ?? '', $name . '.')));

echo "Row 7 — admin page slugs (menu pages, the Freemius menu slug, every admin.php?page= link in PHP and JS)\n";
check(count($slugs) . ' page slugs start with ' . PREFIX_SLUG . describe($o = offenders($slugs, PREFIX_SLUG)), !$o && count($slugs) >= 4);

echo "Row 8 — query args the plugin reads (core: " . implode(', ', CORE_QUERY_ARGS) . ")\n";
check(count($query_args) . ' query args start with ' . PREFIX_FUNCTION . describe($o = offenders($query_args, PREFIX_FUNCTION)), !$o && count($query_args) >= 2);
check('the JS OPEN_ARG (' . implode(', ', $js_open_arg) . ') equals PHP SETUP_QUERY_ARG (' . implode(', ', $php_setup_arg) . ')',
    count($php_setup_arg) === 1 && $js_open_arg === $php_setup_arg);

echo "Row 8b — custom DOM events (new CustomEvent, addEventListener, dispatchEvent literals with a colon)\n";
check(count($events) . ' event names start with ' . PREFIX_JS . ':' . describe($o = offenders($events, PREFIX_JS . ':')), !$o && count($events) >= 4);

echo "Row 9 — no defined name carries pqm in any case\n";
$every = array_merge($classes, $functions, $constants, $globals, $hooks, $actions, $nonces, $groups, $handles, $js_globals, $slugs, $query_args, $events);
$pqm = uniq(array_filter($every, static fn(string $x) => stripos($x, 'pqm') !== false));
check(count($every) . ' defined names, none containing pqm' . describe($pqm), !$pqm);

$tree = $php_all . "\n" . $js_all . "\n" . (string) file_get_contents($plugin . '/templates/n8n-workflow.json');
/* A whole token; a literal ending in `[` or `-` is a prefix (form field names, the webhook path) and may continue. */
$present = static fn(string $lit): bool => (bool) preg_match('/(?<![A-Za-z0-9_-])' . preg_quote($lit, '/') . (preg_match('/[A-Za-z0-9_]$/', $lit) ? '(?![A-Za-z0-9_])' : '') . '/', $tree);

echo "Row 10 — stored names carry postquality_ (options, user meta, capabilities, cron, table, form fields); the old names live only in the legacy map\n";
$missing = array_values(array_filter(STORED_LITERALS, static fn(string $lit) => !$present($lit)));
check(count(STORED_LITERALS) . ' stored literals in the tree' . ($missing ? ' — missing: ' . implode(', ', $missing) : ''), !$missing);
preg_match('/private const LEGACY_NAMES = \[.*?\n    \];/s', $php_files['includes/class-pqm-activator.php'] ?? '', $m);
$legacy_map = $m[0] ?? '';
$in_map = static fn(string $lit): bool => (bool) preg_match('/\'' . preg_quote($lit, '/') . '\'/', $legacy_map);
$missing = array_values(array_filter(LEGACY_LITERALS, static fn(string $lit) => !$in_map($lit)));
check(count(LEGACY_LITERALS) . ' legacy literals named in PostQuality_Activator::LEGACY_NAMES' . ($missing ? ' — missing: ' . implode(', ', $missing) : ''), $legacy_map !== '' && !$missing);
$outside_map = str_replace($legacy_map, '', $tree);
$strays = array_values(array_filter(LEGACY_LITERALS, static fn(string $lit): bool => (bool) preg_match('/(?<![A-Za-z0-9_-])' . preg_quote($lit, '/') . '(?![A-Za-z0-9_])/', $outside_map)));
check('no pqm_ stored name anywhere else in the plugin PHP/JS and the template (an old name read after the upgrade finds nothing)' . describe($strays), !$strays);

echo "Row 11 — permanent exceptions (the wire, the block marker, the REST error codes) are untouched\n";
$missing = array_values(array_filter(PERMANENT_LITERALS, static fn(string $lit) => !$present($lit)));
check(count(PERMANENT_LITERALS) . ' permanent literals still in the tree' . ($missing ? ' — missing: ' . implode(', ', $missing) : ''), !$missing);
preg_match_all("/new\s+WP_Error\s*\(\s*'([^']+)'/", $php_all, $m);
$codes = uniq($m[1]);
$renamed_codes = array_values(array_filter($codes, static fn(string $c) => str_starts_with($c, PREFIX_FUNCTION)));
check(count($codes) . ' WP_Error codes, every one still pqm_ (the workflow and the islands switch on them)' . describe($renamed_codes), !$renamed_codes && count($codes) >= 6);

harness_exit();
