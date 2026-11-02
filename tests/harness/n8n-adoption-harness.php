<?php
/**
 * n8n adoption harness — entry 7 rows: the mis-wiring diagnosis a passing
 * ping of the saved URL runs, one per line of its plan's matrix, on
 * workflows built from the build's template with the ids rewired; every
 * diagnosis call is a GET. Entry 8 rows: the adoption of a hand-made
 * workflow (GET|POST /n8n/adoption), one per line of its plan's matrix,
 * with freely named credentials; every adoption call is a GET, and D3's
 * rule (the map's workflow only while the URL's path is the map's) in the
 * diagnosis rows.
 * WP stubs, no WP install; callbacks are called directly. The world (stubs,
 * constants, builders, the plugin load) is lib/n8n-world.php; story 78
 * build 2 split the one n8n harness by concern.
 * Run: php n8n-adoption-harness.php
 */

require __DIR__ . '/lib/n8n-world.php';

/* ─── Entry 7: the handshake reports a mis-wired workflow ───────────── */
echo "Diagnosis: one row per line of the plan's matrix\n";
$stored = static fn() => postquality_workflow_report()['miswired'] ?? null;
$a_sentence = ['postquality_workflow_version_seen' => ['version' => POSTQUALITY_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000, 'miswired' => SHARED_SENTENCE]];

// Row: the 2026-10-13 shape — no setup map, the v5 path, the trigger's Header Auth credential spread to every Claude node.
connected([resp(200, ['data' => [stored_workflow('wf-other', with_path(wired(), 'pqm-execute-other-example')), stored_workflow('wf-prod', $oct13)], 'nextCursor' => null])]);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => 5]);
$r = connect_ping(V5_HOOK);
check('2026-10-13 shape: one GET of the active workflows, signed; the one whose trigger path is the URL\'s last segment is read', calls() === [LIST_READ] && calls_signed(KEY));
check('... the reply: the sentence names the six Claude nodes, the credential and the fix; a warning (outdated, D2), the step ticks',
    $r['ping_ok'] === true && $r['message'] === SHARED_SENTENCE && $r['outdated'] === true
    && ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] ?? null) === true);
check('... the sentence stored with the report; the reply keeps its shape', $stored() === SHARED_SENTENCE
    && array_keys($r) === ['saved', 'ping_ok', 'outdated', 'message', 'status_html']);
$block = $r['status_html'];
check('... the body: needs attention (failing), the sentence is the status sentence, Test again the one primary; the message its outcome line', state_of($block) === 'failing'
    && str_contains($block, '<p class="pqm-workflow-sentence">' . SHARED_SENTENCE . '</p>') && str_contains($block, '<button type="button" class="button button-primary pqm-test-connection">Test again</button>')
    && one_primary($block) && str_contains($block, '<p class="pqm-workflow-outcome is-warning">' . SHARED_SENTENCE . '</p>'));
check('... the mis-wiring comes first among the fixes; the v5 workflow is also older and hand-made: Re-import the workflow secondary after Test again, its Set up by hand disclosure present, no update',
    str_contains($block, 'Test again</button> <a class="button pqm-reimport" href="#pqm-by-hand">') && str_contains($block, 'id="pqm-by-hand"') && !str_contains($block, 'pqm-update-workflow'));

// Row: a node that needs a credential has none (no setup map: every rule applies, D1).
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(['WordPress - example.com' => null] + WIRED))]])]);
check('missing credential: the sentence names Update WP Post (Rewrite) and the fix', diagnosed() === MISSING_SENTENCE && calls() === [LIST_READ]);
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(['WordPress - example.com' => null, 'Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + WIRED))]])]);
check('... shared and missing at once: the shared credential is reported first', diagnosed() === SHARED_SENTENCE);
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(WIRED, ['Claude - SEO Agent' => static function ($n) { unset($n['credentials']); return $n; }]))]])]);
check('... a Claude node with no credential at all is missing too', diagnosed() === 'In n8n, no credential is set on Claude - SEO Agent: add one there, publish the workflow, then test again.');
connected([resp(200, ['data' => [stored_workflow('wf-prod', wired(['Post Quality webhook - example.com' => ['id' => 'c-webhook', 'name' => ''], 'Anthropic - Claude' => ['id' => 'c-webhook', 'name' => '']] + WIRED))]])]);
check('... a shared credential n8n gives no name: the sentence without one', str_starts_with((string) diagnosed(), 'In n8n, the webhook credential is set on Claude - Review Agent, '));

