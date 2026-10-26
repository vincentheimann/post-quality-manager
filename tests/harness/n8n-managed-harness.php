<?php
/**
 * n8n managed-actions harness — entry 10 rows: the webhook token rotation
 * (POST /n8n/token), one per line of its plan's matrix; its one n8n call is
 * the PATCH of the map's webhook credential, and the new token is in no
 * reply, error or other option. Entry 9 rows: the one-click workflow update
 * (POST /n8n/workflow), one per line of its plan's matrix; its n8n calls are
 * one GET then one PUT of the map's workflow, the PUT body asserted (four
 * keys, the map's ids on every credentialed node, the map's path on the
 * trigger, the read name), and the status block's fix per gate. Story 71
 * rows: start over (DELETE /n8n/setup), and a map that remembers its
 * instance.
 * WP stubs, no WP install; callbacks are called directly. The world (stubs,
 * constants, builders, the plugin load) is lib/n8n-world.php; story 78
 * build 2 split the one n8n harness by concern.
 * Run: php n8n-managed-harness.php
 */

require __DIR__ . '/lib/n8n-world.php';

/* ─── Entry 10: an Administrator rotates the webhook token ──────────── */
echo "Rotation: route\n";
$route = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/n8n/token'] ?? [];
check('/n8n/token: POST, gated by check_manage_settings, no arguments', ($route['methods'] ?? null) === 'POST'
    && ($route['permission_callback'] ?? null) === $gate && !array_key_exists('args', $route));

$GLOBALS['tw']['uuid'] = ROTATED; // wp_generate_uuid4(): the new token, distinct from TOKEN

