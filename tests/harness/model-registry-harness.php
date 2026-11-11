<?php
/**
 * CLI harness — story 101 "The AI Model section offers the provider's
 * current models, and the registry follows their life cycle".
 * Part 1: the plan's I/O & Edge-Case Matrix against postquality_llm_params(),
 * the defaults and sanitize_settings() (the selects' render rows live in
 * render-matrix-harness.php). Part 2: every listed model's request fragment,
 * for every agent and every stored effort, against a table of the
 * provider's documented request rules (API version 2023-06-01, read
 * 2026-11-04), written here apart from the registry so a wrong registry
 * entry fails.
 * Run: php model-registry-harness.php
 */

/* ─── Harness-specific WP stubs (the shared world is lib/wp-stubs.php) ── */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['pqm_test_caps'] = [];
$GLOBALS['tw'] = ['options' => []];

function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/post-quality-manager/'; }
function is_admin() { return false; }
function apply_filters($tag, $value, ...$a) { return $value; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['pqm_test_caps'], true); }
function add_settings_error(...$a) {}

require __DIR__ . '/lib/wp-stubs.php'; // runner, WP classes, the common stubs, fs-stub.php
require dirname(__DIR__, 2) . '/post-quality-manager/post-quality-manager.php';

/* ─── Helpers ────────────────────────────────────────────────────────── */
$EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];
$BUDGET  = postquality_agent_max_tokens(); // the Pro build: all six agents
$RESERVE = POSTQUALITY_THINKING_RESERVE;
$CURRENT = ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'];
$EARLIER = ['claude-opus-4-8', 'claude-sonnet-5', 'claude-sonnet-4-6', 'claude-haiku-4-5'];

/** Settings with $model as the default model and $effort stored for $agent. */
function with_model(string $model, string $agent, string $effort, float $temperature = 0.3): array {
    $s = postquality_default_settings();
    $s['default_model'] = $model;
    $s['agent_prompts'][$agent]['effort'] = $effort;
    $s['agent_prompts'][$agent]['temperature'] = $temperature;
    return $s;
}
function fragment(string $model, string $agent, string $effort, float $temperature = 0.3): array {
    return postquality_llm_params($agent, with_model($model, $agent, $effort, $temperature));
}

/**
 * Today's fragment for the four earlier models: postquality_llm_params() and
 * their registry entries as they were at 4337d6f (before story 101),
 * transcribed. The matrix row "Earlier models unchanged" compares with it.
 */
function baseline_fragment(string $model, string $agent, array $settings): array {
    $caps = [
        'claude-opus-4-8'   => [false, ['low', 'medium', 'high', 'xhigh', 'max'], null],
        'claude-sonnet-5'   => [false, ['low', 'medium', 'high', 'xhigh', 'max'], 'disabled'],
        'claude-sonnet-4-6' => [true,  ['low', 'medium', 'high', 'max'], null],
        'claude-haiku-4-5'  => [true,  [], null],
    ][$model];
    [$temperature, $levels, $thinking] = $caps;
    $a = $settings['agent_prompts'][$agent] ?? [];
    $params = ['model' => $model, 'max_tokens' => postquality_agent_max_tokens()[$agent] ?? 2000];
    if ($temperature) {
        $params['temperature'] = round((float) ($a['temperature'] ?? 0), 1);
    }
    if ($levels) {
        $effort = $a['effort'] ?? 'high';
        if (!in_array($effort, $levels, true)) {
            $effort = 'high';
        }
        $params['output_config'] = ['effort' => $effort];
    }
    if ($thinking === 'disabled') {
        $params['thinking'] = ['type' => 'disabled'];
    }
    return $params;
}

/* ─── Part 1: the plan's I/O & Edge-Case Matrix ─────────────────────── */
echo "\nRow 1: Sonnet 5.5, effort ≤ high\n";
check('review at high: between_tools, no temperature, effort high, the agent budget',
    fragment('claude-sonnet-5-5', 'review', 'high') === [
        'model' => 'claude-sonnet-5-5', 'max_tokens' => 2500,
        'output_config' => ['effort' => 'high'], 'thinking' => ['type' => 'between_tools'],
    ]);
$ok = true;
foreach ($BUDGET as $agent => $budget) {
    foreach (['low', 'medium', 'high'] as $effort) {
        $ok = $ok && fragment('claude-sonnet-5-5', $agent, $effort) === [
            'model' => 'claude-sonnet-5-5', 'max_tokens' => $budget,
            'output_config' => ['effort' => $effort], 'thinking' => ['type' => 'between_tools'],
        ];
    }
}
check('every agent at low, medium, high: the same shape', $ok);