// Row: provisioned — GET /workflows/{id} only (the provisioning rows above run it end to end).
connected($miswired_map(wired()), $setup_map);
check('provisioned: GET /workflows/{id} only, clean', diagnosed() === '' && calls() === [READ_WF]);
connected($miswired_map(wired(['WordPress - example.com' => null] + WIRED)), [PostQuality_N8n::SETUP_OPTION => ['credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic']] + $setup_map[PostQuality_N8n::SETUP_OPTION]]);
check('... without a wordpress credential in the map (D1): Update WP Post (Rewrite) skipped, clean', diagnosed() === '');
connected($miswired_map(wired(['WordPress - example.com' => null] + WIRED)), $setup_map);
check('... with one in the map, the same node is reported', diagnosed() === MISSING_SENTENCE);
// Entry 8, D3: the map's workflow only while the URL's path is the map's; else the workflow the URL calls.
connected($miswired_map(wired()), $setup_map);
check('... D3, paths agree: the map\'s workflow, GET /workflows/{id}', diagnosed(BASE . '/webhook/pqm-execute-example-com/') === '' && calls() === [READ_WF]);
connected([resp(200, ['data' => [stored_workflow('wf-else', with_path($oct13, 'elsewhere'))], 'nextCursor' => null])], $setup_map);
check('... D3, the URL now names another path: the list read, that workflow diagnosed; the map kept', diagnosed(BASE . '/webhook/elsewhere') === SHARED_SENTENCE
    && calls() === [LIST_READ] && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $setup_map[PostQuality_N8n::SETUP_OPTION]);
connected([resp(200, ['data' => [stored_workflow('wf-else', with_path(wired(['WordPress - example.com' => null] + WIRED), 'elsewhere'))]])],
    [PostQuality_N8n::SETUP_OPTION => ['credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic']] + $setup_map[PostQuality_N8n::SETUP_OPTION]]);
check('... D3, another path: the map\'s D1 skip does not reach that workflow (its Update WP Post is reported)', diagnosed(BASE . '/webhook/elsewhere') === MISSING_SENTENCE);

// Row: fixed in the draft, not published — the published version runs.
connected($miswired_map($oct13, wired()), $setup_map);
check('draft fixed, not published: reported (activeVersion is read, not nodes)', diagnosed() === SHARED_SENTENCE);
connected($miswired_map(wired(), $oct13), $setup_map);
check('... and the reverse: a mis-wired draft over a clean published version is clean', diagnosed() === '');
connected([resp(200, ['activeVersion' => null] + stored_workflow('wf1', $oct13))], $setup_map);
check('... activeVersion null: the draft nodes are read', diagnosed() === SHARED_SENTENCE);

// Row: a disabled node is not reported.
connected($miswired_map(wired(['WordPress - example.com' => null] + WIRED, ['Update WP Post (Rewrite)' => static fn($n) => ['disabled' => true] + $n])), $setup_map);
check('disabled node without a credential: not reported', diagnosed() === '');

// Row: no key stored — the stored sentence clears on the next test, zero n8n calls.
site(['postquality_settings' => ['webhook_token' => TOKEN]] + $a_sentence);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => POSTQUALITY_WORKFLOW_VERSION]);
$r = connect_ping(HOOK);
check('no key stored: diagnose answers \'\', zero calls; the sentence clears, the handshake is ping-only', PostQuality_N8n::diagnose(HOOK) === '' && calls() === []
    && $stored() === null && $r['message'] === PING_OK && $r['outdated'] === false && state_of($r['status_html']) === 'set-up');

