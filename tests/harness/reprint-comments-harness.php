<?php
/**
 * Re-print comments harness — bug 125. Freemius generates the Free build by
 * re-printing every PHP file, and the re-print keeps a comment only in some
 * places (memory/delivery-pipeline.md, the Freemius deployment line):
 *   kept:  a comment on its own line before a statement, and a comment
 *          directly before a call argument;
 *   lost:  a comment before a ternary `?` or `:` branch, before a
 *          concatenation operand, before an array item, or after the last
 *          argument;
 *   moved: a trailing `// …` after `;` goes to the next line.
 * The directory form's Plugin Check scan reads the re-printed build, so a
 * translator note or a `phpcs:ignore` in a lost or moved place shows there
 * as a finding (4.0.0: 32). No WP install, no plugin code loaded: the
 * harness tokenizes the plugin's PHP (token_get_all) and fails, naming the
 * file and line, when
 *   1. a gettext call whose string holds a placeholder has no
 *      `translators:` note on the line above it (WPCS's rule: the nearest
 *      previous comment, on the line above the call or followed by code on
 *      the call's line) that also sits where the re-print keeps it: the
 *      token before the note is `;`, `{`, `}`, the open tag or a statement
 *      label's `:` (`case`, `default`, `else:`, `if (…):`), or the note opens
 *      a call argument that is itself a call (`sprintf( /* … *\/ __( … ) )`);
 *   2. a `phpcs:ignore` shares its line with code: an ignore alone on its
 *      line applies to the next line, before and after the re-print.
 * It models the printer from what 4.0.0's build shows; only the release
 * job sees the real re-print. The self-test rows feed the rules each
 * pattern the re-print loses and each one it keeps.
 * Run: php reprint-comments-harness.php
 */

require __DIR__ . '/lib/wp-stubs.php'; // the runner (check(), harness_exit()); no plugin code is loaded here

/** The gettext functions WPCS's I18n sniff reads, and the argument positions of their translatable strings. */
const GETTEXT_STRINGS = [
    '__' => [0], '_e' => [0], '_x' => [0], '_ex' => [0], '_n' => [0, 1], '_nx' => [0, 1], '_n_noop' => [0, 1], '_nx_noop' => [0, 1],
    'esc_html__' => [0], 'esc_html_e' => [0], 'esc_html_x' => [0], 'esc_attr__' => [0], 'esc_attr_e' => [0], 'esc_attr_x' => [0],
];

/** A token's text, for token_get_all()'s arrays and single characters alike. */
function tok_text($t): string { return is_array($t) ? $t[1] : $t; }

/** Each token with its start line, single-character tokens included (token_get_all() gives them none). */
function lined_tokens(string $src): array {
    $out  = [];
    $line = 1;
    foreach (token_get_all($src) as $t) {
        $out[] = ['id' => is_array($t) ? $t[0] : null, 'text' => tok_text($t), 'line' => $line];
        $line += substr_count(tok_text($t), "\n");
    }
    return $out;
}

function is_space(array $t): bool { return $t['id'] === T_WHITESPACE; }
function is_comment(array $t): bool { return $t['id'] === T_COMMENT || $t['id'] === T_DOC_COMMENT; }
/** The last line a token covers (a block comment can span several; the open tag carries its newline). */
function end_line(array $t): int { return $t['line'] + substr_count(rtrim($t['text'], "\n"), "\n"); }

function prev_code(array $tk, int $i): ?int {
    for ($j = $i - 1; $j >= 0; $j--) { if (!is_space($tk[$j])) { return $j; } }
    return null;
}
function next_code(array $tk, int $i): ?int {
    for ($j = $i + 1, $n = count($tk); $j < $n; $j++) { if (!is_space($tk[$j]) && !is_comment($tk[$j])) { return $j; } }
    return null;
}

