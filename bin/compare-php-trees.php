<?php
/**
 * Compares the PHP of two plugin trees as code, not as text (story 58,
 * ADR 0014): Freemius's deployment re-prints every PHP file of the Free build
 * it generates (spacing, trailing commas, parentheses, `if (…): … endif;`
 * rewritten as braces, string re-escaping, comments inside argument lists
 * dropped), so a byte diff against bin/build-free.php never matches while the
 * code is the same.
 *
 *   php bin/compare-php-trees.php <tree-a> <tree-b>
 *
 * Every `.php` file outside vendor/ is reduced to its token stream with
 * whitespace and comments dropped, string literals compared by value, a
 * trailing comma before `]` or `)` dropped, alternative control syntax
 * rewritten as braces, and parentheses ignored. Exit 0 when every file has a
 * counterpart with the same stream; otherwise the first difference of each
 * file is printed with both line numbers, and the exit code is 1. Files that
 * are not PHP, and vendor/, are for `diff -r`. Repository tooling: not in the zip.
 */

const PQM_ALT_OPENERS = [T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_SWITCH];
const PQM_ALT_CLOSERS = [T_ENDIF, T_ENDFOREACH, T_ENDFOR, T_ENDWHILE, T_ENDSWITCH];

/** Relative path => absolute path of every non-vendor PHP file under $root. */
function pqm_php_files(string $root): array {
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
        $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($root)), '/');
        if (str_ends_with($rel, '.php') && !str_starts_with($rel, 'vendor/')) {
            $files[$rel] = $f->getPathname();
        }
    }
    ksort($files);
    return $files;
}

/** The value of a double-quoted body (PHP's escape set, not C's: `"\y"` stays `\y`). */
function pqm_unescape(string $body): string {
    return preg_replace_callback('/\\\\(?:([ntrvef\\\\$"])|([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|u\{([0-9A-Fa-f]+)\})/', static function (array $m): string {
        if ($m[1] !== '') {
            return ['n' => "\n", 't' => "\t", 'r' => "\r", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'][$m[1]];
        }
        if (($m[2] ?? '') !== '') {
            return chr(octdec($m[2]));
        }
        if (($m[3] ?? '') !== '') {
            return chr(hexdec($m[3]));
        }
        return html_entity_decode('&#x' . $m[4] . ';', ENT_QUOTES, 'UTF-8');
    }, $body);
}

/** The value of a single- or double-quoted literal, so escaping differences vanish. */
function pqm_literal(string $raw): string {
    $body = substr($raw, 1, -1);
    return 'S:' . ($raw[0] === "'" ? strtr($body, ['\\\\' => '\\', "\\'" => "'"]) : pqm_unescape($body));
}

/** Normalised stream: [text, line] pairs. */
function pqm_stream(string $file): array {
    $t = [];
    foreach (token_get_all((string) file_get_contents($file)) as $tok) {
        if (is_array($tok) && in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $t[] = $tok;
    }
    // Alternative syntax → braces: the `:` after an opener's condition reads
    // as `{`, `else:` and `elseif (…):` get a `}` in front, `endif;` (and the
    // other closers) read as `}`.
    $open_brace = [];
    $close_before = [];
    $skip = [];
    for ($i = 0, $n = count($t); $i < $n; $i++) {
        if (!is_array($t[$i])) {
            continue;
        }
        $id = $t[$i][0];
        if (in_array($id, PQM_ALT_OPENERS, true) && ($t[$i + 1] ?? null) === '(') {
            for ($j = $i + 1, $depth = 0; $j < $n; $j++) {
                if ($t[$j] === '(') {
                    $depth++;
                } elseif ($t[$j] === ')' && --$depth === 0) {
                    break;
                }
            }
            if (($t[$j + 1] ?? null) === ':') {
                $open_brace[$j + 1] = true;
                if ($id === T_ELSEIF) {
                    $close_before[$i] = true;
                }
            }
        } elseif ($id === T_ELSE && ($t[$i + 1] ?? null) === ':') {
            $close_before[$i] = true;
            $open_brace[$i + 1] = true;
        } elseif (in_array($id, PQM_ALT_CLOSERS, true)) {
            $t[$i] = [T_STRING, '}', $t[$i][2]];
            if (($t[$i + 1] ?? null) === ';') {
                $skip[$i + 1] = true;
            }
        }
    }
    $out = [];
    $line = 1;
    foreach ($t as $i => $tok) {
        if (isset($skip[$i])) {
            continue;
        }
        if (isset($open_brace[$i])) {
            $out[] = ['{', $line];
            continue;
        }
        if (isset($close_before[$i])) {
            $out[] = ['}', $tok[2]];
        }
        if (is_array($tok)) {
            $line = $tok[2] ?? $line;
            $id = $tok[0];
            if ($id === T_OPEN_TAG || $id === T_CLOSE_TAG) {
                $text = trim($tok[1]);
            } elseif ($id === T_INLINE_HTML) {
                $text = preg_replace('/\s+/', ' ', trim($tok[1]));
                if ($text === '') {
                    continue;
                }
                $text = 'H:' . $text;
            } elseif ($id === T_CONSTANT_ENCAPSED_STRING) {
                $text = pqm_literal($tok[1]);
            } elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
                $text = 'E:' . pqm_unescape($tok[1]);
            } else {
                $text = $tok[1];
            }
        } else {
            $text = $tok;
            if ($text === '(' || $text === ')') {
                continue;
            }
            if (($text === ']' || $text === ')') && $out && end($out)[0] === ',') {
                array_pop($out);
            }
        }
        $out[] = [$text, $line];
    }
    return $out;
}

[$a, $b] = [rtrim(str_replace('\\', '/', $argv[1] ?? ''), '/'), rtrim(str_replace('\\', '/', $argv[2] ?? ''), '/')];
if ($a === '' || $b === '' || !is_dir($a) || !is_dir($b)) {
    fwrite(STDERR, "Usage: php bin/compare-php-trees.php <tree-a> <tree-b>\n");
    exit(1);
}
$fa = pqm_php_files($a);
$fb = pqm_php_files($b);
$differences = 0;
foreach (array_diff_key($fa, $fb) as $rel => $_) {
    echo "$rel: only in $a\n";
    $differences++;
}
foreach (array_diff_key($fb, $fa) as $rel => $_) {
    echo "$rel: only in $b\n";
    $differences++;
}
foreach (array_intersect_key($fa, $fb) as $rel => $path) {
    $sa = pqm_stream($path);
    $sb = pqm_stream($fb[$rel]);
    $n = max(count($sa), count($sb));
    for ($i = 0; $i < $n; $i++) {
        if (($sa[$i][0] ?? null) !== ($sb[$i][0] ?? null)) {
            $show = static fn(array $s, int $from): string => implode(' ', array_column(array_slice($s, $from, 12), 0));
            echo "$rel: differs from token $i\n  $a line " . ($sa[$i][1] ?? 'end') . ': ' . $show($sa, $i) . "\n  $b line " . ($sb[$i][1] ?? 'end') . ': ' . $show($sb, $i) . "\n";
            $differences++;
            break;
        }
    }
}
echo $differences ? "$differences PHP difference(s).\n" : 'PHP identical: ' . count($fa) . " files.\n";
exit($differences ? 1 : 0);
