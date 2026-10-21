<?php
/**
 * n8n API client harness — n8n provisioning, entry 1 (connect by key). Runs
 * every row of the plan's I/O & Edge-Case Matrix through GET|POST|DELETE
 * /n8n/connection and PQM_N8n, with wp_remote_request() answering from a
 * scripted queue. Every recorded call is checked for the plugin User-Agent,
 * no redirect following, and the key header; every reply is checked for the
 * key string. WP stubs, no WP install; callbacks are called directly.
 * Entry 2 rows: the failure record (`failed`) a manual-path cause leaves,
 * and what clears it.
 * Story 74 rows: the stored probe is the last probe of the stored key — a
 * 401 from it (Check again, a managed call, the diagnosis) replaces it, and
 * `connected` is the one predicate; the route probe's 401 and JSON 403; the
 * rotation refused while a mis-wiring sentence is stored; a refused Rotate or
 * Update carries the page's body (`status_html` in the error data). The
 * connection state is the client's concern, so these rows sit here although
 * they drive the managed actions (rotate(), update(), proposal(),
 * start_over() come from the world include).
 * The world (stubs, constants, builders, the plugin load) is
 * lib/n8n-world.php; story 78 build 2 split the one n8n harness by concern.
 * Run: php n8n-client-harness.php
 */

require __DIR__ . '/lib/n8n-world.php';

/* ─── Row: No right — every route gated ──────────────────────────────── */
echo "Routes and permission\n";
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/n8n/connection'] ?? [];
check('/n8n/connection: GET, POST and DELETE endpoints', array_column($route, 'methods') === ['GET', 'POST', 'DELETE']);
check('... every one gated by check_manage_settings', count($route) === 3 && array_unique(array_column($route, 'permission_callback'), SORT_REGULAR) === [$gate]);
$GLOBALS['tw']['caps'] = ['pqm_manage_agents', 'pqm_view_reviews', 'pqm_run_free_agents', 'pqm_run_paid_agents'];
check('user without pqm_manage_settings: refused (WP answers 403)', PQM_REST_API::check_manage_settings() === false);
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
check('pqm_manage_settings: allowed', PQM_REST_API::check_manage_settings() === true);

/* ─── Row: Connected ─────────────────────────────────────────────────── */
echo "Connected\n";
site([], [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post(['base_url' => '  https://n8n.example.com/home/workflows?x=1  ', 'api_key' => ' ' . KEY . ' ']);
check('probe ok, "Connected to n8n.", no version claimed', $r['probe'] === ['ok' => true, 'cause' => '', 'message' => 'Connected to n8n.', 'at' => $r['probe']['at']]
    && abs($r['probe']['at'] - time()) <= 5);
check('reply: base URL = the origin (editor path dropped), key set, its last 4 characters, the probe',
    $r['base_url'] === BASE && $r['key_set'] === true && $r['key_hint'] === '6789' && $r['connected'] === true
    && array_keys($r) === ['probe', 'base_url', 'key_set', 'key_hint', 'connected', 'setup', 'adoptable', 'rotatable']);
check('calls, in order: one-item workflow list, the two route probes (D2)', calls() === [
    'GET ' . BASE . '/api/v1/workflows?limit=1',
    'POST ' . BASE . '/api/v1/workflows/pqm-probe-unknown-id/activate',
    'PATCH ' . BASE . '/api/v1/credentials/pqm-probe-unknown-id',
]);
check('every call: plugin User-Agent, no redirect, the key (trimmed)', calls_signed(KEY));
check('the route probes carry an empty JSON object (a body-less PATCH gets 415 before the route is known)',
    array_map(static fn($c) => [$c[1]['headers']['Content-Type'] ?? null, $c[1]['body'] ?? null], array_slice($GLOBALS['tw']['calls'], 1))
    === [['application/json', '{}'], ['application/json', '{}']]);
check('stored in pqm_n8n_api: origin, key, probe — autoload off', $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => BASE, 'api_key' => KEY, 'probe' => $r['probe']]
    && $GLOBALS['tw']['autoload'][PQM_N8n::OPTION] === false);
check('... and only there: pqm_settings never written', $GLOBALS['tw']['writes'] === [PQM_N8n::OPTION]);

/* Acceptance: a reload shows the stored state without calling n8n. */
$GLOBALS['tw']['calls'] = [];
$s = get_state();
check('reload (GET): the stored state — connected, key set — and zero calls to n8n',
    $s === ['base_url' => BASE, 'key_set' => true, 'key_hint' => '6789', 'probe' => $r['probe'], 'connected' => true, 'setup' => null, 'adoptable' => false, 'rotatable' => false] && $GLOBALS['tw']['calls'] === []);

/* ─── Rows: the key hint (last 4 characters, computed at read time) ──── */
echo "Key hint\n";
foreach ([
    'a 40-character key ending ab12: its last 4' => [str_repeat('k', 36) . 'ab12', 'ab12'],
    'a 16-character key (the shortest with a hint)' => ['0123456789abcdef', 'cdef'],
    'a 15-character key: set, no hint (too large a share)' => ['0123456789abcde', ''],
    'a 4-character key: set, no hint' => ['ab12', ''],
] as $label => [$stored_key, $hint]) {
    site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => $stored_key, 'probe' => null]]);
    $s = get_state();
    check($label, $s['key_set'] === true && $s['key_hint'] === $hint && $GLOBALS['tw']['writes'] === []);
}