echo "\nRow 2: Haiku 5.5, effort ≤ high\n";
check('takeaways at medium: disabled, no temperature, effort medium, the agent budget',
    fragment('claude-haiku-5-5', 'takeaways', 'medium') === [
        'model' => 'claude-haiku-5-5', 'max_tokens' => 1000,
        'output_config' => ['effort' => 'medium'], 'thinking' => ['type' => 'disabled'],
    ]);
$ok = true;
foreach ($BUDGET as $agent => $budget) {
    foreach (['low', 'medium', 'high'] as $effort) {
        $ok = $ok && fragment('claude-haiku-5-5', $agent, $effort) === [
            'model' => 'claude-haiku-5-5', 'max_tokens' => $budget,
            'output_config' => ['effort' => $effort], 'thinking' => ['type' => 'disabled'],
        ];
    }
}
check('every agent at low, medium, high: the same shape', $ok);

echo "\nRow 3: a 5.5 model that must think\n";
foreach (['claude-opus-5-5' => $EFFORTS, 'claude-sonnet-5-5' => ['xhigh', 'max'], 'claude-haiku-5-5' => ['xhigh', 'max']] as $model => $efforts) {
    $ok = true;
    foreach ($BUDGET as $agent => $budget) {
        foreach ($efforts as $effort) {
            $ok = $ok && fragment($model, $agent, $effort) === [
                'model' => $model, 'max_tokens' => $budget + $RESERVE, 'output_config' => ['effort' => $effort],
            ];
        }
    }
    check("$model at " . implode(', ', $efforts) . ': no thinking key, no temperature, budget + the reserve, every agent', $ok);
}
check('the reserve is 12,000 tokens', $RESERVE === 12000);
check('the agent budgets (Change Log 1): review 2,500, takeaways 1,000, sources 3,000, seo 1,000, audit 8,000, rewrite 12,000',
    $BUDGET === ['review' => 2500, 'takeaways' => 1000, 'sources' => 3000, 'seo' => 1000, 'audit' => 8000, 'rewrite' => 12000]);
check('Opus 5.5 Rewrite, the largest request, asks for 24,000 output tokens (bounded only by its node timeout)',
    fragment('claude-opus-5-5', 'rewrite', 'high')['max_tokens'] === 24000);

echo "\nRow 4: earlier models unchanged\n";
foreach ($EARLIER as $model) {
    $ok = true;
    foreach ($BUDGET as $agent => $budget) {
        foreach (array_merge($EFFORTS, ['bogus']) as $effort) {
            foreach ([0.0, 0.3, 0.7] as $temperature) {
                $s = with_model($model, $agent, $effort, $temperature);
                $ok = $ok && postquality_llm_params($agent, $s) === baseline_fragment($model, $agent, $s);
            }
        }
    }
    check("$model: today's fragment, every agent × effort × temperature", $ok);
}
check('Sonnet 5 at max: thinking disabled (accepted at every effort)',
    fragment('claude-sonnet-5', 'review', 'max')['thinking'] === ['type' => 'disabled']);
check('Sonnet 4.6: temperature sent, xhigh falls back to high',
    fragment('claude-sonnet-4-6', 'review', 'xhigh', 0.3) === [
        'model' => 'claude-sonnet-4-6', 'max_tokens' => 2500, 'temperature' => 0.3, 'output_config' => ['effort' => 'high'],
    ]);
check('Haiku 4.5: temperature, no effort, no thinking',
    fragment('claude-haiku-4-5', 'takeaways', 'medium', 0.3) === ['model' => 'claude-haiku-4-5', 'max_tokens' => 1000, 'temperature' => 0.3]);

echo "\nRow 5: fresh site\n";
$GLOBALS['tw']['options'] = [];
check('default_model default is claude-sonnet-5-5', postquality_default_settings()['default_model'] === 'claude-sonnet-5-5');
check('nothing stored: the settings read claude-sonnet-5-5', postquality_get_settings()['default_model'] === 'claude-sonnet-5-5');
$GLOBALS['tw']['options'] = ['postquality_settings' => ['webhook_token' => 'stored-token']]; // what activation stores
$map = postquality_llm_map(postquality_get_settings());
check('activated, Settings never saved: every agent calls Sonnet 5.5',
    array_unique(array_column($map, 'model')) === ['claude-sonnet-5-5']);
check('... review (default effort high) and takeaways (medium) with thinking off, the agent budgets',
    $map['review'] === ['model' => 'claude-sonnet-5-5', 'max_tokens' => 2500, 'output_config' => ['effort' => 'high'], 'thinking' => ['type' => 'between_tools']]
    && $map['takeaways'] === ['model' => 'claude-sonnet-5-5', 'max_tokens' => 1000, 'output_config' => ['effort' => 'medium'], 'thinking' => ['type' => 'between_tools']]);

