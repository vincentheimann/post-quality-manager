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
 * Bug 113: the D1 run (no Application Passwords) sends Update WP Post
 * (Rewrite) without a credential and passes the world's publish check, which
 * refuses a credential without an id as n8n 2.46.6 does.
 * Bugs 112 and 114 (one build): the workflow creation waits 30 s, every
 * other call 10; a creation without an answer is looked up once by its exact
 * name and this run's credential ids, rolled back when found, else
 * fingerprinted for the next setup's lookup; a credential creation without
 * an answer is named in the message; the workflow DELETE answered 409 is
 * sent again, up to 4 calls (the wait filtered to 0).
 * WP stubs, no WP install; callbacks are called directly. The world (stubs,
 * constants, builders, the plugin load) is lib/n8n-world.php; story 78
 * build 2 split the one n8n harness by concern.
 * Run: php n8n-provisioning-harness.php
 */

require __DIR__ . '/lib/n8n-world.php';

/* ─── Entry 4: POST /n8n/provision ───────────────────────────────────── */
echo "Provisioning: route\n";
$route = $GLOBALS['pqm_routes'][POSTQUALITY_REST_NAMESPACE . '/n8n/provision'] ?? [];
check('/n8n/provision: POST, gated by check_manage_settings, anthropic_key required', ($route['methods'] ?? null) === 'POST'
    && ($route['permission_callback'] ?? null) === $gate && ($route['args']['anthropic_key']['required'] ?? null) === true);
check('the setup map has its own option, postquality_n8n_setup', PostQuality_N8n::SETUP_OPTION === 'postquality_n8n_setup');

$raw_template   = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json'), true); // placeholders unfilled
$template_names = array_values(array_unique(array_merge(...array_map(static fn($n) => array_column($n['credentials'] ?? [], 'name'), $raw_template['nodes']))));
$expected_names = array_values(PostQuality_N8n::credential_names('__PQM_SITE_HOST__'));
sort($template_names);
sort($expected_names);
check('story 77: the template\'s credential names are credential_names(__PQM_SITE_HOST__) and its header parameter is TOKEN_HEADER (JSON pinned to the PHP source)',
    $template_names === $expected_names && $template_names !== []
    && array_filter($raw_template['nodes'], static fn($n) => in_array(PostQuality_N8n::TOKEN_HEADER, array_column($n['parameters']['headerParameters']['parameters'] ?? [], 'name'), true)) !== []);

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
check('bug 112, cold creation: the POST /workflows request carries timeout 30, every other call 10 (the models request included)',
    $GLOBALS['tw']['calls'][4][1]['timeout'] === 30 && timeouts_ok($GLOBALS['tw']['calls']));
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
check('the trigger path is postquality_webhook_path()', array_column($sent['nodes'], null, 'name')['Webhook Trigger']['parameters']['path'] === postquality_webhook_path());
check('activate is sent {}', $GLOBALS['tw']['calls'][5][1]['body'] === '{}');
$setup = $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION] ?? null;
check('setup map stored, autoload off; bug 73: it names the Application Password {uuid, user_id}', $setup === pro_map($setup['at'] ?? null) && abs($setup['at'] - time()) <= 5
    && $GLOBALS['tw']['autoload'][PostQuality_N8n::SETUP_OPTION] === false);
check('bug 72: the lock claimed before the first call (bug 82: INSERT IGNORE, autoload off) and gone after the map is stored',
    $GLOBALS['tw']['writes'][0] === PostQuality_N8n::LOCK_OPTION
    && $GLOBALS['tw']['writes'][array_search(PostQuality_N8n::SETUP_OPTION, $GLOBALS['tw']['writes'], true) + 1] === '-' . PostQuality_N8n::LOCK_OPTION
    && $GLOBALS['tw']['autoload'][PostQuality_N8n::LOCK_OPTION] === 'off' && lock_free()
    && PostQuality_N8n::LOCK_OPTION === 'postquality_n8n_provisioning');
check('webhook URL saved: connected origin + /webhook/ + the path; the token kept', $GLOBALS['tw']['options']['postquality_settings'] === ['webhook_token' => TOKEN, 'n8n_webhook_url' => HOOK]);
check('pinged that URL once, with the token', count($GLOBALS['tw']['pings']) === 1 && $GLOBALS['tw']['pings'][0][0] === HOOK
    && $GLOBALS['tw']['pings'][0][1]['headers']['X-PQM-Token'] === TOKEN);
check('replied version recorded', ($GLOBALS['tw']['options']['postquality_workflow_version_seen']['version'] ?? null) === POSTQUALITY_WORKFLOW_VERSION);
check('the ping recorded as the connect route does (autoload off): Set up the workflow reads done', ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] ?? null) === true
    && $GLOBALS['tw']['autoload'][POSTQUALITY_SETUP_OPTION] === false);
check('reply: {provisioned, ping_ok, workflow_id, webhook_url, message, rewrite_ready, status_html}; neither the uuid nor the password in it', array_keys($r) === ['provisioned', 'ping_ok', 'workflow_id', 'webhook_url', 'message', 'rewrite_ready', 'status_html']
    && $r['provisioned'] === true && $r['ping_ok'] === true && $r['workflow_id'] === 'wf1' && $r['rewrite_ready'] === true
    && str_starts_with($r['message'], 'Ping OK') && !str_contains(json_encode($r), 'app-uuid') && !str_contains(json_encode($r), APP_PASSWORD));