echo "Rotation: offered or not\n";
managed([]);
$s = get_state();
check('offer: a passing key, a map, the saved URL on the map\'s path — rotatable; zero calls', $s['rotatable'] === true && $s['setup'] !== null && calls() === []);
managed([], $adopted_map, V5_HOOK);
check('... an adopted map (wizard_created false) on its own path: rotatable', get_state()['rotatable'] === true);
$not_offered = [
    'no key'                             => static function () { unset($GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['api_key']); },
    'a key without a passing check'      => static function () { $GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['probe'] = null; },
    'no map'                             => static function () { unset($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]); },
    'a map without a webhook credential' => static function () { unset($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['credentials']['webhook']); },
    'no URL saved'                       => static function () { unset($GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url']); },
    'the URL on another path'            => static function () { $GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url'] = BASE . '/webhook/elsewhere'; },
];
foreach ($not_offered as $label => $change) {
    managed([]);
    $change();
    check("not offered ($label): rotatable false, zero calls", get_state()['rotatable'] === false && calls() === []);
}

echo "Rotation: rotate\n";
$token_at_patch = null;
managed([static function () use (&$token_at_patch) { $token_at_patch = token_now(); return PATCHED; }]);
$settings_before = $GLOBALS['tw']['options']['postquality_settings'];
$r = rotate();
check('one call: PATCH /credentials/{the map\'s webhook id}, signed with the key and the plugin User-Agent', calls() === [PATCH_WEBHOOK] && calls_signed(KEY));
check('... its body: {data: {name: X-PQM-Token, value: the new token}}, JSON', body(0) === ['data' => ['name' => 'X-PQM-Token', 'value' => ROTATED]]
    && $GLOBALS['tw']['calls'][0][1]['headers']['Content-Type'] === 'application/json');
check('... PATCH first: the stored token was still the old one when n8n got the new one', $token_at_patch === TOKEN);
check('... then postquality_settings written once, only webhook_token changed; then the version record', writes() === ['postquality_settings', 'postquality_workflow_version_seen']
    && $GLOBALS['tw']['options']['postquality_settings'] === ['webhook_token' => ROTATED] + $settings_before);
check('... then one ping of the saved URL, with the new token', count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === HOOK
    && $GLOBALS['tw']['pings'][0][1]['headers']['X-PQM-Token'] === ROTATED);
check('... reply {rotated, ping_ok, outdated, message, status_html}: "Token rotated." + the ping\'s message', is_array($r)
    && array_keys($r) === ['rotated', 'ping_ok', 'outdated', 'message', 'status_html'] && $r['rotated'] === true && $r['ping_ok'] === true && $r['outdated'] === false
    && $r['message'] === 'Token rotated. ' . PING_OK);
check('... the version recorded by a test, as provisioning does; the body says so, the message first', postquality_workflow_report()['via'] === 'test'
    && str_contains($r['status_html'], 'Version ' . POSTQUALITY_WORKFLOW_VERSION . ' · checked just now by a test.') && one_primary($r['status_html'])
    && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">Token rotated. ' . PING_OK . '</p>'));
check('... no diagnosis read: n8n is not read after the PATCH', calls() === [PATCH_WEBHOOK]);
check('... the map and the connection untouched', $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $setup_map[PostQuality_N8n::SETUP_OPTION]
    && !in_array(PostQuality_N8n::SETUP_OPTION, $GLOBALS['tw']['writes'], true) && !in_array(PostQuality_N8n::OPTION, $GLOBALS['tw']['writes'], true));
managed([PATCHED], $adopted_map, V5_HOOK);
$r = rotate();
check('an adopted map: the PATCH goes to its own webhook credential id', calls() === ['PATCH ' . BASE . '/api/v1/credentials/c-h1'] && $r['rotated'] === true && token_now() === ROTATED);
managed([PATCHED], [PostQuality_N8n::SETUP_OPTION => ['credentials' => ['webhook' => 'c/../x y'] + $setup_map[PostQuality_N8n::SETUP_OPTION]['credentials']] + $setup_map[PostQuality_N8n::SETUP_OPTION]]);
rotate();
check('... an id with path characters: URL-encoded', calls() === ['PATCH ' . BASE . '/api/v1/credentials/c%2F..%2Fx%20y']);
managed([PATCHED], null, HOOK . '/');
check('... the saved URL with a trailing slash: the same path, rotated', rotate()['rotated'] === true);
managed([PATCHED]);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => POSTQUALITY_WORKFLOW_VERSION - 1]);
$r = rotate();
check('... an older workflow answers the ping: rotated, ping_ok and outdated true, its message after "Token rotated.", status_html present', $r['rotated'] === true
    && $r['ping_ok'] === true && $r['outdated'] === true && str_starts_with($r['message'], 'Token rotated. Ping OK — this site reaches n8n, but your n8n workflow is an older version')
    && isset($r['status_html']) && token_now() === ROTATED);
managed([PATCHED]);
$GLOBALS['tw']['options']['postquality_workflow_version_seen'] = ['version' => POSTQUALITY_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000, 'miswired' => SHARED_SENTENCE];
$r = rotate();
// Story 74 (D3): with the Claude nodes on the webhook credential, a new token would stop them.
check('... a stored mis-wiring sentence (story 74): zero calls, 409 pqm_workflow_miswired, the sentence then the diagnosis\'s fix; the token and the sentence kept',
    refused($r, 'pqm_workflow_miswired', 409) && calls() === [] && token_now() === TOKEN && postquality_workflow_report()['miswired'] === SHARED_SENTENCE
    && $r->message === 'The token was not rotated: the last test found the workflow\'s credentials mis-wired, so fix that first. ' . SHARED_SENTENCE);
check('... D4: the error carries the page\'s body, the refusal its outcome line, Test again the one primary', $r->data['status_html'] === PostQuality_Admin::render_workflow_body($r->message, 'error')
    && str_contains($r->data['status_html'], '<p class="pqm-workflow-outcome is-error">' . $r->message . '</p>') && one_primary($r->data['status_html']));

echo "Rotation: refused at the gate — zero calls, nothing written\n";
foreach ([
    'no key'                             => ['pqm_n8n_not_connected', 400],
    'a key without a passing check'      => ['pqm_n8n_not_connected', 400],
    'no map'                             => ['pqm_workflow_not_managed', 409],
    'a map without a webhook credential' => ['pqm_workflow_not_managed', 409],
    'no URL saved'                       => ['pqm_webhook_url_changed', 409],
    'the URL on another path'            => ['pqm_webhook_url_changed', 409],
] as $label => [$code, $status]) {
    managed([PATCHED]);
    $not_offered[$label]();
    $r = rotate();
    check("$label: $status $code, zero calls, nothing written, no ping, the token kept", refused($r, $code, $status) && calls() === [] && token_now() === TOKEN);
}
managed([]);
$not_offered['no map']();
check('... not managed: the sentence says to let the plugin set up or manage the workflow first', rotate()->message
    === 'The plugin does not manage your n8n workflow yet. Let it set the workflow up, or manage the one you set up by hand, then try again.');
managed([]);
$not_offered['the URL on another path']();
check('... the URL changed: the sentence says the URL no longer points at the managed workflow, nothing was changed (action-neutral: the update shows it too, entry 9)', rotate()->message
    === 'Your n8n Webhook URL no longer points at the workflow the plugin manages, so nothing was changed. Save that workflow\'s production URL as the n8n Webhook URL, then try again.');

echo "Rotation: n8n refuses the PATCH — the stored token unchanged, no ping\n";
foreach ([
    'a 400 quoting the request'  => [resp(400, ['message' => 'request/body/data must match ' . ROTATED]), 'n8n answered HTTP 400'],
    'a 500'                      => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'a proxy\'s non-JSON 404'    => [resp(404, '<html>Not Found</html>'), 'n8n answered HTTP 404'],
    'a 2xx without JSON'         => [resp(200, '<html>Just a moment...</html>'), 'n8n did not confirm the change'],
    'n8n unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
] as $label => [$answer, $says]) {
    managed([$answer]);
    $r = rotate();
    check("$label: 502 pqm_rotation_failed, stopped('credential')'s sentence, n8n's body not repeated", refused($r, 'pqm_rotation_failed', 502)
        && $r->message === sprintf(ROTATION_STOPPED, $says) && !str_contains($r->message, 'Internal error') && !str_contains($r->message, 'must match')
        && calls() === [PATCH_WEBHOOK] && token_now() === TOKEN);
}
managed([NOT_FOUND]);
$r = rotate();
check('the credential is gone (a JSON 404): 502 pqm_rotation_failed, the sentence names it and the check in n8n', refused($r, 'pqm_rotation_failed', 502)
    && $r->message === 'n8n no longer has the webhook credential the plugin manages. Check the Webhook Trigger\'s credential in n8n, then try again. Choose Start over in the n8n connection section to set it up again.'
    && calls() === [PATCH_WEBHOOK] && token_now() === TOKEN);

echo "Rotation: the ping fails after — the token kept on both sides\n";
foreach ([
    'the URL does not answer (404)' => [resp(404, ['message' => 'The requested webhook is not registered.']), ROTATED_PREFIX . ' n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.'],
    'the webhook unreachable'       => [timed_out(), ROTATED_PREFIX . ' Could not connect to n8n: cURL error 28: timed out'],
    'a 401'                         => [resp(401, ''), OTHER_CREDENTIAL],
    'a 403'                         => [resp(403, ''), OTHER_CREDENTIAL],
] as $label => [$ping, $message]) {
    managed([PATCHED]);
    $GLOBALS['tw']['ping_response'] = $ping;
    $r = rotate();
    check("$label: rotated, ping_ok false, the message says so; the body needs attention (the failure recorded, no version)", array_keys($r) === ['rotated', 'ping_ok', 'outdated', 'message', 'status_html']
        && $r['ping_ok'] === false && $r['outdated'] === false && $r['message'] === $message && state_of($r['status_html']) === 'failing'
        && postquality_workflow_report()['version'] === 0 && isset(postquality_workflow_report()['failed']));
    check('... the new token stored: postquality_settings written once, then the failure record', token_now() === ROTATED
        && writes() === ['postquality_settings', 'postquality_workflow_version_seen'] && calls() === [PATCH_WEBHOOK]);
}

echo "Rotation: after\n";
managed([PATCHED]);
rotate();
$GLOBALS['tw']['calls'] = [];
$s = forget();
check('forget key after: rotatable false (the button goes), the token stays as rotated, the map stays', $s['rotatable'] === false && token_now() === ROTATED
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $setup_map[PostQuality_N8n::SETUP_OPTION] && calls() === []);
$r = rotate();
check('... a rotation then: 400 pqm_n8n_not_connected, zero calls', $r instanceof WP_Error && $r->code === 'pqm_n8n_not_connected' && calls() === []);

echo "Rotation: leak scan and the one call\n";
$rotation_calls = array_merge(...array_map(static fn($run) => $run[2], $rotation_runs));
check('every n8n call of the rotations (' . count($rotation_calls) . ') is a PATCH on a map\'s webhook credential id', count($rotation_calls) >= 15
    && !array_filter($rotation_calls, static fn($c) => $c[1]['method'] !== 'PATCH' || !preg_match('#/api/v1/credentials/(c-webhook|c-h1|c%2F\.\.%2Fx%20y)$#', $c[0])));
check('... and no rotation made more than one', !array_filter($rotation_runs, static fn($run) => count($run[2]) > 1));
// Story 67: the reply's status_html is the page's body, whose Webhook disclosure shows the token row the page shows anyway; no reply field carries it.
check('the new token is in no reply field and no error (' . count($rotation_runs) . ' rotations)', !array_filter($rotation_runs, static fn($run) => str_contains(without_body($run[0]), ROTATED)));
check('... in no stored option but postquality_settings[webhook_token]', !array_filter($rotation_runs, static function ($run) {
    $options = $run[1];
    unset($options['postquality_settings']['webhook_token']);
    return str_contains(json_encode($options), ROTATED);
}));
check('... in no outgoing call\'s address or headers (only the PATCH body), and in the ping only as its X-PQM-Token header and the callback\'s auth_token (story 2: as a review payload carries it)', !array_filter($rotation_runs, static fn($run) =>
    array_filter($run[2], static fn($c) => str_contains(json_encode([$c[0], $c[1]['headers']]), ROTATED))
    || array_filter($run[3], static fn($p) => str_contains(json_encode([$p[0], array_diff_key((array) json_decode($p[1]['body'] ?? '', true), ['auth_token' => 0]), array_diff_key($p[1]['headers'], ['X-PQM-Token' => 0])]), ROTATED))));
check('... and in no REST reply field of this harness (' . count($replies) . ')', !array_filter($replies, static fn($r) => str_contains(without_body($r), ROTATED)));
preg_match('/function rotate_token\(.*?\n    }\n.*?function managed_blocked\(.*?\n    }\n/s', $src_n8n, $m);
check('the rotation code calls n8n once, a PATCH through request(), and never reads /credentials', ($m[0] ?? '') !== ''
    && !preg_match("/'(GET|POST|PUT|DELETE)'/", $m[0]) && substr_count($m[0], 'self::request(') === 1 && substr_count($m[0], "self::request( 'PATCH'") === 1);
unset($GLOBALS['tw']['uuid']);

/* ─── Entry 9: one click updates the workflow to the bundled template ─ */
echo "Update: route\n";
$route = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/n8n/workflow'] ?? [];
check('/n8n/workflow: POST, gated by check_manage_settings, no arguments', ($route['methods'] ?? null) === 'POST'
    && ($route['permission_callback'] ?? null) === $gate && !array_key_exists('args', $route));


echo "Update: offered or not — the status block's fix\n";
managed([], $setup_map + $older_report);
$block = PostQuality_Admin::render_workflow_body();
check('offer: a passing key, a map, the saved URL on the map\'s path, an older report — needs attention (outdated), "this plugin needs N", Update workflow the one primary; no re-import; zero calls',
    state_of($block) === 'outdated' && str_contains($block, 'Your workflow is version ' . (POSTQUALITY_WORKFLOW_VERSION - 1) . '; this plugin needs ' . POSTQUALITY_WORKFLOW_VERSION . '.')
    && str_contains($block, '<p class="pqm-workflow-actions">' . UPDATE_BUTTON . ' <button type="button" class="button pqm-test-connection">Test again</button></p>')
    && !str_contains($block, 'pqm-reimport') && one_primary($block) && str_contains($block, IN_PLACE) && !str_contains($block, 'new production URL') && calls() === []);
check('... a managed site: no Set up by hand disclosure; the Webhook disclosure offers Rotate token (a REST action button) beside the token row',
    !str_contains($block, 'id="pqm-by-hand"') && str_contains($block, 'id="pqm-webhook"')
    && str_contains($block, '<button type="button" class="button pqm-rotate-token" data-pqm-post="n8n/token" data-pqm-busy="Rotating the token…">Rotate token</button>')
    && str_contains($block, 'value="' . TOKEN . '"'));
managed([], $adopted_map + $older_report, V5_HOOK);
check('... an adopted map (wizard_created false) on its own v5 path: offered too, asking first (story 76: data-pqm-confirm)', str_contains($block = PostQuality_Admin::render_workflow_body(), str_replace('data-pqm-post="n8n/workflow"', 'data-pqm-post="n8n/workflow" data-pqm-confirm="update"', UPDATE_BUTTON)) && one_primary($block));
managed([], $setup_map + ['postquality_workflow_version_seen' => POSTQUALITY_WORKFLOW_VERSION - 1]);
check('... a pre-v6 report (a legacy int): still no "new production URL" sentence — the update keeps the URL', str_contains($block = PostQuality_Admin::render_workflow_body(), UPDATE_BUTTON)
    && !str_contains($block, 'new production URL') && str_contains($block, IN_PLACE));
managed([], $setup_map + ['postquality_workflow_version_seen' => ['version' => POSTQUALITY_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000, 'variant' => 'free']]);
check('... the Free variant on Pro: the Free-variant sentence, the same button', str_contains($block = PostQuality_Admin::render_workflow_body(), 'Your workflow is the Free variant')
    && state_of($block) === 'outdated' && str_contains($block, UPDATE_BUTTON) && one_primary($block) && !str_contains($block, 'pqm-reimport'));
managed([], $setup_map + ['postquality_workflow_version_seen' => ['version' => POSTQUALITY_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => 1700000000, 'miswired' => SHARED_SENTENCE]]);
$block = PostQuality_Admin::render_workflow_body();
check('mis-wired first, and older: the sentence, Test again the one primary, Update workflow secondary after it', state_of($block) === 'failing'
    && str_contains($block, 'class="button button-primary pqm-test-connection">Test again</button> <button type="button" class="button pqm-update-workflow"') && one_primary($block));
foreach ($not_offered as $label => $change) {
    managed([], $setup_map + $older_report);
    $change();
    $block = PostQuality_Admin::render_workflow_body();
    check("not offered ($label): Re-import the workflow as before, no Update workflow, zero calls", $label === 'no URL saved'
        ? !str_contains($block, 'pqm-update-workflow') && one_primary($block) && calls() === [] // no URL: not set up at all
        : str_contains($block, 'class="button button-primary pqm-reimport"') && !str_contains($block, 'pqm-update-workflow') && !str_contains($block, IN_PLACE) && one_primary($block) && calls() === []);
}
managed([], $setup_map + ['postquality_workflow_version_seen' => ['version' => POSTQUALITY_WORKFLOW_VERSION, 'via' => 'test', 'at' => 1700000000]]);
check('up to date, managed: set up, no update button', state_of($block = PostQuality_Admin::render_workflow_body()) === 'set-up' && !str_contains($block, 'pqm-update-workflow'));

echo "Update: update\n";
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
$r = update();
check('calls: GET /workflows/{the map\'s id}, PUT the same, then the ping\'s diagnosis reads it again (entry 7); every call signed, nothing left unscripted',
    calls() === [READ_WF, PUT_WF, READ_WF] && calls_signed(KEY) && $GLOBALS['tw']['queue'] === []);
$sent = body(1);
check('the PUT body: {name, nodes, connections, settings} only, never `active`; connections and settings the template\'s; JSON',
    array_keys($sent) === ['name', 'nodes', 'connections', 'settings'] && $sent['connections'] === $template['connections'] && $sent['settings'] === $template['settings']
    && $GLOBALS['tw']['calls'][1][1]['headers']['Content-Type'] === 'application/json');
check('... the nodes are the build\'s template, credentials apart', $strip($sent['nodes']) === $strip($template['nodes']));
$links = linked($sent);
check('... all 8 credentialed nodes carry the map\'s id with the name the read workflow carries for it', count($links) === 8
    && $links['Webhook Trigger'] === ['httpHeaderAuth' => WEBHOOK_CRED] && $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => WP_CRED]
    && count(array_filter($links, static fn($c) => $c === ['httpHeaderAuth' => ANTHROPIC_CRED])) === 6);
