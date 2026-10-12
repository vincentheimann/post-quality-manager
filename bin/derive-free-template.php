<?php
/**
 * Derives the Free n8n template from the Pro one (ADR 0014, story 54):
 *
 *   php bin/derive-free-template.php
 *
 * writes post-quality-manager/templates/n8n-workflow-free.json from
 * templates/n8n-workflow.json. Route Command loses the paid commands' rules
 * and outputs, and every node only those outputs reach goes with them (the
 * paid agents, the rewrite's WordPress save and its credential). Parse Input
 * accepts the free commands only, and the Free template states its variant
 * where the Pro one states nothing (absent = Pro): `workflow_variant: 'free'`
 * in the trigger's ping reply, in Parse Input's stamp and in the result
 * callback. Deterministic: same Pro template, same bytes. Edit the Pro
 * template, then run this; tests/harness/template-guard-harness.php fails
 * while the checked-in Free file differs from a fresh derivation.
 * Repository tooling: not in the zip.
 */

const PQM_PAID_COMMANDS = ['sources', 'seo', 'audit', 'rewrite'];

/** The Free template for a decoded Pro template (objects, as json_decode() without assoc returns them). */
function pqm_derive_free_template(object $pro): object {
    $free  = unserialize(serialize($pro)); // deep copy
    $nodes = [];
    foreach ($free->nodes as $node) {
        $nodes[$node->name] = $node;
    }
    $route = $nodes['Route Command'] ?? throw new RuntimeException('Route Command node missing.');
    $rules = $route->parameters->rules->rules;
    $paid  = array_keys(array_filter($rules, static fn($rule) => in_array($rule->value2, PQM_PAID_COMMANDS, true)));
    if (count($paid) !== count(PQM_PAID_COMMANDS)) {
        throw new RuntimeException('Route Command: expected one rule per paid command.');
    }

    // Nodes the trigger still reaches once the paid outputs are cut.
    $reached = [];
    $queue   = ['Webhook Trigger'];
    while ($queue) {
        $name = array_shift($queue);
        if (isset($reached[$name])) {
            continue;
        }
        $reached[$name] = true;
        foreach ($free->connections->{$name}->main ?? [] as $output => $targets) {
            if ($name === 'Route Command' && in_array($output, $paid, true)) {
                continue;
            }
            foreach ($targets as $target) {
                $queue[] = $target->node;
            }
        }
    }

    $free->nodes = array_values(array_filter($free->nodes, static fn($node) => isset($reached[$node->name])));
    foreach (array_keys((array) $free->connections) as $name) {
        if (!isset($reached[$name])) {
            unset($free->connections->{$name});
        }
    }
    $keep = static fn(array $list) => array_values(array_diff_key($list, array_flip($paid)));
    $free->connections->{'Route Command'}->main = $keep($free->connections->{'Route Command'}->main);
    $route->parameters->rules->rules = $keep($rules);
    $route->parameters->fallbackOutput -= count(array_filter($paid, static fn($i) => $i < $route->parameters->fallbackOutput));

    // Parse Input: the free commands only, and the variant stamp.
    $parse = $nodes['Parse Input'];
    $code  = preg_replace_callback('/const validCommands = \[([^\]]*)\];/', static function ($m) {
        preg_match_all("/'([a-z_]+)'/", $m[1], $commands);
        $free_commands = array_diff($commands[1], PQM_PAID_COMMANDS);
        return "const validCommands = ['" . implode("', '", $free_commands) . "'];";
    }, $parse->parameters->jsCode, -1, $found);
    if ($found !== 1) {
        throw new RuntimeException('Parse Input: expected one validCommands list.');
    }
    $parse->parameters->jsCode = preg_replace('/^(\s*)(workflow_version: \d+,)$/m', "\$1\$2\n\$1workflow_variant: 'free',", $code, -1, $found);
    if ($found !== 1) {
        throw new RuntimeException('Parse Input: expected one workflow_version stamp.');
    }

    // The ping reply and the result callback say which variant answered.
    $trigger = $nodes['Webhook Trigger'];
    $reply   = json_decode($trigger->parameters->options->responseData, true);
    $trigger->parameters->options->responseData = json_encode($reply + ['workflow_variant' => 'free']);
    $post   = $nodes['POST Result to WordPress'];
    $anchor = 'workflow_version: $json.workflow_version || 0, ';
    if (substr_count($post->parameters->body, $anchor) !== 1) {
        throw new RuntimeException('POST Result to WordPress: expected one workflow_version field in the body.');
    }
    $post->parameters->body = str_replace($anchor, $anchor . 'workflow_variant: $json.workflow_variant, ', $post->parameters->body);

    return $free;
}

/** JSON as the Pro template is written: two-space indents, slashes and Unicode unescaped, no final newline. */
function pqm_template_json(object $template): string {
    $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return preg_replace_callback('/^ +/m', static fn($m) => str_repeat(' ', intdiv(strlen($m[0]), 2)), $json);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $dir = dirname(__DIR__) . '/post-quality-manager/templates';
    try {
        $free = pqm_derive_free_template(json_decode((string) file_get_contents("$dir/n8n-workflow.json"), false, 512, JSON_THROW_ON_ERROR));
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cannot derive the Free template: ' . $e->getMessage() . "\n");
        exit(1);
    }
    file_put_contents("$dir/n8n-workflow-free.json", pqm_template_json($free));
    echo "Wrote $dir/n8n-workflow-free.json\n";
}