check('... webhook_url = the saved URL (story 77: the wizard reads it instead of rebuilding it)', $r['webhook_url'] === HOOK);
check('... status_html = the page\'s refreshed body with the message first (entry 6; story 67): set up', $r['status_html'] === PostQuality_Admin::render_workflow_body($r['message'], 'success')
    && str_contains($r['status_html'], '<div class="pqm-workflow-status" data-state="set-up">') && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-success">' . $r['message'] . '</p>'));
$hits = array_keys(array_filter($GLOBALS['tw']['calls'], static fn($c) => str_contains(json_encode($c), PROVIDER_KEY)));
check('the provider key travels in the models request and the Anthropic credential body only', $hits === [0, 2]
    && !str_contains(json_encode($GLOBALS['tw']['calls'][2][1]['headers']), PROVIDER_KEY) && !str_contains(json_encode($GLOBALS['tw']['pings']), PROVIDER_KEY));

echo "Provisioning: acceptance — the provider key is in neither read reply\n";
$GLOBALS['tw']['caps'] = ['postquality_manage_settings'];
$conn        = json_encode(get_state());
$setup_reply = json_encode(PostQuality_REST_API::get_setup_callback()->get_data());
$replies[]   = $setup_reply;
check('GET /n8n/connection and GET /setup after provisioning: no provider key, no Application Password, no uuid (bug 73)',
    !str_contains($conn . $setup_reply, PROVIDER_KEY) && !str_contains($conn . $setup_reply, APP_PASSWORD) && !str_contains($conn . $setup_reply, 'app-uuid')
    && str_contains($setup_reply, '"webhook_url"'));
check('bug 73, the wizard: GET /setup on the Pro build names whose Application Password the setup makes (the current user\'s display name)',
    json_decode($setup_reply, true)['application_password_user'] === 'Anna Admin');
$GLOBALS['tw']['app_passwords'] = false;
check('... \'\' where the site offers no Application Passwords (D1): the sentence is not shown', PostQuality_REST_API::get_setup_callback()->get_data()['application_password_user'] === '');
$GLOBALS['tw']['app_passwords'] = true;

echo "Provisioning: Pro, no Application Passwords (D1)\n";
// What n8n stores of it (bug 113, decision 2): Update WP Post (Rewrite) without a credential.
$no_rewrite_credential = ['Update WP Post (Rewrite)' => static function (array $n): array { unset($n['credentials']); return $n; }];
connected([MODELS_OK, created('c-webhook'), created('c-anthropic'), created('wf1'), ACTIVE,
    resp(200, stored_workflow('wf1', wired(WIRED, $no_rewrite_credential)))]);
$GLOBALS['tw']['app_passwords'] = false;
$r = provision();
$sent  = body(3);
$links = linked($sent);
check('entry 7, D1: the diagnosis reads GET /workflows/{id} only and skips Update WP Post, which has no credential: clean, nothing stored, the ping message unchanged',
    calls()[5] === READ_WF && !isset(postquality_workflow_report()['miswired']) && str_starts_with($r['message'], 'Ping OK'));
check('2 credentials, no Application Password, no httpBasicAuth credential', count(calls()) === 6 && $GLOBALS['tw']['app_created'] === []
    && !str_contains(json_encode(array_slice($GLOBALS['tw']['calls'], 1, 2)), 'httpBasicAuth'));
check('bug 113 (decision 2): 7 nodes linked by id; Update WP Post (Rewrite) sent with no `credentials` key at all, never name-only',
    count($links) === 7 && !array_filter($links, static fn($c) => !isset(reset($c)['id'])) && !isset($links['Update WP Post (Rewrite)'])
    && in_array('Update WP Post (Rewrite)', array_column($sent['nodes'], 'name'), true) && !str_contains(json_encode($sent), 'WordPress - example.com'));
check('... n8n publishes it: the activate passes its credential check (the world refuses a credential without an id, as n8n 2.46.6 does)',
    calls()[4] === N8N . 'workflows/wf1/activate' && $GLOBALS['tw']['calls'][4][2] === ACTIVE && $r['provisioned'] === true);
check('reply: provisioned, rewrite_ready false, the message says Rewrite stays off until a credential is added by hand',
    $r['provisioned'] === true && $r['ping_ok'] === true && $r['rewrite_ready'] === false
    && str_contains($r['message'], 'Rewrite stays off') && str_contains($r['message'], 'does not run on HTTPS')
    && str_contains($r['message'], 'a WordPress credential is added in n8n on the Update WP Post (Rewrite) node'));
check('map without a wordpress credential, and without an application_password (bug 73)', $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['credentials'] === ['webhook' => 'c-webhook', 'anthropic' => 'c-anthropic']
    && !array_key_exists('application_password', $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]));

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
site(['postquality_settings' => ['webhook_token' => TOKEN]]);
$GLOBALS['tw']['pings'] = [];
$GLOBALS['tw']['app_created'] = [];
$r = provision();
check('no n8n key stored: pqm_n8n_not_connected, zero calls', $r instanceof WP_Error && $r->code === 'pqm_n8n_not_connected' && calls() === [] && untouched());
connected(PRO_OK, [PostQuality_N8n::SETUP_OPTION => ['workflow_id' => 'old', 'credentials' => [], 'path' => 'p', 'wizard_created' => true, 'at' => 1]]);
$r = provision();
check('a setup map exists: 409 pqm_already_provisioned, zero calls', $r instanceof WP_Error && $r->code === 'pqm_already_provisioned'
    && $r->data['status'] === 409 && calls() === [] && untouched());


/* ─── Entry 5: a failed run leaves nothing behind ────────────────────── */
echo "Rollback: the option and the instance model\n";
check('what a rollback could not remove has its own option, postquality_n8n_leftovers', PostQuality_N8n::LEFTOVERS_OPTION === 'postquality_n8n_leftovers');

/* ─── Bug 72: one run at a time ──────────────────────────────────────── */
echo "Provisioning: two runs started together (bug 72)\n";
// Run B starts while run A is on its first POST /credentials: the scripted answer of that call runs B.
$b = null;
connected(array_merge([MODELS_OK, static function () use (&$b) {
    $calls  = count($GLOBALS['tw']['calls']);
    $writes = count($GLOBALS['tw']['writes']);
    $reply  = PostQuality_REST_API::provision_n8n_callback(new WP_REST_Request(['anthropic_key' => PROVIDER_KEY]));
    $GLOBALS['replies'][] = $reply instanceof WP_Error ? json_encode([$reply->code, $reply->message, $reply->data]) : json_encode($reply->get_data());
    $b = ['reply' => $reply, 'calls' => count($GLOBALS['tw']['calls']) - $calls, 'writes' => array_slice($GLOBALS['tw']['writes'], $writes),
        'lock' => $GLOBALS['tw']['options'][PostQuality_N8n::LOCK_OPTION] ?? null, 'pings' => count($GLOBALS['tw']['pings']), 'created' => count($GLOBALS['tw']['app_created'])];
    return created('c-webhook');
}], array_slice(PRO_OK, 2)));
$r = provision();
check('run B: 409 pqm_already_provisioned, zero n8n calls, nothing written, no ping, no Application Password; A\'s lock {at} held meanwhile',
    $b['reply'] instanceof WP_Error && $b['reply']->code === 'pqm_already_provisioned' && $b['reply']->data['status'] === 409
    && $b['calls'] === 0 && $b['writes'] === [] && $b['pings'] === 0 && $b['created'] === 1
    && is_array($b['lock']) && array_keys($b['lock']) === ['at'] && abs($b['lock']['at'] - time()) <= 5);
check('run A finishes: one workflow and three credentials on the instance, the map A\'s, the lock gone', $r['provisioned'] === true && $r['ping_ok'] === true
    && calls() === [...CREATE_ALL, READ_WF] && on_instance() === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['workflow_id'] === 'wf1' && lock_free() && count($GLOBALS['tw']['app_created']) === 1);

echo "Provisioning: the lock held, the lock stale\n";
$held_at = time() - 60;
connected(PRO_OK, [PostQuality_N8n::LOCK_OPTION => ['at' => $held_at]]);
$r = provision();
check('a lock 1 minute old: 409 pqm_already_provisioned, zero calls, nothing written, the lock as it was', $r instanceof WP_Error && $r->code === 'pqm_already_provisioned'
    && $r->data['status'] === 409 && calls() === [] && $GLOBALS['tw']['writes'] === [] && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === []
    && $GLOBALS['tw']['options'][PostQuality_N8n::LOCK_OPTION] === ['at' => $held_at]);