check('... the trigger on the map\'s path; the workflow\'s name kept (D2)', trigger_path($sent) === 'pqm-execute-example-com' && $sent['name'] === 'Post Quality — example.com');
check('... then one ping of the saved URL, with the token', count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === HOOK
    && $GLOBALS['tw']['pings'][0][1]['headers']['X-PQM-Token'] === TOKEN);
check('... reply {updated, ping_ok, outdated, message, rewrite_ready, status_html}: "Workflow updated." + the ping\'s message', is_array($r)
    && array_keys($r) === ['updated', 'ping_ok', 'outdated', 'message', 'rewrite_ready', 'status_html'] && $r['updated'] === true && $r['ping_ok'] === true
    && $r['outdated'] === false && $r['rewrite_ready'] === true && $r['message'] === 'Workflow updated. ' . PING_OK);
check('... the version recorded by a test; the body is set up with the new version, the message first, no update button', postquality_workflow_report()['version'] === POSTQUALITY_WORKFLOW_VERSION
    && postquality_workflow_report()['via'] === 'test' && str_contains($r['status_html'], 'Version ' . POSTQUALITY_WORKFLOW_VERSION . ' · checked just now by a test.')
    && state_of($r['status_html']) === 'set-up' && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">' . $r['message'] . '</p>')
    && !str_contains($r['status_html'], 'pqm-update-workflow') && one_primary($r['status_html']));
