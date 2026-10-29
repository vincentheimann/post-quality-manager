<?php
/**
 * Template failure harness for bug 31 — every row of the plan's I/O &
 * Edge-Case Matrix proven by running the real node code of
 * post-quality-manager/templates/n8n-workflow.json through `node`:
 * each Code node's jsCode runs with stubbed `$input.first().json` and
 * `$('<Node>').first().json`, then the POST Result to WordPress body
 * expression is evaluated on the resulting item. Needs Node.js on PATH;
 * fails loudly without it. No n8n, no WP install.
 *
 * Bug 97: the notification branch's expressions are evaluated with $json =
 * WordPress's /save-review reply and Result Ready stubbed with the item, on
 * both templates (the Free one is derived from this one).
 */

require __DIR__ . '/lib/wp-stubs.php'; // the runner (check(), harness_exit()); no plugin code is loaded here

$tpl = json_decode(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow.json'), true);
if (!is_array($tpl)) { fwrite(STDERR, "FATAL: template is not valid JSON\n"); exit(1); }
$NODES = array_column($tpl['nodes'], null, 'name');

/* ─── node runner ────────────────────────────────────────────────────── */
exec('node --version 2>&1', $out, $code);
if ($code !== 0) { fwrite(STDERR, "FATAL: `node` is not on PATH; this harness runs the template's Code nodes through Node.js.\n"); exit(1); }

$runner = sys_get_temp_dir() . '/pqm-n8n-runner-' . getmypid() . '.js';
file_put_contents($runner, <<<'JS'
const spec = JSON.parse(require('fs').readFileSync(0, 'utf8'));
const AsyncFunction = (async () => {}).constructor;
const $input = { first: () => ({ json: spec.input }) };
const $ = (name) => {
  if (!(name in (spec.nodes || {}))) throw new Error('unstubbed node reference: ' + name);
  return { first: () => ({ json: spec.nodes[name] }) };
};
const fn = 'code' in spec
  ? new AsyncFunction('$input', '$', spec.code)
  : new AsyncFunction('$json', '$', 'return (' + spec.expr + ');');
fn('code' in spec ? $input : spec.input, $)
  .then((r) => process.stdout.write(JSON.stringify({ ok: r === undefined ? null : r })))
  .catch((e) => process.stdout.write(JSON.stringify({ fail: String(e && e.stack || e) })));
JS);
register_shutdown_function(fn() => @unlink($runner));

function run_node(array $spec) {
    global $runner;
    $p = proc_open(['node', $runner], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fwrite($pipes[0], json_encode($spec));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    proc_close($p);
    $res = json_decode($stdout, true);
    if (!is_array($res) || isset($res['fail'])) {
        fwrite(STDERR, "FATAL: node run failed\n" . ($res['fail'] ?? $stdout . $stderr) . "\n");
        exit(1);
    }
    return $res['ok'];
}

/** Runs a Code node and returns the json of its first output item. */
function run_code(string $node, array $input, array $refs = []): array {
    global $NODES;
    $items = run_node(['code' => $NODES[$node]['parameters']['jsCode'], 'input' => $input, 'nodes' => (object) $refs]);
    return $items[0]['json'];
}

/** Evaluates an n8n `={{ … }}` expression against $json, with `$('<Node>').first().json` stubbed by $refs. */
function run_expr(string $expr, array $json, array $refs = []) {
    if (!preg_match('/^=\{\{(.*)\}\}$/s', $expr, $m)) { fwrite(STDERR, "FATAL: not a single n8n expression: $expr\n"); exit(1); }
    return run_node(['expr' => trim($m[1]), 'input' => $json, 'nodes' => (object) $refs]);
}

/** The body POST Result to WordPress sends for an item (decoded). */
function post_body(array $item): array {
    global $NODES;
    return json_decode(run_expr($NODES['POST Result to WordPress']['parameters']['body'], $item), true);
}

/** Today's (v2) body expression, to prove completing runs post the same review fields. */
const V2_BODY = "={{ JSON.stringify({ post_id: \$json.post_id, command: \$json.command, overall_score: \$json.overall_score || 0, status: \$json.status || '', result: JSON.parse(\$json.result || '{}'), workflow_version: \$json.workflow_version || 0 }) }}";

/** Which node an IF output index leads to. */
function if_target(string $if, int $output): ?string {
    global $tpl;
    return $tpl['connections'][$if]['main'][$output][0]['node'] ?? null;
}

/* ─── Parse Input item, produced by the real Parse Input node ────────── */
function parsed_input(string $command, array $extra = []): array {
    return run_code('Parse Input', ['body' => $extra + [
        'command'      => $command,
        'post_id'      => 7,
        'callback_url' => 'https://example.test/wp-json/post-quality/v1/save-review',
        'auth_token'   => 'token',
        'title'        => 'A post title',
        'link'         => 'https://example.test/a-post-title/',
        'post_rest_url' => 'http://example.test:8080/blog/?rest_route=/wp/v2/pages/7',
        'content'      => 'Body text.',
    ]]);
}

echo "Parse Input\n";
$base = parsed_input('review');
check('stamps workflow_version 8', $base['workflow_version'] === 8 && $base['error'] === false);
check('passes post_rest_url through unchanged (story 35)', $base['post_rest_url'] === 'http://example.test:8080/blog/?rest_route=/wp/v2/pages/7');

/* Row (story 2): the test ping — Parse Input → Ping OK → the body POST Result to WordPress sends back */
echo "Round-trip ping\n";
$ping_url = 'https://example.test/wp-json/post-quality/v1/ping/abcdefghij0123456789';
$ping = run_code('Parse Input', ['body' => ['command' => 'ping', 'source' => 'pqm-test-connection', 'callback_url' => $ping_url, 'auth_token' => 'token']]);
check('Parse Input: the ping item carries the callback address and token, stamps 8, no post, no notification',
    $ping['command'] === 'ping' && $ping['ping'] === true && $ping['error'] === false && $ping['callback_url'] === $ping_url && $ping['auth_token'] === 'token'
    && $ping['notification_url'] === '' && $ping['workflow_version'] === 8 && !array_key_exists('post_id', $ping));
$ok = run_code('Ping OK', $ping);
check('Ping OK passes the item through with score 0, status \'\', result \'{}\' (no error)', $ok['callback_url'] === $ping_url && $ok['auth_token'] === 'token'
    && $ok['overall_score'] === 0 && $ok['status'] === '' && $ok['result'] === '{}' && $ok['error'] === false && $ok['workflow_version'] === 8);
check('Ping OK → Result Ready → POST Result to WordPress, the same connections every review takes',
    if_target('Ping OK', 0) === 'Result Ready' && if_target('Result Ready', 0) === 'POST Result to WordPress');
check('the callback body: command ping, no post_id, score 0, status \'\', result {}, version 8, error_message \'\'',
    post_body($ok) === ['command' => 'ping', 'overall_score' => 0, 'status' => '', 'result' => [], 'workflow_version' => 8, 'error_message' => '']);
$post_node = $NODES['POST Result to WordPress']['parameters'];
check('the callback node reads its address and X-PQM-Token header from the item: /ping/{id} and the site\'s token', run_expr($post_node['url'], $ok) === $ping_url
    && run_expr(array_column($post_node['headerParameters']['parameters'], 'value', 'name')['X-PQM-Token'], $ok) === 'token');
check('the item ends there: notification_url is \'\', so Has Notification URL? sends none', ($ok['notification_url'] ?? null) === '');

$agents = [
    'Review'    => 'Parse Review Result',
    'Takeaways' => 'Parse Takeaways Result',
    'Sources'   => 'Parse Sources Result',
    'SEO'       => 'Parse SEO Result',
    'Audit'     => 'Parse Audit Result',
    'Rewrite'   => 'Parse Rewrite Response',
];

/** Node references a Parse node reads: Parse Input and its Build node (a built request unless $built is given). */
function parse_refs(array $base, string $parse, ?array $built = null): array {
    global $agents;
    return ['Parse Input' => $base, 'Build ' . array_search($parse, $agents, true) . ' Body' => $built ?? $base + ['claudeBody' => '{}']];
}

/* Row: Build node failure (empty post) — the Claude call then fails too; the Build reason wins */
echo "Build node failure\n";
$empty_post = ['error' => true, 'message' => 'Post not found or empty.'];
foreach ($agents as $agent => $parse) {
    $item = run_code($parse, ['error' => ['message' => 'Bad request - please check your parameters']], parse_refs($base, $parse, $empty_post));
    check("$parse: message is the Build node's reason", $item['error'] === true && $item['message'] === "$agent Agent: Post not found or empty."
        && post_body($item)['error_message'] === "$agent Agent: Post not found or empty.");
}

/* Row: model API error — n8n "continue on error" item carries json.error */
echo "Model API error\n";
$api_error = ['error' => [
    'message'     => 'Bad request - please check your parameters',
    'description' => 'Your credit balance is too low to access the Anthropic API.',
    'httpCode'    => '400',
]];
foreach ($agents as $agent => $parse) {
    $item = run_code($parse, $api_error, parse_refs($base, $parse));
    $want = "$agent Agent could not get an answer from the model: Your credit balance is too low to access the Anthropic API.";
    $body = post_body($item);
    check("$parse: error true, message names the agent and the API text", $item['error'] === true && $item['message'] === $want);
    check("$parse: POST body error_message = message, status '', result {}",
        $body['error_message'] === $want && $body['status'] === '' && $body['result'] === [] && $body['post_id'] === 7 && $body['workflow_version'] === 8);
}
$item = run_code('Parse Review Result', ['error' => ['message' => 'connect ECONNREFUSED']], parse_refs($base, 'Parse Review Result'));
check('no description → falls back to the error message', $item['message'] === 'Review Agent could not get an answer from the model: connect ECONNREFUSED');
$item = run_code('Parse Review Result', ['error' => 'timeout of 120000ms exceeded'], parse_refs($base, 'Parse Review Result'));
check('string error → used as is', $item['message'] === 'Review Agent could not get an answer from the model: timeout of 120000ms exceeded');
$status_code_error = ['error' => [
    'name'       => 'StatusCodeError',
    'statusCode' => 400,
    'message'    => '400 - "{\"type\":\"error\",\"error\":{\"type\":\"invalid_request_error\",\"message\":\"Your credit balance is too low to access the Anthropic API.\"}}"',
    'error'      => ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low to access the Anthropic API.']],
]];
foreach ($agents as $agent => $parse) {
    $item = run_code($parse, $status_code_error, parse_refs($base, $parse));
    check("$parse: StatusCodeError → the provider's plain text", $item['message'] === "$agent Agent could not get an answer from the model: Your credit balance is too low to access the Anthropic API.");
}
$item = run_code('Parse Review Result', ['error' => ['message' => '502 - "Bad Gateway"', 'error' => 'Bad Gateway']], parse_refs($base, 'Parse Review Result'));
check('StatusCodeError with a string body → the body as is', $item['message'] === 'Review Agent could not get an answer from the model: Bad Gateway');

/* Row: unparseable output — 2xx but not JSON / no delimiter */
echo "Unparseable output\n";
$garbage = ['content' => [['type' => 'text', 'text' => 'Sorry, I cannot help with that.']]];
foreach ($agents as $agent => $parse) {
    $item = run_code($parse, $garbage, parse_refs($base, $parse));
    $want = $agent === 'Rewrite' ? 'Rewrite Agent: could not find CONTENT_HTML delimiter.' : "$agent Agent returned invalid JSON.";
    check("$parse: today's message, forwarded as error_message", $item['error'] === true && $item['message'] === $want && post_body($item)['error_message'] === $want);
}

/* Row: rewrite parse failure — Rewrite Parsed? sends it away from Update WP Post */
echo "Rewrite parse failure\n";
$rw_base = parsed_input('rewrite');
$failed  = run_code('Parse Rewrite Response', $garbage, parse_refs($rw_base, 'Parse Rewrite Response'));
$cond    = $NODES['Rewrite Parsed?']['parameters']['conditions']['conditions'][0];
check('Rewrite Parsed? tests a boolean "is true"', $cond['operator']['type'] === 'boolean' && $cond['operator']['operation'] === 'true');
check('Rewrite Parsed? on error: true → false', run_expr($cond['leftValue'], $failed) === false);
check('false output → Format Rewrite Confirmation', if_target('Rewrite Parsed?', 1) === 'Format Rewrite Confirmation');
$out = run_code('Format Rewrite Confirmation', $failed, ['Parse Rewrite Response' => $failed]);
check('Format Rewrite Confirmation keeps it a failure', $out['error'] === true && post_body($out)['error_message'] === 'Rewrite Agent: could not find CONTENT_HTML delimiter.');

$good_rw = ['content' => [['type' => 'text', 'text' => "<<<CONTENT_HTML>>><p>New body.</p><<<END_CONTENT_HTML>>>\n<<<EXCERPT>>>New excerpt.<<<END_EXCERPT>>>"]]];
$parsed  = run_code('Parse Rewrite Response', $good_rw, parse_refs($rw_base, 'Parse Rewrite Response'));
check('Rewrite Parsed? on a parsed rewrite → true', $parsed['error'] === false && run_expr($cond['leftValue'], $parsed) === true);
check('true output → Update WP Post (Rewrite)', if_target('Rewrite Parsed?', 0) === 'Update WP Post (Rewrite)');

/* Row: rewrite save failure — Update WP Post returns json.error (or code / data.status) */
echo "Rewrite save failure\n";
$out = run_code('Format Rewrite Confirmation',
    ['error' => ['message' => 'Forbidden - perhaps check your credentials?', 'description' => 'Sorry, you are not allowed to edit this post.']],
    ['Parse Rewrite Response' => $parsed]);
check('wp.error → failure with the save reason', $out['error'] === true && $out['message'] === 'Could not save the rewrite to WordPress: Sorry, you are not allowed to edit this post.');
check('not reported as rewritten', post_body($out)['status'] === '' && post_body($out)['error_message'] === $out['message']);
$out = run_code('Format Rewrite Confirmation',
    ['code' => 'rest_cannot_edit', 'message' => 'Sorry, you are not allowed to edit this post.', 'data' => ['status' => 403]],
    ['Parse Rewrite Response' => $parsed]);
check('wp.code body → failure with the WP message', $out['error'] === true && $out['message'] === 'Could not save the rewrite to WordPress: Sorry, you are not allowed to edit this post.');
$out = run_code('Format Rewrite Confirmation',
    ['error' => [
        'name'       => 'StatusCodeError',
        'statusCode' => 403,
        'message'    => '403 - "{\"code\":\"rest_cannot_edit\",\"message\":\"Sorry, you are not allowed to edit this post.\",\"data\":{\"status\":403}}"',
        'error'      => ['code' => 'rest_cannot_edit', 'message' => 'Sorry, you are not allowed to edit this post.', 'data' => ['status' => 403]],
    ]],
    ['Parse Rewrite Response' => $parsed]);
check('StatusCodeError (WP body under error.error) → the WP plain text', $out['error'] === true && $out['message'] === 'Could not save the rewrite to WordPress: Sorry, you are not allowed to edit this post.');

/* Row: Format Score on an empty post — the failure item keeps post_id/callback so it can be stored */
echo "Score, post not found\n";
$no_title = parsed_input('score');
$no_title['title'] = '';
$out  = run_code('Format Score', $no_title);
$body = post_body($out);
check('POST body carries post_id 7 and error_message "Post not found."', $out['callback_url'] !== '' && $body['post_id'] === 7 && $body['command'] === 'score' && $body['error_message'] === 'Post not found.');

/* Row: unhandled command — Error Fallback names it */
echo "Unhandled command\n";
$apply = parsed_input('apply');
$out   = run_code('Error Fallback', $apply);
$body  = post_body($out);
check('Error Fallback: error true, message names the command, no download advice',
    $out['error'] === true && $out['message'] === 'This workflow has no step for the "apply" command.');
check('POST body: error_message = message, command apply, post_id kept',
    $body['error_message'] === $out['message'] && $body['command'] === 'apply' && $body['post_id'] === 7 && $body['status'] === '');

/* Row: success — body adds error_message '' only */
echo "Success\n";
$review_json = json_encode(['overall_score' => 72, 'status' => 'ship', 'structure' => ['score' => 80], 'readability' => ['score' => 70], 'engagement' => ['score' => 65], 'credibility' => ['score' => 75], 'top_edits' => ['a', 'b', 'c']]);
$successes = [
    'review'  => run_code('Parse Review Result', ['content' => [['type' => 'text', 'text' => $review_json]]], parse_refs($base, 'Parse Review Result')),
    'rewrite' => run_code('Format Rewrite Confirmation', ['id' => 7, 'status' => 'pending'], ['Parse Rewrite Response' => $parsed]),
    'score'   => run_code('Format Score', parsed_input('score')),
];
foreach ($successes as $cmd => $item) {
    $body = post_body($item);
    $v2   = json_decode(run_expr(V2_BODY, $item), true);
    check("$cmd: error_message is ''", $body['error_message'] === '');
    unset($body['error_message']);
    check("$cmd: every other field identical to the v2 body", $body === $v2 && $v2['status'] !== '');
}
check('rewrite success still stored as rewritten', $successes['rewrite']['status'] === 'rewritten');

/* ─── Bug 97: the notification branch reads the item at Result Ready, not WordPress's reply ── */
const RESULT_NODES = ['Format Score', 'Parse Review Result', 'Parse Takeaways Result', 'Parse Sources Result', 'Parse SEO Result',
    'Parse Audit Result', 'Format Rewrite Confirmation', 'Error Fallback', 'Ping OK'];

/** The nodes whose outputs lead into $node. */
function feeders(array $t, string $node): array {
    $from = [];
    foreach ($t['connections'] as $name => $c) {
        foreach ($c['main'] as $targets) {
            foreach ($targets as $to) { if ($to['node'] === $node) { $from[] = $name; } }
        }
    }
    return $from;
}

/** What the notification branch reads once POST Result to WordPress answered $reply (its $json), with Result Ready holding $item. */
function notification(array $t, array $item, array $reply): array {
    $nodes = array_column($t['nodes'], null, 'name');
    $refs  = ['Result Ready' => $item];
    $send  = $nodes['Send Notification']['parameters'];
    return [
        'if'   => run_expr($nodes['Has Notification URL?']['parameters']['conditions']['conditions'][0]['leftValue'], $reply, $refs),
        'url'  => run_expr($send['url'], $reply, $refs),
        'text' => run_expr(array_column($send['bodyParameters']['parameters'], 'value', 'name')['text'], $reply, $refs),
    ];
}

$hook    = 'https://chat.example.test/hooks/abc';
$saved   = ['success' => true, 'id' => 12, 'message' => 'Review saved for post 7 (score).']; // the /save-review reply
$refused = ['error' => ['message' => 'Forbidden - perhaps check your credentials?', 'description' => 'Sorry, you are not allowed to do that.', 'httpCode' => '403']];
$review_base = parsed_input('review', ['notification_url' => $hook]);
$score_item  = run_code('Format Score', parsed_input('score', ['notification_url' => $hook]));
$review_item = run_code('Parse Review Result', ['content' => [['type' => 'text', 'text' => $review_json]]], parse_refs($review_base, 'Parse Review Result'));
$no_post     = run_code('Format Score', ['title' => ''] + parsed_input('score', ['notification_url' => $hook]));
$api_failed  = run_code('Parse Review Result', $api_error, parse_refs($review_base, 'Parse Review Result'));
$bad_json    = run_code('Parse Review Result', $garbage, parse_refs($review_base, 'Parse Review Result'));
$fallback    = run_code('Error Fallback', parsed_input('apply', ['notification_url' => $hook]));
$no_address  = run_code('Format Score', parsed_input('score'));
$rw_failed_in   = run_code('Parse Rewrite Response', $garbage, parse_refs(parsed_input('rewrite', ['notification_url' => $hook]), 'Parse Rewrite Response'));
$rewrite_failed = run_code('Format Rewrite Confirmation', $rw_failed_in, ['Parse Rewrite Response' => $rw_failed_in]);
check('the items under test: two reports with a chat_message, three errors without one, the fallback with its own',
    str_starts_with($score_item['chat_message'], '### 📊 Post Score Report') && str_starts_with($review_item['chat_message'], '### ✍️ Content Review Report')
    && !isset($no_post['chat_message']) && !isset($api_failed['chat_message']) && !isset($bad_json['chat_message'])
    && $fallback['chat_message'] === '❌ ' . $fallback['message'] && $no_address['notification_url'] === '');

$variants = ['Pro' => $tpl, 'Free' => json_decode(file_get_contents(dirname(__DIR__, 2) . '/post-quality-manager/templates/n8n-workflow-free.json'), true)];
foreach ($variants as $variant => $t) {
    echo "Notification ($variant template)\n";
    $nodes = array_column($t['nodes'], null, 'name');
    $want  = array_values(array_intersect(RESULT_NODES, array_keys($nodes)));
    $got   = feeders($t, 'Result Ready');
    sort($want);
    sort($got);
    check("Result Ready is a No Operation node, pqm-node-112", ($nodes['Result Ready']['type'] ?? null) === 'n8n-nodes-base.noOp' && ($nodes['Result Ready']['id'] ?? null) === 'pqm-node-112');
    check('every one of the ' . count($want) . ' result nodes feeds Result Ready', $got === $want && count($want) === ($variant === 'Pro' ? 9 : 5));
    check('Result Ready alone feeds POST Result to WordPress, which feeds Has Notification URL?, whose true output is Send Notification',
        feeders($t, 'POST Result to WordPress') === ['Result Ready']
        && ($t['connections']['Result Ready']['main'] ?? null) === [[['node' => 'POST Result to WordPress', 'type' => 'main', 'index' => 0]]]
        && feeders($t, 'Has Notification URL?') === ['POST Result to WordPress']
        && ($t['connections']['Has Notification URL?']['main'][0][0]['node'] ?? null) === 'Send Notification');
    check('a refused save still reaches the IF: POST Result to WordPress keeps onError continueRegularOutput',
        ($nodes['POST Result to WordPress']['onError'] ?? null) === 'continueRegularOutput');
    $cond = $nodes['Has Notification URL?']['parameters']['conditions']['conditions'][0];
    check('Has Notification URL? is true when its value is not \'\'', $cond['operator'] === ['type' => 'string', 'operation' => 'notEquals'] && $cond['rightValue'] === '');

    foreach (['score' => $score_item, 'review' => $review_item] as $cmd => $item) {
        check("$cmd report: the IF and the POST read the item's address, the text is its chat_message",
            notification($t, $item, $saved) === ['if' => $hook, 'url' => $hook, 'text' => $item['chat_message']]);
    }
    foreach (['score, post not found' => $no_post, 'review, model error' => $api_failed, 'review, invalid JSON' => $bad_json] as $case => $item) {
        check("$case: no chat_message, so the text is '❌ ' + message", notification($t, $item, $saved) === ['if' => $hook, 'url' => $hook, 'text' => '❌ ' . $item['message']]);
    }
    check('unknown command: Error Fallback\'s own chat_message', notification($t, $fallback, $saved) === ['if' => $hook, 'url' => $hook, 'text' => $fallback['chat_message']]);
    if ($variant === 'Pro') {
        check('rewrite error: Format Rewrite Confirmation\'s chat_message is \'❌ \' + message', notification($t, $rewrite_failed, $saved) === ['if' => $hook, 'url' => $hook, 'text' => '❌ ' . $rewrite_failed['message']]);
    }
    check('no address: the IF reads \'\', so nothing is posted', notification($t, $no_address, $saved)['if'] === '');
    check('ping: the IF reads \'\', so nothing is posted', notification($t, $ok, $saved)['if'] === '');
    check('save refused (the 4xx error item goes on): the report still goes to the address',
        notification($t, $score_item, $refused) === ['if' => $hook, 'url' => $hook, 'text' => $score_item['chat_message']]);
}

harness_exit();