connected(PRO_OK, [PostQuality_N8n::LOCK_OPTION => ['at' => time() - 6 * 60]]);
$r = provision();
check('a lock 6 minutes old (a run PHP killed; ceiling 5 minutes): taken over, the normal run, the lock gone after', $r['provisioned'] === true
    && calls() === [...CREATE_ALL, READ_WF] && $GLOBALS['tw']['writes'][0] === PostQuality_N8n::LOCK_OPTION && lock_free()
    && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['workflow_id'] === 'wf1');
check('the ceiling is 5 minutes (LOCK_TTL)', PostQuality_N8n::LOCK_TTL === 300);

/* ─── Bug 82: the lock is claimed atomically at the database ─────────── */
echo "Provisioning: two claims in the same instant (bug 82)\n";
// B's INSERT reaches the database inside A's claim, before A's lands; B's run is on its way (its lock row; PHP runs one
// request at a time, so its remainder is the two-runs row above). A's insert affects 0 rows, A reads a fresh lock and
// answers 409 before any call. Bug 72's claim() (add_option: a read, then an upsert) overwrote B's lock here and ran: AC2.
$db   = $GLOBALS['wpdb'];
$b_at = time() - 1; // B's clock a second ahead of A's: an identical {at} would make core's upsert affect 0 rows and refuse A by coincidence
$claim_lands = static function () use ($db, $b_at) {
    $db->query($db->prepare("INSERT IGNORE INTO wp_options (option_name, option_value, autoload) VALUES (%s, %s, 'off')", PostQuality_N8n::LOCK_OPTION, maybe_serialize(['at' => $b_at])));
};
connected(PRO_OK);
$GLOBALS['tw']['on_claim'] = $claim_lands;
$r = provision();
check('A: 409 pqm_already_provisioned, zero n8n calls, nothing written by A, no ping, no Application Password; B\'s lock {at} held as B wrote it',
    $r instanceof WP_Error && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === []
    && $GLOBALS['tw']['writes'] === [PostQuality_N8n::LOCK_OPTION] && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === []
    && $GLOBALS['tw']['options'][PostQuality_N8n::LOCK_OPTION] === ['at' => $b_at] && $GLOBALS['tw']['on_claim'] === null);

echo "Provisioning: the claim and the takeover bring the option cache in line\n";
connected(PRO_OK);
$GLOBALS['tw']['cache'] = ['options' => ['notoptions' => [PostQuality_N8n::LOCK_OPTION => true, 'pqm_other' => true]]]; // the lock read as absent before: core caches the miss
$r = provision();
$cached = maybe_unserialize($GLOBALS['tw']['cache']['options'][PostQuality_N8n::LOCK_OPTION] ?? null);
check('claim, free: the insert affects 1 row (autoload off), the run proceeds; the option cache holds the serialized {at} and notoptions no longer names the lock',
    is_array($r) && $r['provisioned'] === true && $GLOBALS['tw']['writes'][0] === PostQuality_N8n::LOCK_OPTION && $GLOBALS['tw']['autoload'][PostQuality_N8n::LOCK_OPTION] === 'off'
    && is_array($cached) && array_keys($cached) === ['at'] && abs($cached['at'] - time()) <= 5
    && $GLOBALS['tw']['cache']['options']['notoptions'] === ['pqm_other' => true] && lock_free());
$stale = ['at' => time() - 6 * 60];
connected(PRO_OK, [PostQuality_N8n::LOCK_OPTION => $stale]);
$GLOBALS['tw']['cache'] = ['options' => [PostQuality_N8n::LOCK_OPTION => maybe_serialize($stale)]];
$r = provision();
$cached = maybe_unserialize($GLOBALS['tw']['cache']['options'][PostQuality_N8n::LOCK_OPTION] ?? null);
check('claim, held stale: the compare-and-set update affects 1 row, the run proceeds; the cache holds the new {at}', is_array($r) && $r['provisioned'] === true
    && $GLOBALS['tw']['writes'][0] === PostQuality_N8n::LOCK_OPTION && is_array($cached) && abs($cached['at'] - time()) <= 5 && lock_free());

echo "Provisioning: two takeovers of a stale lock\n";
connected(PRO_OK, [PostQuality_N8n::LOCK_OPTION => $stale]);
$GLOBALS['tw']['on_takeover'] = static function () use ($db, $b_at, $stale) { // B read the same stale value; its UPDATE lands first
    $db->query($db->prepare('UPDATE wp_options SET option_value = %s WHERE option_name = %s AND option_value = %s', maybe_serialize(['at' => $b_at]), PostQuality_N8n::LOCK_OPTION, maybe_serialize($stale)));
};
$r = provision();
check('A\'s update affects 0 rows: 409 pqm_already_provisioned, zero calls, nothing written by A; the lock B\'s', $r instanceof WP_Error
    && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === [] && $GLOBALS['tw']['writes'] === [PostQuality_N8n::LOCK_OPTION]
    && $GLOBALS['tw']['pings'] === [] && $GLOBALS['tw']['app_created'] === [] && $GLOBALS['tw']['options'][PostQuality_N8n::LOCK_OPTION] === ['at' => $b_at]);

echo "Provisioning: a takeover lost to a release\n";
connected(PRO_OK, [PostQuality_N8n::LOCK_OPTION => $stale]);
$GLOBALS['tw']['on_takeover'] = static function () { unset($GLOBALS['tw']['options'][PostQuality_N8n::LOCK_OPTION]); }; // the row deleted meanwhile (another connection's DELETE)
$r = provision();
check('the stale value read, the row gone: the update affects 0 -> 409, zero calls, nothing written, the lock free', $r instanceof WP_Error
    && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === [] && $GLOBALS['tw']['writes'] === [] && lock_free());
$r = provision();
check('the next request claims the free row: the normal run, the lock gone after', is_array($r) && $r['provisioned'] === true && calls() === [...CREATE_ALL, READ_WF]
    && $GLOBALS['tw']['writes'][0] === PostQuality_N8n::LOCK_OPTION && lock_free() && $GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION]['workflow_id'] === 'wf1');

echo "Provisioning: the database refuses the claim\n";
connected(PRO_OK);
$GLOBALS['tw']['db_refuses'] = true;
$r = provision();
check('query() false on the insert: not claimed -> 409 pqm_already_provisioned, zero calls, nothing written, no lock (no run without one)', $r instanceof WP_Error
    && $r->code === 'pqm_already_provisioned' && $r->data['status'] === 409 && calls() === [] && untouched() && $GLOBALS['tw']['writes'] === []);
$GLOBALS['tw']['db_refuses'] = false;

echo "Provisioning: a scalar lock value (hand-edited or corrupted)
";
connected(PRO_OK, [PostQuality_N8n::LOCK_OPTION => 'stuck']);
$r = provision();
check('a stored scalar has no fresh at: stale, taken over by the compare-and-set update (its exact bytes matched), the normal run, the lock gone after',
    is_array($r) && $r['provisioned'] === true && calls() === [...CREATE_ALL, READ_WF] && $GLOBALS['tw']['writes'][0] === PostQuality_N8n::LOCK_OPTION && lock_free());