check('... the version record the one write: the map, the connection and the settings untouched', writes() === ['postquality_workflow_version_seen']
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $setup_map[PostQuality_N8n::SETUP_OPTION]);
managed([resp(200, ['name' => 'Reviews for my blog'] + stored_workflow('wf1', wired())), UPDATED, CLEAN_WF], $setup_map + $older_report);
update();
check('a renamed workflow: the PUT carries the name n8n holds, not the template\'s (D2)', body(1)['name'] === 'Reviews for my blog');
managed([resp(200, stored_workflow('wf1', wired(['Post Quality webhook - example.com' => ['id' => 'c-other', 'name' => 'Header Auth account']] + WIRED))), UPDATED, CLEAN_WF], $setup_map + $older_report);
update();
check('a map id the read workflow no longer carries (the trigger was re-pointed in n8n): linked by the map\'s id, with an empty name',
    linked(body(1))['Webhook Trigger'] === ['httpHeaderAuth' => ['id' => 'c-webhook', 'name' => '']]);
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
$GLOBALS['tw']['ping_response'] = resp(200, ['ok' => true, 'workflow_version' => POSTQUALITY_WORKFLOW_VERSION - 1]);
$r = update();
check('an older workflow still answers the ping (not republished yet): updated, ping_ok and outdated true, a warning; the block offers the update again',
    $r['updated'] === true && $r['ping_ok'] === true && $r['outdated'] === true && str_starts_with($r['message'], 'Workflow updated. Ping OK — this site reaches n8n, but your n8n workflow is an older version')
    && str_contains($r['status_html'], UPDATE_BUTTON));
managed([CLEAN_WF, UPDATED, resp(200, stored_workflow('wf1', $oct13))], $setup_map + $older_report);
$r = update();
check('the ping\'s diagnosis finds the published version mis-wired: the sentence as the message, outdated; the block shows the sentence with Test again as the one primary (the version is current: no update notice)',
    $r['ping_ok'] === true && $r['message'] === 'Workflow updated. ' . SHARED_SENTENCE && $r['outdated'] === true && postquality_workflow_report()['miswired'] === SHARED_SENTENCE
    && str_contains($r['status_html'], 'class="button button-primary pqm-test-connection"') && !str_contains($r['status_html'], 'pqm-update-workflow') && one_primary($r['status_html']));

echo "Update: an adopted workflow (free names, the v5 path, Rewrite unwired)\n";
$hand_read = resp(200, ['name' => 'My blog reviews'] + stored_workflow('wf-hand', with_path(wired(['WordPress - example.com' => null] + HAND_NAMES), 'pqm-execute')));
managed([$hand_read, resp(200, ['id' => 'wf-hand', 'name' => 'My blog reviews']), $hand_read], $adopted_map + $older_report, V5_HOOK);
$r     = update();
$sent  = body(1);
$links = linked($sent);
check('GET then PUT on the adopted id; the trigger keeps pqm-execute (D1: never postquality_webhook_path()); the owner\'s names beside the map\'s ids; the owner\'s workflow name',
    array_slice(calls(), 0, 2) === [READ_HAND, 'PUT ' . BASE . '/api/v1/workflows/wf-hand'] && trigger_path($sent) === 'pqm-execute' && $sent['name'] === 'My blog reviews'
    && $links['Webhook Trigger'] === ['httpHeaderAuth' => HAND_HOOK] && count(array_filter($links, static fn($c) => $c === ['httpHeaderAuth' => HAND_CLAUDE])) === 6);
check('... Rewrite unwired (Pro): Update WP Post (Rewrite) stays name-only; rewrite_ready false and the message says Rewrite stays off', $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => ['name' => 'WordPress - example.com']]
    && $r['rewrite_ready'] === false && $r['ping_ok'] === true && str_starts_with($r['message'], 'Workflow updated. ' . PING_OK)
    && str_ends_with($r['message'], 'Rewrite stays off until a WordPress credential is set in n8n on the Update WP Post (Rewrite) node.'));