/* ─── Row: Missing endpoint vs existing (D2, the 2.42.3 shapes) ─────── */
echo "Endpoint probe shapes\n";
site([], [LIST_OK, ROUTER_404]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('the router\'s generic 404 "not found" on activate = missing endpoint; only its cause stored (entry 2), no URL, no key', $r['probe']['cause'] === 'missing_endpoint'
    && $r['probe']['message'] === 'Your n8n lacks an API endpoint the setup needs. Update n8n to 2.42.3 or later. Or set up the workflow by hand with the Workflow File section.'
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['failed' => ['cause' => 'missing_endpoint', 'message' => 'Your n8n lacks an API endpoint the setup needs. Update n8n to 2.42.3 or later.', 'at' => $r['probe']['at']]]
    && PQM_N8n::public_state() === ['base_url' => '', 'key_set' => false, 'key_hint' => '', 'probe' => null, 'connected' => false, 'setup' => null, 'adoptable' => false, 'rotatable' => false]);
site([], [LIST_OK, ROUTE_OK, resp(404, ['message' => 'NOT FOUND '])]);
check('... case and spaces do not matter', post(['base_url' => BASE, 'api_key' => KEY])['probe']['cause'] === 'missing_endpoint');
foreach ([
    'activate refused for the unknown id (404)' => [resp(404, ['message' => 'You do not have permission to activate this workflow. Ask the owner to share it with you.']), ROUTE_OK],
    'a validator 400 in n8n\'s error shape'      => [ROUTE_OK, resp(400, ['message' => 'request/body must have required property \'data\''])],
    'a 415 in n8n\'s error shape'                => [ROUTE_OK, resp(415, ['message' => 'unsupported media type undefined'])],
] as $label => $routes) {
    site([], array_merge([LIST_OK], $routes));
    $r = post(['base_url' => BASE, 'api_key' => KEY]);
    check("$label = the endpoint exists: connected, stored", $r['probe']['ok'] === true && $r['key_set'] === true);
}

/* ─── Row: Insecure or unusable URL — zero calls ─────────────────────── */
echo "Insecure URL\n";
foreach (['http://n8n.example', 'http://n8n.example.com:5678/home', 'HTTP://10.0.0.5'] as $url) {
    site($stored_conn);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("$url: refused, cause insecure (HTTPS required), zero calls, nothing stored",
        $r['probe']['ok'] === false && $r['probe']['cause'] === 'insecure' && str_contains($r['probe']['message'], 'https://')
        && $GLOBALS['tw']['calls'] === [] && unchanged($stored_conn));
}
foreach (['n8n.example.com', 'ftp://n8n.example.com', 'https://', ''] as $url) {
    site($stored_conn);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("\"$url\": refused, cause invalid_url, zero calls, nothing stored",
        $r['probe']['cause'] === 'invalid_url' && $GLOBALS['tw']['calls'] === [] && unchanged($stored_conn));
}
foreach (['http://localhost:5678' => 'http://localhost:5678', 'http://127.0.0.1:5678/home' => 'http://127.0.0.1:5678', 'http://[::1]:5678' => 'http://[::1]:5678'] as $url => $origin) {
    site([], [LIST_OK, ROUTE_OK, ROUTE_OK]);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("$url: allowed over http (local), stored as $origin", $r['probe']['ok'] === true && $r['base_url'] === $origin
        && ($GLOBALS['tw']['calls'][0][0] ?? '') === "$origin/api/v1/workflows?limit=1");
}

/* ─── Rows: failures keep the stored URL and key ─────────────────────── */
$failures = [
    'Key refused (401)' => [[resp(401, ['message' => 'unauthorized'])], 'key', 'Settings → n8n API'],
    'API disabled (404 on /api/v1/workflows)' => [[resp(404, ['message' => 'not found'])], 'api_disabled', 'public API is turned off'],
    'API disabled, a non-JSON 404' => [[resp(404, 'Cannot GET /api/v1/workflows')], 'api_disabled', 'public API is turned off'],
    'WAF: 403' => [[resp(403, ['message' => 'Forbidden'])], 'proxy', 'proxy or firewall'],
    'WAF: Cloudflare 1010' => [[resp(403, 'error code: 1010')], 'proxy', 'proxy or firewall'],
    'WAF: a non-JSON 200 (a login or challenge page)' => [[resp(200, '<html>Just a moment...</html>')], 'proxy', 'proxy or firewall'],
    'WAF: a redirect (never followed)' => [[resp(302, '')], 'proxy', 'proxy or firewall'],
    'Unreachable (DNS)' => [[new WP_Error('http_request_failed', 'cURL error 6: Could not resolve host: n8n.example.com')], 'unreachable', 'cURL error 6: Could not resolve host: n8n.example.com'],
    'Unreachable (TLS)' => [[new WP_Error('http_request_failed', 'cURL error 60: SSL certificate problem')], 'unreachable', 'cURL error 60: SSL certificate problem'],
    'Missing endpoint: 405 on activate' => [[LIST_OK, resp(405, ['message' => 'method not allowed'])], 'missing_endpoint', 'Update n8n to 2.42.3 or later'],
    'Missing endpoint: a JSON 404 without n8n\'s shape on PATCH /credentials' => [[LIST_OK, ROUTE_OK, resp(404, ['error' => 'route'])], 'missing_endpoint', 'lacks an API endpoint'],
    'Route probe: a non-JSON 404 is a proxy, never a missing endpoint (D2)' => [[LIST_OK, resp(404, '<html>Not Found</html>')], 'proxy', 'proxy or firewall'],
    'Route probe unreachable' => [[LIST_OK, ROUTE_OK, new WP_Error('http_request_failed', 'cURL error 28: timed out')], 'unreachable', 'cURL error 28'],
    'Route probe: the key refused (401, story 74)' => [[LIST_OK, resp(401, ['message' => 'unauthorized'])], 'key', 'Settings → n8n API'],
    'Route probe: a JSON 403, a key whose scopes exclude the setup\'s routes (story 74)' => [[LIST_OK, resp(403, ['message' => 'Forbidden'])], 'scoped', 'cannot create credentials or publish workflows'],
    'Route probe: a non-JSON 403 is a proxy (story 74)' => [[LIST_OK, ROUTE_OK, resp(403, '<html>Forbidden</html>')], 'proxy', 'proxy or firewall'],
    'n8n error (500 JSON)' => [[resp(500, ['message' => 'Internal error'])], 'unexpected', 'HTTP 500'],
];
foreach ($failures as $label => [$queue, $cause, $says]) {
    echo "$label\n";
    site($stored_conn, $queue);
    $r = post(['base_url' => BASE, 'api_key' => KEY]);
    check("cause $cause, message says \"$says\"", $r['probe']['ok'] === false && $r['probe']['cause'] === $cause && str_contains($r['probe']['message'], $says));
    check('... the manual path offered, once, after the cause sentence', $cause === 'key'
        || ($r['probe']['message'] === $r['probe']['reason'] . ' ' . MANUAL_SENTENCE && !str_contains($r['probe']['reason'], MANUAL_SENTENCE)));
    check('... stored URL, key and last passing probe as they were; the reply says so', without_failed() === $stored_conn[PQM_N8n::OPTION]
        && $r['base_url'] === 'https://old.example.com' && $r['key_set'] === true);
    if ($cause === 'key') {
        check('... a refused key stores no failure: nothing written', unchanged($stored_conn));
    } else {
        check('... the cause stored as `failed` {cause, the sentence without the manual path, at}, autoload off',
            $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed'] === ['cause' => $cause, 'message' => $r['probe']['reason'], 'at' => $r['probe']['at']]
            && $GLOBALS['tw']['writes'] === [PQM_N8n::OPTION] && $GLOBALS['tw']['autoload'][PQM_N8n::OPTION] === false);
    }
    check('... every call signed, nothing left unscripted', calls_signed(KEY) && $GLOBALS['tw']['queue'] === []);
}
site([], [LIST_OK, resp(405, '')]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('Missing endpoint: a 405 without a body, only its cause stored', $r['probe']['cause'] === 'missing_endpoint'
    && array_keys($GLOBALS['tw']['options'][PQM_N8n::OPTION]) === ['failed']);

/* ─── Rows: the failure record (entry 2, D1) ─────────────────────────── */
echo "Failure record\n";
$failed_conn = [PQM_N8n::OPTION => $stored_conn[PQM_N8n::OPTION] + ['failed' => ['cause' => 'proxy', 'message' => 'n8n did not answer as expected.', 'at' => 1700000100]]];
site($failed_conn, [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('a passing probe after a stored failure: `failed` gone', $r['probe']['ok'] === true
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => BASE, 'api_key' => KEY, 'probe' => $r['probe']]
    && PQM_N8n::connection()['failed'] === null);
site($failed_conn);
forget();
check('Forget after a stored failure: `failed` gone, URL kept', $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => 'https://old.example.com']);
foreach (['http://n8n.example' => 'insecure', 'n8n.example.com' => 'invalid_url'] as $url => $cause) {
    site($failed_conn);
    $r = post(['base_url' => $url, 'api_key' => KEY]);
    check("cause $cause stores nothing: the earlier failure stays as it was", $r['probe']['cause'] === $cause && unchanged($failed_conn));
}
site($failed_conn, [resp(500, ['message' => 'Internal error'])]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('a newer manual-path failure replaces the stored one', $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']['cause'] === 'unexpected'
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']['message'] === 'n8n answered HTTP 500. Try again in a moment.');
site();
check('fresh site: connection() carries failed => null', PQM_N8n::connection()['failed'] === null);
site([], [new WP_Error('http_request_failed', 'cURL error 6: Could not resolve host')]);
post(['base_url' => BASE, 'api_key' => KEY]);
check('the stored failure never carries the key', !str_contains(json_encode($GLOBALS['tw']['options']), KEY));
check('public_state() leaves the failure out (entries 8 and 10 add setup, adoptable and rotatable; story 74 connected, after probe)', array_keys(PQM_N8n::public_state()) === ['base_url', 'key_set', 'key_hint', 'probe', 'connected', 'setup', 'adoptable', 'rotatable']);

/* ─── Row: Re-check ──────────────────────────────────────────────────── */
echo "Re-check\n";
site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => $stored_conn[PQM_N8n::OPTION]['probe']]], [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post([]);
check('POST without key or URL: the probe re-runs on the stored address with the stored key', $r['probe']['ok'] === true
    && count($GLOBALS['tw']['calls']) === 3 && $GLOBALS['tw']['calls'][0][0] === BASE . '/api/v1/workflows?limit=1' && calls_signed(KEY));
check('... the fresh probe is stored', $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe'] === $r['probe'] && $r['probe']['at'] !== 1700000000);
site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => $stored_conn[PQM_N8n::OPTION]['probe']]], [LIST_OK, ROUTE_OK, ROUTE_OK]);
check('POST with the stored address typed again, key empty: same re-check', post(['base_url' => BASE . '/home', 'api_key' => ''])['probe']['ok'] === true && calls_signed(KEY));
$before = [PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => $stored_conn[PQM_N8n::OPTION]['probe']]];
site($before, [resp(401, ['message' => 'unauthorized'])]);
$r = post([]);
// Story 74, the golden row: the refusal of the stored key replaces its probe.
check('re-check refused (key revoked, story 74): cause key; the stored probe = the refusal {ok, cause, message, at}, the URL and key kept; connected, rotatable, adoptable false; the reply\'s probe the same',
    $r['probe']['cause'] === 'key' && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => BASE, 'api_key' => KEY, 'probe' => $r['probe']]
    && array_keys($r['probe']) === ['ok', 'cause', 'message', 'at'] && $GLOBALS['tw']['writes'] === [PQM_N8n::OPTION]
    && $r['connected'] === false && $r['rotatable'] === false && $r['adoptable'] === false && PQM_N8n::public_state()['connected'] === false);