echo "Rollback: n8n refuses a step, everything removed\n";
// Bug 112: a refused create (an HTTP answer) is never looked up by name; a credential create without an answer is not either,
// and the message says that credential may still appear instead of "Nothing was left in n8n." (the fifth value: the ending).
foreach ([
    'credential_webhook'   => [1, resp(400, ['message' => 'request/body/data must have required property']), [], 'while creating the webhook credential (n8n answered HTTP 400)'],
    'credential_webhook (timed out, nothing to roll back)' => [1, timed_out(), [], 'while creating the webhook credential (n8n could not be reached: cURL error 28: timed out)', CRED_MAY_APPEAR],
    'credential_anthropic' => [2, resp(500, ['message' => 'Internal error']), [DELETED], 'while creating the Anthropic credential (n8n answered HTTP 500)'],
    'credential_wordpress (timed out)' => [3, timed_out(), [DELETED, DELETED], 'while creating the WordPress credential (n8n could not be reached: cURL error 28: timed out)', CRED_MAY_APPEAR],
    'workflow'             => [4, resp(400, ['message' => 'request/body must NOT have additional properties']), [DELETED, DELETED, DELETED], 'while creating the workflow (n8n answered HTTP 400)'],
    'workflow (a 500)'     => [4, resp(500, ['message' => 'Internal error']), [DELETED, DELETED, DELETED], 'while creating the workflow (n8n answered HTTP 500)'],
    'workflow (a 200 without an id)' => [4, resp(200, ['message' => 'ok']), [DELETED, DELETED, DELETED], 'while creating the workflow (n8n did not return what it created)'],
    'activate (refused)'   => [5, resp(400, ['message' => 'Workflow has no node to start the workflow']), [INACTIVE, DELETED, DELETED, DELETED, DELETED], 'while activating the workflow (n8n answered HTTP 400)'],
    'activate (timed out, may be active)' => [5, timed_out(), [INACTIVE, DELETED, DELETED, DELETED, DELETED], 'while activating the workflow (n8n could not be reached: cURL error 28: timed out)'],
    'activate (objects already gone: 404 on DELETE)' => [5, resp(400, ['message' => 'Workflow has no node to start the workflow']), [NOT_FOUND, NOT_FOUND, DELETED, NOT_FOUND, DELETED], 'while activating the workflow (n8n answered HTTP 400)'],
] as $label => $row) {
    [$at, $answer, $undo, $says] = $row;
    $ends = $row[4] ?? CLEAN;
    $step = explode(' ', $label)[0];
    connected(array_merge(array_slice(PRO_OK, 0, $at), [$answer], $undo));
    $r = provision();
    $expected = array_merge(array_slice(CREATE_ALL, 0, $at + 1), $at === 5 ? UNDO_ALL : array_slice(UNDO_CREDS, 0, min($at - 1, 3)));
    check("$label: 502 pqm_provision_failed, data.step = $step", $r instanceof WP_Error && $r->code === 'pqm_provision_failed'
        && $r->data === ['status' => 502, 'step' => $step]);
    check('... calls: up to the refused step, then the rollback of what it created, in the forced order; no lookup by name (bug 112)', calls() === $expected
        && rollback_ordered() && $GLOBALS['tw']['queue'] === [] && !preg_grep('/\?name=/', calls()));
    check('... the instance model holds zero plugin objects; no leftovers recorded', on_instance() === []
        && !in_array(PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));
    check('... the Application Password revoked with the created uuid', revoked());
    check("... the message says \"$says\", " . ($ends === CLEAN ? 'that nothing was left' : 'that n8n may still create that credential, never that nothing was left') . ", never n8n's reply body",
        plain($r, $says) && str_ends_with($r->message, $ends) && ($ends === CLEAN || !str_contains($r->message, 'Nothing was left')));
    check('... nothing saved, no ping; the lock released (bug 72)', nothing_saved() && lock_free());
}
check('a deactivate is sent {}, like activate', ($GLOBALS['tw']['calls'][6][1]['body'] ?? null) === '{}');

echo "Rollback: activate refused with 409, the webhook path held (bug 75)\n";
connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(409, ['message' => 'Workflow has a webhook path conflict']), INACTIVE, DELETED, DELETED, DELETED, DELETED]));
$r = provision();
check('502 pqm_provision_failed, data.step = activate; the rollback as today, the instance clean, the Application Password revoked, nothing saved, the lock released',
    $r instanceof WP_Error && $r->code === 'pqm_provision_failed' && $r->data === ['status' => 502, 'step' => 'activate']
    && calls() === array_merge(CREATE_ALL, UNDO_ALL) && rollback_ordered() && on_instance() === [] && revoked() && nothing_saved() && lock_free()
    && !in_array(PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));
check('... the message names the held path, that nothing was left, and the two ways out; never "Try again in a moment", never n8n\'s reply body',
    $r->message === HELD_PATH . ' Nothing was left in n8n. ' . WAYS_OUT && !str_contains($r->message, 'Try again in a moment') && plain($r, 'while activating the workflow:'));
connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(409, ['message' => 'x']), INACTIVE], STAYS_UNPUBLISHING));
$r = provision();
check('... with leftovers (the workflow DELETE refused 4 times, bug 114): the same cause, the leftovers sentence, the two ways out', $r instanceof WP_Error && $r->data['step'] === 'activate'
    && $r->message === HELD_PATH . ' Some of what it created is still in n8n: the next try removes it first. ' . WAYS_OUT
    && isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]) && lock_free() && $GLOBALS['tw']['queue'] === []);

echo "Rollback: the template names an unknown credential (link)\n";
// The template is filled before the models check, the credential names after it: another host in between.
connected(array_merge([static function () { $GLOBALS['tw']['home'] = 'https://other.example'; return MODELS_OK; }], array_slice(PRO_OK, 1, 3), [DELETED, DELETED, DELETED]));
$r = provision();
check('500, data.step = link; the 3 credentials removed, no workflow sent', $r instanceof WP_Error && $r->data === ['status' => 500, 'step' => 'link']
    && calls() === array_merge(array_slice(CREATE_ALL, 0, 4), UNDO_CREDS) && on_instance() === [] && revoked() && nothing_saved());
check('... the message names the credential, says nothing was left, then to update the plugin',
    str_contains($r->message, 'names a credential the setup does not create (') && str_ends_with($r->message, 'Nothing was left in n8n. Update the plugin, then try again.'));