// Row: the check could not run — the stored sentence (or none) stays, the ping message unchanged.
foreach ([
    'n8n unreachable (WP_Error)'           => [timed_out()],
    'the key refused (401)'                => [resp(401, ['message' => 'unauthorized'])],
    'a proxy\'s non-JSON 200'              => [resp(200, '<html>ok</html>')],
    'no active workflow with this path'    => [resp(200, ['data' => [stored_workflow('wf-other', with_path($oct13, 'pqm-execute-other-example'))], 'nextCursor' => null])],
    'an empty list'                        => [LIST_OK],
] as $label => $queue) {
    connected($queue, $a_sentence);
    $r = connect_ping(HOOK);
    check("could not run ($label): the ping message unchanged, the stored sentence kept", $r['ping_ok'] === true && $r['message'] === PING_OK
        && $r['outdated'] === false && $stored() === SHARED_SENTENCE && calls() === [LIST_READ]);
}
connected([NOT_FOUND], $setup_map + $a_sentence);
$r = connect_ping(HOOK);
check('could not run (the setup map\'s workflow is gone, 404): the stored sentence kept, still shown', calls() === [READ_WF] && $r['message'] === PING_OK
    && $stored() === SHARED_SENTENCE && str_contains($r['status_html'], SHARED_SENTENCE));
connected([resp(200, (object) [])], $setup_map + $a_sentence);
$r = connect_ping(HOOK);
check('could not run (a 2xx `{}` by id: no nodes, no trigger): null, the stored sentence kept', calls() === [READ_WF] && $r['message'] === PING_OK && $stored() === SHARED_SENTENCE);
connected([LIST_OK]);
check('... with none stored, none stored', connect_ping(HOOK)['message'] === PING_OK && $stored() === null);
connected([]);
check('... a URL without /webhook/: null, no call', PostQuality_N8n::diagnose(BASE . '/hooks/pqm-execute') === null && calls() === []);

// Row: a command reports the version — only a test clears the sentence (rest-reviews-harness runs the callback itself).
connected([], $a_sentence);
postquality_record_workflow_version(POSTQUALITY_WORKFLOW_VERSION, 'command', 'pro');
check('command report while a sentence is stored: kept', $stored() === SHARED_SENTENCE && postquality_workflow_report()['via'] === 'command');
postquality_record_workflow_version(POSTQUALITY_WORKFLOW_VERSION, 'test', 'pro', null);
check('... a test that could not diagnose (null): kept', $stored() === SHARED_SENTENCE);
postquality_record_workflow_version(POSTQUALITY_WORKFLOW_VERSION, 'test', 'pro', '');
check('... a clean test (\'\'): cleared, the stored report back to its shape before entry 7',
    $GLOBALS['tw']['options']['postquality_workflow_version_seen'] === ['version' => POSTQUALITY_WORKFLOW_VERSION, 'via' => 'test', 'at' => $GLOBALS['tw']['options']['postquality_workflow_version_seen']['at']]);

// Row: fixed in n8n, published, Test again — the status shows the version line.
connected($miswired_map(wired()), $setup_map + $a_sentence);
$r = connect_ping(HOOK);
check('fixed, Test again: \'\' stored (the sentence gone), the up-to-date message', $stored() === null && $r['message'] === PING_OK && $r['outdated'] === false);
check('... the body: set up, the version line, ' . (APP_BUILT ? 'one primary (the first review)' : 'unbuilt: no primary (no dialog to open)'), state_of($r['status_html']) === 'set-up' && one_primary($r['status_html'])
    && str_contains($r['status_html'], 'Version ' . POSTQUALITY_WORKFLOW_VERSION . ' · checked just now by a test.')
    && (APP_BUILT ? str_contains($r['status_html'], 'Run your first review') : !str_contains($r['status_html'], 'button-primary')
        && str_contains($r['status_html'], '<p class="pqm-workflow-actions"><button type="button" class="button pqm-test-connection">Test again</button></p>')));

