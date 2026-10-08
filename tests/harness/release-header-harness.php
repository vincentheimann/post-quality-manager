<?php
/**
 * CLI harness for story 4.1 (release tracer bullet) — no WP install.
 * Guards the release-please annotations in the plugin main file: the header
 * still parses the way WordPress reads it (get_file_data + its
 * _cleanup_header_comment trimming), the start/end block encloses exactly the
 * Version line, and only PQM_VERSION carries the inline annotation.
 * The header == PQM_VERSION == package.json check is entry 2, not here.
 * Story 4.6 adds rows 4-5: the Domain Path header and the init-time loader,
 * and the tests/i18n/ fixtures still parse and still name live strings.
 */

error_reporting(E_ALL);

$file = dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

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
check('it loads the post-quality-manager domain from /languages',
    preg_match("/load_plugin_textdomain\(\s*'post-quality-manager'\s*,\s*false\s*,[^;]*'\/languages'\s*\)/", $src) === 1);
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

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
