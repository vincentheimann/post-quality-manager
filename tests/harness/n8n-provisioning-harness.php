<?php
/**
 * n8n provisioning harness — entry 4 rows: POST /n8n/provision, one per line
 * of its plan's matrix (Pro build; the Free run is in free-build-harness.php),
 * and the provider-key leak scan. Entry 5 rows: the rollback of a failed
 * run, one per line of its plan's matrix (Pro build), checked on an
 * instance model: what the create calls answered minus what a DELETE
 * removed. Entry 6: a passing provisioning answers the page's status block;
 * the ping's record (`steps.connect`) keeps the dialog's step open on a failure.
 * Bugs 72, 73, 75, 80 (one build): the provisioning lock (a second run
 * started during the first is refused before any call; a stale lock is
 * taken over), the Application Password the setup map keeps and what
 * revokes it, the 409-on-activate sentence, and the ping after activation
 * retried over n8n's publication window (404 only; the wait filter set to 0).
 * WP stubs, no WP install; callbacks are called directly. The world (stubs,
 * constants, builders, the plugin load) is lib/n8n-world.php; story 78
 * build 2 split the one n8n harness by concern.
 * Run: php n8n-provisioning-harness.php
 */

require __DIR__ . '/lib/n8n-world.php';

/* ─── Entry 4: POST /n8n/provision ───────────────────────────────────── */
echo "Provisioning: route\n";
$route = $GLOBALS['pqm_routes'][PQM_REST_NAMESPACE . '/n8n/provision'] ?? [];
check('/n8n/provision: POST, gated by check_manage_settings, anthropic_key required', ($route['methods'] ?? null) === 'POST'
    && ($route['permission_callback'] ?? null) === $gate && ($route['args']['anthropic_key']['required'] ?? null) === true);
check('the setup map has its own option, pqm_n8n_setup', PQM_N8n::SETUP_OPTION === 'pqm_n8n_setup');

$raw_template   = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json'), true); // placeholders unfilled
$template_names = array_values(array_unique(array_merge(...array_map(static fn($n) => array_column($n['credentials'] ?? [], 'name'), $raw_template['nodes']))));
$expected_names = array_values(PQM_N8n::credential_names('__PQM_SITE_HOST__'));
sort($template_names);
sort($expected_names);
check('story 77: the template\'s credential names are credential_names(__PQM_SITE_HOST__) and its header parameter is TOKEN_HEADER (JSON pinned to the PHP source)',
    $template_names === $expected_names && $template_names !== []
    && array_filter($raw_template['nodes'], static fn($n) => in_array(PQM_N8n::TOKEN_HEADER, array_column($n['parameters']['headerParameters']['parameters'] ?? [], 'name'), true)) !== []);

echo "Provisioning: Pro, fresh instance\n";
connected(PRO_OK);
$r = provision();
check('calls, in order: models GET, 3x POST /credentials, POST /workflows, activate; then the diagnosis of the ping reads the workflow (entry 7)', calls() === [
    MODELS,
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/credentials',
    'POST ' . BASE . '/api/v1/workflows',
    'POST ' . BASE . '/api/v1/workflows/wf1/activate',
    READ_WF,
] && $GLOBALS['tw']['queue'] === []);
check('the models request: x-api-key = the provider key, anthropic-version 2023-06-01, no n8n key; every call with the plugin UA, no redirect',
    $GLOBALS['tw']['calls'][0][1]['headers'] === ['x-api-key' => PROVIDER_KEY, 'anthropic-version' => '2023-06-01'] && calls_signed(KEY));
check('webhook credential: httpHeaderAuth {X-PQM-Token, the site token}, under its deterministic name',
    body(1) === ['name' => 'Post Quality webhook - example.com', 'type' => 'httpHeaderAuth', 'data' => ['name' => 'X-PQM-Token', 'value' => TOKEN]]);
check('Anthropic credential: httpHeaderAuth {x-api-key, the provider key}',
    body(2) === ['name' => 'Anthropic - Claude', 'type' => 'httpHeaderAuth', 'data' => ['name' => 'x-api-key', 'value' => PROVIDER_KEY]]);
check('WordPress credential: httpBasicAuth {the current user, a new Application Password}',
    body(3) === ['name' => 'WordPress - example.com', 'type' => 'httpBasicAuth', 'data' => ['user' => 'admin-anna', 'password' => APP_PASSWORD]]);
check('... one Application Password, for the current user', count($GLOBALS['tw']['app_created']) === 1 && $GLOBALS['tw']['app_created'][0][0] === 7
    && str_starts_with($GLOBALS['tw']['app_created'][0][1]['name'], 'Post Quality Manager (n8n) '));
$sent = body(4);
check('workflow: {name, nodes, connections, settings}, no `active`, the build template otherwise unchanged',
    array_keys($sent) === ['name', 'nodes', 'connections', 'settings'] && $sent['name'] === 'Post Quality — example.com'
    && $strip($sent['nodes']) === $strip($template['nodes']) && $sent['connections'] === $template['connections'] && $sent['settings'] === $template['settings']);
$links = linked($sent);
$claude = ['httpHeaderAuth' => ['id' => 'c-anthropic', 'name' => 'Anthropic - Claude']];
check('acceptance: all 8 credentialed nodes carry the created id, none left name-only', count($links) === 8
    && $links['Webhook Trigger'] === ['httpHeaderAuth' => ['id' => 'c-webhook', 'name' => 'Post Quality webhook - example.com']]
    && $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => ['id' => 'c-wp', 'name' => 'WordPress - example.com']]
    && count(array_filter($links, static fn($c) => $c === $claude)) === 6);