check('... the diagnosis after skips that node (6.7\'s D1): the version recorded, the body set up, the message a warning (Rewrite off)', postquality_workflow_report()['version'] === POSTQUALITY_WORKFLOW_VERSION
    && state_of($r['status_html']) === 'set-up' && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-warning">'));
// The map's path is the trigger's raw string: a `"` in it must survive the template fill (JSON-escaped), not break the decode.
$quoted_read = resp(200, ['name' => 'My blog reviews'] + stored_workflow('wf-hand', with_path(wired(HAND_NAMES), 'pqm-"quoted"')));
managed([$quoted_read, resp(200, ['id' => 'wf-hand']), $quoted_read], [PostQuality_N8n::SETUP_OPTION => ['path' => 'pqm-"quoted"'] + $adopted_map[PostQuality_N8n::SETUP_OPTION]] + $older_report, BASE . '/webhook/pqm-"quoted"');
$r = update();
check('an adopted path carrying a `"`: the template fills and decodes, the PUT body\'s trigger path is the raw path, updated', is_array($r) && $r['updated'] === true
    && is_array(body(1)) && trigger_path(body(1)) === 'pqm-"quoted"' && array_slice(calls(), 0, 2) === [READ_HAND, 'PUT ' . BASE . '/api/v1/workflows/wf-hand']);

echo "Update: refused at the gate — zero calls, nothing written\n";
foreach ([
    'no key'                             => ['pqm_n8n_not_connected', 400],
    'a key without a passing check'      => ['pqm_n8n_not_connected', 400],
    'no map'                             => ['pqm_workflow_not_managed', 409],
    'a map without a webhook credential' => ['pqm_workflow_not_managed', 409],
    'no URL saved'                       => ['pqm_webhook_url_changed', 409],
    'the URL on another path'            => ['pqm_webhook_url_changed', 409],
] as $label => [$code, $status]) {
    managed([CLEAN_WF, UPDATED], $setup_map + $older_report);
    $not_offered[$label]();
    $r = update();
    check("$label: $status $code, zero calls, nothing written, no ping", refused($r, $code, $status) && calls() === []);
}
managed([CLEAN_WF, UPDATED], [PostQuality_N8n::SETUP_OPTION => ['workflow_id' => ''] + $setup_map[PostQuality_N8n::SETUP_OPTION]] + $older_report);
check('a map without a workflow id: 409 pqm_workflow_not_managed, zero calls; the block offers Re-import, not rotatable either', refused(update(), 'pqm_workflow_not_managed', 409)
    && calls() === [] && str_contains(PostQuality_Admin::render_workflow_body(), 'pqm-reimport') && PostQuality_N8n::public_state()['rotatable'] === false);

echo "Update: the workflow is gone\n";
managed([NOT_FOUND, UPDATED], $setup_map + $older_report);
$r = update();
check('a JSON 404 on the GET: 502 pqm_update_failed, the sentence says the workflow is gone and the two ways out; no PUT, nothing written, no ping',
    refused($r, 'pqm_update_failed', 502) && $r->message === WORKFLOW_GONE && calls() === [READ_WF] && $GLOBALS['tw']['queue'] === [UPDATED]);

echo "Update: the read refused — no PUT\n";
foreach ([
    'a 500'                      => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'a proxy\'s non-JSON 404'    => [resp(404, '<html>Not Found</html>'), 'n8n answered HTTP 404'],
    'a proxy\'s non-JSON 200'    => [resp(200, '<html>Just a moment...</html>'), 'n8n did not answer with your workflows'],
    'n8n unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
] as $label => [$answer, $says]) {
    managed([$answer, UPDATED], $setup_map + $older_report);
    $r = update();
    check("$label: 502 pqm_update_failed, could_not('read')'s sentence, n8n's body not repeated; no PUT, nothing written, no ping", refused($r, 'pqm_update_failed', 502)
        && $r->message === "The plugin could not finish reading your workflows ($says). Try again in a moment." && !str_contains($r->message, 'Internal error')
        && calls() === [READ_WF] && $GLOBALS['tw']['queue'] === [UPDATED]);
}

echo "Update: the PUT refused — the version record untouched, no ping\n";
foreach ([
    'a 400 quoting the request'  => [resp(400, ['message' => 'request/body/nodes must match ' . TOKEN]), 'n8n answered HTTP 400'],
    'a 500'                      => [resp(500, ['message' => 'Internal error']), 'n8n answered HTTP 500'],
    'a JSON 404 (gone meanwhile)' => [NOT_FOUND, 'n8n answered HTTP 404'],
    'a 2xx without JSON'         => [resp(200, '<html>Just a moment...</html>'), 'n8n did not confirm the update'],
    'n8n unreachable (WP_Error)' => [timed_out(), 'n8n could not be reached: cURL error 28: timed out'],
] as $label => [$answer, $says]) {
    managed([CLEAN_WF, $answer], $setup_map + $older_report);
    $r = update();
    check("$label: 502 pqm_update_failed, stopped('update')'s sentence, n8n's body not repeated; GET then PUT, nothing written, no ping", refused($r, 'pqm_update_failed', 502)
        && $r->message === sprintf(UPDATE_STOPPED, $says) && !str_contains($r->message, 'Internal error') && !str_contains($r->message, 'must match')
        && calls() === [READ_WF, PUT_WF] && $GLOBALS['tw']['options']['postquality_workflow_version_seen'] === $older_report['postquality_workflow_version_seen']);
}
check('... the block still offers the update', str_contains(PostQuality_Admin::render_workflow_body(), UPDATE_BUTTON));

echo "Update: the ping fails after — updated, no version recorded\n";
foreach ([
    'the URL does not answer (404, after the retries)' => [resp(404, ['message' => 'The requested webhook is not registered.']), 'The workflow was updated in n8n. n8n responded with 404. Check the URL and make sure the workflow is activated in n8n.', 3],
    'the webhook unreachable'       => [timed_out(), 'The workflow was updated in n8n. Could not connect to n8n: cURL error 28: timed out', 1],
    'a 401'                         => [resp(401, ''), 'The workflow was updated in n8n. n8n refused the webhook credential (HTTP 401).', 1],
] as $label => [$ping, $message, $pings]) {
    managed([CLEAN_WF, UPDATED], $setup_map + $older_report);
    $GLOBALS['tw']['ping_response'] = $ping;
    $r = update();
    check("$label: updated, ping_ok false, the message says so; the body needs attention (the failure the only option written, once per ping; the older version kept)", is_array($r)
        && array_keys($r) === ['updated', 'ping_ok', 'outdated', 'message', 'rewrite_ready', 'status_html'] && $r['updated'] === true && $r['ping_ok'] === false && $r['outdated'] === false
        && str_starts_with($r['message'], $message) && writes() === array_fill(0, $pings, 'postquality_workflow_version_seen') && postquality_workflow_report()['version'] === POSTQUALITY_WORKFLOW_VERSION - 1
        && state_of($r['status_html']) === 'failing' && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-error">' . $r['message'] . '</p>')
        && calls() === [READ_WF, PUT_WF] && count($GLOBALS['tw']['pings']) === $pings);
}

echo "Update: the ping meets n8n's publication window (bug 84)\n";
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
$GLOBALS['tw']['ping_queue'] = [resp(404, ['message' => 'The requested webhook is not registered.'])];
$r = update();
check('404 then a pass: 2 pings of the saved URL, with the token; updated, ping_ok, "Workflow updated." + the ping\'s message', is_array($r) && $r['ping_ok'] === true
    && count($GLOBALS['tw']['pings']) === 2 && array_unique(array_column($GLOBALS['tw']['pings'], 0)) === [HOOK] && $GLOBALS['tw']['pings'][1][1]['headers']['X-PQM-Token'] === TOKEN
    && str_starts_with($r['message'], 'Workflow updated. Ping OK'));
check('... the template\'s version recorded, no failure on record; the diagnosis read once, after the passing ping; the block set up',
    postquality_workflow_report()['version'] === POSTQUALITY_WORKFLOW_VERSION && !isset(postquality_workflow_report()['failed']) && calls() === [READ_WF, PUT_WF, READ_WF]
    && $GLOBALS['tw']['queue'] === [] && state_of($r['status_html']) === 'set-up');
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
$GLOBALS['tw']['ping_queue'] = [resp(404, ['message' => 'not registered']), resp(404, ['message' => 'not registered'])];
$r = update();
check('404, 404, then a pass: 3 pings, then as passed', count($GLOBALS['tw']['pings']) === 3 && $r['ping_ok'] === true && !isset(postquality_workflow_report()['failed']));
managed([CLEAN_WF, UPDATED], $setup_map + $older_report);
$GLOBALS['tw']['ping_queue'] = [resp(401, '')];
$r = update();
check('a 401 first: no retry (only a 404 is the window), one ping, the failure', count($GLOBALS['tw']['pings']) === 1 && $r['ping_ok'] === false);

echo "Update: after\n";
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report);
update();
$GLOBALS['tw']['calls'] = [];
forget();
$GLOBALS['tw']['options']['postquality_workflow_version_seen'] = $older_report['postquality_workflow_version_seen'];
check('forget key after: the notice falls back to Re-import the workflow, the map stays', str_contains($block = PostQuality_Admin::render_workflow_body(), 'pqm-reimport')
    && !str_contains($block, 'pqm-update-workflow') && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $setup_map[PostQuality_N8n::SETUP_OPTION] && calls() === []);