/** The literal strings of a call's arguments at the given positions ('' for an argument that is not one literal). */
function call_strings(array $tk, int $open, array $positions): array {
    $args = [[]];
    $depth = 0;
    for ($j = $open + 1, $n = count($tk); $j < $n; $j++) {
        $s = $tk[$j]['text'];
        if (in_array($s, ['(', '[', '{'], true) || $tk[$j]['id'] === T_CURLY_OPEN || $tk[$j]['id'] === T_DOLLAR_OPEN_CURLY_BRACES) { $depth++; }
        if (in_array($s, [')', ']', '}'], true)) {
            if ($depth === 0) { break; }
            $depth--;
        }
        if ($s === ',' && $depth === 0) { $args[] = []; continue; }
        if (!is_space($tk[$j]) && !is_comment($tk[$j])) { $args[count($args) - 1][] = $tk[$j]; }
    }
    $strings = [];
    foreach ($positions as $p) {
        $a = $args[$p] ?? [];
        $strings[] = count($a) === 1 && $a[0]['id'] === T_CONSTANT_ENCAPSED_STRING ? substr($a[0]['text'], 1, -1) : '';
    }
    return $strings;
}

/** Whether a string holds a printf placeholder, as WPCS's I18n sniff matches them (`%%` is not one). */
function has_placeholder(string $s): bool {
    return preg_match('/%(?:[0-9]+\$)?[+-]?(?:[ 0]|\'.)?-?[0-9]*(?:\.[0-9]+)?[bcdeEfFgGosuxX]/', str_replace('%%', '', $s)) === 1;
}

/** Whether the `:` at $i ends a statement label (`case`, `default`, `else:`, `if (…):` and the other alternative forms), not a ternary branch. */
function is_label_colon(array $tk, int $i): bool {
    $p = prev_code($tk, $i);
    if ($p === null) { return false; }
    if (in_array($tk[$p]['id'], [T_ELSE, T_DEFAULT], true)) { return true; }
    if ($tk[$p]['text'] === ')') { // if (…): elseif (…): foreach (…): for (…): while (…): switch (…):
        $depth = 0;
        for ($j = $p; $j >= 0; $j--) {
            if ($tk[$j]['text'] === ')') { $depth++; }
            if ($tk[$j]['text'] === '(' && --$depth === 0) {
                $k = prev_code($tk, $j);
                return $k !== null && in_array($tk[$k]['id'], [T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_SWITCH], true);
            }
        }
        return false;
    }
    // case <expression>: back to the `case` at this level, before any statement or ternary boundary.
    $depth = 0;
    for ($j = $p; $j >= 0; $j--) {
        $s = $tk[$j]['text'];
        if (in_array($s, [')', ']'], true)) { $depth++; }
        if (in_array($s, ['(', '['], true)) { $depth--; }
        if ($depth === 0 && $tk[$j]['id'] === T_CASE) { return true; }
        if ($depth <= 0 && (in_array($s, [';', '{', '}', '?'], true) || $tk[$j]['id'] === T_OPEN_TAG)) { return false; }
    }
    return false;
}

/** Whether the re-print keeps the comment at $c where it is (and so in front of the code after it). */
function reprint_keeps(array $tk, int $c): bool {
    $p = prev_code($tk, $c);
    if ($p === null) { return true; }
    $before = $tk[$p];
    if (in_array($before['text'], [';', '{', '}'], true) || $before['id'] === T_OPEN_TAG) { return true; }
    if (is_comment($before)) { return reprint_keeps($tk, $p); } // a run of comments in one place
    if ($before['text'] === ':' && is_label_colon($tk, $p)) { return true; }
    if (in_array($before['text'], ['(', ','], true)) { // the note opens a call argument that is itself a call
        $n = next_code($tk, $c);
        $after = $n === null ? null : next_code($tk, $n);
        $open = $p;
        if ($before['text'] === ',') { // the `(` this argument list opened with
            $depth = 0;
            for ($open = $p - 1; $open >= 0; $open--) {
                $s = $tk[$open]['text'];
                if (in_array($s, [')', ']'], true)) { $depth++; }
                if (in_array($s, ['(', '['], true) && $depth-- === 0) { break; }
            }
        }
        $callee = $open >= 0 ? prev_code($tk, $open) : null;
        return $n !== null && $tk[$n]['id'] === T_STRING && $after !== null && $tk[$after]['text'] === '('
            && $open >= 0 && $tk[$open]['text'] === '(' && $callee !== null && in_array($tk[$callee]['id'], [T_STRING, T_VARIABLE], true);
    }
    return false;
}

/**
 * The re-print findings of one file: [line, message] per translator note the
 * re-print loses or WPCS would not see, and per phpcs:ignore sharing a line.
 */