check('the trigger path is pqm_webhook_path()', array_column($sent['nodes'], null, 'name')['Webhook Trigger']['parameters']['path'] === pqm_webhook_path());
check('activate is sent {}', $GLOBALS['tw']['calls'][5][1]['body'] === '{}');
$setup = $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION] ?? null;
check('setup map stored, autoload off; bug 73: it names the Application Password {uuid, user_id}', $setup === pro_map($setup['at'] ?? null) && abs($setup['at'] - time()) <= 5
    && $GLOBALS['tw']['autoload'][PQM_N8n::SETUP_OPTION] === false);
check('bug 72: the lock claimed before the first call (bug 82: INSERT IGNORE, autoload off) and gone after the map is stored',
    $GLOBALS['tw']['writes'][0] === PQM_N8n::LOCK_OPTION
    && $GLOBALS['tw']['writes'][array_search(PQM_N8n::SETUP_OPTION, $GLOBALS['tw']['writes'], true) + 1] === '-' . PQM_N8n::LOCK_OPTION
    && $GLOBALS['tw']['autoload'][PQM_N8n::LOCK_OPTION] === 'off' && lock_free()
    && PQM_N8n::LOCK_OPTION === 'pqm_n8n_provisioning');
check('webhook URL saved: connected origin + /webhook/ + the path; the token kept', $GLOBALS['tw']['options']['pqm_settings'] === ['webhook_token' => TOKEN, 'n8n_webhook_url' => HOOK]);
check('pinged that URL once, with the token', count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === HOOK
    && $GLOBALS['tw']['pings'][0][1]['headers']['X-PQM-Token'] === TOKEN);
check('replied version recorded', ($GLOBALS['tw']['options']['pqm_workflow_version_seen']['version'] ?? null) === PQM_WORKFLOW_VERSION);
check('the ping recorded as the connect route does (autoload off): Set up the workflow reads done', ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === true
    && $GLOBALS['tw']['autoload'][PQM_SETUP_OPTION] === false);
check('reply: {provisioned, ping_ok, workflow_id, webhook_url, message, rewrite_ready, status_html}; neither the uuid nor the password in it', array_keys($r) === ['provisioned', 'ping_ok', 'workflow_id', 'webhook_url', 'message', 'rewrite_ready', 'status_html']
    && $r['provisioned'] === true && $r['ping_ok'] === true && $r['workflow_id'] === 'wf1' && $r['rewrite_ready'] === true
    && str_starts_with($r['message'], 'Ping OK') && !str_contains(json_encode($r), 'app-uuid') && !str_contains(json_encode($r), APP_PASSWORD));
check('... webhook_url = the saved URL (story 77: the wizard reads it instead of rebuilding it)', $r['webhook_url'] === HOOK);
check('... status_html = the page\'s refreshed body with the message first (entry 6; story 67): set up', $r['status_html'] === PQM_Admin::render_workflow_body($r['message'], 'success')
    && str_contains($r['status_html'], '<div class="pqm-workflow-status" data-state="set-up">') && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">' . $r['message'] . '</p>'));
$hits = array_keys(array_filter($GLOBALS['tw']['calls'], static fn($c) => str_contains(json_encode($c), PROVIDER_KEY)));
check('the provider key travels in the models request and the Anthropic credential body only', $hits === [0, 2]
    && !str_contains(json_encode($GLOBALS['tw']['calls'][2][1]['headers']), PROVIDER_KEY) && !str_contains(json_encode($GLOBALS['tw']['pings']), PROVIDER_KEY));

echo "Provisioning: acceptance — the provider key is in neither read reply\n";
$GLOBALS['tw']['caps'] = ['pqm_manage_settings'];
$conn        = json_encode(get_state());
$setup_reply = json_encode(PQM_REST_API::get_setup_callback()->get_data());
$replies[]   = $setup_reply;
check('GET /n8n/connection and GET /setup after provisioning: no provider key, no Application Password, no uuid (bug 73)',
    !str_contains($conn . $setup_reply, PROVIDER_KEY) && !str_contains($conn . $setup_reply, APP_PASSWORD) && !str_contains($conn . $setup_reply, 'app-uuid')
    && str_contains($setup_reply, '"webhook_url"'));
check('bug 73, the wizard: GET /setup on the Pro build names whose Application Password the setup makes (the current user\'s display name)',
    json_decode($setup_reply, true)['application_password_user'] === 'Anna Admin');
$GLOBALS['tw']['app_passwords'] = false;
check('... \'\' where the site offers no Application Passwords (D1): the sentence is not shown', PQM_REST_API::get_setup_callback()->get_data()['application_password_user'] === '');
$GLOBALS['tw']['app_passwords'] = true;

echo "Provisioning: Pro, no Application Passwords (D1)\n";
// What n8n stores of it: Update WP Post (Rewrite) without a credential id.
connected([MODELS_OK, created('c-webhook'), created('c-anthropic'), created('wf1'), ACTIVE,
    resp(200, stored_workflow('wf1', wired(['WordPress - example.com' => null] + WIRED)))]);
$GLOBALS['tw']['app_passwords'] = false;
$r = provision();
$links = linked(body(3));
check('entry 7, D1: the diagnosis reads GET /workflows/{id} only and skips the name-only Update WP Post: clean, nothing stored, the ping message unchanged',
    calls()[5] === READ_WF && !isset(pqm_workflow_report()['miswired']) && str_starts_with($r['message'], 'Ping OK'));
check('2 credentials, no Application Password, no httpBasicAuth credential', count(calls()) === 6 && $GLOBALS['tw']['app_created'] === []
    && !str_contains(json_encode(array_slice($GLOBALS['tw']['calls'], 1, 2)), 'httpBasicAuth'));
check('7 nodes linked, Update WP Post left name-only', count(array_filter($links, static fn($c) => isset(reset($c)['id']))) === 7
    && $links['Update WP Post (Rewrite)'] === ['httpBasicAuth' => ['name' => 'WordPress - example.com']]);
check('reply: provisioned, rewrite_ready false, the message says Rewrite stays off until a credential is added by hand',
    $r['provisioned'] === true && $r['ping_ok'] === true && $r['rewrite_ready'] === false
    && str_contains($r['message'], 'Rewrite stays off') && str_contains($r['message'], 'does not run on HTTPS')
    && str_contains($r['message'], 'a WordPress credential is added in n8n on the Update WP Post (Rewrite) node'));
check('map without a wordpress credential, and without an application_password (bug 73)', $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]['credentials'] === ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic']
    && !array_key_exists('application_password', $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]));

