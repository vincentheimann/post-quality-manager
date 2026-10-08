<?php
/**
 * CLI harness for story 4.1 (release tracer bullet) — no WP install.
 * Guards the release-please annotations in the plugin main file: the header
 * still parses the way WordPress reads it (get_file_data + its
 * _cleanup_header_comment trimming), the start/end block encloses exactly the
 * Version line, and only PQM_VERSION carries the inline annotation.
 * The header == PQM_VERSION == package.json check is entry 2, not here.
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

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
