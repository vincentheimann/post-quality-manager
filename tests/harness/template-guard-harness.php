<?php
/**
 * Template guard harness for story 4.3 (CAP-4) — fails, naming the cause,
 * when post-quality-manager/templates/n8n-workflow.json is invalid JSON,
 * carries a placeholder pqm_workflow_json() does not fill (or lacks one it
 * fills), contains a site-specific string or any literal URL but the Claude
 * API's, or has a Code node whose jsCode does not parse.
 * Code nodes are compiled by Node.js, never run. Needs Node.js on PATH;
 * fails loudly without it. No n8n, no WP install.
 *
 * Story 54 (ADR 0014): every check runs on both templates, and the Free
 * one (templates/n8n-workflow-free.json) must equal a fresh derivation by
 * bin/derive-free-template.php, carry none of the paid branches and state
 * its variant, while the Pro one states none (absent = Pro).
 *
 * Provisioning entry 3: the Webhook Trigger's path must be the per-site
 * placeholder __PQM_WEBHOOK_PATH__, never a literal (spike finding 5).
 */

require __DIR__ . '/lib/wp-stubs.php'; // the runner (check(), harness_exit()); no plugin code is loaded here

/** Must match the str_replace() in pqm_workflow_json() (includes/pqm-functions.php). */
const FILLED_PLACEHOLDERS = ['__PQM_SITE_HOST__', '__PQM_WEBHOOK_PATH__'];

/** Hosts the template may name literally; every WordPress address arrives at runtime. */
const ALLOWED_HOSTS = ['api.anthropic.com'];

/** Keys of a bare export; an instance export adds id, versionId, meta.instanceId, pinData, tags… */
const TOP_LEVEL_KEYS = ['active', 'connections', 'name', 'nodes', 'settings'];

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

$dir = dirname(__DIR__, 2) . '/post-quality-manager/templates';
$templates = [];
foreach (['n8n-workflow.json' => 'Pro', 'n8n-workflow-free.json' => 'Free'] as $file => $variant) {
    echo "\n=== $variant template ($file) ===\n";
    $raw = (string) @file_get_contents("$dir/$file");

    echo "Valid JSON\n";
    $tpl = json_decode($raw, true);
    if (!is_array($tpl) || !is_array($tpl['nodes'] ?? null)) {
        fwrite(STDERR, "FATAL: $file is not valid JSON (" . json_last_error_msg() . ")\n");
        exit(1);
    }
    $templates[$variant] = ['raw' => $raw, 'tpl' => $tpl];
    check('template parses as a workflow with a nodes array', true);

    echo "Placeholders\n";
    preg_match_all('/__PQM_[A-Z0-9_]+__/', $raw, $m);
    $unknown = array_values(array_diff(array_unique($m[0]), FILLED_PLACEHOLDERS));
    check('every placeholder is one pqm_workflow_json() fills'
        . ($unknown ? ' — unknown: ' . implode(', ', $unknown) . ' (fill it in pqm_workflow_json() and list it in FILLED_PLACEHOLDERS)' : ''),
        !$unknown);
    $absent = array_values(array_diff(FILLED_PLACEHOLDERS, $m[0]));
    check('every placeholder pqm_workflow_json() fills is in the template'
        . ($absent ? ' — missing: ' . implode(', ', $absent) : ''),
        !$absent);

    echo "Site-specific strings\n";
    preg_match_all('#https?://([^/"\s\\\\:?]+)#i', $raw, $m);
    $hosts = array_values(array_diff(array_unique(array_map('strtolower', $m[1])), ALLOWED_HOSTS));
    check('every literal URL host is allow-listed' . ($hosts ? ' — not allowed: ' . implode(', ', $hosts) : ''), !$hosts);
    preg_match_all('#https?://[^"\s\\\\]*#', $raw, $m);
    $urls = array_values(array_unique($m[0]));
    check('the only literal URL is the Claude API (https://api.anthropic.com/v1/messages)'
        . ($urls !== ['https://api.anthropic.com/v1/messages'] ? ' — found: ' . ($urls ? implode(', ', $urls) : 'none') : ''),
        $urls === ['https://api.anthropic.com/v1/messages']);

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

    $trigger_path = array_column($tpl['nodes'], null, 'name')['Webhook Trigger']['parameters']['path'] ?? null;
    check('the Webhook Trigger path is the per-site placeholder __PQM_WEBHOOK_PATH__'
        . ($trigger_path !== '__PQM_WEBHOOK_PATH__' ? ' — found: ' . var_export($trigger_path, true) : ''),
        $trigger_path === '__PQM_WEBHOOK_PATH__');

    echo "Code nodes parse\n";
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
}