echo "Provisioning: the provider key refused\n";
foreach ([401, 403] as $code) {
    connected([resp($code, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']])]);
    $r = provision();
    check("Anthropic answers $code: 400 pqm_provider_key_invalid, zero n8n calls, nothing saved",
        $r instanceof WP_Error && $r->code === 'pqm_provider_key_invalid' && $r->data['status'] === 400 && calls() === [MODELS] && untouched());
}
connected([]);
$r = provision('  ');
check('an empty key: refused before any call', $r instanceof WP_Error && $r->code === 'pqm_provider_key_invalid' && calls() === [] && untouched());

echo "Provisioning: Anthropic unreachable\n";
foreach (['a WP_Error' => new WP_Error('http_request_failed', 'cURL error 28: timed out'), 'a 500' => resp(500, ['type' => 'error']), 'a 529 (overloaded)' => resp(529, ['type' => 'error'])] as $label => $answer) {
    connected([$answer]);
    $r = provision();
    check("$label: 502 pqm_provider_unreachable, zero n8n calls, nothing saved", $r instanceof WP_Error && $r->code === 'pqm_provider_unreachable'
        && $r->data['status'] === 502 && calls() === [MODELS] && untouched());
}

echo "Provisioning: not connected, already provisioned\n";
site(['pqm_settings' => ['webhook_token' => TOKEN]]);
$GLOBALS['tw']['pings'] = [];
$GLOBALS['tw']['app_created'] = [];
$r = provision();
check('no n8n key stored: pqm_n8n_not_connected, zero calls', $r instanceof WP_Error && $r->code === 'pqm_n8n_not_connected' && calls() === [] && untouched());
connected(PRO_OK, [PQM_N8n::SETUP_OPTION => ['workflow_id' => 'old', 'credentials' => [], 'path' => 'p', 'wizard_created' => true, 'at' => 1]]);
$r = provision();
check('a setup map exists: 409 pqm_already_provisioned, zero calls', $r instanceof WP_Error && $r->code === 'pqm_already_provisioned'
    && $r->data['status'] === 409 && calls() === [] && untouched());


/* ─── Entry 5: a failed run leaves nothing behind ────────────────────── */
echo "Rollback: the option and the instance model\n";
check('what a rollback could not remove has its own option, pqm_n8n_leftovers', PQM_N8n::LEFTOVERS_OPTION === 'pqm_n8n_leftovers');

/* ─── Bug 72: one run at a time ──────────────────────────────────────── */
echo "Provisioning: two runs started together (bug 72)\n";
// Run B starts while run A is on its first POST /credentials: the scripted answer of that call runs B.
$b = null;
connected(array_merge([MODELS_OK, static function () use (&$b) {
    $calls  = count($GLOBALS['tw']['calls']);
    $writes = count($GLOBALS['tw']['writes']);
    $reply  = PQM_REST_API::provision_n8n_callback(new WP_REST_Request(['anthropic_key' => PROVIDER_KEY]));
    $GLOBALS['replies'][] = $reply instanceof WP_Error ? json_encode([$reply->code, $reply->message, $reply->data]) : json_encode($reply->get_data());
    $b = ['reply' => $reply, 'calls' => count($GLOBALS['tw']['calls']) - $calls, 'writes' => array_slice($GLOBALS['tw']['writes'], $writes),
        'lock' => $GLOBALS['tw']['options'][PQM_N8n::LOCK_OPTION] ?? null, 'pings' => count($GLOBALS['tw']['pings']), 'created' => count($GLOBALS['tw']['app_created'])];
    return created('c-webhook');
}], array_slice(PRO_OK, 2)));
$r = provision();
check('run B: 409 pqm_already_provisioned, zero n8n calls, nothing written, no ping, no Application Password; A\'s lock {at} held meanwhile',
    $b['reply'] instanceof WP_Error && $b['reply']->code === 'pqm_already_provisioned' && $b['reply']->data['status'] === 409
    && $b['calls'] === 0 && $b['writes'] === [] && $b['pings'] === 0 && $b['created'] === 1
    && is_array($b['lock']) && array_keys($b['lock']) === ['at'] && abs($b['lock']['at'] - time()) <= 5);
check('run A finishes: one workflow and three credentials on the instance, the map A\'s, the lock gone', $r['provisioned'] === true && $r['ping_ok'] === true
    && calls() === [...CREATE_ALL, READ_WF] && on_instance() === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']
    && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]['workflow_id'] === 'wf1' && lock_free() && count($GLOBALS['tw']['app_created']) === 1);

echo "Provisioning: the lock held, the lock stale\n";
$held_at = time() - 60;
connected(PRO_OK, [PQM_N8n::LOCK_OPTION => ['at' => $held_at]]);
$r = provision();
check('a lock 1 minute old: 409 pqm_already_provisioned, zero calls, nothing written, the lock as it was', $r instanceof WP_Error && $r->code === 'pqm_already_provisioned'
    && $r->data['status'] === 409 && calls() === [] && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === []
    && $GLOBALS['tw']['options'][PQM_N8n::LOCK_OPTION] === ['at' => $held_at]);
connected(PRO_OK, [PQM_N8n::LOCK_OPTION => ['at' => time() - 6 * 60]]);
$r = provision();
check('a lock 6 minutes old (a run PHP killed; ceiling 5 minutes): taken over, the normal run, the lock gone after', $r['provisioned'] === true
    && calls() === [...CREATE_ALL, READ_WF] && $GLOBALS['tw']['writes'][0] === PQM_N8n::LOCK_OPTION && lock_free()
    && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]['workflow_id'] === 'wf1');
check('the ceiling is 5 minutes (LOCK_TTL)', PQM_N8n::LOCK_TTL === 300);

/* ─── Bug 82: the lock is claimed atomically at the database ─────────── */
echo "Provisioning: two claims in the same instant (bug 82)\n";
// B's INSERT reaches the database inside A's claim, before A's lands; B's run is on its way (its lock row; PHP runs one
// request at a time, so its remainder is the two-runs row above). A's insert affects 0 rows, A reads a fresh lock and
// answers 409 before any call. Bug 72's claim() (add_option: a read, then an upsert) overwrote B's lock here and ran: AC2.
$db   = $GLOBALS['wpdb'];
$b_at = time() - 1; // B's clock a second ahead of A's: an identical {at} would make core's upsert affect 0 rows and refuse A by coincidence
$claim_lands = static function () use ($db, $b_at) {
    $db->query($db->prepare("INSERT IGNORE INTO wp_options (option_name, option_value, autoload) VALUES (%s, %s, 'off')", PQM_N8n::LOCK_OPTION, maybe_serialize(['at' => $b_at])));
};
connected(PRO_OK);
$GLOBALS['tw']['on_claim'] = $claim_lands;
$r = provision();
check('A: 409 pqm_already_provisioned, zero n8n calls, nothing written by A, no ping, no Application Password; B\'s lock {at} held as B wrote it',
    $r instanceof WP_Error && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === []
    && $GLOBALS['tw']['writes'] === [PQM_N8n::LOCK_OPTION] && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === []
    && $GLOBALS['tw']['options'][PQM_N8n::LOCK_OPTION] === ['at' => $b_at] && $GLOBALS['tw']['on_claim'] === null);

echo "Provisioning: the claim and the takeover bring the option cache in line\n";
connected(PRO_OK);
$GLOBALS['tw']['cache'] = ['options' => ['notoptions' => [PQM_N8n::LOCK_OPTION => true, 'pqm_other' => true]]]; // the lock read as absent before: core caches the miss
$r = provision();
$cached = maybe_unserialize($GLOBALS['tw']['cache']['options'][PQM_N8n::LOCK_OPTION] ?? null);
check('claim, free: the insert affects 1 row (autoload off), the run proceeds; the option cache holds the serialized {at} and notoptions no longer names the lock',
    is_array($r) && $r['provisioned'] === true && $GLOBALS['tw']['writes'][0] === PQM_N8n::LOCK_OPTION && $GLOBALS['tw']['autoload'][PQM_N8n::LOCK_OPTION] === 'off'
    && is_array($cached) && array_keys($cached) === ['at'] && abs($cached['at'] - time()) <= 5
    && $GLOBALS['tw']['cache']['options']['notoptions'] === ['pqm_other' => true] && lock_free());
$stale = ['at' => time() - 6 * 60];
connected(PRO_OK, [PQM_N8n::LOCK_OPTION => $stale]);
$GLOBALS['tw']['cache'] = ['options' => [PQM_N8n::LOCK_OPTION => maybe_serialize($stale)]];
$r = provision();
$cached = maybe_unserialize($GLOBALS['tw']['cache']['options'][PQM_N8n::LOCK_OPTION] ?? null);
check('claim, held stale: the compare-and-set update affects 1 row, the run proceeds; the cache holds the new {at}', is_array($r) && $r['provisioned'] === true
    && $GLOBALS['tw']['writes'][0] === PQM_N8n::LOCK_OPTION && is_array($cached) && abs($cached['at'] - time()) <= 5 && lock_free());

echo "Provisioning: two takeovers of a stale lock\n";
connected(PRO_OK, [PQM_N8n::LOCK_OPTION => $stale]);
$GLOBALS['tw']['on_takeover'] = static function () use ($db, $b_at, $stale) { // B read the same stale value; its UPDATE lands first
    $db->query($db->prepare('UPDATE wp_options SET option_value = %s WHERE option_name = %s AND option_value = %s', maybe_serialize(['at' => $b_at]), PQM_N8n::LOCK_OPTION, maybe_serialize($stale)));
};
$r = provision();
check('A\'s update affects 0 rows: 409 pqm_already_provisioned, zero calls, nothing written by A; the lock B\'s', $r instanceof WP_Error
    && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === [] && $GLOBALS['tw']['writes'] === [PQM_N8n::LOCK_OPTION]
    && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === [] && $GLOBALS['tw']['options'][PQM_N8n::LOCK_OPTION] === ['at' => $b_at]);

echo "Provisioning: a takeover lost to a release\n";
connected(PRO_OK, [PQM_N8n::LOCK_OPTION => $stale]);
$GLOBALS['tw']['on_takeover'] = static function () { unset($GLOBALS['tw']['options'][PQM_N8n::LOCK_OPTION]); }; // the row deleted meanwhile (another connection's DELETE)
$r = provision();
check('the stale value read, the row gone: the update affects 0 -> 409, zero calls, nothing written, the lock free', $r instanceof WP_Error
    && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === [] && $GLOBALS['tw']['writes'] === [] && lock_free());
$r = provision();
check('the next request claims the free row: the normal run, the lock gone after', is_array($r) && $r['provisioned'] === true && calls() === [...CREATE_ALL, READ_WF]
    && $GLOBALS['tw']['writes'][0] === PQM_N8n::LOCK_OPTION && lock_free() && $GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION]['workflow_id'] === 'wf1');