check('... an update then: 400 pqm_n8n_not_connected, zero calls', update()->code === 'pqm_n8n_not_connected' && calls() === []);

echo "Update: leak scan and the two calls\n";
$update_calls = array_merge(...array_map(static fn($run) => array_slice($run[2], 0, 2), $update_runs)); // the update's own calls; a third is the ping's diagnosis
check('every n8n call of the updates (' . count($update_calls) . ') is a GET or a PUT of the map\'s workflow id, never a credential, never a list', count($update_calls) >= 20
    && !array_filter($update_calls, static fn($c) => !in_array($c[1]['method'], ['GET', 'PUT'], true) || !preg_match('#/api/v1/workflows/(wf1|wf-hand)$#', $c[0])));
check('... no update made more than one PUT, and only after a GET of the same workflow', !array_filter($update_runs, static function ($run) {
    $puts = array_values(array_filter($run[2], static fn($c) => $c[1]['method'] === 'PUT'));
    return count($puts) > 1 || ($puts !== [] && ($run[2][0][1]['method'] !== 'GET' || $run[2][0][0] !== $puts[0][0] || $run[2][1] !== $puts[0]));
}));
check('the n8n key, the webhook token and the provider key are in no reply field and no PUT body (credential ids and names only) (' . count($update_runs) . ' updates)',
    !array_filter($update_runs, static fn($run) => str_contains($run[0], KEY) || str_contains(without_body($run[0]), TOKEN) || str_contains($run[0], PROVIDER_KEY)
        || array_filter($run[2], static fn($c) => $c[1]['method'] === 'PUT' && preg_match('/' . preg_quote(KEY, '/') . '|' . preg_quote(TOKEN, '/') . '|' . preg_quote(PROVIDER_KEY, '/') . '/', (string) ($c[1]['body'] ?? '')))));
preg_match('/function update_workflow\(.*?\n    }\n/s', $src_n8n, $m);
check('the update code calls n8n twice through request(), a GET then a PUT, and never reads /credentials or the list', ($m[0] ?? '') !== ''
    && substr_count($m[0], 'self::request(') === 2 && str_contains($m[0], "self::request( 'GET', \$path )") && str_contains($m[0], "self::request( 'PUT', \$path, \$body )")
    && !str_contains($m[0], '/credentials') && !str_contains($m[0], 'ACTIVE_WORKFLOWS') && !preg_match("/'(POST|PATCH|DELETE)'/", $m[0]));
check('every status block the update rows answered: at most one primary, one whenever a notice shows', !array_filter($update_runs, static function ($run) {
    $html = json_decode($run[0], true)['status_html'] ?? null;
    return is_string($html) && !one_primary($html);
}));

/* ─── Story 71: start over, and a map that remembers its instance ────── */
echo "Start over: route\n";
$route = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/n8n/setup'] ?? [];
check('/n8n/setup: DELETE, gated by check_manage_settings, remove_from_n8n a boolean, false by default', ($route['methods'] ?? null) === 'DELETE'
    && ($route['permission_callback'] ?? null) === $gate && ($route['args']['remove_from_n8n'] ?? null) === ['required' => false, 'type' => 'boolean', 'default' => false]);


echo "Start over: one row per line of the plan's matrix\n";
managed([INACTIVE, DELETED, DELETED, DELETED, DELETED], [PostQuality_N8n::SETUP_OPTION => pro_map(1)] + $report);
$r = start_over(true);
check('remove, a wizard-created map: deactivate, DELETE the workflow, DELETE each credential — the map\'s ids, the rollback order; signed', calls() === UNDO_ALL && calls_signed(KEY) && $GLOBALS['tw']['queue'] === []);
check('... the Application Password revoked (bug 73\'s helper), once', $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']]);
check('... the map, the version report and the URL gone (one write of postquality_settings, the token kept); the key stays; no leftovers, no ping', started_over()
    && count(array_keys($GLOBALS['tw']['writes'], 'postquality_settings', true)) === 1 && !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]) && $GLOBALS['tw']['pings'] === []);
check('... reply = public_state() (setup null, not adoptable, not rotatable) + message + status_html: "removed here and in n8n"', is_array($r)
    && array_keys($r) === [...$state_keys, 'message', 'status_html'] && $r['setup'] === null && $r['adoptable'] === false && $r['rotatable'] === false && $r['key_set'] === true
    && $r['message'] === REMOVED_BOTH);
