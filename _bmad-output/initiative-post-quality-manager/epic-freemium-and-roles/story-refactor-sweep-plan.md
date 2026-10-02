---
title: 'Refactor sweep'
type: 'chore'
ticket: '7'
created: '2026-10-02'
status: 'done'
baseline_revision: 'a6e9deb80ffa0cfc3af575a07d36d0cc66b39351'
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

**Problem:** The epic's build records name two leftovers. Story 1.2 — the epic's highest-risk story — verified its capability matrix with a scratchpad harness that was never persisted: nothing in `tests/harness/` covers `capabilities()`, register/remove semantics or the `why_not()` role matrix, although AGENTS.md requires a persisted harness per I/O-matrix story and demands the whole suite green on any gate change. And `memory/freemium-and-roles.md`'s Gate bullet still calls `is_pro()` a "stub … until story 3", contradicting the License bullet one line below since story 1.3 shipped.

**Approach:** Cleanup only, per the ticket: persist a capabilities harness reproducing story 1.2's verified matrix, and fix the stale memory sentence. **Owner decision (2026-10-02, ADR 0005):** UI-rendering cleanup (dead GET trigger path, `apply_` branch, unused CSS, inline styles) is excluded — it belongs to the admin-UI rebuild epic. Story 4's rejected non-atomic-quota finding stays rejected; story 6's registry-built-twice observation stays accepted as KISS.

## Boundaries & Constraints