site($stored_conn, [LIST_OK, ROUTE_OK, ROUTE_OK]);
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('Connect with a new key: the reply carries the last 4 of the new key at once', $r['probe']['ok'] === true && $r['key_hint'] === '6789');
site($stored_conn, [resp(401, ['message' => 'unauthorized'])]);
$r = post(['base_url' => 'https://old.example.com', 'api_key' => KEY]);
check('Connect with a refused key: the hint of the stored key is unchanged', $r['probe']['cause'] === 'key' && $r['key_hint'] === '3210' && unchanged($stored_conn));
site($stored_conn);
$r = post(['base_url' => BASE]);
check('a new address without a key: 400, the stored key never sent there (zero calls)', $r instanceof WP_Error && $r->data['status'] === 400
    && $GLOBALS['tw']['calls'] === [] && unchanged($stored_conn));
site();
$r = post(['base_url' => BASE]);
check('no key given, none stored: 400, zero calls, nothing stored', $r instanceof WP_Error && $r->data['status'] === 400 && $GLOBALS['tw']['calls'] === [] && $GLOBALS['tw']['writes'] === []);

/* ─── Row: Forget ────────────────────────────────────────────────────── */
echo "Forget\n";
site($stored_conn);
$r = forget();
check('DELETE: key and probe removed, URL kept, "not set", no hint', $r === ['base_url' => 'https://old.example.com', 'key_set' => false, 'key_hint' => '', 'probe' => null, 'connected' => false, 'setup' => null, 'adoptable' => false, 'rotatable' => false]
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION] === ['base_url' => 'https://old.example.com'] && $GLOBALS['tw']['autoload'][PQM_N8n::OPTION] === false);
check('... nothing sent to n8n', $GLOBALS['tw']['calls'] === []);
check('... a reload shows "not set"', get_state() === $r);
site();
check('fresh site GET: nothing stored, not set, no hint', get_state() === ['base_url' => '', 'key_set' => false, 'key_hint' => '', 'probe' => null, 'connected' => false, 'setup' => null, 'adoptable' => false, 'rotatable' => false] && $GLOBALS['tw']['writes'] === []);

