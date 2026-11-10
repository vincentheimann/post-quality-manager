<?php
/**
 * CLI harness — story 70: the Add Plugins preview, bin/directory-preview.php,
 * a development-only must-use plugin. The plan's I/O matrix without
 * WordPress: which `plugins_api` requests it answers (the plugin's search,
 * its slug's details), that every other request gets its incoming result
 * back untouched, and that a preview not set up (Plugin Check inactive, any
 * of the five Markdown files missing) does the same after one error_log line
 * naming what is missing. The record itself needs Plugin Check's parser, which CI lacks:
 * the recipe in docs/local-verification.md, "Add Plugins preview (story 70)",
 * proves it on the showcase.
 * Run: php directory-preview-harness.php
 */

define('ABSPATH', __DIR__ . '/');
define('WP_PLUGIN_DIR', dirname(__DIR__, 2)); // holds post-quality-manager/readme.txt, so only the parser and the Markdown are missing
define('WPMU_PLUGIN_URL', 'https://example.test/wp-content/mu-plugins');

/* add_filter records its registrations (the shared stub is a no-op). */
$GLOBALS['pqm_filters'] = [];
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['pqm_filters'][] = [$tag, $callback, $priority, $accepted_args]; return true; }

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs
require dirname(__DIR__, 2) . '/bin/directory-preview.php';

/* error_log() goes to a temporary file the rows read. */
$log = tempnam(sys_get_temp_dir(), 'pqm');
ini_set('error_log', $log);
function log_lines(): array {
    global $log;
    clearstatcache();
    // PHP_EOL ends each line (\r\n on Windows); the "[date time] " prefix is dropped, so rows compare the message only.
    $lines = array_values(array_filter(preg_replace('/^\[[^\]]*\] /', '', preg_split('/\R/', (string) file_get_contents($log)))));
    file_put_contents($log, '');
    return $lines;
}

$slug = 'post-quality-manager';

echo "\nThe filter\n";
check('registered on plugins_api, last (PHP_INT_MAX), with its three arguments',
    $GLOBALS['pqm_filters'] === [['plugins_api', 'postquality_directory_preview_answer', PHP_INT_MAX, 3]]);

echo "\nRow 1: a search for the plugin gets its card\n";
// post%20… is the AJAX search's form: updates.js sends encodeURIComponent( value ) and the list table reads `s` as it came.
foreach (['Post Quality Manager', 'post quality', 'post%20quality', 'post%20quality%20manager', $slug, 'Post-Quality-Manager', 'post-quality', '  POST   Quality '] as $term) {
    check("search '$term' → search", postquality_directory_preview_route('query_plugins', (object) ['search' => $term, 'page' => 1, 'per_page' => 36]) === 'search');
}

echo "\nRow 2: any other list keeps its incoming result\n";
$other_lists = [
    "search 'post'"                  => ['search' => 'post'],
    "search 'quality manager'"       => ['search' => 'quality manager'],
    "search 'post quality managers'" => ['search' => 'post quality managers'],
    "search 'seo'"                   => ['search' => 'seo'],
    "search ''"                      => ['search' => ''],
    'search not a string'            => ['search' => ['post quality']],
    "tag '$slug'"                    => ['tag' => $slug],
    "author 'postquality'"           => ['author' => 'postquality'],
    "browse 'popular'"               => ['browse' => 'popular'],
    "browse 'recommended'"           => ['browse' => 'recommended', 'installed_plugins' => [$slug]],
];
foreach ($other_lists as $label => $args) {
    check("query_plugins with $label → passes", postquality_directory_preview_route('query_plugins', (object) $args) === '');
}

echo "\nRow 3: the details of the slug get the record\n";
check("plugin_information '$slug' (object) → details", postquality_directory_preview_route('plugin_information', (object) ['slug' => $slug]) === 'details');
check("plugin_information '$slug' (array) → details", postquality_directory_preview_route('plugin_information', ['slug' => $slug]) === 'details');

echo "\nRow 4: anything else keeps its incoming result\n";
$elsewhere = [
    "plugin_information 'plugin-check'"   => ['plugin_information', ['slug' => 'plugin-check']],
    "plugin_information 'Post Quality Manager'" => ['plugin_information', ['slug' => 'Post Quality Manager']],
    'plugin_information without a slug'   => ['plugin_information', []],
    "hot_tags"                            => ['hot_tags', ['number' => 10]],
    "query_plugins with the slug only"    => ['query_plugins', ['slug' => $slug]],
    "an unknown action with the search"   => ['other_action', ['search' => 'post quality', 'slug' => $slug]],
];
foreach ($elsewhere as $label => [$action, $args]) {
    check("$label → passes", postquality_directory_preview_route($action, (object) $args) === '');
}