echo "Rollback: n8n refuses the rollback\n";
[$deactivate, $delete_wf] = UNDO_ALL;
foreach ([
    'stays refused: the workflow DELETE answers 409 four times (bug 114), no fifth call' => [[resp(400, ['message' => 'x']), INACTIVE, ...STAYS_UNPUBLISHING], [$deactivate, $delete_wf, $delete_wf, $delete_wf, $delete_wf]],
    'the workflow DELETE answers 409, then n8n unreachable: no further try' => [[resp(400, ['message' => 'x']), INACTIVE, UNPUBLISHING, timed_out()], [$deactivate, $delete_wf, $delete_wf]],
    'the workflow DELETE answers 500: only a 409 is retried'               => [[resp(400, ['message' => 'x']), INACTIVE, resp(500, ['message' => 'Internal error'])], [$deactivate, $delete_wf]],
    'the workflow DELETE unreachable: not retried'                         => [[resp(400, ['message' => 'x']), INACTIVE, timed_out()], [$deactivate, $delete_wf]],
    'n8n unreachable during the rollback: the deactivate stops it, no DELETE sent' => [[timed_out(), timed_out()], [$deactivate]],
] as $label => [$tail, $undo]) {
    connected(array_merge(array_slice(PRO_OK, 0, 5), $tail));
    $r = provision();
    $record = $GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION] ?? null;
    check("$label: 502, the original step (activate); stops at the workflow after " . count($undo) . ' rollback call(s), no credential deleted', $r instanceof WP_Error
        && $r->data === ['status' => 502, 'step' => 'activate'] && calls() === array_merge(CREATE_ALL, $undo) && $GLOBALS['tw']['queue'] === []);
    check('... the Application Password revoked all the same', revoked());
    check('... postquality_n8n_leftovers = {base_url, the workflow, every credential id, at}, autoload off', $record === [
        'base_url' => BASE, 'workflow_id' => 'wf1', 'credentials' => ['c-webhook', 'c-anthropic', 'c-wp'], 'at' => $record['at'] ?? null]
        && abs($record['at'] - time()) <= 5 && $GLOBALS['tw']['autoload'][PostQuality_N8n::LEFTOVERS_OPTION] === false);
    check('... the model holds exactly what the record names', on_instance() === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']);
    check('... the message says some objects are still in n8n and the next try removes them first', plain($r, 'while activating the workflow') && str_ends_with($r->message, LEFT));
    check('... nothing saved, no ping', nothing_saved());
}
connected(array_merge(array_slice(PRO_OK, 0, 4), [resp(400, ['message' => 'x']), resp(409, ['message' => 'x']), DELETED, resp(500, ['message' => 'Internal error'])]));
$r = provision();
check('no workflow, two credential DELETEs refused (HTTP): every credential tried, only the refused ones recorded', $r instanceof WP_Error && $r->data['step'] === 'workflow'
    && calls() === array_merge(array_slice(CREATE_ALL, 0, 5), UNDO_CREDS) && revoked() && str_ends_with($r->message, LEFT)
    && array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-webhook', 'c-wp']]
    && on_instance() === ['credentials/c-webhook', 'credentials/c-wp']);
connected(array_merge(array_slice(PRO_OK, 0, 4), [resp(400, ['message' => 'x']), DELETED, timed_out()]));
$r = provision();
check('no workflow, a credential DELETE unreachable: stops there, that id and the untried one recorded without a call', $r instanceof WP_Error
    && count(calls()) === 7 && calls() === array_merge(array_slice(CREATE_ALL, 0, 5), array_slice(UNDO_CREDS, 0, 2)) && $GLOBALS['tw']['queue'] === []
    && array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-anthropic', 'c-wp']]
    && on_instance() === ['credentials/c-anthropic', 'credentials/c-wp'] && str_ends_with($r->message, LEFT));
connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(400, ['message' => 'x']), INACTIVE, DELETED, resp(404, '<html>Not Found</html>'), DELETED, DELETED]));
$r = provision();
check('a credential DELETE answers a non-JSON 404 (a proxy): not counted as removed, the id recorded as left', $r instanceof WP_Error
    && calls() === array_merge(CREATE_ALL, UNDO_ALL) && $GLOBALS['tw']['queue'] === [] && str_ends_with($r->message, LEFT)
    && array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-webhook']]
    && on_instance() === ['credentials/c-webhook']);

echo "Rollback: the next try removes the leftovers first\n";
$old = [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'workflow_id' => 'wf-old', 'credentials' => ['c-old1', 'c-old2', 'c-old3'], 'at' => 1700000000]];
$cleanup = [N8N . 'workflows/wf-old/deactivate', DEL . 'workflows/wf-old', DEL . 'credentials/c-old1', DEL . 'credentials/c-old2', DEL . 'credentials/c-old3'];
connected(array_merge([MODELS_OK, INACTIVE, DELETED, DELETED, NOT_FOUND, DELETED], array_slice(PRO_OK, 1)), $old);
$r = provision();
check('removable: deactivate → DELETE workflow → DELETE credentials, before anything is created; then the normal run',
    calls() === array_merge([MODELS], $cleanup, array_slice(CREATE_ALL, 1), [READ_WF]) && rollback_ordered() && $r['provisioned'] === true && $GLOBALS['tw']['queue'] === []);
check('... the record deleted; the model holds only the new setup', !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION])
    && in_array('-' . PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true)
    && on_instance(OLD_OBJECTS) === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf1']);
connected(array_merge([MODELS_OK, INACTIVE], STAYS_UNPUBLISHING), $old);
$r = provision();
$record = $GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION] ?? null;
check('still refused (409 on all 4 DELETE calls, bug 114): 502 step cleanup, zero creations, no Application Password', $r instanceof WP_Error && $r->code === 'pqm_provision_failed'
    && $r->data === ['status' => 502, 'step' => 'cleanup'] && calls() === array_merge([MODELS, $cleanup[0]], array_fill(0, 4, $cleanup[1]))
    && $GLOBALS['tw']['queue'] === [] && $GLOBALS['tw']['app_created'] === [] && $GLOBALS['tw']['app_revoked'] === [] && nothing_saved());
check('... the record updated to what remains (all of it), a fresh `at`', array_diff_key($record, ['at' => 0]) === array_diff_key($old[PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0])
    && $record['at'] !== 1700000000 && on_instance(OLD_OBJECTS) === OLD_OBJECTS);
check('... the message says nothing was created, to try again, and the way out when it keeps happening', plain($r, 'nothing was created. Try again in a moment.')
    && str_ends_with($r->message, 'If this keeps happening, check that your n8n API key is still valid by connecting again on the n8n Workflow page, or remove the Post Quality workflow and credentials in n8n by hand.'));
