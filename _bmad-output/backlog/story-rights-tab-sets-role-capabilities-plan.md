---
title: 'An Administrator sets each role''s plugin rights on a Roles tab, and View as is retired'
type: 'feature'
ticket: '42'
created: '2026-10-08'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
baseline_revision: 'e974e0637551b6b1b8fa999aa0314f6df717d9a5'
context: ['{project-root}/docs/brand.md', '{project-root}/_bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/access-matrix.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The toolbar "View as" switch simulates a role from outside the plugin and was reported as stranding the Administrator; there is no in-plugin way to set what each role may see and run.

**Approach:** Add a fifth Settings tab, "Roles", rendered for core `manage_options` holders: a table of every site role against the five plugin capabilities, applied to the roles when the one Settings form is saved. Remove View as entirely. Activation and upgrades stop resetting the roles an Administrator set.

**Decision (owner, 2026-10-08):** the site's roles are the only store (option B). Save writes the roles directly; nothing is stored in `pqm_settings` and there is no carry rule. `PQM_Features::register_capabilities()` grants a capability's shipped defaults only when no role holding `manage_options` has it yet. Plan kept whole above the token guideline (owner, 2026-10-08).

## Boundaries & Constraints

**Always:** Tab label "Roles", last after Usage & Limits; headline "What each role can do"; rows = role display names from `wp_roles()`, custom roles included; columns = View reviews · Run Review and Key Takeaways · Run paid agents · Manage agents · Manage settings — no `pqm_` slug shown. Rows of roles holding `manage_options` render all-checked and disabled, and Save re-grants them all five (capability-keyed, never a role name). Pro only: on a free site the table sits in the existing locked pattern (lock icon, `pqm-locked-note`, `<fieldset disabled class="pqm-locked-section">`) and Save writes no role. Roles are written only when the saver holds `manage_options`, the site is Pro and the request posted the tab. Only roles are written — never per-user grants, never core capabilities. A role whose state is unchanged gets no `add_cap`/`remove_cap` call. Story 27 tab mechanics unchanged (one form, one save, hash survives save, no React).

**Never:** No change to the gate, the quota rule, any REST route or permission callback, the n8n template. No new capability, no `CAPS_VERSION` bump. No confirm dialog. No preview-as-a-role control. No new JS file; no dependency.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Admin, Pro, change | uncheck Author · View reviews, Save | Author lacks `pqm_view_reviews`; other roles byte-identical | — |
| Pinned row | POST omits or unchecks Administrator caps | Administrator still holds all five | — |
| Custom role | site role `writer`, check Run Review and Key Takeaways | `writer` gains `pqm_run_free_agents` only | — |
| Editor saves | `pqm_manage_agents`, no `manage_options` | no Roles tab in markup; roles unchanged | — |
| Free site | Admin saves | tab shows current grants, locked; roles unchanged | — |
| Programmatic save | `update_option('pqm_settings')` (setup dialog) | roles unchanged | — |
| Re-activation | Author stripped of View reviews by the tab | still stripped after `activate()` / `maybe_upgrade()` | — |
| Fresh install | no role holds any `pqm_` cap | shipped default map granted (today's behavior) | — |
| Hint | a row's View reviews unchecked | inline hint "Without this, the role does not see Dashboard or Post Reviews." visible beside it | — |
| View as leftover | user meta `pqm_view_as = author` | no toolbar node, caps unfiltered; uninstall still deletes the meta | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` -- `render_settings()` L550+: `$tabs` array L566 (add `roles` when `current_user_can('manage_options')`), `$panel_open` helper, Usage panel L1041–1056, `submit_button()` L1058; locked pattern to copy L788–798. `sanitize_settings()` L331: add the roles write after the existing branches (it holds the posted input; reuse `PQM_Features::is_pro()`).
- `post-quality-manager/includes/class-pqm-features.php` -- `capabilities()` L30 (keys = column order), `register_capabilities()` L45 (change per Q1).
- `post-quality-manager/includes/class-pqm-activator.php` -- `activate()` L16 and `maybe_upgrade()` L33 both call `register_capabilities()`; no change needed under B.
- `post-quality-manager/admin/js/pqm-admin.js` L37–106 -- tab switching is generic over `.nav-tab` / `#pqm-panel-<slug>`: no change.
- `post-quality-manager/admin/css/pqm-admin.css` L15–18 -- tab styles; add the CSS-only hint rule here.
- Delete: `post-quality-manager/includes/class-pqm-view-as.php`; its require at `post-quality-manager/post-quality-manager.php:42` and hooks at :65–67 and :79–81; `tests/harness/view-as-harness.php`. Keep `post-quality-manager/uninstall.php:26–27`, and reword its comment to "leftover from the retired View as".
- `README.md:83` -- replace the View as paragraph with the Roles tab one; the capability table that `caps-harness.php` checks stays.
- Harnesses: `tests/harness/caps-harness.php` (role stubs L26–67; Row 1 fresh grant; Row 8 L313–348 asserts "lower version re-grants a hand-removed cap" — rewrite to the new rule); `tests/harness/render-matrix-harness.php` (tab markup L757–782); `tests/harness/sanitize-matrix-harness.php` (L125+). CI runs `tests/harness/*.php` by glob.
- `tests/js/settings-tabs.test.js` -- no change expected; run it.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-features.php` -- `register_capabilities()` grants a capability's defaults only when no `manage_options` role holds it (B) -- activation and upgrade stop resetting choices.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- Roles tab and panel with hidden `pqm_settings[roles_posted]` marker, per-row hidden `pqm_settings[roles_shown][<role>]` (the caps the row rendered checked, comma-separated), per-cell checkbox `pqm_settings[roles][<role>][]=<cap>`, hint span after the View reviews checkbox linked by `aria-describedby`, paid-agents quota note under the table, Free lock; `sanitize_settings()` applies only the cells the Administrator changed (see Design Notes) and stores none of the three keys -- the feature.
- [x] `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-role-hint` shown only when the preceding checkbox is unchecked -- hint with no JS.
- [x] `post-quality-manager/includes/class-pqm-activator.php` -- comments only: file header and `register_capabilities()` docblock say defaults are granted only for a capability no `manage_options` role holds yet -- docs truth (review finding 6).
- [x] View as removal: delete the class, its require and hooks, and its harness; reword the uninstall comment -- retire the feature.
- [x] `README.md` -- Roles tab paragraph replaces View as -- docs truth.
- [x] `tests/harness/caps-harness.php`, `render-matrix-harness.php`, `sanitize-matrix-harness.php` -- one row per matrix line, including the story's criterion 10 invariants and the stale-page rows in Design Notes; the no-View-as check looks for `PQM_View_As`, `class-pqm-view-as`, `filter_caps` and the `pqm_view_as` switch action, never for generic hook names such as `user_has_cap` or `admin_bar_menu` -- lock the behavior.
- [x] `memory/freemium-and-roles.md` -- Roles tab bullet from "decided" to built facts; drop the View as bullet -- canonical memory.

**Acceptance Criteria:**
- Given an Administrator on a Pro site, when they open Settings, then "Roles" is the last tab, and the tab and the panel follow the story-27 keyboard and hash behavior.
- Given any role change saved, when that role's user loads any plugin page or REST route next, then menus, sections and refusals follow without signing in again.

Skipped: per-user grants and a stored matrix — add if a site needs to restore a set of roles wholesale.

## Implementation Notes

- Attempt 1 (2026-10-08) implemented the plan; the quick review sent it back (loop 1, see Plan Change Log). Code was reverted to the baseline and the attempt kept as a reference patch.
- Attempt 2 applied that patch, then changed only what loop 1 names. `save_roles($posted, $shown)` writes a cell only where the posted box differs from the row's `roles_shown`, and skips roles without one. Pinned rows are re-granted all five. On Free, pinned rows render current grants. The hint is linked by `aria-describedby`. Copy follows Design Notes. Activator comments are corrected. Caps harness Row 10 checks View-as identifiers only.
- Surprise: two render-harness checks from attempt 1 flattened the checkbox grid with `array_merge` on string keys, so they only saw the last row. Attempt 2 fixed them.
- Verified by the orchestrator: `php -l` clean; 14 of 14 harnesses green (caps 276, render-matrix 295, sanitize-matrix 224); Jest 26 of 26; `node --check` clean. The matrix audit maps all 10 rows plus the stale-page rows to checks that ran.
- Not run: the manual pass on Local.

## Plan Change Log

- **Loop 1 (2026-10-08), quick review.** Trigger: any Pro Administrator save, from any tab, rewrote every role to the page's load-time state, undoing role-editor changes made meanwhile and stripping roles created since — the failure ADR 0012 rejected option A for. Amended: Tasks (admin, activator comments, harness), Design Notes (stale-page diff rule, Free pinned rendering, copy). Known-bad state avoided: posted-state-wins writes. Folded in from the same review: Free pinned rows misstating grants, "rights"/`manage_options` wording in visible copy, stale activator comments, a harness check banning generic hook names. KEEP: everything else in the first attempt, saved as a reference patch at `C:/Users/vhe/AppData/Local/Temp/claude/c--Git-Post-Quality/2359c747-b384-41d8-9fd8-397113bde359/scratchpad/story42-attempt1.patch` (apply it, then change only what this entry names): `register_capabilities()` admin-role guard; `role_cap_labels()`; the tab entry and panel markup with the locked pattern; marker inside the locked fieldset; idempotent per-cell writes; CSS-only hint; View as removal with the uninstall line kept; README paragraph; harness rows 8–10 and Case 15 structure. `memory/freemium-and-roles.md` already carries the first attempt's built facts: reconcile it with the final code.

## Review Triage Log

Loop 1, quick lens — high 0 · medium 1 · low 5 · false 1 · maybe-false 0.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | Pro Administrator save rewrites every role to load-time state; roles created since lose all caps | medium | bad_plan | Panel always in the form; marker unconditional; `save_roles()` iterates every role and treats a missing row as all-unchecked. Plan's marker note did not foresee it. Loop back. |
| 2 | Free site: pinned rows show all five checked even when the role lacks one | low | bad_plan (folded) | `checked($pinned \|\| …)` ignores plan; story criterion 5 and the Free matrix row say current grants. Direct correction, carried into Design Notes. |
| 3 | A saver with a direct per-user `manage_options` on an unpinned role can strip its own Settings access | low | rejected | Real but needs a per-user `manage_options` grant on a non-Administrator role, which is rare; the fix adds a branch to `settings_page_capability()`. |
| 4a | Accepted ADR 0012 rewritten in place | false | rejected | The owner approved amending ADR 0012 before merge when choosing option B; it exists only on this unmerged branch, history kept in git. |
| 4b | ADR 0012 Consequences still cites the carry rule and the stored matrix | low | doc fix | Stale sentence; corrected directly (docs are outside the code revert). |
| 5 | Visible copy says "rights" and "manage the site's options" | low | bad_plan (folded) | Owner label decision keeps "rights" internal; brand.md asks for no jargon. Copy fixed in Design Notes. |
| 6 | Stale docs: access-matrix line 15, initiative line 76, story assumption and risk notes, activator comments | low | doc fix + bad_plan (folded) | Each line contradicts option B; docs corrected directly, activator comments added as a task. |
| 7 | Caps harness Row 10 bans `user_has_cap` / `admin_bar_menu` plugin-wide | low | bad_plan (folded) | A future legitimate hook use would fail CI; narrow to View as identifiers. |

Loop 2, quick lens — high 0 · medium 0 · low 4 · false 0 · maybe-false 0.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 8 | A row pinned at load posts `roles_shown` = all five while its disabled boxes post nothing; if the role loses `manage_options` before Save, all five are removed | low | patch | Verified in `save_roles()`: unpinned at save, `$was` = all five, `$checked` = []. Rare, but the fix (no `roles_shown` on pinned rows) is a direct correction. |
| 9 | Settings page falls back to `pqm_manage_agents`, so a `manage_options` user whose role lost both plugin caps never reaches the Roles tab | low | patch | `settings_page_capability()` L325 unchanged by the diff; contradicts the spec constraint "never behind a plugin capability". Fix: last-resort value `manage_options`, which changes nothing for users lacking it. Also covers rejected finding 3. |
| 10 | Intro copy and README promise locked rows "always keep everything" | low | patch | False on Free (no re-grant) and on Pro until the next save. Reworded to "you cannot take anything away from them"; supersedes the Design Notes intro sentence. |
| 11 | `memory/freemium-and-roles.md` still says four tabs | low | doc fix | Stale against the built fifth tab; corrected directly, together with the page-gate fallback sentence. |

## Design Notes

The marker tells "tab posted with every box cleared" apart from "tab not posted". The marker is absent inside a disabled fieldset (Free), absent for non-admin savers, and absent from programmatic `update_option` calls, so one check covers all three carry cases. Writing roles inside `sanitize_settings()` is a side effect. WordPress can run that callback twice on the first save of a new option. The write is idempotent and skips unchanged cells, so a second run is harmless.

**Stale page (review loop 1).** The panel sits in the one form, so every Administrator save on Pro posts it, from any tab. Writing the posted state would undo any role change made after the page loaded, and strip a role created since. So each row also posts what it rendered (`roles_shown`), and Save writes a cell only where the posted box differs from what that row showed. A role with no `roles_shown` entry (created after load, or forged) is never touched. A pinned role still ends with all five. Harness rows: a cap granted elsewhere after load survives a save that did not touch that cell; a role absent from the POST keeps its caps; an Administrator who unchecks a box still removes it.

**Pinned rows on Free.** All-checked-and-disabled is the Pro rendering (story criterion 2, "on a Pro site"). On Free, pinned rows stay disabled but show the role's current grants, like every other row (story criterion 5, matrix row "Free site").

**Copy.** Never "rights" or `manage_options` wording in visible text (story 42 label decision, docs/brand.md jargon rule). Use: headline "What each role can do"; intro "Saving applies these choices to every user with that role. Locked rows belong to roles that run the whole site, such as Administrator: they always keep everything."; Free note "Available with Pro. Upgrade to choose what each role can do — until then, every role keeps what is shown here."; quota note and hint as before.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || exit 1; done` -- expected: every harness passes
- `php -l` on each touched PHP file -- expected: no syntax errors
- `node --check post-quality-manager/admin/js/pqm-admin.js && npm run test:unit` -- expected: green

**Manual checks (if no CLI):**
- On Local: Admin Pro unchecks Author · View reviews and saves. An Author account then sees no Dashboard or Post Reviews. The Administrator row cannot be changed. On a Free site the tab is locked. The View as toolbar node is gone.
