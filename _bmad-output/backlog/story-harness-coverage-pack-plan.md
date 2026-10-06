---
title: 'The untested enforcement paths get harness coverage'
type: 'chore'
ticket: '12'
created: '2026-10-06'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '42e38825a95b9545df469c6710b50ba59da39bdc'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Several enforcement paths are untested, or only grepped (retro A6, verification-gap findings 1–3 and 5). No harness covers `check_can_trigger()`, `PQM_Activator::maybe_upgrade()`, `check_view_reviews()` for an `edit_posts`-only user, or a free-site save that smuggles `agent_prompts`. The `option_page_capability_pqm_settings_group` filter is checked with a regex over the source.

**Approach:** Execute each path in an existing harness. Each check must fail under the named mutation: a typo'd cap, an inverted version comparison, a posted-else-stored `agent_prompts` refactor, a wrong filter callback. Test code only, no plugin change.

**Decisions (owner's standing approval of default proposals, 2026-10-06):**
- The `quota_remaining` passthrough pin is already in place (`quota-harness.php`: 14 after a 2xx, null when exempt, unchanged after an n8n 500, response keys pinned). Re-verify it and record it in Implementation Notes; add no duplicate.
- Retro A3 (shared stub bootstrap) stays out, per the ticket's 2026-10-06 note.

## Boundaries & Constraints

**Always:** stubs keep WP's real arity (lessons 2026-10-05). Each new check runs the real function, never greps its source. Prove each mutation makes the suite fail, then restore the file (record each in Implementation Notes).

**Never:** edit plugin code under `post-quality-manager/`. Add a test framework or dependency. Create a new harness file when an existing one already has the stubs.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Trigger route, free cap | caps `[pqm_run_free_agents]` | `check_can_trigger()` true | — |
| Trigger route, paid cap | caps `[pqm_run_paid_agents]` | true | — |
| Trigger route, capless | caps `[]` and `[edit_posts]` | false | — |
| View, edit_posts only | caps `[edit_posts]` | `check_view_reviews()` false | — |
| Upgrade, option absent | no `pqm_caps_version` | default map granted, option = `CAPS_VERSION` | — |
| Upgrade, equal version | option = `CAPS_VERSION`, a role edited by hand | no grant: roles byte-identical, no option write | — |
| Upgrade, lower version | option = `CAPS_VERSION - 1` | re-grants, stores `CAPS_VERSION` | — |
| Free-save smuggle | free site, agents-holder POSTs `agent_prompts` with new values | `agent_prompts` === stored | — |
| Settings save filter | `PQM_Admin::register_settings()` | recorded `add_filter('option_page_capability_pqm_settings_group', [PQM_Admin, 'settings_page_capability'])` | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php:121-131` `check_can_trigger()` (any registry capability) and `:111` `check_view_reviews()`. Drive both from `tests/harness/rest-posts-harness.php`, which already has the caps world (`$GLOBALS['tw']['caps']`) and the "No capability" rows near L229-234.
- `post-quality-manager/includes/class-pqm-activator.php:33-45` `maybe_upgrade()` → private `register_capabilities()` (grants via `PQM_Features::register_capabilities()`, `update_option('pqm_caps_version', CAPS_VERSION)`). Use `tests/harness/caps-harness.php` (WP_Role stubs, roles world). It needs recording `get_option`/`update_option` stubs (store in `$GLOBALS['tw']`, count writes) and must require `class-pqm-activator.php`; check that file's other dependencies before requiring it.
- `post-quality-manager/admin/class-pqm-admin.php` `sanitize_settings()` free/carry branch. Add the smuggle case to `tests/harness/sanitize-matrix-harness.php` (Case 3 covers free saves that omit `agent_prompts`, not ones that post it).
- `post-quality-manager/admin/class-pqm-admin.php:290-302` `register_settings()`: `register_setting()` + `add_filter('option_page_capability_pqm_settings_group', …)`. In `tests/harness/render-matrix-harness.php`, make the `add_filter` stub (L16) record its calls, add a `register_setting` stub if missing, call `PQM_Admin::register_settings()`, and assert the recorded filter. Replace the regex check at ~L236 ("option save uses settings_page_capability").
- `tests/harness/quota-harness.php` ~L140/170/189 -- the existing `quota_remaining` pin. Read only.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/rest-posts-harness.php` -- `check_can_trigger` rows + `check_view_reviews` edit_posts-only row
- [x] `tests/harness/caps-harness.php` -- `maybe_upgrade()` rows (absent / equal / lower)
- [x] `tests/harness/sanitize-matrix-harness.php` -- free-save `agent_prompts` smuggle case
- [x] `tests/harness/render-matrix-harness.php` -- recording `add_filter`, replace the regex check
- [x] Mutation proof for each, recorded in Implementation Notes

**Acceptance Criteria:**
- Given each named path, when the suite runs, then a green check executes it (none grepped), and the named mutation makes the suite fail.

## Implementation Notes

**What was added (test code only; `git diff -- post-quality-manager/` empty):**
- `rest-posts-harness.php` ("No capability" block): `check_view_reviews()` false for caps `[edit_posts]`; new "Trigger route permission" block runs `check_can_trigger()` for `[pqm_run_free_agents]` → true, `[pqm_run_paid_agents]` → true, `[]` → false, `[edit_posts]` → false; plus the `/trigger-review` permission_callback assertion. 143 → 149 checks.
- `caps-harness.php`: recording `get_option($option, $default_value = false)` / `update_option($option, $value, $autoload = null)` stubs (WP arity) over `$GLOBALS['tw']['options']`, writes counted in `$GLOBALS['tw']['option_writes']`; requires `class-pqm-activator.php` (its only file-level dependency is `ABSPATH`). Row 8 runs `PQM_Activator::maybe_upgrade()`: option absent → the default map granted + option = `CAPS_VERSION`, one write; equal version with author's `pqm_view_reviews` removed by hand → every role's caps array `===` the snapshot, zero writes; stored `CAPS_VERSION - 1` (= 0 today) with the same removal → re-granted, option = `CAPS_VERSION`, one write. 249 → 259 checks.
- `sanitize-matrix-harness.php` Case 11: free site, Admin and Editor (both hold `pqm_manage_agents`) POST new `agent_prompts` for review/seo/takeaways → `agent_prompts === pqm_get_settings()['agent_prompts']`. 142 → 144 checks.
- `render-matrix-harness.php`: `add_filter` stub now records `[hook, callback, priority, accepted_args]` into `$GLOBALS['tw_filters']` (WP arity, returns true); `register_setting($option_group, $option_name, $args = [])` stub added. The regex check "option save uses settings_page_capability" is replaced: the recording is reset, `PQM_Admin::register_settings()` is called, and the recording must equal `[['option_page_capability_pqm_settings_group', ['PQM_Admin', 'settings_page_capability'], 10, 1]]`. Count unchanged (95; one check replaced). The two other static checks in that block (settings_page_capability body, menu cap) are out of this plan's scope and were left as they were.

**Review fixes (2026-10-06):** the equal/lower `pqm_caps_version` seeds are strings, as the real `get_option` returns them, so the `(int)` cast in `maybe_upgrade()` is pinned (a `!==` refactor now fails the equal-version rows). `rest-posts-harness.php` also asserts `/trigger-review`'s `permission_callback` is `['PQM_REST_API', 'check_can_trigger']`. The render-harness heading now says the option_page_capability filter is executed, and only the menu cap and the settings_page_capability() body are still static checks. **Known limit:** with `CAPS_VERSION = 1` the lower-version row stores 0, which is the same as an absent option, so a refactor that only registers when the option is missing stays green. This row covers a distinct case only once `CAPS_VERSION > 1` (a comment in Row 8 says so).

**`quota_remaining` pin re-verified (no duplicate added):** `quota-harness.php` L140 (14 after a 2xx), L146-147 (response keys `{success,message,n8n_status,quota_remaining}`), L170 (null when exempt), L189 (unchanged 15 after an n8n 500). All green (54/54).

**Mutation proofs (each applied with sed to a copy-backed plugin file, full suite run, file restored from the backup; `git diff -- post-quality-manager/` empty afterwards):**

| # | Mutation | Result | New check(s) failing |
|---|---|---|---|
| M1 | `check_can_trigger`: `array_column(…, 'capability')` → `'capabilty'` (typo'd cap) | rest-posts 7 failed | free cap → true, paid cap → true (plus viewer `can_trigger`/quota rows) |
| M2 | `check_can_trigger`: `current_user_can( $cap )` → `current_user_can( 'pqm_run_free_agents' )` | rest-posts 1 failed | paid cap → true |
| M3 | `check_view_reviews`: `… \|\| current_user_can( 'edit_posts' )` | rest-posts 1 failed | edit_posts-only refused |
| M4 | `maybe_upgrade`: `<` → `>` (inverted comparison) | caps 8 failed | all option-absent rows + both lower-version rows |
| M5 | `maybe_upgrade`: `<` → `<=` | caps 2 failed | equal version: roles byte-identical, no option write |
| M6 | `sanitize_settings` carry branch: `$existing['agent_prompts']` → `$input['agent_prompts'] ?? $existing['agent_prompts']` (posted-else-stored) | sanitize 4 failed | Case 11 Admin + Editor (Case 9's settings-only rows fail too) |
| M7 | `$can_edit_agents` drops `&& PQM_Features::is_pro()` | sanitize 5 failed | Case 11 Admin + Editor (no earlier check posted `agent_prompts` as an agents-holder on a free site) |
| M8 | `register_settings`: filter callback `'settings_page_capability'` → `'sanitize_settings'` | render 1 failed | option save uses settings_page_capability |
| M9 | `maybe_upgrade`: `(int) get_option(…) <` → `get_option(…) !==` (review fix) | caps 2 failed | equal version: both rows |
| M10 | `/trigger-review` `permission_callback` → `check_view_reviews` (review fix) | rest-posts 1 failed | /trigger-review gated by check_can_trigger |

Final run: all 10 harnesses green before the review fixes; after them only caps, rest-posts and render-matrix were re-run, all green (caps 259, cron-gate 26, insert-block 66, license 21, quota 54, render-matrix 95, rest-posts 149, rest-reviews 36, sanitize-matrix 144, template-failure 54); `php -l` clean on the four touched files.

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 0 medium, 4 low. Three patched; one recorded as a known ceiling. All 10 harnesses exit 0.
- low, patch: the "equal version" row seeded an int, but real `get_option` returns a string, so the `(int)` cast was unpinned. Seeded as a string; a `!==` refactor now fails (M9).
- low, accepted ceiling: with `CAPS_VERSION = 1` the "lower version" row stores 0, indistinguishable from an absent option. This can't be fixed without a version bump; commented at the row and in Implementation Notes.
- low, patch: nothing pinned `/trigger-review`'s `permission_callback` to `check_can_trigger`. Route assertion added (M10).
- low, patch: the render-matrix heading still called the `option_page_capability` check static. Reworded.

## Verification

**Commands:**
- `php -l` on touched harness files; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green