connected([MODELS_OK, DELETED, timed_out()], [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'credentials' => ['c-old1', 'c-old2'], 'at' => 1700000000]]);
$r = provision();
check('credentials only, the last one unreachable: no deactivate, the record keeps the one that remains', $r instanceof WP_Error && $r->data['step'] === 'cleanup'
    && calls() === [MODELS, DEL . 'credentials/c-old1', DEL . 'credentials/c-old2']
    && array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-old2']]);
connected(PRO_OK, [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://old.example.com'] + $old[PostQuality_N8n::LEFTOVERS_OPTION]]);
$r = provision();
check('a record of another instance: dropped, nothing sent there, the normal run', calls() === [...CREATE_ALL, READ_WF] && $r['provisioned'] === true
    && !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]));
connected([resp(401, ['type' => 'error'])], $old);
$r = provision();
check('a refused provider key: the leftovers are not touched (no n8n call)', $r->code === 'pqm_provider_key_invalid' && calls() === [MODELS] && untouched());

/* ─── Bug 114: the workflow DELETE meets n8n's unpublish window ──────── */
echo "Rollback: the workflow DELETE meets n8n's unpublish window (bug 114)\n";
foreach ([
    'still unpublishing: 409, then 200'                   => [UNPUBLISHING, DELETED],
    '409 three times, the fourth and last call passes'    => [UNPUBLISHING, UNPUBLISHING, UNPUBLISHING, DELETED],
    'the other 409 (a published workflow), then 200'      => [PUBLISHED, DELETED],
] as $label => $deletes) {
    connected(array_merge(array_slice(PRO_OK, 0, 5), [resp(400, ['message' => 'x']), INACTIVE], $deletes, [DELETED, DELETED, DELETED]));
    $r = provision();
    check("$label: the workflow DELETE sent " . count($deletes) . ' times, then the credentials; the instance clean, no leftovers, "Nothing was left in n8n."',
        $r instanceof WP_Error && $r->data === ['status' => 502, 'step' => 'activate']
        && calls() === array_merge(CREATE_ALL, [$deactivate], array_fill(0, count($deletes), $delete_wf), UNDO_CREDS) && rollback_ordered() && $GLOBALS['tw']['queue'] === []
        && on_instance() === [] && !in_array(PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true) && revoked() && str_ends_with($r->message, CLEAN));
}
connected(array_merge([MODELS_OK, INACTIVE, UNPUBLISHING, DELETED, DELETED, NOT_FOUND, DELETED], array_slice(PRO_OK, 1)), $old);
$r = provision();
check('the next setup\'s cleanup the same: 409, then 200 on the old workflow, then its credentials and the normal run; the record deleted',
    calls() === array_merge([MODELS, $cleanup[0], $cleanup[1]], array_slice($cleanup, 1), array_slice(CREATE_ALL, 1), [READ_WF]) && rollback_ordered()
    && $r['provisioned'] === true && !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]) && $GLOBALS['tw']['queue'] === []);

/* ─── Bug 112: a workflow creation without an answer ─────────────────── */
echo "Provisioning: the workflow creation gets no answer (bug 112)\n";
$run_ids    = ['c-webhook', 'c-anthropic', 'c-wp'];
$orphan     = ['name' => WF_NAME, 'credentials' => $run_ids];
$to_lookup  = array_merge(array_slice(CREATE_ALL, 0, 5), [NAME_READ]);
$same_name  = listed('wf-hand', WF_NAME, HAND_NAMES);   // a hand-imported workflow: the same name, other credentials
$a_copy     = listed('wf-copy', WF_NAME . ' copy');      // a duplicate made in n8n: this run's ids, another name
$timed_out_cause = 'The n8n setup stopped while creating the workflow (n8n could not be reached: cURL error 28: timed out).';
connected(array_merge(array_slice(PRO_OK, 0, 4), [timed_out(), name_list($same_name, listed('wf1', WF_NAME), $a_copy), INACTIVE, DELETED, DELETED, DELETED, DELETED]));
$r = provision();
check('timed out, found: one lookup by the exact name sent, then the workflow n8n listed under that name with a node on this run\'s credential id rolled back with the run\'s credentials, in the forced order; signed',
    calls() === array_merge($to_lookup, UNDO_ALL) && rollback_ordered() && $GLOBALS['tw']['queue'] === [] && calls_signed(KEY));
check('... the create waited 30 s, the lookup and every other call 10 s', $GLOBALS['tw']['calls'][4][1]['timeout'] === 30 && timeouts_ok($GLOBALS['tw']['calls']));
check('... the same-name workflow on other credentials and the "… copy" untouched, nothing of the run left, no record',
    on_instance(['workflows/wf-copy', 'workflows/wf-hand', 'workflows/wf1']) === ['workflows/wf-copy', 'workflows/wf-hand']
    && !in_array(PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true));
check('... 502 step workflow, "Nothing was left in n8n. Try again in a moment.", the Application Password revoked, nothing saved, the lock released', $r instanceof WP_Error
    && $r->data === ['status' => 502, 'step' => 'workflow'] && $r->message === $timed_out_cause . ' ' . CLEAN && revoked() && nothing_saved() && lock_free());

foreach ([
    'no match: the same name on other credentials, a "… copy" of another name' => name_list($same_name, $a_copy),
    'nothing listed'           => name_list(),
    'the lookup unanswered'    => timed_out(),
    'the lookup refused (500)' => resp(500, ['message' => 'Internal error']),
] as $label => $answer) {
    connected(array_merge(array_slice(PRO_OK, 0, 4), [timed_out(), $answer, DELETED, DELETED, DELETED]));
    $r = provision();
    $record = $GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION] ?? null;
    check("timed out, not found ($label): the lookup, then the run's credentials deleted; no workflow deactivated or deleted", calls() === array_merge($to_lookup, UNDO_CREDS)
        && $GLOBALS['tw']['queue'] === [] && on_instance(['workflows/wf-copy', 'workflows/wf-hand']) === ['workflows/wf-copy', 'workflows/wf-hand']);
    check('... recorded, autoload off: {base_url, credentials: [], orphan: {the name sent, the run\'s credential ids}, at}', is_array($record)
        && array_keys($record) === ['base_url', 'credentials', 'orphan', 'at'] && array_diff_key($record, ['at' => 0]) === ['base_url' => BASE, 'credentials' => [], 'orphan' => $orphan]
        && abs($record['at'] - time()) <= 5 && $GLOBALS['tw']['autoload'][PostQuality_N8n::LEFTOVERS_OPTION] === false);
    check('... 502 step workflow: the cause, then "n8n did not answer in time and may still create the workflow: the next try removes it first. Try again in a moment."',
        $r instanceof WP_Error && $r->data === ['status' => 502, 'step' => 'workflow'] && $r->message === $timed_out_cause . ' ' . ORPHANED && revoked() && nothing_saved() && lock_free());
}
connected(array_merge(array_slice(PRO_OK, 0, 4), [timed_out(), name_list(), DELETED, resp(500, ['message' => 'Internal error']), DELETED]));
$r = provision();
check('timed out, not found, a credential DELETE refused: the record keeps that id beside the fingerprint; the same message (the next try removes both)',
    array_diff_key($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION], ['at' => 0]) === ['base_url' => BASE, 'credentials' => ['c-anthropic'], 'orphan' => $orphan]
    && $r->message === $timed_out_cause . ' ' . ORPHANED);
connected(array_merge(array_slice(PRO_OK, 0, 4), [timed_out(), timed_out(), timed_out()]));
$r = provision();
$quiet = $GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION] ?? null;
check('n8n gone quiet: the lookup and the first credential DELETE unanswered, no further call; every credential id and the fingerprint recorded',
    calls() === array_merge($to_lookup, [UNDO_CREDS[0]]) && $GLOBALS['tw']['queue'] === []
    && array_diff_key($quiet, ['at' => 0]) === ['base_url' => BASE, 'credentials' => $run_ids, 'orphan' => $orphan] && $r->message === $timed_out_cause . ' ' . ORPHANED);