echo "Provisioning: the database refuses the claim\n";
connected(PRO_OK);
$GLOBALS['tw']['db_refuses'] = true;
$r = provision();
check('query() false on the insert: not claimed -> 409 pqm_already_provisioned, zero calls, nothing written, no lock (no run without one)', $r instanceof WP_Error
    && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === [] && untouched() && $GLOBALS['tw']['writes'] === []);
$GLOBALS['tw']['db_refuses'] = false;

echo "Provisioning: a scalar lock value (hand-edited or corrupted)
";
connected(PRO_OK, [PQM_N8n::LOCK_OPTION => 'stuck']);
$r = provision();
check('a stored scalar has no fresh at: stale, taken over by the compare-and-set update (its exact bytes matched), the normal run, the lock gone after',
    is_array($r) && $r['provisioned'] === true && calls() === [...CREATE_ALL, READ_WF] && $GLOBALS['tw']['writes'][0] === PQM_N8n::LOCK_OPTION && lock_free());

echo "Rollback: n8n refuses a step, everything removed\n";
foreach ([
    'credential_webhook'   => [1, resp(400, ['message' => 'request/body/data must have required property']), [], 'while creating the webhook credential (n8n answered HTTP 400)'],
    'credential_anthropic' => [2, resp(500, ['message' => 'Internal error']), [DELETED], 'while creating the Anthropic credential (n8n answered HTTP 500)'],
    'credential_wordpress' => [3, timed_out(), [DELETED, DELETED], 'while creating the WordPress credential (n8n could not be reached: cURL error 28: timed out)'],
    'workflow'             => [4, resp(400, ['message' => 'request/body must NOT have additional properties']), [DELETED, DELETED, DELETED], 'while creating the workflow (n8n answered HTTP 400)'],
    'workflow (a 200 without an id)' => [4, resp(200, ['message' => 'ok']), [DELETED, DELETED, DELETED], 'while creating the workflow (n8n did not return what it created)'],
    'activate (refused)'   => [5, resp(400, ['message' => 'Workflow has no node to start the workflow']), [INACTIVE, DELETED, DELETED, DELETED, DELETED], 'while activating the workflow (n8n answered HTTP 400)'],
    'activate (timed out, may be active)' => [5, timed_out(), [INACTIVE, DELETED, DELETED, DELETED, DELETED], 'while activating the workflow (n8n could not be reached: cURL error 28: timed out)'],
    'activate (objects already gone: 404 on DELETE)' => [5, resp(400, ['message' => 'Workflow has no node to start the workflow']), [NOT_FOUND, NOT_FOUND, DELETED, NOT_FOUND, DELETED], 'while activating the workflow (n8n answered HTTP 400)'],
] as $label => [$at, $answer, $undo, $says]) {
    $step = explode(' ', $label)[0];
    connected(array_merge(array_slice(PRO_OK, 0, $at), [$answer], $undo));
    $r = provision();
    $expected = array_merge(array_slice(CREATE_ALL, 0, $at + 1), $at === 5 ? UNDO_ALL : array_slice(UNDO_CREDS, 0, min($at - 1, 3)));
    check("$label: 502 pqm_provision_failed, data.step = $step", $r instanceof WP_Error && $r->code === 'pqm_provision_failed'
        && $r->data === ['status' => 502, 'step' => $step]);
    check('... calls: up to the refused step, then the rollback of what it created, in the forced order', calls() === $expected
        && rollback_ordered() && $GLOBALS['tw']['queue'] === []);
    check('... the instance model holds zero plugin objects; no leftovers recorded', on_instance() === []
        && !in_array(PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));
    check('... the Application Password revoked with the created uuid', revoked());
    check("... the message says \"$says\", that nothing was left, never n8n's reply body", plain($r, $says) && str_ends_with($r->message, CLEAN));
    check('... nothing saved, no ping; the lock released (bug 72)', nothing_saved() && lock_free());
}
check('a deactivate is sent {}, like activate', ($GLOBALS['tw']['calls'][6][1]['body'] ?? null) === '{}');