/* ─── The client boundary later entries call ─────────────────────────── */
echo "PQM_N8n::request()\n";
site();
$r = PQM_N8n::request('GET', '/credentials?limit=250');
check('no key stored: WP_Error, zero calls', $r instanceof WP_Error && $r->code === 'pqm_n8n_not_connected' && $GLOBALS['tw']['calls'] === []);
site([PQM_N8n::OPTION => ['base_url' => BASE, 'api_key' => KEY, 'probe' => null]], [resp(200, ['id' => 'abc'])]);
$r = PQM_N8n::request('POST', '/credentials', ['name' => 'x', 'type' => 'httpHeaderAuth', 'data' => []]);
[$url, $args] = $GLOBALS['tw']['calls'][0];
check('a call: /api/v1 + path on the stored origin, signed, JSON body with its Content-Type, decoded answer',
    $url === BASE . '/api/v1/credentials' && $args['method'] === 'POST' && calls_signed(KEY)
    && $args['headers']['Content-Type'] === 'application/json' && $args['body'] === '{"name":"x","type":"httpHeaderAuth","data":[]}'
    && $r === ['code' => 200, 'json' => ['id' => 'abc']]);

/* ─── Story 74: the n8n connection state tells the truth ─────────────── */
echo "Story 74: a refusal of the stored key replaces its probe (Check again)\n";

