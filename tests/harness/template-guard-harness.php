<?php
/**
 * Template guard harness for story 4.3 (CAP-4) — fails, naming the cause,
 * when post-quality-manager/templates/n8n-workflow.json is invalid JSON,
 * carries a placeholder pqm_workflow_json() does not fill, contains a
 * site-specific string, or has a Code node whose jsCode does not parse.
 * Code nodes are compiled by Node.js, never run. Needs Node.js on PATH;
 * fails loudly without it. No n8n, no WP install.
 */

error_reporting(E_ALL);

/** Must match the str_replace() in pqm_workflow_json() (post-quality-manager.php). */
const FILLED_PLACEHOLDERS = ['__PQM_SITE_HOST__'];

/** Hosts the template may name literally; every WordPress address arrives at runtime. */
const ALLOWED_HOSTS = ['api.anthropic.com'];

/** Keys of a bare export; an instance export adds id, versionId, meta.instanceId, pinData, tags… */
const TOP_LEVEL_KEYS = ['active', 'connections', 'name', 'nodes', 'settings'];

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok  $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

$raw = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json');

echo "Valid JSON\n";
$tpl = json_decode($raw, true);
if (!is_array($tpl) || !is_array($tpl['nodes'] ?? null)) {
    fwrite(STDERR, "FATAL: template is not valid JSON (" . json_last_error_msg() . ")\n");
    exit(1);
}
check('template parses as a workflow with a nodes array', true);

echo "Placeholders\n";
preg_match_all('/__PQM_[A-Z0-9_]+__/', $raw, $m);
$unknown = array_values(array_diff(array_unique($m[0]), FILLED_PLACEHOLDERS));
check('every placeholder is one pqm_workflow_json() fills'
    . ($unknown ? ' — unknown: ' . implode(', ', $unknown) . ' (fill it in pqm_workflow_json() and list it in FILLED_PLACEHOLDERS)' : ''),
    !$unknown);

echo "Site-specific strings\n";
preg_match_all('#https?://([^/"\s\\\\:?]+)#i', $raw, $m);
$hosts = array_values(array_diff(array_unique(array_map('strtolower', $m[1])), ALLOWED_HOSTS));
check('every literal URL host is allow-listed' . ($hosts ? ' — not allowed: ' . implode(', ', $hosts) : ''), !$hosts);

preg_match_all('/\blocalhost\b|\b\d{1,3}(?:\.\d{1,3}){3}\b/i', $raw, $m);
check('no localhost or IP address' . ($m[0] ? ' — found: ' . implode(', ', array_unique($m[0])) : ''), !$m[0]);

$keys = array_keys($tpl);
sort($keys);
$extra = array_values(array_diff($keys, TOP_LEVEL_KEYS));
check('top-level keys are a bare export' . ($extra ? ' — instance keys: ' . implode(', ', $extra) : ''), $keys === TOP_LEVEL_KEYS);

$bad_ids = []; $webhook_ids = []; $cred_ids = [];
foreach ($tpl['nodes'] as $node) {
    $name = $node['name'] ?? '?';
    if (!preg_match('/^pqm-node-\d+$/', (string) ($node['id'] ?? ''))) { $bad_ids[] = $name; }
    if (array_key_exists('webhookId', $node)) { $webhook_ids[] = $name; }
    foreach ($node['credentials'] ?? [] as $type => $cred) {
        if (array_keys((array) $cred) !== ['name']) { $cred_ids[] = "$name ($type)"; }
    }
}
check('every node id is a pqm-node-N id' . ($bad_ids ? ' — not: ' . implode(', ', $bad_ids) : ''), !$bad_ids);
check('no node carries a webhookId' . ($webhook_ids ? ' — on: ' . implode(', ', $webhook_ids) : ''), !$webhook_ids);
check('credentials carry a name only, never an id' . ($cred_ids ? ' — on: ' . implode(', ', $cred_ids) : ''), !$cred_ids);

echo "Code nodes parse\n";
exec('node --version 2>&1', $out, $code);
if ($code !== 0) { fwrite(STDERR, "FATAL: `node` is not on PATH; this harness compiles the template's Code nodes with Node.js.\n"); exit(1); }

$runner = sys_get_temp_dir() . '/pqm-n8n-compile-' . getmypid() . '.js';
file_put_contents($runner, <<<'JS'
const nodes = JSON.parse(require('fs').readFileSync(0, 'utf8'));
const AsyncFunction = (async () => {}).constructor;
const errors = {};
for (const [name, code] of Object.entries(nodes)) {
  try { new AsyncFunction('$input', '$', '$json', code); errors[name] = null; }
  catch (e) { errors[name] = String(e && e.message || e); }
}
process.stdout.write(JSON.stringify(errors));
JS);
register_shutdown_function(fn() => @unlink($runner));

$code_nodes = [];
foreach ($tpl['nodes'] as $node) {
    if (($node['type'] ?? '') === 'n8n-nodes-base.code') {
        $code_nodes[$node['name']] = (string) ($node['parameters']['jsCode'] ?? '');
    }
}
check('the template has Code nodes to compile', count($code_nodes) > 0);

$p = proc_open(['node', $runner], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], json_encode((object) $code_nodes));
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
proc_close($p);
$errors = json_decode($stdout, true);
if (!is_array($errors)) { fwrite(STDERR, "FATAL: node compile run failed\n$stdout$stderr\n"); exit(1); }

foreach ($code_nodes as $name => $_) {
    $err = array_key_exists($name, $errors) ? $errors[$name] : 'not compiled';
    check("$name jsCode parses" . ($err !== null ? " — $err" : ''), $err === null);
}

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