function reprint_findings(string $src): array {
    $tk = lined_tokens($src);
    $findings = [];
    foreach ($tk as $i => $t) {
        if ($t['id'] !== T_STRING || !isset(GETTEXT_STRINGS[$t['text']])) { continue; }
        $open = next_code($tk, $i);
        $prev = prev_code($tk, $i);
        if ($open === null || $tk[$open]['text'] !== '(') { continue; }
        if ($prev !== null && (in_array($tk[$prev]['id'], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR], true))) { continue; }
        $strings = call_strings($tk, $open, GETTEXT_STRINGS[$t['text']]);
        if (!array_filter($strings, 'has_placeholder')) { continue; }
        $call = "{$t['text']}() on line {$t['line']}";
        $c = null;
        for ($j = $i - 1; $j >= 0; $j--) { if (is_comment($tk[$j])) { $c = $j; break; } }
        $note = $c === null ? '' : ltrim(preg_replace('#^(/\*+|//)#', '', $tk[$c]['text']), " \t\r\n*");
        if ($c === null || stripos($note, 'translators:') !== 0) {
            $findings[] = [$t['line'], "$call: the nearest comment above is no translators: note"];
            continue;
        }
        $after = next_code($tk, $c);
        if (end_line($tk[$c]) + 1 !== $t['line'] && ($after === null || $tk[$after]['line'] !== $t['line'])) {
            $findings[] = [$t['line'], "$call: the translators: note is not on the line above the call"];
            continue;
        }
        if (!reprint_keeps($tk, $c)) {
            $findings[] = [$t['line'], "$call: the re-print drops its translators: note (line {$tk[$c]['line']}, after `{$tk[prev_code($tk, $c)]['text']}`)"];
        }
    }
    foreach ($tk as $i => $t) {
        if (!is_comment($t) || !str_contains($t['text'], 'phpcs:ignore')) { continue; }
        $p = prev_code($tk, $i);
        $n = next_code($tk, $i);
        $code_before = $p !== null && $tk[$p]['line'] === $t['line'] && !is_comment($tk[$p]) && $tk[$p]['id'] !== T_OPEN_TAG;
        $code_after  = $n !== null && $tk[$n]['line'] === end_line($t) && $tk[$n]['id'] !== T_CLOSE_TAG;
        if ($code_before || $code_after) {
            $findings[] = [$t['line'], 'a phpcs:ignore shares its line with code: the re-print moves it to the next line, where it no longer applies'];
        }
    }
    return $findings;
}

