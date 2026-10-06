---
title: 'README and the capability behavior tell the same truth'
type: 'feature'
ticket: '16'
created: '2026-10-06'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'f5d520c550aa6b99135c92c9b56d5b37e00fb7cc'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The README and the code disagree in three places (retro A10). (1) The README matrix gates Agent-Specific Overrides and Automated Reviews on `pqm_manage_agents`, but the code renders them editable, and saves them, for anyone who reaches Settings. A role remapped to hold `pqm_manage_settings` without `pqm_manage_agents` can edit them. (2) The README says `pqm_*` caps go to "Administrator/Editor/Author by default", which is wrong per cap. (3) Single-site-only support is never stated, though the quota user meta is network-global against a per-site setting.

**Approach:** Align the code to the documented matrix (owner default decision 2026-10-02). Both sections render only with `pqm_manage_agents`: hidden otherwise, and role-hidden beats plan-locked as everywhere else. `sanitize_settings()` takes them from the request only when the saver has `pqm_manage_agents`, the site is pro and the section posted; otherwise it carries the stored values (bug 19's carry, one more condition). The README gets the real capability → default-roles table from `PQM_Features::capabilities()`, a harness keeps the two equal, and the README states single-site support explicitly.

## Boundaries & Constraints

**Always:** the carry stays wholesale per section (an absent `cron_enabled` never reads as unchecked). Today's behavior holds for Editors and Admins (both hold `pqm_manage_agents` by default). README copy follows `docs/brand.md`.

**Never:** change `settings_page_capability()` (a settings-only role still reaches the page for its own sections). Change `capabilities()` or `CAPS_VERSION`. Add multisite support.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Settings-only saver, pro | caps `[pqm_manage_settings]`, POST carries cron + overrides | Admin-only keys applied; every cron key and `agent_prompts` carried byte-identically | — |
| Settings-only render | same caps, pro and free | Neither section heading, fieldset nor field renders; the admin sections do | — |
| Editor / Admin | default caps | Exactly today's render and sanitize (existing cases green) | — |
| README drift | README table ≠ `capabilities()` | Harness fails | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` -- `sanitize_settings()` plan-gated block (after bug 19): `$is_pro && array_intersect_key(...)` for cron, `if ($is_pro)` for the agent loop. Add `current_user_can('pqm_manage_agents')` to both conditions, and fix the comment. `render_settings()`: `$can_manage_settings` at L497. Add `$can_manage_agents` and wrap the Overrides block (from the L679 comment) and the Automated Reviews block (L826 comment through its closing `</fieldset>`/table) in `if ($can_manage_agents)`. `admin/js/pqm-admin.js` guards each block on its own elements, so no JS change is expected; confirm with a grep.
- `README.md:58-66` -- "Admin pages & capabilities": replace the blanket parenthetical with a table *Capability | Default roles* rendered exactly from `capabilities()` (5 rows: `pqm_view_reviews` Administrator, Editor, Author; `pqm_run_free_agents` same; `pqm_run_paid_agents` Administrator, Editor; `pqm_manage_agents` same; `pqm_manage_settings` Administrator). Keep "remappable with any role editor". Settings row: say both sections need `pqm_manage_agents` and are locked until Pro. Add a short "Single-site only" note: multisite unsupported, the quota usage counter is network-wide user meta.
- `tests/harness/sanitize-matrix-harness.php` -- new case: settings-only saver on pro posting cron + overrides → carried (=== stored); admin-only keys applied.
- `tests/harness/render-matrix-harness.php` -- new row: `['pqm_manage_settings']` on pro and free → no `Agent-Specific Overrides`, no `Automated Reviews (Cron)`, no locked fieldset; admin sections shown.
- `tests/harness/caps-harness.php` -- new row: parse the README capability table and assert it equals `capabilities()` (role names capitalized in the README, so map them).
- `memory/freemium-and-roles.md` -- "Settings sections" bullet: the two sections need `pqm_manage_agents` (hidden otherwise); the carry condition gains it; README table guarded by caps-harness; single-site stated.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- render gate + sanitize condition
- [x] `README.md` -- capability table + single-site note
- [x] `tests/harness/sanitize-matrix-harness.php`, `render-matrix-harness.php`, `caps-harness.php` -- the matrix rows
- [x] `memory/freemium-and-roles.md` -- facts

**Acceptance Criteria:**
- Given a `pqm_manage_settings`-only user, when they open and save Settings, then the two agent sections neither render nor change.
- Given the README, when the harnesses run, then its capability table matches `capabilities()`, and the single-site statement is present.

## Implementation Notes

- `sanitize_settings()`: one `$can_edit_agents = current_user_can('pqm_manage_agents') && is_pro()` feeds both the cron and the agent-loop conditions. The old `$is_pro` local is gone, and the comment now names all three conditions.
- `render_settings()`: one `if ($can_manage_agents)` wraps both sections, from the Overrides comment to the Automated Reviews closing fieldset. The fieldset and upgrade copy stay inside it, so role-hidden beats plan-locked.
- JS: every block uses `querySelectorAll(...).forEach` or guards on its own element, so no change was needed.
- README: the Settings row also names `pqm_manage_settings` as an entry capability (true per `settings_page_capability()`) and lists License among the admin sections. Contributor is noted as holding no capability.
- caps-harness Row 7 also asserts the single-site statement (second acceptance criterion).
- Verified that the new sanitize rows (7 checks) and render rows (8 checks) fail against the baseline `class-pqm-admin.php`, and that a README role edit fails Row 7.
- Review fix: the Dashboard cron nudge (`GET /dashboard`) and its dismiss handler (`render_dashboard_app()`) are now gated on `pqm_manage_agents`, the cap of the `#pqm-cron-settings` section they link to. `can_manage_settings` is unchanged. Before this fix, a settings-only role got the nudge without seeing the section, and Editors never got it. `rest-posts-harness.php` pins the new gate. Caps Row 7 now compares the README cells exactly as written, and fails on duplicate or extra `pqm_` rows.

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 1 medium, 2 low. All patched, then verified (all 10 harnesses exit 0).
- medium, patch: the Dashboard cron nudge and its dismiss handler were gated on `pqm_manage_settings`, but the `#pqm-cron-settings` section they link to now needs `pqm_manage_agents`. A settings-only role was sent to a hidden section, and Editors never got the nudge. Both now gate on `pqm_manage_agents`; the rest-posts nudge rows pin settings-only/author → none and Editor → nudge.
- low, patch: the memory "Settings sections" opener said the page moved to `pqm_manage_agents`, contradicting `settings_page_capability()` and the README. Reworded.
- low, patch: caps Row 7 lowercased README roles and merged duplicate rows. It now compares the exact cell (capitalized, ", "-joined) and the ordered row list.

## Verification

**Commands:**
- `php -l` on touched PHP; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green
