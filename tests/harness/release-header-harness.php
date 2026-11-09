<?php
/**
 * CLI harness for story 4.1 (release tracer bullet) — no WP install.
 * Guards the release-please annotations in the plugin main file: the header
 * still parses the way WordPress reads it (get_file_data + its
 * _cleanup_header_comment trimming), the start/end block encloses exactly the
 * Version line, and only POSTQUALITY_VERSION carries the inline annotation.
 * Story 4.2 adds row 6: header Version == POSTQUALITY_VERSION == package.json; the
 * release workflow runs this harness before it builds and publishes.
 * Story 4.6 added rows 4-5; story 95 (D2) turned row 4 around: translations
 * come from the language packs in WP_LANG_DIR/plugins, so no Domain Path
 * header, no load_plugin_textdomain() outside vendor/, no path argument on the
 * admin app's script translations and no languages/ folder. Row 5: the
 * tests/i18n/ fixtures still parse and still name live strings.
 * Row 7 (2026-10-13): readme.txt (Freemius refuses a zip without it) parses
 * the way the WordPress.org readme parser reads it, release-please's block
 * encloses exactly its Stable tag, and its headers agree with the plugin
 * header the way Plugin Check compares them. Story 95 adds the directory's
 * 10 KB cap (10,240 bytes, read as the file's byte size).
 * Row 8 (story 118, ADR 0018): every php-version in ci.yml's php job is at
 * least the header's Requires PHP, and the header's value is among them, so
 * CI keeps running the checks on the floor when the floor moves.
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

echo "Row 3 — inline annotation on POSTQUALITY_VERSION only\n";
$define = static fn($name) => array_values(array_filter($lines, static fn($l) => str_contains($l, "define('$name'")))[0] ?? '';
check('POSTQUALITY_VERSION line carries x-release-please-version', str_contains($define('POSTQUALITY_VERSION'), 'x-release-please-version'));
check('POSTQUALITY_WORKFLOW_VERSION line carries no annotation', $define('POSTQUALITY_WORKFLOW_VERSION') !== ''
    && !str_contains($define('POSTQUALITY_WORKFLOW_VERSION'), 'x-release-please'));

echo "Row 4 — translations come from language packs (story 95, D2)\n";
check('no Domain Path header', header_value($data, 'Domain Path') === null);
$plugin_dir = dirname($file);
$loaders = [];
$script_translations = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir, FilesystemIterator::SKIP_DOTS)) as $f) {
    $rel = str_replace('\\', '/', substr((string) $f, strlen($plugin_dir) + 1));
    if (str_starts_with($rel, 'vendor/') || !str_ends_with($rel, '.php')) {
        continue;
    }
    $php_src = (string) file_get_contents((string) $f);
    if (str_contains($php_src, 'load_plugin_textdomain(')) {
        $loaders[] = $rel;
    }
    preg_match_all('/wp_set_script_translations\([^;]*\);/', $php_src, $m);
    $script_translations = array_merge($script_translations, $m[0]);
}
check('no load_plugin_textdomain( in the plugin outside vendor/' . ($loaders ? ' (found in ' . implode(', ', $loaders) . ')' : ''), $loaders === []);
check("the admin app's script translations name the handle and the domain, no path (" . count($script_translations) . ' call(s))',
    count($script_translations) === 1
    && preg_match("/^wp_set_script_translations\(\s*'postquality-app'\s*,\s*'post-quality-manager'\s*\);$/", $script_translations[0]) === 1);
check('no languages/ folder ships', !file_exists($plugin_dir . '/languages'));

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
preg_match("/define\('POSTQUALITY_VERSION',\s*'([^']*)'\)/", $define('POSTQUALITY_VERSION'), $m);
$constant = $m[1] ?? null;
$package = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/package.json'), true)['version'] ?? null;
check("header $version == POSTQUALITY_VERSION $constant == package.json $package",
    $version !== null && $version === $constant && $constant === $package);

echo "Row 7 — readme.txt: headers as WordPress.org parses them, in step with the plugin header\n";
$readme_lines = explode("\n", str_replace("\r", '', (string) @file_get_contents(dirname($file) . '/readme.txt')));
check('readme.txt exists beside the main file', count($readme_lines) > 1);
$n = (int) @filesize(dirname($file) . '/readme.txt');
check("readme.txt is under 10 KB ($n bytes)", $n < 10240);
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

echo "Row 8 — CI runs the checks on the Requires PHP floor and on nothing below it (story 118, ADR 0018)\n";
$floor = (string) header_value($data, 'Requires PHP');
$ci = str_replace("\r", '', (string) file_get_contents($root . '/.github/workflows/ci.yml'));
$php_job = preg_match('/^  php:\n(.*?)(?=^  \S|\z)/ms', $ci, $j) ? $j[1] : ''; // the php job, up to the next job key
preg_match_all('/^\s*php-version:\s*[\'"]?([\d.]+)[\'"]?\s*$/m', $php_job, $m);
$ci_php = $m[1];
check("ci.yml's php job sets up PHP " . implode(', ', $ci_php) . ": the header's Requires PHP $floor is among them and none is below it",
    in_array($floor, $ci_php, true) && array_filter($ci_php, static fn($v) => version_compare($v, $floor, '<')) === []);

harness_exit();