echo "Rollback: activate refused with 409, the webhook path held (bug 75)\n";
connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(409, ['message' => 'Workflow has a webhook path conflict']), INACTIVE, DELETED, DELETED, DELETED, DELETED]));
$r = provision();
check('502 pqm_provision_failed, data.step = activate; the rollback as today, the instance clean, the Application Password revoked, nothing saved, the lock released',
    $r instanceof WP_Error && $r->code === 'pqm_provision_failed' && $r->data === ['status' => 502, 'step' => 'activate']
    && calls() === array_merge(CREATE_ALL, UNDO_ALL) && rollback_ordered() && on_instance() === [] && revoked() && nothing_saved() && lock_free()
    && !in_array(PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));
check('... the message names the held path, that nothing was left, and the two ways out; never "Try again in a moment", never n8n\'s reply body',
    $r->message === HELD_PATH . ' Nothing was left in n8n. ' . WAYS_OUT && !str_contains($r->message, 'Try again in a moment') && plain($r, 'while activating the workflow:'));
connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(409, ['message' => 'x']), INACTIVE, UNPUBLISHING]));
$r = provision();
check('... with leftovers: the same cause, the leftovers sentence, the two ways out', $r instanceof WP_Error && $r->data['step'] === 'activate'
    && $r->message === HELD_PATH . ' Some of what it created is still in n8n: the next try removes it first. ' . WAYS_OUT
    && isset($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION]) && lock_free());