/* ─── Self-test: the rules on each pattern the re-print loses and keeps ── */
echo "Self-test: the patterns of 4.0.0's Free build\n";
$f = static fn(string $body): array => array_column(reprint_findings("<?php\n" . $body . "\n"), 1);
$lost = [
    'a note before a ternary `?` branch' => "\$s = \$a\n    /* translators: %s: x */\n    ? sprintf(__('A %s', 'd'), \$x)\n    : '';",
    'a note before a ternary `:` branch' => "\$s = \$a\n    ? ''\n    /* translators: %s: x */\n    : sprintf(__('B %s', 'd'), \$x);",
    'notes before both branches of a ternary format' => "return sprintf(\n    \$a\n        /* translators: %s: x */\n        ? __('A %s', 'd')\n        /* translators: %s: x */\n        : __('B %s', 'd'),\n    \$x\n);",
    'a note before a concatenation operand' => "\$s = '<b>'\n    /* translators: %s: x */\n    . esc_html(sprintf(__('C %s', 'd'), \$x)) . '</b>';",
    'a note before an array item' => "\$a = [\n    'k' => 1,\n    /* translators: %s: x */\n    'm' => sprintf(__('D %s', 'd'), \$x),\n];",
    'a note before an argument that is no call' => "f(\n    /* translators: %s: x */\n    \$a ? __('E %s', 'd') : '',\n);",
    'no note at all' => "\$s = sprintf(__('F %s', 'd'), \$x);",
    'a note above another statement' => "/* translators: %s: x */\n\$y = 1;\n\$s = sprintf(__('G %s', 'd'), \$x);",
    'another comment nearer than the note' => "/* translators: %s: x */\n\$s = sprintf( // why\n    __('H %s', 'd'), \$x);",
];
foreach ($lost as $label => $body) {
    $r = $f($body);
    check("flagged: $label", count($r) >= 1 && !array_filter($r, static fn($m) => str_contains($m, 'phpcs:ignore')));
}
$kept = [
    'a note on its own line above the statement' => "if (\$a) {\n    /* translators: %s: x */\n    \$s = sprintf(__('A %s', 'd'), \$x);\n}",
    'a note above a format assigned in each branch' => "if (\$a) {\n    /* translators: %s: x */\n    \$format = __('A %s', 'd');\n} else {\n    /* translators: %s: x */\n    \$format = __('B %s', 'd');\n}\n\$s = sprintf(\$format, \$x);",
    'a note directly before a call that is the first argument' => "printf(\n    /* translators: %s: x */\n    esc_html__('C %s', 'd'),\n    '<b>' . esc_html(\$x) . '</b>'\n);",
    'a note before a call argument after a comma' => "f(\$y,\n    /* translators: %s: x */\n    sprintf(__('D %s', 'd'), \$x)\n);",
    'a note after the open tag, on the call\'s line' => "?><p><?php /* translators: %s: x */ echo esc_html(sprintf(__('E %s', 'd'), \$x)); ?></p><?php",
    'a note under a case label' => "switch (\$k) {\n    case 'a':\n        /* translators: %s: x */\n        \$s = sprintf(__('F %s', 'd'), \$x);\n        break;\n}",
    'a note under an if (…): label' => "if (\$a):\n    /* translators: %s: x */\n    printf(esc_html__('G %s', 'd'), 'x');\nendif;",
    'a note before a return' => "if (\$a) {\n    return '';\n}\n/* translators: %s: x */\nreturn sprintf(__('H %s', 'd'), \$x);",
    'a string with no placeholder needs no note' => "\$s = \$a ? __('100%% sure', 'd') : __('No', 'd');",
    'a method named like gettext is not gettext' => "\$s = \$o->__('I %s');",
    'a phpcs:ignore alone on its line' => "// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file body, not HTML\necho \$json;",
];
foreach ($kept as $label => $body) {
    $r = $f($body);
    check("passes: $label", $r === []);
}
$ignores = [
    'a phpcs:ignore trailing a statement' => "echo \$x; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.",
    'a phpcs:ignore trailing an argument' => "printf(\n    '%s',\n    \$x // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.\n);",
    'a phpcs:ignore trailing an open parenthesis' => "\$rows = \$wpdb->query( \$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery\n    'SELECT 1'\n) );",
    'a phpcs:ignore trailing an echo in a template' => "?><p><?php echo \$x; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><?php",
];
foreach ($ignores as $label => $body) {
    $r = $f($body);
    check("flagged: $label", count($r) === 1 && str_contains($r[0], 'phpcs:ignore'));
}
check('a finding names the line', reprint_findings("<?php\n\n\$s = \$a\n    /* translators: %s: x */\n    ? sprintf(__('A %s', 'd'), \$x)\n    : '';\n")[0][0] === 5);

/* ─── The plugin: every PHP file outside vendor/ and the git-ignored build ── */
echo "The plugin's PHP\n";
$plugin = dirname(__DIR__, 2) . '/post-quality-manager';
$files  = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS)) as $file) {
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($plugin) + 1));
    if ($file->getExtension() === 'php' && !str_starts_with($rel, 'vendor/') && !str_starts_with($rel, 'admin/build/')) {
        $files[$rel] = $file->getPathname();
    }
}
ksort($files);
check('the three files the form scanned are read', array_diff(['admin/class-pqm-admin.php', 'includes/class-pqm-n8n.php', 'includes/class-pqm-rest-api.php'], array_keys($files)) === []);
$notes = 0;
foreach ($files as $rel => $path) {
    $src = (string) file_get_contents($path);
    $notes += preg_match_all('/translators:/i', $src);
    $findings = reprint_findings($src);
    foreach ($findings as [$line, $message]) {
        echo "    $rel:$line $message\n";
    }
    check("$rel: every translator note and phpcs:ignore survives the re-print", $findings === []);
}
check("the files hold translator notes ($notes)", $notes > 50);

harness_exit();