check('... status_html: state 2 (connected, not set up), the message its success line, one primary', state_of($r['status_html']) === 'connected'
    && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">' . REMOVED_BOTH . '</p>') && one_primary($r['status_html']));
managed([], $adopted_map + $report, V5_HOOK);
$r = start_over(false);
check('keep, an adopted map: zero calls, the same clears, no revoke (the map names no password), "stay in n8n", state 2', calls() === [] && started_over()
    && $GLOBALS['tw']['app_revoked'] === [] && $r['message'] === REMOVED_HERE && state_of($r['status_html']) === 'connected' && $r['setup'] === null);
managed([], [PostQuality_N8n::SETUP_OPTION => pro_map(1)] + $report);
$r = start_over(false);
check('keep, a wizard-created map: zero calls, the password revoked all the same (useless without the map), "stay in n8n"', calls() === [] && started_over()
    && $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']] && $r['message'] === REMOVED_HERE);
managed([INACTIVE, UNPUBLISHING], [PostQuality_N8n::SETUP_OPTION => pro_map(1)] + $report);
$r = start_over(true);
$record = $GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION] ?? null;
check('partial refusal (the DELETE answers 409, still unpublishing): the credentials untouched, no call for them', calls() === array_slice(UNDO_ALL, 0, 2));
check('... postquality_n8n_leftovers = {base_url, workflow_id, credentials, at}, autoload off', is_array($record) && array_diff_key($record, ['at' => 0]) === ['base_url' => BASE, 'workflow_id' => 'wf1', 'credentials' => ['c-webhook', 'c-anthropic', 'c-wp']]
    && abs($record['at'] - time()) <= 5 && $GLOBALS['tw']['autoload'][PostQuality_N8n::LEFTOVERS_OPTION] === false);
check('... the clears done anyway, the password revoked; the message says some of it is still in n8n, a warning line, state 2', started_over() && $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']]
    && $r['message'] === REMOVED_LEFT && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-warning">' . REMOVED_LEFT . '</p>') && state_of($r['status_html']) === 'connected');
managed([timed_out()], [PostQuality_N8n::SETUP_OPTION => pro_map(1)] + $report);
$r = start_over(true);
check('unreachable on the deactivate: one call, the whole map on record, the clears done, the warning', calls() === [UNDO_ALL[0]]
    && array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'workflow_id' => 'wf1', 'credentials' => ['c-webhook', 'c-anthropic', 'c-wp']]
    && started_over() && $r['message'] === REMOVED_LEFT);
managed([], $elsewhere + $report);
$r = start_over(true);
check('other instance, remove: 409 pqm_other_instance, zero calls, nothing cleared, no revoke', refused($r, 'pqm_other_instance', 409) && $r->message === OTHER_BLOCKED && calls() === []
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] === $elsewhere[PostQuality_N8n::SETUP_OPTION] && $GLOBALS['tw']['app_revoked'] === [] && token_now() === TOKEN);
managed([], $elsewhere + $report);
$r = start_over(false);
check('other instance, keep: the clears done, zero calls (the map\'s ids never reach the connected instance), the password revoked', calls() === [] && started_over()
    && $r['message'] === REMOVED_HERE && $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']]);
connected([]);
$GLOBALS['tw']['writes'] = [];
check('no map: 409 pqm_workflow_not_managed, nothing written, either way', refused(start_over(true), 'pqm_workflow_not_managed', 409)
    && refused(start_over(false), 'pqm_workflow_not_managed', 409) && calls() === []);
managed([], [PostQuality_N8n::SETUP_OPTION => pro_map(1)] + $report);
unset($GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['api_key']);
$r = start_over(false);
check('no key, keep: the clears done, zero calls, "removed here"', calls() === [] && $r['message'] === REMOVED_HERE && !isset($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION])
    && $GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url'] === '' && !isset($GLOBALS['tw']['options']['postquality_workflow_version_seen']) && $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']]);
managed([], [PostQuality_N8n::SETUP_OPTION => pro_map(1)] + $report);
unset($GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['api_key']);
check('no key, remove: 400 pqm_n8n_not_connected, zero calls, nothing cleared (the island never offers it)', refused(start_over(true), 'pqm_n8n_not_connected', 400) && calls() === []
    && isset($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]) && $GLOBALS['tw']['app_revoked'] === []);
// Review fix: a refused removal must not silently replace a record of another instance (a failed run on A, then B adopted and started over).
managed([INACTIVE, UNPUBLISHING], [PostQuality_N8n::SETUP_OPTION => pro_map(1), PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://old.example.com', 'workflow_id' => 'wf-old', 'credentials' => ['c-old1'], 'at' => 1700000000]] + $report);
$r = start_over(true);
check('partial refusal over a record of another instance: the connected instance\'s remainder is the record now (the actionable one); the reply names the old host first, then the warning', is_array($r)
    && array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'workflow_id' => 'wf1', 'credentials' => ['c-webhook', 'c-anthropic', 'c-wp']]
    && $r['message'] === 'An earlier setup left a workflow or credentials on old.example.com. The plugin does not touch another instance: remove them there by hand. ' . REMOVED_LEFT
    && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-warning">' . $r['message'] . '</p>') && started_over() && !isset($r['dropped_leftovers']));
managed([INACTIVE, UNPUBLISHING], [PostQuality_N8n::SETUP_OPTION => pro_map(1), PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'credentials' => ['c-old1'], 'at' => 1700000000]] + $report);
$r = start_over(true);
check('... over a record of the connected instance: replaced by the remainder, no prefix (the earlier remainder was for the same place; the record names what is there now)',
    array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'workflow_id' => 'wf1', 'credentials' => ['c-webhook', 'c-anthropic', 'c-wp']] && $r['message'] === REMOVED_LEFT);
$src_n8n = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-n8n.php');
check('the removal goes through roll_back() (one caller more than fail() and remove_leftovers()); the key is not forgotten (D1)', substr_count($src_n8n, 'self::roll_back(') === 3
    && preg_match('/function start_over\(.*?\n    }\n/s', $src_n8n, $m) && !str_contains($m[0], 'forget(') && !str_contains($m[0], 'self::OPTION'));

echo "Start over: the map remembers its instance\n";
connected(PRO_OK);
provision();
check('provision(): the map carries base_url = the connected origin; public_state().setup carries host and same_instance', ($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['base_url'] ?? null) === BASE
    && array_diff_key(PostQuality_N8n::public_state()['setup'], ['at' => 0]) === ['wizard_created' => true, 'host' => 'n8n.example.com', 'same_instance' => true]);
hand_made([hand_list($hand_nodes)]);
adopt_post('wf-hand');
check('adopt(): the same', ($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['base_url'] ?? null) === BASE
    && array_diff_key(PostQuality_N8n::public_state()['setup'], ['at' => 0]) === ['wizard_created' => false, 'host' => 'n8n.example.com', 'same_instance' => true]);

echo "Start over: a map made on another instance\n";
managed([], $elsewhere + $older_report);
$blocked = PostQuality_N8n::managed_blocked();
check('managed_blocked(): 409 pqm_other_instance, the sentence names the instance and Start over; zero calls', $blocked instanceof WP_Error && $blocked->code === 'pqm_other_instance'
    && $blocked->data === ['status' => 409] && $blocked->message === OTHER_BLOCKED && calls() === []);
$s = get_state();
check('... the state: rotatable false, setup.same_instance false, setup.host the map\'s', $s['rotatable'] === false && $s['setup']['same_instance'] === false && $s['setup']['host'] === 'other.example.com');
check('... rotate and update refused the same, zero calls, nothing written', refused(rotate(), 'pqm_other_instance', 409) && refused(update(), 'pqm_other_instance', 409) && calls() === []);
managed([], $elsewhere + $older_report, BASE . '/webhook/elsewhere');
check('... the URL off the map\'s path too: still pqm_other_instance (the instance gate before the path one — saving the map\'s URL could not pass on this instance)',
    refused(rotate(), 'pqm_other_instance', 409) && PostQuality_N8n::managed_blocked()->code === 'pqm_other_instance' && calls() === []);