echo "Rollback: the template names an unknown credential (link)\n";
// The template is filled before the models check, the credential names after it: another host in between.
connected(array_merge([static function () { $GLOBALS['tw']['home'] = 'https://other.example'; return MODELS_OK; }], array_slice(PRO_OK, 1, 3), [DELETED, DELETED, DELETED]));
$r = provision();
check('500, data.step = link; the 3 credentials removed, no workflow sent', $r instanceof WP_Error && $r->data === ['status' => 500, 'step' => 'link']
    && calls() === array_merge(array_slice(CREATE_ALL, 0, 4), UNDO_CREDS) && on_instance() === [] && revoked() && nothing_saved());
check('... the message names the credential, says nothing was left, then to update the plugin',
    str_contains($r->message, 'names a credential the setup does not create (') && str_ends_with($r->message, 'Nothing was left in n8n. Update the plugin, then try again.'));

echo "Rollback: n8n refuses the rollback\n";
foreach ([
    'the workflow DELETE answers 409 (still unpublishing)' => [[resp(400, ['message' => 'x']), INACTIVE, UNPUBLISHING], 2],
    'the workflow DELETE unreachable'                      => [[resp(400, ['message' => 'x']), INACTIVE, timed_out()], 2],
    'n8n unreachable during the rollback: the deactivate stops it, no DELETE sent' => [[timed_out(), timed_out()], 1],
] as $label => [$tail, $undone]) {
    connected(array_merge(array_slice(PRO_OK, 0, 5), $tail));
    $r = provision();
    $record = $GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION] ?? null;
    check("$label: 502, the original step (activate); stops at the workflow after $undone rollback call(s), no credential deleted", $r instanceof WP_Error
        && $r->data === ['status' => 502, 'step' => 'activate'] && count(calls()) === count(CREATE_ALL) + $undone
        && calls() === array_merge(CREATE_ALL, array_slice(UNDO_ALL, 0, $undone)) && $GLOBALS['tw']['queue'] === []);
    check('... the Application Password revoked all the same', revoked());
    check('... pqm_n8n_leftovers = {base_url, the workflow, every credential id, at}, autoload off', $record === [
        'base_url' => BASE, 'workflow_id' => 'wf1', 'credentials' => ['c-webhook', 'c-anthropic', 'c-wp'], 'at' => $record['at'] ?? null]
        && abs($record['at'] - time()) <= 5 && $GLOBALS['tw']['autoload'][PQM_N8n::LEFTOVERS_OPTION] === false);
    check('... the model holds exactly what the record names', on_instance() === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']);
    check('... the message says some objects are still in n8n and the next try removes them first', plain($r, 'while activating the workflow') && str_ends_with($r->message, LEFT));
    check('... nothing saved, no ping', nothing_saved());
}
connected(array_merge(array_slice(PRO_OK, 0, 4), [resp(400, ['message' => 'x']), resp(409, ['message' => 'x']), DELETED, resp(500, ['message' => 'Internal error'])]));
$r = provision();
check('no workflow, two credential DELETEs refused (HTTP): every credential tried, only the refused ones recorded', $r instanceof WP_Error && $r->data['step'] === 'workflow'
    && calls() === array_merge(array_slice(CREATE_ALL, 0, 5), UNDO_CREDS) && revoked() && str_ends_with($r->message, LEFT)
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-webhook', 'c-wp']]
    && on_instance() === ['credentials/c-webhook', 'credentials/c-wp']);
connected(array_merge(array_slice(PRO_OK, 0, 4), [resp(400, ['message' => 'x']), DELETED, timed_out()]));
$r = provision();
check('no workflow, a credential DELETE unreachable: stops there, that id and the untried one recorded without a call', $r instanceof WP_Error
    && count(calls()) === 7 && calls() === array_merge(array_slice(CREATE_ALL, 0, 5), array_slice(UNDO_CREDS, 0, 2)) && $GLOBALS['tw']['queue'] === []
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-anthropic', 'c-wp']]
    && on_instance() === ['credentials/c-anthropic', 'credentials/c-wp'] && str_ends_with($r->message, LEFT));
connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(400, ['message' => 'x']), INACTIVE, DELETED, resp(404, '<html>Not Found</html>'), DELETED, DELETED]));
$r = provision();
check('a credential DELETE answers a non-JSON 404 (a proxy): not counted as removed, the id recorded as left', $r instanceof WP_Error
    && calls() === array_merge(CREATE_ALL, UNDO_ALL) && $GLOBALS['tw']['queue'] === [] && str_ends_with($r->message, LEFT)
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-webhook']]
    && on_instance() === ['credentials/c-webhook']);

