<?php
/**
 * Builds the Free build of the plugin locally (ADR 0014, story 54): the same
 * stripping Freemius's deployment applies to the zip it receives, so CI can
 * check the Free build on every pull request.
 *
 *   php bin/build-free.php <out-dir> [<plugin-dir>]
 *
 * Copies <plugin-dir> (default: post-quality-manager/) to
 * <out-dir>/post-quality-manager/, then:
 *  - leaves out the paths of the main file's `@fs_premium_only` header list
 *    and every file named `*__premium_only.*`;
 *  - removes, outside vendor/, every function or method named
 *    `*__premium_only` (with its doc comment) and every
 *    `if ( pqm_fs()->is__premium_only() ) { … }` block — that exact
 *    condition, braces, no `else`;
 *  - sets the SDK's `'is_premium' => true` to false.
 * Anything else that names a premium symbol (an inline condition, an
 * assignment from a premium method, an unwrapped call, an `else`) is left in
 * place on purpose: the survivor check then fails with its file and line.
 * Exit 0 on a clean build, 1 otherwise. Repository tooling: not in the zip.
 */

const PQM_SUFFIX = '__premium_only';

function pqm_fail(string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/** Paths listed in the main file's `@fs_premium_only` header, relative, no leading slash. */
function pqm_premium_paths(string $main_file): array {
    if (!preg_match('/@fs_premium_only\s+([^\r\n]+)/', (string) file_get_contents($main_file), $m)) {
        return [];
    }
    return array_values(array_filter(array_map(static fn($p) => trim(trim($p), '/'), explode(',', $m[1]))));
}

/** Whether $rel or one of its folders is listed, or named `*__premium_only[.ext]`. */
function pqm_is_premium_path(string $rel, array $premium_paths): bool {
    $parts = explode('/', $rel);
    foreach ($parts as $k => $part) {
        if (preg_match('/' . PQM_SUFFIX . '(\.[^.]*)?$/', $part) || in_array(implode('/', array_slice($parts, 0, $k + 1)), $premium_paths, true)) {
            return true;
        }
    }
    return false;
}

/** Index of the next token that is not whitespace or a comment, or null. */
function pqm_next(array $t, int $i): ?int {
    for ($n = count($t); $i < $n; $i++) {
        if (!is_array($t[$i]) || !in_array($t[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }
    return null;
}

/** Index of the token closing the bracket opened at $open ('(' or '{'). */
function pqm_close(array $t, int $open): int {
    $want  = ['(' => ')', '{' => '}'][$t[$open]];
    $depth = 0;
    for ($i = $open, $n = count($t); $i < $n; $i++) {
        $text = is_array($t[$i]) ? $t[$i][1] : $t[$i];
        if ($want === '}' ? ($text === '{' || (is_array($t[$i]) && in_array($t[$i][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) : $text === '(') {
            $depth++;
        } elseif ($text === $want && --$depth === 0) {
            return $i;
        }
    }
    pqm_fail("Unbalanced bracket at line {$t[$open][2]}.");
}

/** Token ranges [start, end] to remove from one PHP file. */
function pqm_premium_ranges(array $t): array {
    $ranges = [];
    for ($i = 0, $n = count($t); $i < $n; $i++) {
        $id = is_array($t[$i]) ? $t[$i][0] : null;
        if ($id === T_FUNCTION) {
            $name = pqm_next($t, $i + 1);
            if ($name === null || !is_array($t[$name]) || !str_ends_with($t[$name][1], PQM_SUFFIX)) {
                continue; // A closure, or a function of the Free build.
            }
            $end = $name;
            while ($t[$end] !== '{') {
                $end = $t[$end] === '(' ? pqm_close($t, $end) + 1 : $end + 1;
            }
            $end = pqm_close($t, $end);
            // Back over the modifiers, then the doc comment right above them.
            $start = $i;
            for ($j = $i - 1; $j >= 0 && is_array($t[$j]); $j--) {
                if (in_array($t[$j][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
                    $start = $j;
                } elseif ($t[$j][0] === T_DOC_COMMENT) {
                    $start = $j;
                    break;
                } elseif ($t[$j][0] !== T_WHITESPACE) {
                    break;
                }
            }
            $ranges[] = [$start, $end];
            $i = $end;
        } elseif ($id === T_IF) {
            $open = pqm_next($t, $i + 1);
            $close = pqm_close($t, $open);
            $cond = '';
            for ($j = $open + 1; $j < $close; $j++) {
                if (!is_array($t[$j]) || !in_array($t[$j][0], [T_WHITESPACE, T_COMMENT], true)) {
                    $cond .= is_array($t[$j]) ? $t[$j][1] : $t[$j];
                }
            }
            $brace = pqm_next($t, $close + 1);
            if ($cond !== 'pqm_fs()->is' . PQM_SUFFIX . '()' || $t[$brace] !== '{') {
                continue;
            }
            $end = pqm_close($t, $brace);
            $after = pqm_next($t, $end + 1);
            if ($after !== null && is_array($t[$after]) && in_array($t[$after][0], [T_ELSE, T_ELSEIF], true)) {
                continue; // An else would survive in the Free build: left for the survivor check.
            }
            $ranges[] = [$i, $end];
            $i = $end;
        }
    }
    return $ranges;
}

/** The file with the premium ranges removed, whole lines where they stood alone. */
function pqm_strip_php(string $code): string {
    $t = token_get_all($code);
    $text = array_map(static fn($tok) => is_array($tok) ? $tok[1] : $tok, $t);
    foreach (pqm_premium_ranges($t) as [$start, $end]) {
        for ($i = $start; $i <= $end; $i++) {
            $text[$i] = '';
        }
        // Drop the indentation before the range and the line break after it.
        if ($start > 0 && is_array($t[$start - 1]) && $t[$start - 1][0] === T_WHITESPACE) {
            $text[$start - 1] = preg_replace('/[ \t]+$/', '', $text[$start - 1]);
        }
        if (isset($t[$end + 1]) && is_array($t[$end + 1]) && $t[$end + 1][0] === T_WHITESPACE) {
            $text[$end + 1] = preg_replace('/^\r?\n/', '', $text[$end + 1]);
        }
    }
    return implode('', $text);
}

/* ─── Main ───────────────────────────────────────────────────────────── */

$out = $argv[1] ?? '';
$src = rtrim(str_replace('\\', '/', $argv[2] ?? dirname(__DIR__) . '/post-quality-manager'), '/');
if ($out === '' || !is_file($src . '/post-quality-manager.php')) {
    pqm_fail('Usage: php bin/build-free.php <out-dir> [<plugin-dir>]');
}
$dest = rtrim(str_replace('\\', '/', $out), '/') . '/post-quality-manager';
if (file_exists($dest)) {
    pqm_fail("$dest already exists; choose an empty output folder.");
}

$premium_paths = pqm_premium_paths($src . '/post-quality-manager.php');
$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
mkdir($dest, 0777, true);
foreach ($items as $item) {
    $rel = ltrim(substr(str_replace('\\', '/', $item->getPathname()), strlen($src)), '/');
    if (pqm_is_premium_path($rel, $premium_paths)) {
        continue;
    }
    if ($item->isDir()) {
        mkdir("$dest/$rel");
    } elseif (str_ends_with($rel, '.php') && !str_starts_with($rel, 'vendor/')) {
        file_put_contents("$dest/$rel", pqm_strip_php((string) file_get_contents($item->getPathname())));
    } else {
        copy($item->getPathname(), "$dest/$rel");
    }
}

$main = "$dest/post-quality-manager.php";
$code = preg_replace("/('is_premium'\\s*=>\\s*)true\\b/", '${1}false', (string) file_get_contents($main), -1, $flipped);
if ($flipped !== 1) {
    pqm_fail("post-quality-manager.php: expected one 'is_premium' => true, found $flipped.");
}
file_put_contents($main, $code);

// Survivor check: no premium symbol may be left outside vendor/.
$survivors = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS)) as $file) {
    $rel = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($dest)), '/');
    if (str_starts_with($rel, 'vendor/')) {
        continue;
    }
    foreach (preg_split('/\r?\n/', (string) file_get_contents($file->getPathname())) as $n => $line) {
        if (str_contains($line, PQM_SUFFIX)) {
            $survivors[] = "$rel:" . ($n + 1) . ': ' . trim($line);
        }
    }
}
if ($survivors) {
    pqm_fail("Premium code survived the strip (wrap it in if ( pqm_fs()->is__premium_only() ) { … }):\n  " . implode("\n  ", $survivors));
}

echo "Free build written to $dest\n";