echo "\nRow 6: a saved earlier model\n";
$GLOBALS['tw']['options'] = ['postquality_settings' => ['default_model' => 'claude-sonnet-4-6', 'webhook_token' => 'stored-token']];
check('stored claude-sonnet-4-6: the settings still read it', postquality_get_settings()['default_model'] === 'claude-sonnet-4-6');
$GLOBALS['pqm_test_caps'] = ['postquality_manage_settings'];
// The Settings form posts the selected option back (the render rows prove it renders selected).
$out = PostQuality_Admin::sanitize_settings(['default_model' => 'claude-sonnet-4-6', 'custom_model' => '']);
check('a Settings save keeps claude-sonnet-4-6', $out['default_model'] === 'claude-sonnet-4-6');
$ok = true;
foreach (array_merge($CURRENT, $EARLIER, ['custom']) as $model) {
    $ok = $ok && PostQuality_Admin::sanitize_settings(['default_model' => $model, 'custom_model' => ''])['default_model'] === $model;
}
check('the sanitizer accepts every listed model and custom', $ok);
check('an unknown model posted falls back to the default (Sonnet 5.5)',
    PostQuality_Admin::sanitize_settings(['default_model' => 'claude-fable-5-1'])['default_model'] === 'claude-sonnet-5-5');
$GLOBALS['pqm_test_caps'] = [];
$GLOBALS['tw']['options'] = [];

echo "\nRow 7: Custom ID\n";
$ok = true;
foreach ($BUDGET as $agent => $budget) {
    foreach ($EFFORTS as $effort) {
        $s = with_model('custom', $agent, $effort);
        $s['custom_model'] = 'claude-fable-5-1';
        $ok = $ok && postquality_llm_params($agent, $s) === ['model' => 'claude-fable-5-1', 'max_tokens' => $budget];
    }
}
check('custom + any ID: model and the agent budget only, every agent and effort', $ok);
$s = with_model('custom', 'review', 'high');
check('custom with an empty ID: Sonnet 5.5 and its fragment', postquality_llm_params('review', $s) === fragment('claude-sonnet-5-5', 'review', 'high'));
$s = with_model('claude-sonnet-4-6', 'review', 'medium');
$s['agent_prompts']['review']['model'] = 'claude-opus-5-5';
check('a per-agent override follows its own model\'s rule (Opus 5.5 over a Sonnet 4.6 default)',
    postquality_llm_params('review', $s) === ['model' => 'claude-opus-5-5', 'max_tokens' => 2500 + $RESERVE, 'output_config' => ['effort' => 'medium']]);

/* ─── Part 2: every listed model against the provider's rules ───────── */
/*
 * The provider's documented request rules per model on API version
 * 2023-06-01 (the claude-api reference read 2026-11-04):
 * - price: list price per million tokens, input / output (Haiku 5.5: prompts up to 100K tokens);
 * - temperature: whether a non-default value is accepted (the 5.5 models take only the default, 1; Opus 4.7+ and Sonnet 5 none);
 * - effort: the output_config.effort levels accepted (null: the parameter is rejected);
 * - off: the thinking types the plugin may send to turn thinking off, with the efforts each is accepted at
 *   (Opus 5.5: none; Sonnet 5.5: between_tools only, up to high, disabled is a 400; Haiku 5.5: disabled up to high);
 * - thinks: whether the model thinks when the request omits the thinking key.
 */
$ALL = $EFFORTS;
$PROVIDER = [
    'claude-opus-5-5'   => ['price' => '$4 / $20',      'temperature' => false, 'effort' => $ALL, 'off' => [], 'thinks' => true],
    'claude-sonnet-5-5' => ['price' => '$2 / $10',      'temperature' => false, 'effort' => $ALL, 'off' => ['between_tools' => ['low', 'medium', 'high']], 'thinks' => true],
    'claude-haiku-5-5'  => ['price' => '$0.10 / $0.50', 'temperature' => false, 'effort' => $ALL, 'off' => ['disabled' => ['low', 'medium', 'high']], 'thinks' => true],
    'claude-opus-4-8'   => ['price' => '$5 / $25',      'temperature' => false, 'effort' => $ALL, 'off' => ['disabled' => $ALL], 'thinks' => false],
    'claude-sonnet-5'   => ['price' => '$2 / $10',      'temperature' => false, 'effort' => $ALL, 'off' => ['disabled' => $ALL], 'thinks' => true],
    'claude-sonnet-4-6' => ['price' => '$3 / $15',      'temperature' => true,  'effort' => ['low', 'medium', 'high', 'max'], 'off' => ['disabled' => $ALL], 'thinks' => false],
    'claude-haiku-4-5'  => ['price' => '$1 / $5',       'temperature' => true,  'effort' => null, 'off' => ['disabled' => [null]], 'thinks' => false],
];
$LABELS = [
    'claude-opus-5-5'   => 'Claude Opus 5.5 — highest quality',
    'claude-sonnet-5-5' => 'Claude Sonnet 5.5 — best quality/cost balance',
    'claude-haiku-5-5'  => 'Claude Haiku 5.5 — fastest, lowest cost',
    'claude-opus-4-8'   => 'Claude Opus 4.8',
    'claude-sonnet-5'   => 'Claude Sonnet 5',
    'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
    'claude-haiku-4-5'  => 'Claude Haiku 4.5',
];