/* ─── Story 54: the Free template (ADR 0014) ──────────────────────────── */
echo "\n=== Free template: derived, no paid branch, variant stated ===\n";
const PAID_COMMANDS = ['sources', 'seo', 'audit', 'rewrite'];
const PAID_NODES = [
    'Build Sources Body', 'Claude - Sources Agent', 'Parse Sources Result',
    'Build SEO Body', 'Claude - SEO Agent', 'Parse SEO Result',
    'Build Audit Body', 'Claude - Audit Agent', 'Parse Audit Result',
    'Build Rewrite Body', 'Claude - Rewrite Agent', 'Parse Rewrite Response',
    'Rewrite Parsed?', 'Update WP Post (Rewrite)', 'Format Rewrite Confirmation',
];
require dirname(__DIR__, 2) . '/bin/derive-free-template.php';
$pro  = $templates['Pro']['tpl'];
$free = $templates['Free']['tpl'];

$derived = json_decode(json_encode(pqm_derive_free_template(json_decode($templates['Pro']['raw']))), true);
$drift = null;
if ($derived !== $free) {
    $by_name = static fn(array $t) => array_column($t['nodes'], null, 'name');
    $want = $by_name($derived);
    $have = $by_name($free);
    foreach (array_unique(array_merge(array_keys($want), array_keys($have))) as $name) {
        if (($want[$name] ?? null) !== ($have[$name] ?? null)) { $drift = "node '$name'"; break; }
    }
    $drift ??= $derived['connections'] !== $free['connections'] ? 'connections' : 'top-level keys';
}
check('n8n-workflow-free.json equals a fresh derivation from the Pro template'
    . ($drift ? " — first difference: $drift (run php bin/derive-free-template.php)" : ''), $drift === null);

$names = array_column($free['nodes'], 'name');
check('none of the 15 paid nodes is left' . (array_intersect(PAID_NODES, $names) ? ' — found: ' . implode(', ', array_intersect(PAID_NODES, $names)) : ''),
    !array_intersect(PAID_NODES, $names) && !array_diff(PAID_NODES, array_column($pro['nodes'], 'name')));
check('every other Pro node is kept', array_values(array_diff(array_column($pro['nodes'], 'name'), PAID_NODES)) === $names);
$nodes = array_column($free['nodes'], null, 'name');
$route = $nodes['Route Command']['parameters'];
$rules = array_column($route['rules']['rules'], 'value2');
check('Route Command knows score, review, takeaways, ping only', $rules === ['score', 'review', 'takeaways', 'ping']);
check('its fallback is the output after the rules, wired to Error Fallback', $route['fallbackOutput'] === count($rules)
    && count($free['connections']['Route Command']['main']) === count($rules) + 1
    && $free['connections']['Route Command']['main'][count($rules)][0]['node'] === 'Error Fallback'
    && $free['connections']['Route Command']['main'][3][0]['node'] === 'Ping OK');
preg_match('/const validCommands = \[([^\]]*)\]/', $nodes['Parse Input']['parameters']['jsCode'], $m);
preg_match_all("/'([a-z_]+)'/", $m[1] ?? '', $valid);
check('Parse Input accepts score, review, takeaways, apply only', $valid[1] === ['score', 'review', 'takeaways', 'apply']);
$dangling = [];
foreach ($free['connections'] as $from => $c) {
    if (!isset($nodes[$from])) { $dangling[] = $from; }
    foreach ($c['main'] as $targets) {
        foreach ($targets as $t) {
            if (!isset($nodes[$t['node']])) { $dangling[] = "$from → {$t['node']}"; }
        }
    }
}
check('every connection joins existing nodes' . ($dangling ? ' — ' . implode(', ', $dangling) : ''), !$dangling);
$quoted = [];
array_walk_recursive($free, static function ($v) use (&$quoted) {
    if (is_string($v) && (in_array($v, PAID_COMMANDS, true) || preg_match('/[\'"](' . implode('|', PAID_COMMANDS) . ')[\'"]/', $v))) { $quoted[] = $v; }
});
check('no paid command key anywhere in the Free template' . ($quoted ? ' — e.g. ' . substr($quoted[0], 0, 80) : ''), !$quoted);
$cred_types = [];
foreach ($free['nodes'] as $node) { $cred_types += array_flip(array_keys($node['credentials'] ?? [])); }
check('only Header Auth credentials are left (the WordPress one went with the rewrite)', array_keys($cred_types) === ['httpHeaderAuth']);

$pro_nodes = array_column($pro['nodes'], null, 'name');
$pro_reply = json_decode($pro_nodes['Webhook Trigger']['parameters']['options']['responseData'], true);
check('the ping reply states variant free, same version as the Pro one',
    json_decode($nodes['Webhook Trigger']['parameters']['options']['responseData'], true) === $pro_reply + ['workflow_variant' => 'free']);
check("Parse Input stamps workflow_variant: 'free' under its workflow_version",
    (bool) preg_match("/workflow_version: \d+,\n\s*workflow_variant: 'free',/", $nodes['Parse Input']['parameters']['jsCode']));
check('the result callback forwards the variant',
    str_contains($nodes['POST Result to WordPress']['parameters']['body'], 'workflow_variant: $json.workflow_variant, '));
check('the Pro template states no variant (absent = Pro)', !str_contains($templates['Pro']['raw'], 'workflow_variant'));

harness_exit();
