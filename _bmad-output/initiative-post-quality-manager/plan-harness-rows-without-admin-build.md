---
title: 'The harness sweep passes with or without the built admin app'
type: 'bugfix'
ticket: ''
created: '2026-11-02'
status: 'done'
baseline_revision: 'e59cb73342553256a165f76caa12b1f73d68e73a'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** 13 rows in 4 harnesses expect buttons that `PostQuality_Admin::app_built()` renders only when the git-ignored `post-quality-manager/admin/build/index.asset.php` exists. Our PC has a build; CI's PHP job has none. So the first CI run on main since 2026-10-15 (run 40899212083, PHP 8.3.36, Ubuntu 26) fails, and because the sweep stops at the first failing file, it never reaches `release-header-harness.php`, which is the only check that guards a release-PR merge. A clean worktree of e59cb73 fails the same 13 rows. With the build copied in, all 4 harnesses pass, so the plugin code is right and the tests are wrong.

**Approach:** Make each of the 13 rows hold in both states, as 6 rows already do (`render-matrix-harness.php` L670, L743, L1164; `free-build-harness.php` L360). Built: the row asserts what it asserts today. Unbuilt: it asserts that no dialog button renders (`pqm-open-setup` absent), and that the other primaries are unchanged.

## Boundaries & Constraints

**Always:** the built branch of every row keeps today's assertion, unweakened, and the unbuilt branch asserts the unbuilt rendering positively (it is not a skip). The build check reads the same path `app_built()` reads. For the shared n8n world, that check lives once in `tests/harness/lib/n8n-world.php`.