echo "\nRows 2 and 4 through the filter: the incoming result, untouched, and no log line\n";
$incoming = [
    'false (nothing answered yet)'  => false,
    'another filter\'s object'      => (object) ['plugins' => [['slug' => 'other']], 'info' => ['results' => 1]],
    'a WP_Error'                    => new WP_Error('plugins_api_failed', 'An unexpected error occurred.'),
];
$requests = array_merge(
    array_map(static fn($args) => ['query_plugins', (object) $args], $other_lists),
    array_map(static fn($pair) => [$pair[0], (object) $pair[1]], $elsewhere)
);
foreach ($incoming as $label => $result) {
    $same = true;
    foreach ($requests as [$action, $args]) {
        $same = $same && postquality_directory_preview_answer($result, $action, $args) === $result;
    }
    check("$label comes back identical on all " . count($requests) . ' requests', $same);
}
check('no error_log line', log_lines() === []);

echo "\nRow 5: the preview not set up keeps the incoming result, with one error_log line\n";
// Plugin Check's parser is not loaded here, and bin/ holds no postquality-directory-preview/ folder.
check('bin/postquality-directory-preview/ does not exist (the harness relies on it)', !file_exists(dirname(__DIR__, 2) . '/bin/postquality-directory-preview'));
$sentinel = (object) ['from' => 'an earlier filter'];
foreach (['the search' => ['query_plugins', (object) ['search' => 'post quality']], 'the details' => ['plugin_information', (object) ['slug' => $slug]]] as $label => [$action, $args]) {
    check("Plugin Check inactive and no Markdown: $label keeps the incoming object", postquality_directory_preview_answer($sentinel, $action, $args) === $sentinel);
    check("… and false stays false", postquality_directory_preview_answer(false, $action, $args) === false);
    $lines = log_lines();
    check('… one line per request, naming Plugin Check and the Markdown, not the readme', count($lines) === 2
        && str_contains($lines[0], 'Post Quality directory preview: missing') && str_contains($lines[0], 'Plugin Check')
        && str_contains($lines[0], "directory's Markdown") && !str_contains($lines[0], "plugin's readme") && $lines[0] === $lines[1]);
}
check('… and the Markdown line names all five files', isset($lines[0]) && count(array_filter(POSTQUALITY_PREVIEW_MARKDOWN, static fn($f) => str_contains($lines[0], $f))) === 5);
class PQM_Harness_Parser {}
class_alias('PQM_Harness_Parser', 'WordPress\Plugin_Check\Lib\Readme\Parser'); // Plugin Check active
check('Plugin Check active, no Markdown: the details keep the incoming object', postquality_directory_preview_answer($sentinel, 'plugin_information', (object) ['slug' => $slug]) === $sentinel);
$lines = log_lines();
check('… one line, naming the Markdown only', count($lines) === 1 && str_contains($lines[0], "directory's Markdown") && !str_contains($lines[0], 'Plugin Check'));

echo "\nRow 5, a partial download: every one of the five Markdown files is checked, so none is a fatal\n";
// The record builder on a folder holding only some of the five (empty files): it must stop before any require.
check('the five files: the class and the four Michelf files it loads', count(POSTQUALITY_PREVIEW_MARKDOWN) === 5
    && POSTQUALITY_PREVIEW_MARKDOWN[0] === 'class-markdown.php'
    && count(preg_grep('#^libs/michelf-php-markdown-1\.6\.0/Michelf/[A-Za-z.]+\.php$#', POSTQUALITY_PREVIEW_MARKDOWN)) === 4);
$partial = sys_get_temp_dir() . '/pqm-preview-' . bin2hex(random_bytes(4));
$made    = [];
$cases   = [
    'the class alone'             => ['class-markdown.php'],
    'all but MarkdownExtra.inc.php' => array_values(array_diff(POSTQUALITY_PREVIEW_MARKDOWN, ['libs/michelf-php-markdown-1.6.0/Michelf/MarkdownExtra.inc.php'])),
    'all but Markdown.php'        => array_values(array_diff(POSTQUALITY_PREVIEW_MARKDOWN, ['libs/michelf-php-markdown-1.6.0/Michelf/Markdown.php'])),
    'the four Michelf files, no class' => array_slice(POSTQUALITY_PREVIEW_MARKDOWN, 1),
];
foreach ($cases as $label => $present) {
    foreach ($present as $file) {
        $path = "$partial/$file";
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0777, true); }
        if (!file_exists($path)) { file_put_contents($path, ''); $made[] = $path; }
    }
    foreach (array_diff(POSTQUALITY_PREVIEW_MARKDOWN, $present) as $file) { @unlink("$partial/$file"); }
    $absent = array_values(array_diff(POSTQUALITY_PREVIEW_MARKDOWN, $present));
    check("$label: no record", postquality_directory_preview_record($partial, 'https://example.test/x') === null);
    $lines = log_lines();
    check("… one line naming exactly the missing " . count($absent) . ', the Markdown only', count($lines) === 1
        && preg_match('/missing the directory\'s Markdown in .*\(([^()]*)\)\. The request keeps its own answer\.$/', $lines[0], $m) === 1
        && explode(', ', $m[1]) === $absent);
}
/* Remove what the harness made: the files, then the folders, deepest first. */
foreach ($made as $path) { @unlink($path); }
foreach (['libs/michelf-php-markdown-1.6.0/Michelf', 'libs/michelf-php-markdown-1.6.0', 'libs', ''] as $sub) { @rmdir(rtrim("$partial/$sub", '/')); }
check('the partial folders are removed', !file_exists($partial));

@unlink($log);
harness_exit();
