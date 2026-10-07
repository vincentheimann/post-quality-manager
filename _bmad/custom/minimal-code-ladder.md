# Minimal-code ladder (house rule, ADR 0007)

Loaded into every `bmad-build` run as a persistent fact and read by the `overbuild` review lens. Adapted for this repo from Ponytail (`skills/ponytail/SKILL.md` and `skills/ponytail-review/SKILL.md`, https://github.com/DietrichGebert/ponytail, MIT License, Copyright (c) 2026 DietrichGebert). The plugin itself is not installed; ADR 0007 says why.

## Building: climb the ladder after reading, stop at the first rung that holds

Read the code the change touches and trace the real flow first. The ladder shortens the solution, never the reading.

1. **Does it need to exist?** A speculative need is skipped and named in one line. Anything the ticket's acceptance criteria or the user explicitly ask for is never skipped.
2. **Already in this codebase?** Reuse the existing helper, class or pattern (`PQM_*` classes, the React kit in `src/kit/`, the harness stubs in `tests/harness/`).
3. **PHP or JS standard library does it?** Use it.
4. **WordPress core or the browser covers it?** Options API, REST API, WP-Cron, `$wpdb` abstractions, capabilities, `@wordpress/components` and `@wordpress/api-fetch`, native HTML inputs.
5. **An installed dependency solves it?** Use it. Never add a dependency for what a few lines can do (a new Composer or npm dependency needs the owner's approval anyway).
6. **One line?** One line.
7. **Only then:** the minimum code that works.

Bug fix = root cause: grep every caller of the function you touch and fix it once where all callers route through (AGENTS.md "no symptom patches").

## Never simplified away

Ponytail's laziness stops at these lines, and this repo adds its own:

- `sanitize_*` / `esc_*`, nonces, `permission_callback`, capability and feature-gate checks — every trust boundary stays guarded.
- Error handling that prevents data loss; UI loading and error states.
- Accessibility basics.
- A visible "Custom" path beside any preset — the one editable field (`memory/lessons.md` 2026-10-07, replacing 2026-07-23).
- The check that proves non-trivial logic: it lives in `tests/harness/` (extend a harness or add one), never an ad-hoc `demo()` or one-off test file.

`AGENTS.md`, `memory/lessons.md` and `docs/brand.md` win over this file wherever they disagree.

## Plans and code comments

- A plan names what it deliberately skipped in one line: `Skipped: X — add when Y.`
- A deliberate shortcut with a known ceiling (an O(n²) scan, a global lock, a naive heuristic) carries one comment naming the ceiling and the upgrade path. When it should not wait for "later", it becomes a backlog ticket through `bmad-ticket`.

## Reviewing: the `overbuild` lens

Scope is over-engineering only; correctness, security and performance belong to the other lenses. One finding per line, numbered across the report:

`<N>. <file>:L<line>: <tag> <what>. <replacement>.`

Tags:

- `delete:` dead code, unused flexibility, speculative feature. Replacement: nothing.
- `stdlib:` hand-rolled thing PHP or JS already ships. Name the function.
- `native:` code or dependency doing what WordPress core or the browser already does. Name the API or element.
- `reuse:` equivalent helper, class or pattern already in this repo. Name the path.
- `yagni:` abstraction with one implementation, a setting nobody sets, a layer with one caller.
- `shrink:` same logic, fewer lines. Show the shorter form.

Never flag anything under "Never simplified away" above, and never flag a harness case for deletion. End with `net: -<N> lines possible.`, or `Lean already.` when there is nothing to cut.