**Always:** No behavior change anywhere — the harness asserts what the code already does (story 1.2's Implementation Notes and review fixes are the expected values, e.g. `remove_capabilities()` strips explicit denies unconditionally). Harness follows the house pattern (`$GLOBALS`-world WP stubs, plain PHP, exit code). The full suite and lints are the story's verify.

**Never:** No change to plugin runtime code, to the n8n template, to tickets/specs, or to any UI file (PHP render, JS, CSS). No new scope — anything discovered beyond the two items becomes a backlog ticket, not a task here.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Default map | Fresh registration | `capabilities()` equals the ADR 0003 access matrix; every cap granted to exactly its default roles | No error expected |
| Missing role | A role absent on the site | `register_capabilities()` skips it, no error | Silent skip |
| Uninstall strip | Roles hold grants + one explicit deny (`cap => false`) | `remove_capabilities()` leaves no `pqm_*` key on any role | No error expected |
| why_not matrix | Admin/Editor/Author/Contributor × 8 commands × plan on/off | Matches story 1.2's verified matrix: role before plan, free fallback for non-registry commands | No error expected |
| Disjoint sets | Any role × plan | `hidden_commands() ∩ gated_commands() = ∅`; hidden + gated + allowed = registry | No error expected |
| Stale memory | Gate bullet read today | No "stub until story 3" claim; delegation to `PQM_License` stated once, consistently with the License bullet | n/a |

</frozen-after-approval>

## Code Map

- `tests/harness/caps-harness.php` -- new, patterned on `cron-gate-harness.php`/`license-harness.php` (mutable `$GLOBALS['tw']`, WP stubs incl. `WP_Role` objects with `add_cap`/`remove_cap`/`capabilities` array, `wp_roles()`, `get_role()`, `current_user_can` reading the stub roles, `apply_filters` honoring `pqm_is_pro`): requires `class-pqm-license.php` + `class-pqm-features.php`; asserts the five code rows of the matrix.
- `memory/freemium-and-roles.md` -- Gate bullet: replace the "stub `is_pro()` behind the public `pqm_is_pro` filter (mu-plugin `__return_true` demos the paid path until story 3)" clause with the post-story-3 fact (delegates to `PQM_License`, filter stays the dev override) — one sentence, no duplication of the License bullet's detail.
- Expected values source: story 1.2's plan (`story-role-capabilities-replace-binary-checks-plan.md`, Implementation Notes + Review Triage Log) — the matrix it verified is the contract.
- Don't touch: all plugin runtime files, every existing harness, `_bmad-output/` tickets, `templates/`.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/caps-harness.php` -- persist story 1.2's capability matrix as a regression harness -- the epic's highest-risk logic gets suite coverage.
- [x] `memory/freemium-and-roles.md` -- de-stale the Gate bullet -- memory says what is true now.

**Acceptance Criteria:**
- Given the full harness sweep (`for f in tests/harness/*.php; do php "$f" || break; done`), when it runs, then every harness passes including the new one — and no plugin file changed (`git diff` touches only the two named files).
- Given story 1.2's verified matrix, when any future change breaks the capability map, register/remove semantics, or the hidden/gated split, then the new harness fails.

## Implementation Notes

- 2026-10-02 — `tests/harness/caps-harness.php` added on the house pattern (mutable `$GLOBALS['tw']`, `WP_Role` stubs with `add_cap`/`remove_cap`/`capabilities`, `wp_roles()`, `get_role()`, `current_user_can` reading the stub roles, `apply_filters` honoring `pqm_is_pro`; requires `class-pqm-license.php` + `class-pqm-features.php`). 173 checks over the five code rows: `capabilities()` equals the ADR 0003 map literally and fresh registration grants each cap to exactly its default roles (idempotent); a role absent on the site is skipped silently; `remove_capabilities()` strips every `pqm_*` key including explicit denies (`cap => false`, the 2026-10-01 review fix) while non-pqm caps survive; the full `why_not()` matrix — Admin/Editor/Author/Contributor × 8 commands (6 registry + `score`/`apply_takeaways` free fallback) × plan on/off, role before plan, `can()` mirrored in every cell, mu-plugin `pqm_is_pro` override still winning; hidden/gated disjoint and hidden + gated + allowed = registry for every role × plan, with exact expected lists per cell. Plan on/off toggled through the real chain (`license_key` → `PQM_License::is_pro()` → filter), not a shortcut flag.
- 2026-10-02 — `memory/freemium-and-roles.md` Gate bullet: the "stub `is_pro()` … until story 3" clause replaced with "`is_pro()` delegating to `PQM_License` since story 3" — one sentence; the filter/dev-override detail stays with the License bullet, no duplication.
- No plugin runtime file, existing harness, template or UI file touched; nothing beyond the two items surfaced that would need a backlog ticket.
- Verified: `php -l` clean on the new harness; full sweep green — caps 173, cron-gate 24, license 21, quota 32, render-matrix 38, sanitize-matrix 86, all 0 failed; `git diff --stat` touches only `memory/freemium-and-roles.md` (harness + this plan are new/untracked files).

## Plan Change Log

## Review Triage Log

### 2026-10-02 — quick lens, pass 1 (verdicts: 0 high, 0 medium, 2 low, 0 false, 0 maybe-false)

- **low / patch** — `tests/harness/caps-harness.php` Row-4 `can()` mirror loop: assertions only registered on failure, so 64 mirror checks were uncounted/invisible on green runs and the documented "109 checks" excluded them. Verified in the diff (fail-only `if` guard); the lens's mutation test proved they fire on breakage, so AC 2 held regardless. Patch applied by the implementer: counted per-cell `check()`; harness now 173/173, plan count updated.
- **low / patch** — `memory/freemium-and-roles.md` Gate bullet: the rewritten sentence's "(still the dev override)" parenthetical duplicated the License bullet's filter-override fact, which the plan's Code Map ("no duplication") rules out. Patch applied: Gate bullet states the delegation only.
- Lens's empirical verification beyond the findings: full sweep green, diff containment exact (two files), and three mutation tests (role/plan order flip, deny-skipping strip, hardwired `can()`) each made the harness fail — the regression tripwire works.

## Design Notes

The sweep is deliberately this small: every other review finding in the epic was patched in its own story, rejected with cause, or (UI rendering) reassigned to the rebuild epic by ADR 0005. Persisting the 1.2 harness is cleanup, not new scope — the checks existed and ran in-session on 2026-10-01; this writes them down where AGENTS.md says they must live.

## Verification

**Commands:**
- `php -l tests/harness/caps-harness.php` -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all six harnesses green
- `git diff --stat` -- expected: exactly `tests/harness/caps-harness.php` + `memory/freemium-and-roles.md` (+ this plan)

**Manual checks (if no CLI):**
- None — no runtime behavior changes.