managed([UNAUTHORIZED], $setup_map + $older_report);
$failed_before = ['cause' => 'proxy', 'message' => 'n8n did not answer as expected.', 'at' => 1700000100];
$GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed'] = $failed_before;
$r = post([]);
check('re-check refused on a managed site: the refusal stored with the key sentence, `failed` kept; rotatable false; the page offers neither Update nor Rotate',
    refusal_stored('key') && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe']['message'] === KEY_SENTENCE
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed'] === $failed_before && $r['rotatable'] === false && $r['connected'] === false
    && !str_contains($block = PQM_Admin::render_workflow_body(), 'pqm-update-workflow') && !str_contains($block, 'pqm-rotate-token'));
hand_made([UNAUTHORIZED]);
check('... on a hand-made site: adoptable false (no offer)', post([])['adoptable'] === false && refusal_stored('key'));
site($passing_conn, [UNAUTHORIZED]);
check('... the stored key typed again on the stored address counts as the stored key: recorded', post(['base_url' => BASE, 'api_key' => KEY])['probe']['cause'] === 'key' && refusal_stored('key'));
foreach ([
    'a 500'                   => [resp(500, ['message' => 'Internal error'])],
    'a timeout'               => [timed_out()],
    'a 404 on the list'       => [resp(404, ['message' => 'not found'])],
    'a proxy\'s 404 on a route' => [LIST_OK, resp(404, '<html>Not Found</html>')],
] as $label => $queue) {
    site($passing_conn, $queue);
    $r = post([]);
    check("re-check, $label (D1): the passing probe kept, `failed` recorded as today, connected true", $r['probe']['ok'] === false && $r['connected'] === true
        && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe'] === $passing_probe && ($GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']['cause'] ?? '') === $r['probe']['cause']
        && PQM_N8n::connected() === true);
}
site($passing_conn, [UNAUTHORIZED]);
$r = post(['base_url' => BASE, 'api_key' => OLD_KEY]);
check('a new key refused on Connect (D2): nothing stored changes; the reply\'s probe cause key, connected true', unchanged($passing_conn) && $r['probe']['cause'] === 'key' && $r['connected'] === true);
site($passing_conn, [UNAUTHORIZED]);
$r = post(['base_url' => 'https://other.example.com', 'api_key' => KEY]);
check('... the stored key typed for another address: not the stored connection, nothing stored changes', unchanged($passing_conn) && $r['probe']['cause'] === 'key');