echo "Rollback: the next try removes the leftovers first\n";
$old = [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'workflow_id' => 'wf-old', 'credentials' => ['c-old1', 'c-old2', 'c-old3'], 'at' => 1700000000]];
$cleanup = [N8N . 'workflows/wf-old/deactivate', DEL . 'workflows/wf-old', DEL . 'credentials/c-old1', DEL . 'credentials/c-old2', DEL . 'credentials/c-old3'];
connected(array_merge([MODELS_OK, INACTIVE, DELETED, DELETED, NOT_FOUND, DELETED], array_slice(PRO_OK, 1)), $old);
$r = provision();
check('removable: deactivate → DELETE workflow → DELETE credentials, before anything is created; then the normal run',
    calls() === array_merge([MODELS], $cleanup, array_slice(CREATE_ALL, 1), [READ_WF]) && rollback_ordered() && $r['provisioned'] === true && $GLOBALS['tw']['queue'] === []);
check('... the record deleted; the model holds only the new setup', !isset($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION])
    && in_array('-' . PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true)
    && on_instance(OLD_OBJECTS) === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']);
connected([MODELS_OK, INACTIVE, UNPUBLISHING], $old);
$r = provision();
$record = $GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION] ?? null;
check('still refused (409): 502 step cleanup, zero creations, no Application Password', $r instanceof WP_Error && $r->code === 'pqm_provision_failed'
    && $r->data === ['status' => 502, 'step' => 'cleanup'] && calls() === array_merge([MODELS], array_slice($cleanup, 0, 2))
    && $GLOBALS['tw']['app_created'] === [] && $GLOBALS['tw']['app_revoked'] === [] && nothing_saved());
check('... the record updated to what remains (all of it), a fresh `at`', array_diff_key($record, ['at' => 0]) === array_diff_key($old[PQM_N8n::LEFTOVERS_OPTION], ['at' => 0])
    && $record['at'] !== 1700000000 && on_instance(OLD_OBJECTS) === OLD_OBJECTS);
check('... the message says nothing was created, to try again, and the way out when it keeps happening', plain($r, 'nothing was created. Try again in a moment.')
    && str_ends_with($r->message, 'If this keeps happening, check that your n8n API key is still valid by connecting again on the n8n Workflow page, or remove the Post Quality workflow and credentials in n8n by hand.'));