**Never:** no change to plugin code, `ci.yml` or the CI loop. No writing a fake `admin/build/` into the plugin folder. No deleting or weakening any row. No change to the 6 rows that already branch.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Built | `admin/build/index.asset.php` present (our PC) | all 22 harnesses: 0 failed; the 13 rows assert the dialog buttons as today | No error expected |
| Unbuilt | clean checkout, no `admin/build/` (CI's PHP job, a fresh worktree) | all 22 harnesses: 0 failed; the 13 rows assert no `pqm-open-setup` and no primary where the dialog button was the primary | No error expected |
| Regression | a row's real assertion broken in either state | that row still FAILs | the sweep exits 1 |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` L659–662 `app_built()`; L1629 `$setup_button` returns `''` unbuilt; L1640–1654: the primary is the setup button in states not-set-up, connected, and set-up (checked, no result: "Run your first review"). Other primaries (Test, Open Post Reviews, Update, Re-import, Save and test) don't depend on the build. Read only.
- `tests/harness/lib/n8n-world.php` L469 `one_primary()` (exactly one `button-primary`), L473 `state_of()`. Shared by the adoption and managed harnesses.
- `tests/harness/free-build-harness.php` L448 (fixed Free workflow: "Run your first review"). `$built_free` (L360) reads the temp Free tree, which `bin/build-free.php` copies along with `admin/build/`.
- `tests/harness/n8n-adoption-harness.php` L126 (set-up body with "Run your first review"), L143 (aggregate `one_primary` over `$status_blocks`).
- `tests/harness/n8n-managed-harness.php` L65 (rotation reply body), L220 (the "no URL saved" branch), L247 (update reply body), L407 (aggregate over `$update_runs`), L429 (start over → state 2).
- `tests/harness/render-matrix-harness.php` L664 (row 1), L676 (row 2), L687 (stored key refusal), L730 (row 4, the exact actions `<p>`), L1171 (fresh site `pqm-open-setup`). Reuse the `$built` variables already defined at L670 and L1164: L664 sits before L670, so move that definition up or read it once.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/lib/n8n-world.php` -- add one build check, read once at the path `app_built()` uses, and let `one_primary()` also accept zero primaries when the app is unbuilt and the block is in a setup-button state (not-set-up, connected or set-up) -- the aggregate rows and the per-row `one_primary` calls then hold in both states without rewriting each one.
- [x] `tests/harness/n8n-adoption-harness.php`, `tests/harness/n8n-managed-harness.php` -- in the rows above, make every literal "Run your first review" or setup-button expectation branch on the build check -- keeps the built assertion exact.
- [x] `tests/harness/render-matrix-harness.php` -- branch rows 1, 2, the key refusal, row 4 and L1171 the same way. Unbuilt row 4: the actions `<p>` holds the Test again button alone (secondary), with 0 primaries -- same.
- [x] `tests/harness/free-build-harness.php` L448 -- branch on `$built_free` -- same.

**Acceptance Criteria:**
- Given the repo with its local build, when every harness runs, then each prints `0 failed`.
- Given a clean worktree at the branch head (no `admin/build/`), when every harness runs, then each prints `0 failed`.
- Given either state, when a row's expected button string is deliberately changed by one character, then that row fails (spot-check one row per file, then revert).

## Implementation Notes

- `lib/n8n-world.php`: `APP_BUILT`, a constant read once after the plugin load from `POSTQUALITY_PLUGIN_DIR . 'admin/build/index.asset.php'` (the call `app_built()` makes). Built, `one_primary()` is unchanged (exactly 1). Unbuilt, it also asserts no `pqm-open-setup` anywhere in the body, then accepts 1 primary, or 0 when `state_of()` is `not-set-up`, `connected` or `set-up`. The `pqm-open-setup` clause makes every unbuilt call a positive check, so it is not a skip.
- `n8n-managed-harness.php` needed no edit. None of its five rows (L65, L220, L247, L407, L429) carries a literal setup-button string; each only calls `one_primary()`, which now holds in both states (Task 1's "without rewriting each one"). So 4 files changed, not 5.
- `n8n-adoption-harness.php` L126, `free-build-harness.php` L448 and the five `render-matrix-harness.php` rows: built, the old expression is used exactly. Unbuilt, the row asserts no `pqm-open-setup` and 0 primaries. Row 1 also asserts there is no actions line. Row 4 asserts the exact actions `<p>` with Test again alone. Each label names the branch that ran (`built: …` / `unbuilt: …`), as L745 and L875 already did.
- `render-matrix-harness.php`: the `$built` read moved from L670 to just above row 1; L743 `$built_here` and L1164 `$built` are left alone (the "no change to the 6 rows" boundary). The L1171 row now renders the fresh page once, into `$fresh_page`, instead of twice. The input and assertions are the same.
- Verification: built (repo): all 21 harnesses `0 failed` (`fs-stub.php` prints nothing, as before). Unbuilt (clean `git worktree` at e59cb73 + the 4 files): all 21 `0 failed`, and the CI loop (`php "$f" || break`) runs to the end. Spot-check, one-character mutations in the scratch worktree, each in both states: n8n-world, adoption, render-matrix row 4, free-build L448. Every mutation failed its row(s) and exited 1, and the managed rows failed through the n8n-world mutation. For the built run, the real build was copied into the scratch worktree only, then the worktree was removed. `php -l` is clean on all 4 files, and they keep LF endings.
- Review pass 1 patches, 4 of them. (1) Unbuilt, `one_primary()` now wants exactly 0 primaries in `not-set-up` and `connected`, at most 1 in `set-up` and exactly 1 elsewhere, keeping the `pqm-open-setup` clause; its docblock says so. (2) Unbuilt, free-build L448 and adoption L126 also assert the exact actions `<p>` holding Test again alone. (3) Unbuilt, the render-matrix fresh-site row also asserts `$state_of() === 'not-set-up'` and the sentence "Not connected to n8n yet.". (4) The managed L429 label names the branch that ran, the file's only change. All 4 harnesses print `0 failed` in both states (built: the repo; unbuilt: an own fresh worktree, `wt-fix2`, removed after). Spot-check mutations, run unbuilt:
  - First build, both states: "Run your first review" → "Run your first reviex" (adoption L126, free-build L448, render-matrix row 4); "Test again" → "Test agaim" (render-matrix row 4's unbuilt `<p>`); `return $primaries === 1;` → `=== 2` (built); `'set-up'` → `'set-uq'` (unbuilt); "!str_contains(…'button-primary')" → "str_contains" (adoption L126); free-build's `=== 0` → `=== 1`.
  - This pass: `return $primaries === 0;` → `=== 1` (2 managed rows fail); "Test again" → "Test agaim" in the new adoption and free-build `<p>` clauses (each row fails); "Not connected to n8n yet." → "…yet!" in the fresh-site row (it fails). Each failing harness exits 1.
  - Exception: `return $primaries <= 1;` → `<= 0` fails nothing, because no n8n row renders a checked `set-up` block with results. The at-most-1 bound in `set-up` is untested there; render-matrix's "4 set up, results stored" row pins Open Post Reviews instead.

## Plan Change Log

## Review Triage Log

Pass 1, 2026-11-02, Quick lens (Opus): 6 findings: low 4, false 1, rejected (plan edit) 1; 4 patched.
- low, patch: `lib/n8n-world.php` `one_primary()` unbuilt accepts 0 or 1 primary in not-set-up, connected and set-up, so the managed and aggregate rows never pin "none". In not-set-up and connected the setup button is the only primary the code renders (`class-pqm-admin.php` L1640–1642, secondaries are never `button-primary`), so unbuilt the count there is exactly 0; set-up stays at most 1 (Test the connection unchecked, Open Post Reviews with results). Patch: exactly 0 in those two states, at most 1 in set-up, exactly 1 elsewhere.
- low, patch: AC3's one-character mutation leaves the unbuilt branches of free-build L448 and adoption L126 green: they assert only absence (`pqm-open-setup`, `button-primary`), and a mutated needle makes an absence check vacuous (reviewer's runs: 91/0, 88/0). Shared positive clauses (state, sentence, version line) kept them from being skips. Patch: unbuilt, assert the exact actions `<p>` with Test again alone, as render-matrix row 4 does.
- low, patch: render-matrix fresh-site row, unbuilt branch: all three clauses are absence checks, so an empty page passes, against the frozen "asserts the unbuilt rendering positively". Patch: a positive clause on the rendered state.
- low, patch: managed L429's label says "one primary" while the unbuilt run holds none, so CI's log states an unchecked claim. Patch: the label names the branch that ran.
- rejected (fix edits this plan): the Verification line says 5 touched files and Task 2 is ticked for `n8n-managed-harness.php`, which the build left unedited. The L429 patch above edits that file, so both now hold.
- false: "the plan says the worktree was removed, but one is still registered". The implementer removed its own worktree. The registered `scratchpad/wt-clean` is the orchestrator's reproduction worktree from before the plan; it is removed at step 5.

## Design Notes

Skipped: CI's PHP job downloading the build artifact so CI tests the built branch. Add it when a row only the built state can prove regresses unseen. Each branch is tested somewhere: unbuilt on CI and in fresh worktrees, built on the dev PC; `render-matrix-harness.php` L870 pins the `app_built()` gate in source.
Skipped: running every harness file before the sweep fails (CI hid 3 of the 4 failing files). Add it if a sweep failure hides another one again.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" | tail -1; done` (repo, built) -- expected: every line `N passed, 0 failed`.
- the same loop in a clean `git worktree add --detach <scratchpad>/wt <branch head>` -- expected: the same; then `git worktree remove` it.
- `php -l` on the 5 touched files -- expected: no syntax errors.