echo "Provisioning: the next setup looks a fingerprint up first (bug 112)\n";
// The record the quiet run above wrote: n8n has finished that create since; the stored nodes keep the deleted credentials' ids.
connected(array_merge([MODELS_OK, name_list($same_name, listed('wf1', WF_NAME)), INACTIVE, DELETED, DELETED, DELETED, DELETED], array_slice(PRO_OK, 1, 3),
    [created('wf2'), ACTIVE, resp(200, stored_workflow('wf2', wired()))]), [PostQuality_N8n::LEFTOVERS_OPTION => $quiet]);
$r = provision();
check('found: the lookup, then that workflow removed with the record\'s other ids (deactivate, DELETE, the credentials) before anything is created; then the normal run',
    calls() === array_merge([MODELS, NAME_READ], UNDO_ALL, array_slice(CREATE_ALL, 1, 4), [N8N . 'workflows/wf2/activate', 'GET ' . BASE . '/api/v1/workflows/wf2'])
    && rollback_ordered() && $r['provisioned'] === true && $r['workflow_id'] === 'wf2' && $GLOBALS['tw']['queue'] === []);
check('... the record deleted; one workflow of that name for the run on the instance, the same-name one untouched',
    !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]) && in_array('-' . PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true)
    && on_instance(['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf-hand', 'workflows/wf1'])
        === ['credentials/c-anthropic', 'credentials/c-webhook', 'credentials/c-wp', 'workflows/wf-hand', 'workflows/wf2']);
$printed = [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => BASE, 'credentials' => ['c-old1'], 'orphan' => ['name' => WF_NAME, 'credentials' => ['c-old1', 'c-old2', 'c-old3']], 'at' => 1700000000]];
connected(array_merge([MODELS_OK, name_list($same_name, $a_copy), DELETED], array_slice(PRO_OK, 1)), $printed);
$r = provision();
check('not found (the same name on other credentials only, a copy): the fingerprint dropped, the record\'s credential removed, nothing else touched; then the normal run',
    calls() === array_merge([MODELS, NAME_READ, DEL . 'credentials/c-old1'], array_slice(CREATE_ALL, 1), [READ_WF]) && $r['provisioned'] === true
    && !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]) && $GLOBALS['tw']['queue'] === []);
$late = listed('wf-old', WF_NAME, [
    'Post Quality webhook - example.com' => ['id' => 'c-old1', 'name' => 'Post Quality webhook - example.com'],
    'Anthropic - Claude'                 => ['id' => 'c-old2', 'name' => 'Anthropic - Claude'],
    'WordPress - example.com'            => ['id' => 'c-old3', 'name' => 'WordPress - example.com'],
]);
connected(array_merge([MODELS_OK, name_list($late), INACTIVE], STAYS_UNPUBLISHING), $printed);
$r = provision();
$record = $GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION] ?? null;
check('found, its removal refused: today\'s 502 step cleanup, zero creations; the record names the found workflow by id in place of the fingerprint',
    $r instanceof WP_Error && $r->data === ['status' => 502, 'step' => 'cleanup']
    && calls() === array_merge([MODELS, NAME_READ, N8N . 'workflows/wf-old/deactivate'], array_fill(0, 4, DEL . 'workflows/wf-old'))
    && is_array($record) && array_diff_key($record, ['at' => 0]) === ['base_url' => BASE, 'workflow_id' => 'wf-old', 'credentials' => ['c-old1']]
    && $GLOBALS['tw']['app_created'] === [] && nothing_saved() && $GLOBALS['tw']['queue'] === []);
foreach (['timed out' => timed_out(), 'answered 401 (the key refused)' => UNAUTHORIZED] as $label => $answer) {
    connected([MODELS_OK, $answer], $printed);
    $r = provision();
    check("the lookup $label: today's 502 step cleanup, nothing else sent, zero creations; the record unchanged (the fingerprint kept for the next try)",
        $r instanceof WP_Error && $r->data === ['status' => 502, 'step' => 'cleanup'] && calls() === [MODELS, NAME_READ]
        && ($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION] ?? null) === $printed[PostQuality_N8n::LEFTOVERS_OPTION]
        && !in_array(PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true) && !in_array('-' . PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true)
        && $GLOBALS['tw']['app_created'] === [] && nothing_saved() && lock_free());
}
connected(PRO_OK, [PostQuality_N8n::LEFTOVERS_OPTION => ['base_url' => 'https://old.example.com'] + $printed[PostQuality_N8n::LEFTOVERS_OPTION]]);
$r = provision();
check('a fingerprint of another instance: dropped without a lookup, nothing sent there, the normal run', calls() === [...CREATE_ALL, READ_WF] && $r['provisioned'] === true
    && !isset($GLOBALS['tw']['options'][PostQuality_N8n::LEFTOVERS_OPTION]));

echo "Provisioning: the ping meets n8n's publication window (bug 80)\n";
connected(PRO_OK);
$GLOBALS['tw']['ping_queue'] = [HOOK_404];
$r = provision();
check('404 then 200: 2 pings of the saved URL, with the token; the reply ping_ok, "Ping OK"', count($GLOBALS['tw']['pings']) === 2 && $r['provisioned'] === true && $r['ping_ok'] === true
    && array_unique(array_column($GLOBALS['tw']['pings'], 0)) === [HOOK] && $GLOBALS['tw']['pings'][1][1]['headers']['X-PQM-Token'] === TOKEN && str_starts_with($r['message'], 'Ping OK'));
check('... the version recorded, no failure on record; Set up the workflow done; status_html set up; the diagnosis read once, after the passing ping',
    postquality_workflow_report()['version'] === POSTQUALITY_WORKFLOW_VERSION && !isset(postquality_workflow_report()['failed']) && ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] ?? null) === true
    && str_contains($r['status_html'], 'data-state="set-up"') && calls() === [...CREATE_ALL, READ_WF] && $GLOBALS['tw']['queue'] === []);
connected(PRO_OK);
$GLOBALS['tw']['ping_queue'] = [HOOK_404, HOOK_404];
$r = provision();
check('404, 404, 200: 3 pings, then as passed', count($GLOBALS['tw']['pings']) === 3 && $r['ping_ok'] === true && !isset(postquality_workflow_report()['failed']));

echo "Provisioning: the ping fails\n";
connected(PRO_OK);
$GLOBALS['tw']['ping_response'] = HOOK_404;
$r = provision();
check('404 three times: 3 pings, no more; 200 {provisioned, ping_ok false, message}: set up and active, the webhook did not answer yet, Test again first, the editor-address hint second',
    count($GLOBALS['tw']['pings']) === 3 && $r['provisioned'] === true && $r['ping_ok'] === false && $r['message'] === EARLY_404);
check('... everything kept: map stored, URL saved, no version recorded; the failure on record is that sentence (story 67)', isset($GLOBALS['tw']['options'][PostQuality_N8n::SETUP_OPTION])
    && $GLOBALS['tw']['options']['postquality_settings']['n8n_webhook_url'] === HOOK && postquality_workflow_report()['version'] === 0
    && (postquality_workflow_report()['failed']['message'] ?? '') === EARLY_404);
check('... the failed ping recorded, so Set up the workflow stays open (setup incomplete); status_html needs attention, the message its outcome line', ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] ?? null) === false
    && str_contains($r['status_html'], 'data-state="failing"') && str_contains($r['status_html'], '<p class="pqm-workflow-outcome is-error">' . $r['message'] . '</p>') && !postquality_setup_complete());