echo "Story 74: the route probe's 401 and 403\n";
site($passing_conn, [LIST_OK, UNAUTHORIZED]);
$r = post([]);
check('route 401 (list 200, activate 401): cause key, the key sentence, no manual path; the stored key\'s, so recorded', $r['probe']['cause'] === 'key'
    && $r['probe']['message'] === KEY_SENTENCE && !isset($r['probe']['reason']) && refusal_stored('key') && !isset($GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']));
site($passing_conn, [LIST_OK, ROUTE_OK, resp(403, ['message' => 'Forbidden'])]);
$r = post([]);
check('route 403 with JSON (on PATCH /credentials): cause scoped, the scoped sentence + the manual one; `failed` recorded; the stored key\'s: the probe replaced, without `reason`',
    $r['probe']['cause'] === 'scoped' && $r['probe']['message'] === SCOPED_SENTENCE . ' ' . MANUAL_SENTENCE && $r['probe']['reason'] === SCOPED_SENTENCE
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed'] === ['cause' => 'scoped', 'message' => SCOPED_SENTENCE, 'at' => $r['probe']['at']]
    && refusal_stored('scoped') && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe']['message'] === $r['probe']['message']);
check('... the Workflow File section says so after a reload: the manual reason is the scoped sentence, the page on the manual path', pqm_manual_reason() === ['kind' => 'probe', 'message' => SCOPED_SENTENCE, 'at' => gmdate('F j, Y', $r['probe']['at'])]
    && state_of($block = PQM_Admin::render_workflow_body()) === 'manual' && str_contains($block, SCOPED_SENTENCE));
site($passing_conn + ['pqm_settings' => ['webhook_token' => TOKEN]], [UNAUTHORIZED]);
$GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed'] = ['cause' => 'proxy', 'message' => 'n8n did not answer as expected.', 'at' => 1700000100];
post([]);
check('an older proxy record, then a re-check refused (401): no manual reason (the record predates the refusal), the page not set up (not manual), GET /setup not manual',
    refusal_stored('key') && pqm_manual_reason() === null && state_of(PQM_Admin::render_workflow_body()) === 'not-set-up'
    && PQM_REST_API::get_setup_callback()->get_data()['manual'] === false);
site($passing_conn, [LIST_OK, ROUTE_OK, resp(403, ['message' => 'Forbidden'])]);
$r = post([]);
check('... a scoped refusal writes the probe and `failed` with one `at`: the manual reason is its sentence', ($GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']['at'] ?? 0) === $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe']['at']
    && (pqm_manual_reason()['message'] ?? '') === SCOPED_SENTENCE);
site($passing_conn, [LIST_OK, resp(403, '<html>Forbidden</html>')]);
$r = post([]);
check('route 403 without JSON: proxy as today; the passing probe kept, `failed` recorded', $r['probe']['cause'] === 'proxy'
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe'] === $passing_probe && ($GLOBALS['tw']['options'][PQM_N8n::OPTION]['failed']['cause'] ?? '') === 'proxy');

echo "Story 74: a 401 on a managed call is recorded\n";
managed([UNAUTHORIZED]);
$r = rotate();
check('Rotate, the PATCH answers 401: 502 pqm_rotation_failed, "could not finish updating the webhook credential: n8n refused the API key…"; recorded; the token kept, no ping',
    $r instanceof WP_Error && $r->code === 'pqm_rotation_failed' && $r->data['status'] === 502 && calls() === [PATCH_WEBHOOK]
    && $r->message === 'The plugin could not finish updating the webhook credential: ' . REFUSED_FIX && refusal_stored('key') && token_now() === TOKEN && $GLOBALS['tw']['pings'] === []);
check('... D4: the error carries the page\'s body as it is now — the refusal its outcome line, neither Rotate token nor Update workflow on it',
    $r->data['status_html'] === PQM_Admin::render_workflow_body($r->message, 'error') && str_contains($r->data['status_html'], '<p class="pqm-workflow-outcome is-error">' . $r->message . '</p>')
    && !str_contains($r->data['status_html'], 'pqm-rotate-token') && !str_contains($r->data['status_html'], 'pqm-update-workflow'));
managed([UNAUTHORIZED, UPDATED], $setup_map + $older_report);
$r = update();
check('Update, the read answers 401: 502 pqm_update_failed, "could not finish reading your workflows: n8n refused the API key…"; no PUT; recorded; the body without Update workflow',
    $r instanceof WP_Error && $r->code === 'pqm_update_failed' && $r->data['status'] === 502 && $r->message === 'The plugin could not finish reading your workflows: ' . REFUSED_FIX
    && calls() === [READ_WF] && refusal_stored('key') && $r->data['status_html'] === PQM_Admin::render_workflow_body($r->message, 'error')
    && !str_contains($r->data['status_html'], 'pqm-update-workflow'));
managed([CLEAN_WF, UNAUTHORIZED], $setup_map + $older_report);
$r = update();
check('... the PUT answers 401: "could not finish updating the workflow: n8n refused the API key…"; recorded; the version record untouched',
    $r->message === 'The plugin could not finish updating the workflow: ' . REFUSED_FIX && calls() === [READ_WF, PUT_WF] && refusal_stored('key')
    && $GLOBALS['tw']['options']['pqm_workflow_version_seen'] === $older_report['pqm_workflow_version_seen']);
hand_made([UNAUTHORIZED]);
$r = proposal();
check('the adoption read answers 401: 502 pqm_adoption_failed, could_not(\'read\') with the same cause; recorded; the offer gone', $r instanceof WP_Error && $r->code === 'pqm_adoption_failed'
    && $r->data === ['status' => 502] && $r->message === 'The plugin could not finish reading your workflows: ' . REFUSED_FIX && refusal_stored('key') && get_state()['adoptable'] === false);
managed([UNAUTHORIZED]);
check('the diagnosis answers 401: null as today; recorded', diagnosed() === null && calls() === [READ_WF] && refusal_stored('key'));
$refused_at = $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe']['at'] = 1700000500;
$GLOBALS['tw']['calls'] = [];
$GLOBALS['tw']['queue'] = [CLEAN_WF];
check('... a stored refusal, the next passing ping\'s diagnosis: null with zero calls (the refused key is not sent again), the stored probe\'s `at` unchanged',
    diagnosed() === null && calls() === [] && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe']['at'] === $refused_at);
managed([UNAUTHORIZED, UNAUTHORIZED], [PQM_N8n::SETUP_OPTION => pro_map(1)] + $report);
$r = start_over(true);
check('Start over\'s removal answers 401: the clears done, what n8n kept on record (the warning), the refusal recorded', is_array($r) && $r['message'] === REMOVED_LEFT && started_over()
    && calls() === array_slice(UNDO_ALL, 0, 2) && refusal_stored('key'));
managed([resp(403, ['message' => 'Forbidden'])]);
$r = rotate();
check('a 403 on a managed call stays could_not() (a WAF can answer it): "n8n answered HTTP 403", nothing recorded, still connected',
    $r->message === sprintf(ROTATION_STOPPED, 'n8n answered HTTP 403') && PQM_N8n::connected() && !in_array(PQM_N8n::OPTION, $GLOBALS['tw']['writes'], true));

echo "Story 74: after a refusal — zero calls, the stored refusal's sentence\n";
foreach ([
    'Rotate'                     => [static fn() => managed([PATCHED]), static fn() => rotate()],
    'Update'                     => [static fn() => managed([CLEAN_WF, UPDATED], $GLOBALS['setup_map'] + $GLOBALS['older_report']), static fn() => update()],
    'GET /n8n/adoption'          => [static fn() => hand_made([LIST_OK]), static fn() => proposal()],
    'provision'                  => [static fn() => connected(PRO_OK), static fn() => provision()],
    'Start over with the removal' => [static fn() => managed([INACTIVE], [PQM_N8n::SETUP_OPTION => pro_map(1)]), static fn() => start_over(true)],
] as $label => [$arrange, $run]) {
    $arrange();
    $GLOBALS['tw']['options'][PQM_N8n::OPTION] = $refused_conn[PQM_N8n::OPTION];
    $r = $run();
    check("$label: 400 pqm_n8n_not_connected, the stored probe's message (not \"No n8n API key is set\"), zero calls, nothing written",
        refused($r, 'pqm_n8n_not_connected', 400) && $r->message === KEY_SENTENCE && calls() === [] && untouched());
}
managed([LIST_OK, ROUTE_OK, ROUTE_OK]);
$GLOBALS['tw']['options'][PQM_N8n::OPTION] = $refused_conn[PQM_N8n::OPTION];
$r = post(['base_url' => BASE, 'api_key' => KEY]);
check('a probe passes again: connected, rotatable — Update and Rotate back on the page', $r['connected'] === true && $r['rotatable'] === true && PQM_N8n::connected()
    && $GLOBALS['tw']['options'][PQM_N8n::OPTION]['probe']['ok'] === true);

echo "Story 74: the mis-wired workflow — Update stays the repair\n";
managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + ['pqm_workflow_version_seen' => ['version' => PQM_WORKFLOW_VERSION - 1, 'via' => 'test', 'at' => 1700000000, 'miswired' => SHARED_SENTENCE]]);
$r = update();
check('mis-wired, Update (D3): allowed — GET, PUT, the ping\'s read; the clean read clears the sentence', is_array($r) && $r['updated'] === true
    && calls() === [READ_WF, PUT_WF, READ_WF] && !isset(pqm_workflow_report()['miswired']));

echo "Story 74: one predicate\n";
site($refused_conn + ['pqm_settings' => ['webhook_token' => TOKEN]]);
check('a stored refusal: connected() false; public_state().connected false; pqm_manual_reason() null (cause key: no manual path); the page state not connected; GET /setup\'s n8n step open',
    PQM_N8n::connected() === false && PQM_N8n::public_state()['connected'] === false && pqm_manual_reason() === null
    && state_of(PQM_Admin::render_workflow_body()) === 'not-set-up' && PQM_REST_API::get_setup_callback()->get_data()['steps']['n8n'] === false);
$sources = ['includes/class-pqm-rest-api.php', 'includes/pqm-functions.php', 'admin/class-pqm-admin.php'];
check('every reader asks PQM_N8n::connected(): the stored probe\'s `ok` is read in connected() only (source)', substr_count($src_n8n = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-n8n.php'), "['probe']['ok']") === 1
    && !array_filter($sources, static fn($f) => str_contains(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/' . $f), "['probe']['ok']")));
check('story 77: is_2xx() = 200, 204, 299 pass; 300 and 199 fail; origin_of() = scheme://host[:port] lower-cased, empty without scheme or host',
    PQM_N8n::is_2xx(200) && PQM_N8n::is_2xx(204) && PQM_N8n::is_2xx(299) && !PQM_N8n::is_2xx(300) && !PQM_N8n::is_2xx(199)
    && PQM_N8n::origin_of('HTTP://Host.Example:5678/webhook/x') === 'http://host.example:5678' && PQM_N8n::origin_of('host.example/webhook/x') === '' && PQM_N8n::origin_of('ftp://host.example') === '');
check('story 77: the token header, the Anthropic credential name and the 2xx test are spelled once each, in the client; the REST class, functions and admin read them',
    substr_count($src_n8n, "'X-PQM-Token'") === 1 && substr_count($src_n8n, "'Anthropic - Claude'") === 1 && substr_count($src_n8n, '>= 200 &&') === 1
    && !array_filter($sources, static fn($f) => preg_match("/'X-PQM-Token'|'Anthropic - Claude'|>= 200 &&/", file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/' . $f))));

key_leak_scan($replies);
/* The two source pins of the old file's last section, once: the client is the one place that calls n8n. */
$src  = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-n8n.php');
$rest = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-rest-api.php');
check('the client and the REST class log nothing (no error_log, print_r, var_dump)', !preg_match('/\b(error_log|print_r|var_dump|var_export)\s*\(/', $src . $rest));
check('the client sends to n8n through wp_remote_request() in one place; its one wp_remote_get() goes to Anthropic', substr_count($src, 'wp_remote_request(') === 1
    && !str_contains($src, 'wp_remote_post(') && substr_count($src, 'wp_remote_get(') === 1 && str_contains($src, "wp_remote_get( 'https://api.anthropic.com/v1/models?limit=1'"));

harness_exit();