$block = PostQuality_Admin::render_workflow_body();
check('... the page: Re-import the workflow, no Update workflow, no Rotate token', str_contains($block, 'pqm-reimport') && !str_contains($block, 'pqm-update-workflow') && !str_contains($block, 'pqm-rotate-token'));
managed([LIST_OK], $elsewhere);
check('diagnose(): the map ignored — the list read, no GET by the old id', diagnosed(HOOK) === null && calls() === [LIST_READ]);
managed([], $elsewhere);
$r = provision();
check('provisioning with a map: 409 pqm_already_provisioned, the sentence ends with the Start over clause; zero calls', refused($r, 'pqm_already_provisioned', 409) && calls() === []
    && str_ends_with($r->message, ' ' . START_OVER_CLAUSE));
check('... adoption too', refused(proposal(), 'pqm_already_provisioned', 409) && calls() === []);
check('the gone sentence of the update ends with the clause too (the rotation\'s is asserted in its row)', str_ends_with(WORKFLOW_GONE, ' ' . START_OVER_CLAUSE));

echo "Start over: the workflow is gone (a 404 test ping)\n";
managed([NOT_FOUND], $setup_map);
$GLOBALS['tw']['ping_response'] = resp(404, ['message' => 'The requested webhook is not registered.']);
$ping = PostQuality_Admin::ping_webhook(HOOK);
check('a 404 of the saved URL, the map\'s workflow a JSON 404 by id: one GET, the gone sentence, recorded as the failed test', calls() === [READ_WF] && $ping['ok'] === false
    && $ping['message'] === GONE_PING && (postquality_workflow_report()['failed']['message'] ?? null) === GONE_PING && !array_key_exists('miswired', $ping));
$block = PostQuality_Admin::render_workflow_body();
check('... the page needs attention with that sentence, one primary', state_of($block) === 'failing' && str_contains($block, GONE_PING) && one_primary($block));
foreach (['n8n answers 200 (the workflow is there)' => CLEAN_WF, 'n8n unreachable' => timed_out(), 'a proxy\'s non-JSON 404' => resp(404, '<html>Not Found</html>')] as $label => $answer) {
    managed([$answer], $setup_map);
    $GLOBALS['tw']['ping_response'] = resp(404, ['message' => 'not registered']);
    $ping = PostQuality_Admin::ping_webhook(HOOK);
    check("not gone ($label): one GET, the ordinary 404 sentence", calls() === [READ_WF] && $ping['message'] === PLAIN_404 && (postquality_workflow_report()['failed']['message'] ?? null) === PLAIN_404);
}
foreach ([
    'the URL on another path'   => static function () { $GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url'] = BASE . '/webhook/elsewhere'; },
    'a map of another instance' => static function () { $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['base_url'] = OTHER_INSTANCE; },
    'no key'                    => static function () { unset($GLOBALS['tw']['options'][PostQuality_N8n::OPTION]['api_key']); },
    'no map'                    => static function () { unset($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]); },
    'the ping a 401, not a 404' => static function () { $GLOBALS['tw']['ping_response'] = resp(401, ''); },
] as $label => $change) {
    managed([NOT_FOUND], $setup_map);
    $GLOBALS['tw']['ping_response'] = resp(404, ['message' => 'not registered']);
    $change();
    $ping = PostQuality_Admin::ping_webhook($GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url']);
    check("no read ($label): zero calls, the ping's own sentence", calls() === [] && $ping['message'] !== GONE_PING && $GLOBALS['tw']['queue'] === [NOT_FOUND]);
}
managed([PATCHED, NOT_FOUND], $setup_map);
$GLOBALS['tw']['ping_response'] = resp(404, ['message' => 'not registered']);
$r = rotate();
check('the rotation\'s ping (no diagnosis): no read, the ordinary 404 sentence after the rotated prefix', calls() === [PATCH_WEBHOOK] && $GLOBALS['tw']['queue'] === [NOT_FOUND]
    && $r['message'] === ROTATED_PREFIX . ' ' . PLAIN_404);
managed([CLEAN_WF, UPDATED, NOT_FOUND], $setup_map + $older_report);
$GLOBALS['tw']['ping_response'] = resp(404, ['message' => 'not registered']);
$r = update();
check('the update\'s ping: no read either (the PUT just passed)', calls() === [READ_WF, PUT_WF] && $GLOBALS['tw']['queue'] === [NOT_FOUND] && str_ends_with($r['message'], PLAIN_404));
connected(PRO_OK);
$GLOBALS['tw']['ping_response'] = HOOK_404;
$r = provision();
check('the pings after provisioning: no read either (n8n\'s publication window), the bug 80 sentence', calls() === CREATE_ALL && $r['message'] === EARLY_404);

echo "Start over: leftovers of another instance\n";
$old_record = [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://old.example.com', 'workflow_id' => 'wf-old', 'credentials' => ['c-old1'], 'at' => 1700000000]];
connected([], $old_record);
$s = PostQuality_REST_API::get_setup_callback()->get_data();
$replies[] = json_encode($s);
check('GET /setup before the run: leftovers false, leftovers_elsewhere = the old host', $s['leftovers'] === false && $s['leftovers_elsewhere'] === 'old.example.com');
connected([], [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'credentials' => ['c1'], 'at' => 1]]);
$s = PostQuality_REST_API::get_setup_callback()->get_data();
check('... a record of the connected instance: leftovers true, leftovers_elsewhere \'\'', $s['leftovers'] === true && $s['leftovers_elsewhere'] === '');
connected([]);
$s = PostQuality_REST_API::get_setup_callback()->get_data();
check('... no record: both off', $s['leftovers'] === false && $s['leftovers_elsewhere'] === '');
connected(PRO_OK, $old_record);
$r = provision();
check('provisioning: the normal run, nothing sent to the old instance, the record dropped', calls() === [...CREATE_ALL, READ_WF] && $r['provisioned'] === true
    && !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]));
check('... the reply\'s message prefixed with the old host and the by-hand removal, then the ping\'s; the body shows it; the map stores no trace of it; the reply\'s keys as before',
    $r['message'] === 'An earlier setup left a workflow or credentials on old.example.com. The plugin does not touch another instance: remove them there by hand. ' . PING_OK
    && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">' . $r['message'] . '</p>') && !isset($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['dropped_leftovers'])
    && array_keys($r) === ['provisioned', 'ping_ok', 'workflow_id', 'webhook_url', 'message', 'rewrite_ready', 'status_html'] && postquality_workflow_report()['version'] === POSTQUALITY_WORKFLOW_VERSION);
connected(PRO_OK, $old_record);
$GLOBALS['tw']['ping_response'] = HOOK_404;
$r = provision();
check('... a failed ping after: the prefix on the reply, the failure on record without it', str_starts_with($r['message'], 'An earlier setup left a workflow or credentials on old.example.com.')
    && str_ends_with($r['message'], EARLY_404) && (postquality_workflow_report()['failed']['message'] ?? null) === EARLY_404);

key_leak_scan($replies);

harness_exit();