check('... no rollback: nothing deactivated, deleted or revoked, no leftovers recorded; the lock released', calls() === CREATE_ALL && $GLOBALS['tw']['app_revoked'] === []
    && !in_array(PostQuality_N8n::LEFTOVERS_OPTION, $GLOBALS['tw']['writes'], true) && lock_free());
foreach (['a 401' => [resp(401, ['message' => 'Authorization data is wrong!']), 'n8n refused the webhook credential (HTTP 401)'],
    'a 500' => [resp(500, ['message' => 'Workflow error']), 'n8n responded with HTTP 500'],
    'a WP_Error' => [timed_out(), 'Could not connect to n8n: cURL error 28: timed out']] as $label => [$answer, $cause]) {
    connected(PRO_OK);
    $GLOBALS['tw']['ping_response'] = $answer;
    $r = provision();
    check("$label: one ping, today's sentence (the test did not pass, its own cause, the editor-address hint); that cause on record", count($GLOBALS['tw']['pings']) === 1 && $r['ping_ok'] === false
        && str_starts_with($r['message'], 'Your workflow is set up and active in n8n, but the test did not pass. ' . $cause) && !str_contains($r['message'], 'did not answer yet')
        && str_ends_with($r['message'], 'paste it as the n8n Webhook URL.') && str_starts_with(postquality_workflow_report()['failed']['message'] ?? '', $cause));
}
/* Story 2: the ping got through and its callback failed — the address was right, so the sentence carries no editor-address hint */
foreach (['refused' => 'n8n reached this site, but the callback was refused: it carried a token that is not this site\'s webhook token. In n8n, open the POST Result to WordPress node and set its X-PQM-Token header to {{ $json.auth_token }}, then test again.',
    'none' => 'n8n accepted the ping but did not call this site back within 0 seconds. n8n must reach https://example.com/wp-json/ (this site\'s REST API): a local, private or password-protected site cannot be called back. Check the ping\'s execution in n8n, then test again.'] as $callback => $cause) {
    connected(PRO_OK);
    $GLOBALS['tw']['ping_callback'] = $callback;
    $r = provision();
    check("the callback $callback: one ping, ping_ok false, 'the test did not pass' + the callback's own sentence, no editor-address hint; that cause on record, Set up the workflow open", count($GLOBALS['tw']['pings']) === 1
        && $r['provisioned'] === true && $r['ping_ok'] === false && $r['message'] === 'Your workflow is set up and active in n8n, but the test did not pass. ' . $cause
        && (postquality_workflow_report()['failed']['message'] ?? '') === $cause && ($GLOBALS['tw']['options'][POSTQUALITY_SETUP_OPTION]['steps']['connect'] ?? null) === false
        && str_contains($r['status_html'], 'data-state="failing"'));
}
$GLOBALS['tw']['ping_callback'] = 'received';
check('the retry wait is the filter postquality_provision_ping_retry_wait, 2 s by default, in the REST callbacks\' ping_after_activation() only, never in the Test button\'s ping (class-pqm-admin.php); which callbacks retry is n8n-managed\'s rows (bug 84)',
    (bool) preg_match('/sleep\\( max\\( 0, \\(int\\) apply_filters\\( \'postquality_provision_ping_retry_wait\', 2 \\) \\) \\);/', file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-rest-api.php'))
    && !preg_match('/postquality_provision_ping_retry_wait|(?<!u)sleep\\(/', file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/admin/class-pqm-admin.php'))); // usleep() is the ping's callback wait (story 2), not a retry

echo "Provisioning: bugs 112 and 114 across every run, and in the source\n";
check('after every run (' . count($provision_runs) . '): the workflow creation waited 30 s, every other n8n call 10 s',
    !array_filter($provision_runs, static fn($run) => $run[1] !== [] && !timeouts_ok($run[1])));
check('... no run ever sent GET /credentials (no credential lookup by name)', !array_filter($provision_runs,
    static fn($run) => array_filter($run[1], static fn($c) => $c[1]['method'] === 'GET' && str_contains($c[0], '/api/v1/credentials'))));
check('... no run sent a second POST /workflows (a create is never retried)', !array_filter($provision_runs,
    static fn($run) => count(array_filter($run[1], static fn($c) => $c[1]['method'] === 'POST' && str_ends_with($c[0], '/api/v1/workflows'))) > 1));
$src = file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/includes/class-pqm-n8n.php');
preg_match('/function orphan_id\(.*?\n    }\n/s', $src, $lookup);
preg_match('/function roll_back\(.*?\n    }\n/s', $src, $rollback);
check('the lookup (source): one GET of /workflows?name=<the name sent>&limit=250&excludePinnedData=true through request(); a workflow counts by its exact name and a node\'s credential id, never the name alone',
    ($lookup[0] ?? '') !== '' && substr_count($lookup[0], 'self::request(') === 1
    && str_contains($lookup[0], "self::request( 'GET', '/workflows?name=' . rawurlencode( \$name ) . '&limit=250&excludePinnedData=true' )")
    && str_contains($lookup[0], "\$name !== ( \$workflow['name'] ?? null )") && str_contains($lookup[0], "in_array( (string) \$credential['id'], \$ids, true )")
    && !preg_match("#'GET',\\s*'/credentials#", $src));
check('the unpublish wait (source): the filter postquality_unpublish_retry_wait, 1 s by default, the client\'s one sleep(), in roll_back(); UNPUBLISH_DELETES = 4, CREATE_TIMEOUT = 30',
    substr_count($src, 'sleep(') === 1 && str_contains($rollback[0] ?? '', "sleep( max( 0, (int) apply_filters( 'postquality_unpublish_retry_wait', 1 ) ) );")
    && str_contains($rollback[0], '409 !== $deleted[\'code\']') && PostQuality_N8n::UNPUBLISH_DELETES === 4 && PostQuality_N8n::CREATE_TIMEOUT === 30
    && str_contains($src, "self::request( 'POST', '/workflows', \$body, self::CREATE_TIMEOUT )"));

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
site([PostQuality_N8n::SETUP_OPTION => pro_map(1)]);
$GLOBALS['tw']['app_revoked'] = [];
PostQuality_N8n::revoke_setup_password__premium_only();
check('a map with application_password: delete_application_password(user_id, uuid), once; the map untouched', $GLOBALS['tw']['app_revoked'] === [[7, 'app-uuid']] && $GLOBALS['tw']['writes'] === []);
foreach (['a map without the key (an earlier version, or adopted)' => [PostQuality_N8n::SETUP_OPTION => array_diff_key(pro_map(1), ['application_password' => 0])], 'no map' => []] as $label => $options) {
    site($options);
    $GLOBALS['tw']['app_revoked'] = [];
    PostQuality_N8n::revoke_setup_password__premium_only();
    check("$label: no call", $GLOBALS['tw']['app_revoked'] === [] && $GLOBALS['tw']['writes'] === []);
}

key_leak_scan($replies);

harness_exit();
