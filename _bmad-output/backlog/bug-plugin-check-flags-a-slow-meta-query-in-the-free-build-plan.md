---
title: 'Plugin Check flags a slow meta query in the Free build'
type: 'bugfix'
ticket: '139'
created: '2026-11-19'
status: done
baseline_revision: '613bca077a8795bed2871eef22ee6b379f3a049e'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** In `PostQuality_Activator::migrate_user_meta()` an own-line `phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key` sits above `foreach ( get_users( [ 'meta_key' => $old, 'fields' => 'ids' ] ) as $user_id ) {`. Freemius's re-print of the Free build splits that two-item array over its own lines (4.0.5's build: the ignore on L238, `'meta_key'` on L240), so the ignore no longer covers the flagged line and Plugin Check reports one warning beyond the accepted list (story 38, P068 and P035).

**Approach:** Replace the ignore with a `phpcs:disable` / `phpcs:enable` pair for that sniff, each on its own line where the re-print keeps a comment (before the `foreach`, and as the first line inside its body), so the suppression spans the re-printed lines; the migration's code is unchanged. Extend `tests/harness/reprint-comments-harness.php` with a third rule: an own-line `phpcs:ignore` whose next line holds an array literal of two or more items fails (the re-print moves the items to later lines), with self-test rows for the flagged and the kept form.

</frozen-after-approval>

## Implementation Notes

- Oneshot route: one call's comment layout in one file plus one harness rule (3 files with this plan, under 100 lines, no schema, REST, payload or template change).
- The plugin's other own-line `phpcs:ignore` comments (17, grepped 2026-11-19) sit above statements without a multi-item array on their line, so the new rule flags only this one on the baseline.
- `bmad-build` applied by hand from the workspace root (CLAUDE.md, owner rule 2026-10-16).
- Surprise: the first explanatory comment said "not a phpcs:ignore"; the harness's rule 2/3 matches the literal `phpcs:ignore` in any comment, so it read the note as an ignore. Reworded to "a one-line ignore".
- Files: `includes/class-pqm-activator.php` (the pair around the migration's `get_users()` loop head, an explanatory line), `tests/harness/reprint-comments-harness.php` (rule 3, two kept and two flagged self-test rows, the activator's pair pinned). Rule 3 flagged only the activator on the baseline (42 passed, 2 failed); 44 passed after the fix.
- No release-notes line: nothing a site owner meets changes (a Plugin Check warning in the build, no behaviour).

## Review Triage Log

**Pass 1 (quick lens, 2026-11-19):** high 0, medium 1, low 4, false 0, maybe-false 0.

- `memory/delivery-pipeline.md` does not record the 4.0.5 re-print of multi-item arrays — medium, patched: the Definition of Done asks for durable facts in memory in the same change; the "Comments in the re-print" passage gained the multi-item array fact, the disable / enable pair and rule 3.
- `reprint_findings()` docblock stale (rule 3 not named) — low, patched: one-line doc correction.
- Rule 3 inherits rule 2's loose `str_contains('phpcs:ignore')`, so prose mentioning the word counts as an ignore (it fired on this fix's own comment) — low, patched: the match is anchored to a comment that opens with the directive, as PHPCS reads it; a self-test row keeps prose above a multi-item array passing.
- The activator check pinned only that both directives exist somewhere — low, patched: it now checks the disable before the `get_users( [ 'meta_key' => $old` call, the enable after it, once each, and the file list asserts the activator is read.
- The literal detection takes `[` after a namespaced constant or a destructuring `[` as an array literal — low, rejected: no plugin line hits it, nothing shows the re-print splits destructuring, and the fix adds branches for an unseen case.

## Verification

**Commands:**
- `php tests/harness/reprint-comments-harness.php` -- expected: 0 failed on the fix; the new rule flags `class-pqm-activator.php` on the baseline
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed (the prefix harness covers the migration)
- `php -l post-quality-manager/includes/class-pqm-activator.php` -- expected: no syntax errors

**Manual checks (if no CLI):**
- Story 38's re-walk on the release: Plugin Check on the Free release zip lists the accepted warnings only (P068, P035).
