---
title: 'Only registry commands reach the webhook; score and apply have explicit free tiers'
type: 'feature'
ticket: '14'
created: '2026-10-06'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '3f2404aebb487390da2cdcf37d087e2355c1dd15'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The feature gate fails open. `PQM_Features::why_not()` and `plan_allows()` treat any command missing from `registry()` as free (`pqm_run_free_agents`), and the REST enums on `/trigger-review` and `/save-review` are hand-written lists beside the registry. A paid agent added to an enum without a registry entry would run for free users (retro A8, owner default decision 2026-10-02).

**Approach:** Make the registry the one command whitelist. `score` and `apply` get explicit free-tier entries (`pqm_run_free_agents`, same as today's fallback, so they behave exactly as now and quota keeps counting them). Both route enums become `array_keys( PQM_Features::registry() )`, so drift is impossible and WP's argument validation refuses any other command with 400 at the trigger chokepoint. The fallback in `why_not()` / `plan_allows()` fails closed: an unknown command is refused (`'role'` / `false`) for any caller that bypasses REST validation (cron, direct calls).

**Decisions (owner's standing approval of default proposals, 2026-10-06):**
- Enum derived from the registry rather than a separate whitelist check, plus a harness assertion that both enums equal the registry keys. Single source beats a drift detector.
- `apply_*` variants get no entries: the AJAX/GET paths that sent them were deleted in story 5.7, and neither enum lists them.
- The viewer's `hidden_commands` / `gated_commands` (`GET /posts`) now also list `score` / `apply` where refused. The client only does `.includes()` checks (`src/kit/runs.js:40,52`), so this is harmless and truthful.

## Boundaries & Constraints

**Always:** `score` / `apply` gating, quota consumption and every refusal copy stay exactly as today. `permission_callback`s unchanged. Run every harness.

**Never:** change the n8n template (its `validCommands` already equals the new enum). Add a new capability or bump `CAPS_VERSION`. Touch the React client.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Registry command | `review`…`audit`, `score`, `apply` | `why_not()` exactly as today for every role × plan | — |
| Unknown command, REST | `POST /trigger-review` `command=foo` | Refused by arg validation: enum = registry keys | 400 `rest_invalid_param` (WP core) |
| Unknown command, direct | `why_not('foo')` / `plan_allows('foo')` | `'role'` / `false` (fail closed) | — |
| Drift | A registry or enum edit that diverges | Harness fails | — |
| Viewer lists | Contributor (no pqm caps) | `hidden_commands` lists all 8 registry commands | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-features.php:80-94` -- `registry()`: add `score`, `apply` (free, `pqm_run_free_agents`). Update the docblock (no more fallback list). `plan_allows()` L113-117 and `why_not()` L126-138: unknown → `false` / `'role'`. Update the file header L6 if it mentions the fallback. `commands_refused_for()` L258 iterates the registry, so it picks the new entries up by itself.
- `post-quality-manager/includes/class-pqm-rest-api.php:321` (`save_review_args`) and `:447` (`trigger_review_args`) -- `'enum' => array_keys( PQM_Features::registry() )`. `check_can_trigger()` L122 takes `array_column(registry(), 'capability')`; its set is unchanged.
- `post-quality-manager/includes/class-pqm-cron.php:74` -- `plan_allows( $command )` with `cron_command` forced to `review`; unaffected.
- `tests/harness/caps-harness.php` -- Row 4 is the why_not matrix (role × 8 commands × plan). Its expectations must not change. Add rows: unknown → `'role'` / `false`; registry keys === the 8 commands with the expected tiers.
- `tests/harness/rest-posts-harness.php` -- has the `register_routes()` stub (`$GLOBALS['pqm_routes']`). Add: `/trigger-review` and `/save-review` `command` enum === `array_keys(registry())`. L481 "all six registry commands hidden" becomes the 8-command list.
- `memory/freemium-and-roles.md` -- registry / gate facts: score and apply explicit, fail-closed fallback, enums derived.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-features.php` -- registry entries + fail-closed fallback + docblocks
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- both enums from the registry
- [x] `tests/harness/caps-harness.php` -- unknown-command rows; Row 4 unchanged and green
- [x] `tests/harness/rest-posts-harness.php` -- enum === registry keys for both routes; viewer lists updated to 8 commands
- [x] `memory/freemium-and-roles.md` -- facts updated

**Acceptance Criteria:**
- Given a command with no registry entry, when it is posted to `/trigger-review`, then it is refused with 400 before any webhook call.
- Given `score` or `apply`, when any role on any plan triggers it, then the outcome (gate, quota) is identical to today's.
- Given the registry and an enum drifting apart, when the harnesses run, then one fails.

## Implementation Notes

- Registry order: the six agents keep their order, `score` then `apply` are appended. The enums therefore list the same 8 values as before in a different order (WP validation and the n8n `validCommands` check are order-free).
- Fail closed in one guard each: `plan_allows()` returns `$entry !== null && (...)`, `why_not()` returns `'role'` when the entry is null, before the capability check. The `pqm_is_pro` override cannot open an unknown command (asserted).
- Mutation-checked: a hand-written `/trigger-review` enum with an extra `foo` fails `rest-posts-harness` (2 checks); removing `apply` from the registry fails `caps-harness` (Row 4 + Row 6) and `rest-posts-harness` (enum + viewer rows).

## Plan Change Log

- 2026-10-06 — Row 4 of `caps-harness.php` swapped its 8th command `apply_takeaways` for `apply`. The code map assumed Row 4's 8 commands were the 8 registry commands; it actually held `apply_takeaways` (a non-registry variant). Keeping it would have flipped its cells to `'role'` (Decision: `apply_*` get no entry), contradicting "expectations must not change". `apply` takes the identical cells (null for cap holders, `'role'` for contributor); `apply_takeaways` moved to the new Row 6 unknown-command list (`foo`, `apply_takeaways`, `ping`, `''` → `'role'` / `false`, every role × plan). Row 5's `$registry_commands` grew to 8 (contributor hidden = all 8).
- 2026-10-06 — Outside the code map, label-only: `cron-gate-harness.php` Row 4 + one Extra label and `quota-harness.php` one Extra label called `score` "non-registry / free fallback"; reworded to "free-tier registry entry". No assertion changed.
- 2026-10-06 — Review fixes: Row 6's override check starts from a free site; `rest-posts-harness.php` also asserts the n8n template's Parse Input `validCommands` equals the registry keys as a set (third list); `quota-harness.php` asserts `trigger_review_callback('foo')` returns `pqm_feature_gated` with no webhook call, no processing row, no quota spent (author + editor); the memory Gate bullet names all three places a new command must go.

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 1 medium, 2 low. All patched, then verified (all 10 harnesses exit 0).
- low, patch: Row 6's "mu-plugin override" check started from a pro site, so the filter proved nothing. It now starts from a free site.
- medium, patch: memory said one registry entry adds a command, but the template's Parse Input `validCommands` is a third list that would reject it. `rest-posts-harness.php` now checks that `validCommands` equals the registry keys as a set, and memory names all three places plus the Route Command branch.
- low, patch: nothing showed an unknown command stopping before the webhook. `quota-harness.php` now drives `trigger_review_callback('foo')`: WP_Error, no `wp_remote_post`, no processing row, no quota spent. The REST 400 itself is WP core's arg validation, kept as a manual REST check.

## Verification

**Commands:**
- `php -l` on touched PHP; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green

**Result (2026-10-06):** `php -l` clean on all touched PHP. Harnesses all green: caps 243, cron-gate 24, insert-block 66, license 21, quota 40, render-matrix 59, rest-posts 138, rest-reviews 36, sanitize-matrix 115, template-failure 54 — 0 failed.