echo "\nThe registry lists the owner's seven models\n";
$registry = postquality_model_registry();
check('keys: Opus 5.5, Sonnet 5.5, Haiku 5.5, then Opus 4.8, Sonnet 5, Sonnet 4.6, Haiku 4.5',
    array_keys($registry) === array_merge($CURRENT, $EARLIER) && array_keys($registry) === array_keys($PROVIDER));
check('groups: the three current models "current", the four earlier ones "earlier"',
    array_column($registry, 'group') === ['current', 'current', 'current', 'earlier', 'earlier', 'earlier', 'earlier']);
check('labels: the current ones with their role, the earlier ones by name only',
    array_combine(array_keys($registry), array_column($registry, 'label')) === $LABELS);
check('prices: the provider\'s list prices of 2026-11-04 (Sonnet 5 at $2 / $10)',
    array_combine(array_keys($registry), array_column($registry, 'pricing')) === array_combine(array_keys($PROVIDER), array_column($PROVIDER, 'price')));
check('every entry still carries supports_temperature and effort_levels (the admin JS reads them)',
    array_filter($registry, static fn($m) => !is_bool($m['supports_temperature']) || !is_array($m['effort_levels'])) === []);
check('Fable 5.1 is not listed (the Custom model ID reaches it)', !isset($registry['claude-fable-5-1']));

echo "\nEvery listed model's fragment, every agent × stored effort, against the provider's rules\n";
foreach ($PROVIDER as $model => $rule) {
    $bad = [];
    foreach ($BUDGET as $agent => $budget) {
        foreach (array_merge($EFFORTS, ['bogus']) as $stored_effort) {
            $f = fragment($model, $agent, $stored_effort, 0.3);
            $where = "$agent/$stored_effort";
            $effort = $f['output_config']['effort'] ?? null;
            if (array_diff(array_keys($f), ['model', 'max_tokens', 'temperature', 'output_config', 'thinking']) !== []) {
                $bad[] = "$where: unexpected key";
            }
            if ($f['model'] !== $model) {
                $bad[] = "$where: model {$f['model']}";
            }
            if (isset($f['temperature']) !== $rule['temperature']) {
                $bad[] = "$where: temperature " . (isset($f['temperature']) ? 'sent, rejected' : 'not sent, accepted');
            }
            if ($rule['effort'] === null ? isset($f['output_config']) : !in_array($effort, $rule['effort'], true)) {
                $bad[] = "$where: effort " . var_export($effort, true);
            }
            if (isset($f['thinking'])) {
                $type = $f['thinking']['type'] ?? null;
                if ($f['thinking'] !== ['type' => $type] || !in_array($effort, $rule['off'][$type] ?? [], true)) {
                    $bad[] = "$where: thinking " . json_encode($f['thinking']) . ' at effort ' . var_export($effort, true);
                }
            }
            $may_think = !isset($f['thinking']) && $rule['thinks'];
            if ($f['max_tokens'] !== $budget + ($may_think ? $RESERVE : 0)) {
                $bad[] = "$where: max_tokens {$f['max_tokens']} (expected the budget, + the reserve when it may think)";
            }
        }
    }
    check("$model: valid for every agent and effort" . ($bad ? ' — ' . implode('; ', array_slice($bad, 0, 3)) : ''), $bad === []);
}
check('Sonnet 5.5 and Haiku 5.5 turn thinking off wherever the effort allows it (only xhigh and max think)',
    array_filter(['claude-sonnet-5-5', 'claude-haiku-5-5'], static function ($m) use ($EFFORTS) {
        foreach ($EFFORTS as $e) {
            if (isset(fragment($m, 'review', $e)['thinking']) !== in_array($e, ['low', 'medium', 'high'], true)) { return true; }
        }
        return false;
    }) === []);

harness_exit();