// The Settings field pings an unsaved URL: not diagnosed.
connected([LIST_OK]);
$GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url'] = HOOK;
$ping = PostQuality_Admin::ping_webhook(BASE . '/webhook/pqm-execute-unsaved');
check('a ping of an unsaved URL: miswired null, no n8n call', array_key_exists('miswired', $ping) && $ping['miswired'] === null && calls() === []);
$ping = PostQuality_Admin::ping_webhook(HOOK);
check('... the saved URL: diagnosed', $ping['miswired'] === null && calls() === [LIST_READ]); // LIST_OK holds no workflow: could not run
$GLOBALS['tw']['ping_response'] = resp(404, ['message' => 'not registered']);
$GLOBALS['tw']['calls'] = [];
check('... a failed ping: no miswired key, no n8n call', !array_key_exists('miswired', PostQuality_Admin::ping_webhook(HOOK)) && calls() === []);

echo "Diagnosis: n8n is only read\n";
check('every call the diagnosis rows made is a GET (' . count($diagnosis_calls) . ' calls)', $diagnosis_calls !== []
    && array_unique(array_map(static fn($c) => $c[1]['method'], $diagnosis_calls)) === ['GET']);
check('every status block the pings above answered (' . count($status_blocks) . '): at most one primary, one whenever a notice shows',
    count($status_blocks) >= 9 && !array_filter($status_blocks, static fn($b) => !one_primary($b)));

/* ─── Entry 8: an Administrator adopts an existing manual setup ─────── */
echo "Adoption: routes\n";
$route = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/n8n/adoption'] ?? [];
check('/n8n/adoption: GET and POST, each gated by check_manage_settings; POST requires workflow_id (string)', array_column($route, 'methods') === ['GET', 'POST']
    && array_unique(array_column($route, 'permission_callback'), SORT_REGULAR) === [$gate]
    && ($route[1]['args']['workflow_id'] ?? null) === ['required' => true, 'type' => 'string']);

$proposed   = [
    'workflow'    => ['id' => 'wf-hand', 'name' => 'My blog reviews', 'path' => 'pqm-execute'],
    'credentials' => ['webhook' => HAND_HOOK, 'anthropic' => HAND_CLAUDE, 'wordpress' => HAND_WP],
];