connected([MODELS_OK, DELETED, timed_out()], [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'credentials' => ['c-old1', 'c-old2'], 'at' => 1700000000]]);
$r = provision();
check('credentials only, the last one unreachable: no deactivate, the record keeps the one that remains', $r instanceof WP_Error && $r->data['step'] === 'cleanup'
    && calls() === [MODELS, DEL . 'credentials/c-old1', DEL . 'credentials/c-old2']
    && array_diff_key($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-old2']]);
connected(PRO_OK, [PQM_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://old.example.com'] + $old[PQM_N8n::LEFTOVERS_OPTION]]);
$r = provision();
check('a record of another instance: dropped, nothing sent there, the normal run', calls() === [...CREATE_ALL, READ_WF] && $r['provisioned'] === true
    && !isset($GLOBALS['tw']['options'][PQM_N8n::LEFTOVERS_OPTION]));
connected([resp(401, ['type' => 'error'])], $old);
$r = provision();
check('a refused provider key: the leftovers are not touched (no n8n call)', $r->code === 'pqm_provider_key_invalid' && calls() === [MODELS] && untouched());

echo "Provisioning: the ping meets n8n's publication window (bug 80)\n";
connected(PRO_OK);
$GLOBALS['tw']['ping_queue'] = [HOOK_404];
$r = provision();
check('404 then 200: 2 pings of the saved URL, with the token; the reply ping_ok, "Ping OK"', count($GLOBALS['tw']['pings']) === 2 && $r['provisioned'] === true && $r['ping_ok'] === true
    && array_unique(array_column($GLOBALS['tw']['pings'], 0)) === [HOOK] && $GLOBALS['tw']['pings'][1][1]['headers']['X-PQM-Token'] === TOKEN && str_starts_with($r['message'], 'Ping OK'));
check('... the version recorded, no failure on record; Set up the workflow done; status_html set up; the diagnosis read once, after the passing ping',
    pqm_workflow_report()['version'] === PQM_WORKFLOW_VERSION && !isset(pqm_workflow_report()['failed']) && ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === true
    && str_contains($r['status_html'], 'data-state="set-up"') && calls() === [...CREATE_ALL, READ_WF] && $GLOBALS['tw']['queue'] === []);
connected(PRO_OK);
$GLOBALS['tw']['ping_queue'] = [HOOK_404, HOOK_404];
$r = provision();
check('404, 404, 200: 3 pings, then as passed', count($GLOBALS['tw']['pings']) === 3 && $r['ping_ok'] === true && !isset(pqm_workflow_report()['failed']));

echo "Provisioning: the ping fails\n";
connected(PRO_OK);
$GLOBALS['tw']['ping_response'] = HOOK_404;
$r = provision();
check('404 three times: 3 pings, no more; 200 {provisioned, ping_ok false, message}: set up and active, the webhook did not answer yet, Test again first, the editor-address hint second',
    count($GLOBALS['tw']['pings']) === 3 && $r['provisioned'] === true && $r['ping_ok'] === false && $r['message'] === EARLY_404);
check('... everything kept: map stored, URL saved, no version recorded; the failure on record is that sentence (story 67)', isset($GLOBALS['tw']['options'][PQM_N8n::SETUP_OPTION])
    && $GLOBALS['tw']['options']['pqm_settings']['n8n_webhook_url'] === HOOK && pqm_workflow_report()['version'] === 0
    && (pqm_workflow_report()['failed']['message'] ?? '') === EARLY_404);
check('... the failed ping recorded, so Set up the workflow stays open (setup incomplete); status_html needs attention, the message its outcome line', ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === false
    && str_contains($r['status_html'], 'data-state="failing"') && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-error">' . $r['message'] . '</p>') && !pqm_setup_complete());
check('... no rollback: nothing deactivated, deleted or revoked, no leftovers recorded; the lock released', calls() === CREATE_ALL && $GLOBALS['tw']['app_revoked'] === []
    && !in_array(PQM_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true) && lock_free());
foreach (['a 401' => [resp(401, ['message' => 'Authorization data is wrong!']), 'n8n refused the webhook credential (HTTP 401)'],
    'a 500' => [resp(500, ['message' => 'Workflow error']), 'n8n responded with HTTP 500'],
    'a WP_Error' => [timed_out(), 'Could not connect to n8n: cURL error 28: timed out']] as $label => [$answer, $cause]) {
    connected(PRO_OK);
    $GLOBALS['tw']['ping_response'] = $answer;
    $r = provision();
    check("$label: one ping, today's sentence (the test did not pass, its own cause, the editor-address hint); that cause on record", count($GLOBALS['tw']['pings']) === 1 && $r['ping_ok'] === false
        && str_starts_with($r['message'], 'Your workflow is set up and active in n8n, but the test did not pass. ' . $cause) && !str_contains($r['message'], 'did not answer yet')
        && str_ends_with($r['message'], 'paste it as the n8n Webhook URL.') && str_starts_with(pqm_workflow_report()['failed']['message'] ?? '', $cause));
}
/* Story 2: the ping got through and its callback failed — the address was right, so the sentence carries no editor-address hint */
foreach (['refused' => 'n8n reached this site, but the callback was refused: it carried a token that is not this site\'s webhook token. In n8n, open the POST Result to WordPress node and set its X-PQM-Token header to {{ $json.auth_token }}, then test again.',
    'none' => 'n8n accepted the ping but did not call this site back within 0 seconds. n8n must reach https://example.com/wp-json/ (this site\'s REST API): a local, private or password-protected site cannot be called back. Check the ping\'s execution in n8n, then test again.'] as $callback => $cause) {
    connected(PRO_OK);
    $GLOBALS['tw']['ping_callback'] = $callback;
    $r = provision();
    check("the callback $callback: one ping, ping_ok false, 'the test did not pass' + the callback's own sentence, no editor-address hint; that cause on record, Set up the workflow open", count($GLOBALS['tw']['pings']) === 1
        && $r['provisioned'] === true && $r['ping_ok'] === false && $r['message'] === 'Your workflow is set up and active in n8n, but the test did not pass. ' . $cause
        && (pqm_workflow_report()['failed']['message'] ?? '') === $cause && ($GLOBALS['tw']['options'][PQM_SETUP_OPTION]['steps']['connect'] ?? null) === false
        && str_contains($r['status_html'], 'data-state="failing"'));
}
$GLOBALS['tw']['ping_callback'] = 'received';
check('the retry wait is the filter pqm_provision_ping_retry_wait, 2 s by default, in the provisioning callback only (the Test button, rotation and update ping once)',
    (bool) preg_match('/sleep\\( max\\( 0, \\(int\\) apply_filters\\( \'pqm_provision_ping_retry_wait\', 2 \\) \\) \\);/', file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-rest-api.php'))
    && !preg_match('/pqm_provision_ping_retry_wait|(?<!u)sleep\\(/', file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php'))); // usleep() is the ping's callback wait (story 2), not a retry

echo "Provisioning: the provider key leak scan\n";
check('after every run (' . count($provision_runs) . '): the provider key and the Application Password are in no option and no ping',
    !array_filter($provision_runs, static fn($run) => str_contains(json_encode([$run[0], $run[2]]), PROVIDER_KEY) || str_contains(json_encode([$run[0], $run[2]]), APP_PASSWORD)));
check('... and in no outgoing call but the models request and the Anthropic credential body',
    !array_filter($provision_runs, static fn($run) => array_filter($run[1], static fn($c) => str_contains(json_encode($c), PROVIDER_KEY)
        && $c[0] !== 'https://api.anthropic.com/v1/models?limit=1' && !str_starts_with($c[1]['body'] ?? '', '{"name":"Anthropic - Claude"'))));
check('... the Application Password\'s uuid (bug 73) is in no ping and no outgoing call (it lives in the setup map only)',
    !array_filter($provision_runs, static fn($run) => str_contains(json_encode([$run[1], $run[2]]), 'app-uuid')));

/* ─── Bug 73: the setup map's Application Password is revoked with the map ─ */
echo "Revoke: the setup map's Application Password\n";
site([PQM_N8n::SETUP_OPTION => pro_map(1)]);
$GLOBALS['tw']['app_revoked'] = [];
PQM_N8n::revoke_setup_password__premium_only();
check('a map with application_password: delete_application_password(user_id, uuid), once; the map untouched', $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']] && $GLOBALS['tw']['writes'] === []);
foreach (['a map without the key (an earlier version, or adopted)' => [PQM_N8n::SETUP_OPTION => array_diff_key(pro_map(1), ['application_password' => 0])], 'no map' => []] as $label => $options) {
    site($options);
    $GLOBALS['tw']['app_revoked'] = [];
    PQM_N8n::revoke_setup_password__premium_only();
    check("$label: no call", $GLOBALS['tw']['app_revoked'] === [] && $GLOBALS['tw']['writes'] === []);
}

key_leak_scan($replies);

harness_exit();
