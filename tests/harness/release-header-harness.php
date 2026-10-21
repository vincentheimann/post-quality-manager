<?php
/**
 * CLI harness for story 4.1 (release tracer bullet) — no WP install.
 * Guards the release-please annotations in the plugin main file: the header
 * still parses the way WordPress reads it (get_file_data + its
 * _cleanup_header_comment trimming), the start/end block encloses exactly the
 * Version line, and only PQM_VERSION carries the inline annotation.
 * Story 4.2 adds row 6: header Version == PQM_VERSION == package.json; the
 * release workflow runs this harness before it builds and publishes.
 * Story 4.6 adds rows 4-5: the Domain Path header and the init-time loader,
 * and the tests/i18n/ fixtures still parse and still name live strings.
 * Row 7 (2026-10-13): readme.txt (Freemius refuses a zip without it) parses
 * the way the WordPress.org readme parser reads it, release-please's block
 * encloses exactly its Stable tag, and its headers agree with the plugin
 * header the way Plugin Check compares them.
 */

require __DIR__ . '/lib/wp-stubs.php'; // the runner (check(), harness_exit()); no plugin code is loaded here

$file = dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* WordPress get_file_data(): first 8 KB, CR → LF, one regex per header. */
function header_value($data, $name) {
    if (!preg_match('/^[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/mi', $data, $m)) {
        return null;
    }
    return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $m[1])); // _cleanup_header_comment()
}
$data = str_replace("\r", "\n", file_get_contents($file, false, null, 0, 8192));

echo "Row 1 — the header parses as WordPress reads it\n";
$version = header_value($data, 'Version');
check("Version is a bare semver (got '$version')", is_string($version) && preg_match('/^\d+\.\d+\.\d+$/', $version) === 1);
check('Plugin Name parses', header_value($data, 'Plugin Name') === 'Post Quality Manager');
check('Requires at least parses', preg_match('/^\d+\.\d+$/', (string) header_value($data, 'Requires at least')) === 1);
check('Requires PHP parses', preg_match('/^\d+\.\d+$/', (string) header_value($data, 'Requires PHP')) === 1);

echo "Row 2 — the block annotation encloses exactly the Version line\n";
$lines = explode("\n", str_replace("\r", '', file_get_contents($file)));
$starts = array_keys(array_filter($lines, static fn($l) => str_contains($l, 'x-release-please-start-version')));
$ends   = array_keys(array_filter($lines, static fn($l) => str_contains($l, 'x-release-please-end')));
check('one start marker and one end marker', count($starts) === 1 && count($ends) === 1);
check('exactly one line between them', count($starts) === 1 && count($ends) === 1 && $ends[0] - $starts[0] === 2);
check('that line is the header Version line',
    count($starts) === 1 && preg_match('/^[ \t\/*#@]*Version:/i', $lines[$starts[0] + 1]) === 1);

echo "Row 3 — inline annotation on PQM_VERSION only\n";
$define = static fn($name) => array_values(array_filter($lines, static fn($l) => str_contains($l, "define('$name'")))[0] ?? '';
check('PQM_VERSION line carries x-release-please-version', str_contains($define('PQM_VERSION'), 'x-release-please-version'));
check('PQM_WORKFLOW_VERSION line carries no annotation', $define('PQM_WORKFLOW_VERSION') !== ''
    && !str_contains($define('PQM_WORKFLOW_VERSION'), 'x-release-please'));

echo "Row 4 — translations load from languages/ on init\n";
check('Domain Path parses as /languages', header_value($data, 'Domain Path') === '/languages');
$src = file_get_contents($file);
check('pqm_load_textdomain() is hooked on init',
    preg_match("/add_action\(\s*'init'\s*,\s*'pqm_load_textdomain'\s*\)/", $src) === 1);
/* Story 54: the helper lives in includes/pqm-functions.php (the main file declares no function, see its Includes comment). */
$functions_src = file_get_contents(dirname($file) . '/includes/pqm-functions.php');
check('it loads the post-quality-manager domain from /languages',
    preg_match("/load_plugin_textdomain\(\s*'post-quality-manager'\s*,\s*false\s*,[^;]*'\/languages'\s*\)/", $functions_src) === 1);
$langDir = dirname($file) . '/languages';
check('no empty languages/ folder ships', !is_dir($langDir) || count(array_diff(scandir($langDir), ['.', '..'])) > 0);