echo "Adoption: offered or not\n";
hand_made([]);
$s = get_state();
check('offer: a passing key, the URL saved, no map — adoptable, no setup; zero calls', $s['adoptable'] === true && $s['setup'] === null && calls() === []);
foreach ([
    'no key' => static function () { unset($GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['api_key']); },
    'a key without a passing check' => static function () { $GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['probe'] = null; },
    'no URL saved' => static function () { unset($GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url']); },
] as $label => $change) {
    hand_made([]);
    $change();
    check("not offered ($label): adoptable false", get_state()['adoptable'] === false);
}
foreach ([true, false] as $wizard_created) {
    hand_made([], V5_HOOK, [PostQuality_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => [], 'path' => 'pqm-execute', 'wizard_created' => $wizard_created, 'at' => HAND_MAP_AT]]);
    $s = get_state();
    check('not offered (a map, wizard_created ' . var_export($wizard_created, true) . '): adoptable false, setup = {wizard_created, at, host empty (a map without base_url, D3), same_instance true}',
        $s['adoptable'] === false && $s['setup'] === ['wizard_created' => $wizard_created, 'at' => HAND_MAP_AT, 'host' => '', 'same_instance' => true]);
}

echo "Adoption: the proposal\n";
hand_made([hand_list($hand_nodes)]);
$r = proposal();
check('v5 path, free names: the workflow at the URL\'s path, its credentials by role from its own nodes, by name; rewrite_ready (Pro)',
    $r === $proposed + ['rewrite_ready' => true]);
check('... one GET, the list read of the diagnosis, signed; nothing written, no ping', calls() === [LIST_READ] && calls_signed(KEY) && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === []);
hand_made([hand_list($hand_nodes, '/my-own-path/')], BASE . '/webhook/my-own-path');
check('a custom trigger path: found by it, the path as the trigger carries it (slashes trimmed)', proposal()['workflow'] === ['id' => 'wf-hand', 'name' => 'My blog reviews', 'path' => 'my-own-path']);

echo "Adoption: not adoptable — zero calls\n";
foreach (['proposal' => static fn() => proposal(), 'confirm' => static fn() => adopt_post('wf-hand')] as $which => $run) {
    site(['postquality_settings' => ['n8n_webhook_url' => V5_HOOK]]);
    $GLOBALS['tw']['pings'] = [];
    check("$which, no key: 400 pqm_n8n_not_connected", refused($run(), 'pqm_n8n_not_connected', 400) && calls() === []);
    hand_made([]);
    $GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['probe'] = null;
    check("$which, a key without a passing check: 400 pqm_n8n_not_connected", refused($run(), 'pqm_n8n_not_connected', 400) && calls() === []);
    hand_made([], '');
    check("$which, no URL saved: 400 pqm_webhook_url_missing", refused($run(), 'pqm_webhook_url_missing', 400) && calls() === []);
    $map = [PostQuality_N8n::SETUP_OPTION => ['workflow_id' => 'wf1', 'credentials' => [], 'path' => 'pqm-execute', 'wizard_created' => true, 'at' => 1]];
    hand_made([], V5_HOOK, $map);
    $r = $run();
    check("$which, a map exists: 409 pqm_already_provisioned, provision's message; the map untouched", refused($r, 'pqm_already_provisioned', 409)
        && $r->message === 'n8n is already set up for this site. Choose Start over in the n8n connection section to set it up again.' && calls() === [] && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $map[PostQuality_N8n::SETUP_OPTION]);
}

echo "Adoption: no workflow at the URL\n";
foreach ([
    'another path only' => [[hand_list($hand_nodes, 'pqm-execute-elsewhere')], V5_HOOK, [LIST_READ]],
    'an empty list'     => [[LIST_OK], V5_HOOK, [LIST_READ]],
    'a workflow there without a Claude node' => [[hand_list(array_values(array_filter($hand_nodes, static fn($n) => !str_contains($n['parameters']['url'] ?? '', 'api.anthropic.com'))))], V5_HOOK, [LIST_READ]],
    'a URL without /webhook/' => [[], BASE . '/hooks/pqm-execute', []],
] as $label => [$queue, $url, $expected]) {
    hand_made($queue, $url);
    $r = proposal();
    check("$label: 404 pqm_workflow_not_found, check the URL or publish, then try again", refused($r, 'pqm_workflow_not_found', 404)
        && str_contains($r->message, 'Check the URL, or publish the workflow in n8n, then try again.') && calls() === $expected);
}

echo "Adoption: n8n did not answer\n";
foreach ([
    'unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
    'a 500'                  => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'a proxy\'s non-JSON 200' => [resp(200, '<html>Just a moment...</html>'), 'n8n did not answer with your workflows'],
] as $label => [$answer, $says]) {
    hand_made([$answer]);
    $r = proposal();
    check("$label: 502 pqm_adoption_failed, could_not()'s sentence, n8n's body never repeated", refused($r, 'pqm_adoption_failed', 502)
        && $r->message === "The plugin could not finish reading your workflows ($says). Try again in a moment."
        && !str_contains($r->message, 'Internal error') && !str_contains($r->message, 'Just a moment'));
}

echo "Adoption: mis-wired workflows are refused with the diagnosis sentence\n";
$then_try = static fn(string $sentence) => str_replace('then test again.', 'then try again.', $sentence);
hand_made([hand_list(wired(['Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + HAND_NAMES))]);
$r = proposal();
check('shared (the 2026-10-13 shape): 409 pqm_workflow_miswired, the 6.7 sentence ending "then try again."', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === $then_try(SHARED_SENTENCE));
hand_made([hand_list(wired(HAND_NAMES, ['Claude - SEO Agent' => static function ($n) { unset($n['credentials']); return $n; }]))]);
$r = proposal();
check('none (a Claude node without a credential): 409, the missing sentence', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === 'In n8n, no credential is set on Claude - SEO Agent: add one there, publish the workflow, then try again.');
hand_made([hand_list(wired(['Post Quality webhook - example.com' => ['name' => 'x']] + HAND_NAMES))]);
$r = proposal();
check('... the trigger without a credential: 409, the sentence names it', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === 'In n8n, no credential is set on Webhook Trigger: add one there, publish the workflow, then try again.');
hand_made([hand_list(wired(HAND_NAMES, ['Claude - SEO Agent' => static fn($n) => array_replace_recursive($n, ['credentials' => ['httpHeaderAuth' => ['id' => 'c-k2', 'name' => 'old claude key']]])]))]);
$r = proposal();
check('two Anthropic credentials: 409 pqm_workflow_miswired, a sentence naming the Claude nodes: pick one credential, publish, try again', refused($r, 'pqm_workflow_miswired', 409)
    && $r->message === 'In n8n, Claude - Review Agent, Claude - Takeaways Agent, Claude - Sources Agent, Claude - SEO Agent, Claude - Audit Agent, and Claude - Rewrite Agent use different credentials: pick one Anthropic credential on all of them, publish the workflow, then try again.');

echo "Adoption: Rewrite unwired (Pro, D2)\n";
$unwired = wired(['WordPress - example.com' => null] + HAND_NAMES);
hand_made([hand_list($unwired)]);
$r = proposal();
check('Update WP Post (Rewrite) without a credential: the proposal without wordpress, rewrite_ready false', $r === [
    'workflow' => $proposed['workflow'], 'credentials' => ['webhook' => HAND_HOOK, 'anthropic' => HAND_CLAUDE], 'rewrite_ready' => false]);
hand_made([hand_list($unwired)]);
$r = adopt_post('wf-hand');
check('... confirmed: the map stores no wordpress, the reply says rewrite_ready false', $r['rewrite_ready'] === false
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['credentials'] === ['webhook' => 'c-h1', 'anthropic' => 'c-k1']);
$GLOBALS['tw']['queue'] = [resp(200, ['name' => 'My blog reviews'] + stored_workflow('wf-hand', with_path($unwired, 'pqm-execute')))];
$GLOBALS['tw']['calls'] = [];
check('... so the diagnosis then skips that node (6.7\'s D1): clean, by id', diagnosed(V5_HOOK) === '' && calls() === [READ_HAND]);

echo "Adoption: confirm\n";
hand_made([hand_list($hand_nodes)]);
$r   = adopt_post('wf-hand');
$map = $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] ?? null;
check('the map stored in provision()\'s shape: {workflow_id, credentials: {webhook, anthropic, wordpress}, path: the trigger\'s, wizard_created false, at}, autoload off',
    $map === ['workflow_id' => 'wf-hand', 'credentials' => ['webhook' => 'c-h1', 'anthropic' => 'c-k1', 'wordpress' => 'c-b1'], 'path' => 'pqm-execute', 'base_url' => BASE, 'wizard_created' => false, 'at' => $map['at'] ?? null]
    && abs($map['at'] - time()) <= 5 && $GLOBALS['tw']['autoload'][PostQuality_N8n::SETUP_OPTION] === false);
check('... the same keys as a provisioned map, in the same order', array_keys($map) === array_keys($setup_map[PostQuality_N8n::SETUP_OPTION])
    && array_keys($map['credentials']) === array_keys($setup_map[PostQuality_N8n::SETUP_OPTION]['credentials']));
check('... the proposal read again, one GET; nothing else written (report, progress, settings untouched), no ping', calls() === [LIST_READ]
    && $GLOBALS['tw']['writes'] === [PostQuality_N8n::SETUP_OPTION] && $GLOBALS['tw']['pings'] === []);
check('... reply = public_state() + rewrite_ready: managed since now, no longer adoptable', $r === PostQuality_N8n::public_state() + ['rewrite_ready' => true]
    && $r['setup'] === ['wizard_created' => false, 'at' => $map['at'], 'host' => 'n8n.example.com', 'same_instance' => true] && $r['adoptable'] === false && $r['key_set'] === true);
$adopted = $GLOBALS['tw']['options'];
$GLOBALS['tw']['calls']  = [];
$GLOBALS['tw']['writes'] = [];
check('... a second confirm: 409 pqm_already_provisioned, zero calls, the map kept', refused(adopt_post('wf-hand'), 'pqm_already_provisioned', 409) && calls() === []
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $map);

// A provisioning run stores its map while the confirm's read is under way: add_option() keeps it.
$raced = ['workflow_id' => 'wf1', 'credentials' => ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic'], 'path' => 'pqm-execute', 'wizard_created' => true, 'at' => HAND_MAP_AT];
hand_made([static function () use ($hand_nodes, $raced) { $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] = $raced; return hand_list($hand_nodes); }]);
$r = adopt_post('wf-hand');
check('a map stored between the gate and the write: 409 pqm_already_provisioned, the map untouched, nothing written', refused($r, 'pqm_already_provisioned', 409)
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $raced && calls() === [LIST_READ]);

echo "Adoption: changed since the proposal\n";
hand_made([hand_list($hand_nodes)]);
$r = adopt_post('wf-shown-earlier');
check('another workflow listens there now: 409 pqm_adoption_changed, look again; nothing stored', refused($r, 'pqm_adoption_changed', 409)
    && $r->message === 'n8n changed since you looked: another workflow listens on your n8n Webhook URL now. Press Let the plugin manage this workflow again to see it.' && calls() === [LIST_READ]);
hand_made([hand_list($hand_nodes)]);
check('... an empty id: the same 409', refused(adopt_post(''), 'pqm_adoption_changed', 409));
hand_made([timed_out()]);
check('the fresh read fails: its own error (502 pqm_adoption_failed), nothing stored', refused(adopt_post('wf-hand'), 'pqm_adoption_failed', 502));
hand_made([hand_list(wired(['Post Quality webhook - example.com' => SHARED, 'Anthropic - Claude' => SHARED] + HAND_NAMES))]);
check('... mis-wired since: its own error (409 pqm_workflow_miswired), nothing stored', refused(adopt_post('wf-hand'), 'pqm_workflow_miswired', 409));

echo "Adoption: after\n";
$GLOBALS['tw']['options'] = $adopted;
$s = PostQuality_REST_API::get_setup_callback()->get_data();
$replies[] = json_encode($s);
check('wizard after (URL saved, no failed ping): provisioned, not manual, Connect your n8n and Set up the workflow ticked', $s['provisioned'] === true && $s['manual'] === false
    && array_slice($s['steps'], 0, 3, true) === ['n8n' => true, 'provision' => true, 'manual' => true]);
$GLOBALS['tw']['queue'] = [resp(200, stored_workflow('wf-hand', with_path($hand_nodes, 'pqm-execute')))];
$GLOBALS['tw']['calls'] = [];
$r = connect_ping(V5_HOOK);
$adoption_calls = array_merge($adoption_calls, $GLOBALS['tw']['calls']);
check('diagnosis after: a test ping of the saved URL reads GET /workflows/{id} (the paths agree), clean', calls() === [READ_HAND] && $r['message'] === PING_OK);
$GLOBALS['tw']['queue'] = [hand_list($hand_nodes, 'pqm-execute-example-com')];
$GLOBALS['tw']['calls'] = [];
$r = connect_ping(HOOK);
$adoption_calls = array_merge($adoption_calls, $GLOBALS['tw']['calls']);
check('... the URL changed to another path: the list read, the map kept', calls() === [LIST_READ] && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $map);
$GLOBALS['tw']['options'] = $adopted;
$GLOBALS['tw']['calls']   = [];
$r = forget();
check('forget key after: the map stays, not adoptable, the managed line stays (setup)', $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $map
    && $r['adoptable'] === false && $r['setup'] === ['wizard_created' => false, 'at' => $map['at'], 'host' => 'n8n.example.com', 'same_instance' => true] && calls() === []);

echo "Adoption: n8n is only read\n";
check('every call the adoption rows made is a GET (' . count($adoption_calls) . ' calls)', count($adoption_calls) >= 20
    && array_unique(array_map(static fn($c) => $c[1]['method'], $adoption_calls)) === ['GET']);
preg_match('/function propose_adoption\(.*?\n    \/\* ─── Provisioning/s', $src_n8n, $m);
check('the adoption code sends no POST, PUT, PATCH or DELETE and never calls GET /credentials', ($m[0] ?? '') !== ''
    && !preg_match("/'(POST|PUT|PATCH|DELETE)'|\\/credentials/", $m[0]));

key_leak_scan($replies);

harness_exit();