echo "Row 5 — the tests/i18n fixtures parse and name strings the plugin still uses\n";
$root = dirname(__DIR__, 2);
$php = include $root . '/tests/i18n/post-quality-manager-de_DE.l10n.php';
check('PHP fixture returns a messages array', is_array($php) && is_array($php['messages'] ?? null) && $php['messages'] !== []);
$adminSrc = file_get_contents($root . '/post-quality-manager/admin/class-pqm-admin.php');
foreach (array_keys($php['messages'] ?? []) as $msgid) {
    check("PHP msgid '$msgid' is still a string in class-pqm-admin.php",
        str_contains($adminSrc, "__('" . $msgid . "', 'post-quality-manager')"));
}
$json = json_decode((string) file_get_contents($root . '/tests/i18n/post-quality-manager-de_DE-pqm-app.json'), true);
$jsMessages = array_diff_key($json['locale_data']['messages'] ?? [], ['' => true]);
check('script fixture is Jed JSON with messages', is_array($json) && $jsMessages !== []);
$jsSrc = implode("\n", array_map('file_get_contents', array_merge(glob($root . '/src/*.js'), glob($root . '/src/*/*.js'))));
foreach (array_keys($jsMessages) as $msgid) {
    check("JS msgid '$msgid' is still a string in src/", str_contains($jsSrc, "__( '" . $msgid . "'"));
}

echo "Row 6 — the three release versions agree\n";
preg_match("/define\('PQM_VERSION',\s*'([^']*)'\)/", $define('PQM_VERSION'), $m);
$constant = $m[1] ?? null;
$package = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/package.json'), true)['version'] ?? null;
check("header $version == PQM_VERSION $constant == package.json $package",
    $version !== null && $version === $constant && $constant === $package);

echo "Row 7 — readme.txt: headers as WordPress.org parses them, in step with the plugin header\n";
$readme_lines = explode("\n", str_replace("\r", '', (string) @file_get_contents(dirname($file) . '/readme.txt')));
check('readme.txt exists beside the main file', count($readme_lines) > 1);
/* The parser's header loop (wordpress.org plugin-directory readme/class-parser.php):
 * after the === name === line, a "key: value" line is a header (unknown keys are
 * dropped while no blank line came before them); the first other line ends it. */
$readme_name = preg_match('/^===\s*(.+?)\s*===$/', trim($readme_lines[0] ?? ''), $m) ? $m[1] : '';
$readme = [];
$short = '';
$after_blank = false;
foreach (array_slice($readme_lines, 1) as $i => $line) {
    $line = trim($line);
    if ($line === '') { $after_blank = true; continue; }
    if (!str_contains($line, ':') || str_starts_with($line, '#') || str_starts_with($line, '=')) { $short = $line; break; }
    [$key, $value] = array_map('trim', explode(':', $line, 2));
    $key = strtolower($key);
    if (in_array($key, ['contributors', 'tags', 'requires at least', 'tested up to', 'requires php', 'stable tag', 'license', 'license uri'], true)) {
        $readme[$key] = $value;
    } elseif ($after_blank) {
        $short = $line; break;
    }
    $after_blank = false;
}
check('name line equals the Plugin Name header', $readme_name !== '' && $readme_name === header_value($data, 'Plugin Name'));
check("Stable tag equals the header Version ('" . ($readme['stable tag'] ?? '') . "')", ($readme['stable tag'] ?? null) === $version);
check('Requires at least and Requires PHP equal the plugin header',
    ($readme['requires at least'] ?? null) === header_value($data, 'Requires at least')
    && ($readme['requires php'] ?? null) === header_value($data, 'Requires PHP'));
check('Tested up to is a major.minor, no patch (Plugin Check: invalid_tested_upto_minor)', preg_match('/^\d+\.\d+$/', $readme['tested up to'] ?? '') === 1);
check('License equals the plugin header License', ($readme['license'] ?? null) === header_value($data, 'License'));
check('Contributors is the wordpress.org account postquality', ($readme['contributors'] ?? null) === 'postquality');
check('at most 5 tags', ($readme['tags'] ?? '') !== '' && count(explode(',', $readme['tags'])) <= 5);
$tagline = preg_match('/^\*\*Tagline \(readme short description\):\*\* (.+)$/m', (string) file_get_contents($root . '/docs/brand.md'), $m) ? trim($m[1]) : null;
check('short description is the docs/brand.md tagline, verbatim, at most 150 characters', $short !== '' && $short === $tagline && preg_match_all('/./us', $short) <= 150);
$r_starts = array_keys(array_filter($readme_lines, static fn($l) => str_contains($l, 'x-release-please-start-version')));
$r_ends   = array_keys(array_filter($readme_lines, static fn($l) => str_contains($l, 'x-release-please-end')));
check('one release-please block, enclosing exactly the Stable tag line', count($r_starts) === 1 && count($r_ends) === 1
    && $r_ends[0] - $r_starts[0] === 2 && str_starts_with(trim($readme_lines[$r_starts[0] + 1]), 'Stable tag:'));
check('the marker lines sit inside the header block (no blank line around them), so the parser drops them',
    count($r_starts) === 1 && count($r_ends) === 1 && trim($readme_lines[$r_starts[0] - 1]) !== '' && trim($readme_lines[$r_ends[0] + 1]) !== '');
$rp = json_decode((string) file_get_contents($root . '/release-please-config.json'), true);
check('release-please-config.json lists readme.txt as a generic extra file', in_array(
    ['type' => 'generic', 'path' => 'post-quality-manager/readme.txt'], $rp['packages']['.']['extra-files'] ?? [], true));

harness_exit();
