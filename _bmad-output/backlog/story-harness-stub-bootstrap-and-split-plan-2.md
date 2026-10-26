---
title: 'The n8n API harness is split by concern over a shared world (story 78, build 2 of 2)'
type: 'refactor'
build_of: '78'
created: '2026-10-21'
status: 'done'
baseline_revision: '9d4a753a1cae4cf1ee954d154fef6946ad3ab8f5'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['_bmad/custom/minimal-code-ladder.md']
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `tests/harness/n8n-api-harness.php` is one 2,270-line file (581 checks) grown over twelve entries, with its world builders, constants and HTTP stubs inside it, where every other harness is one file per concern. A failure is hard to place and the file is hard to extend.

**Approach:** Build 2 of story 78 (build 1 landed the shared stub bootstrap, PR #164): move the n8n world (stubs, constants, builders, the plugin load, the route registration) into one include under `tests/harness/lib/`, and split the sections into four harnesses by concern, each runnable alone. Every check keeps its label and its condition; the only new checks are the three extra copies of the key-leak scan (owner decision 2026-10-21).

## Boundaries & Constraints

**Always:** Every existing check moves verbatim (label and condition); total 581 + 3 = 584 across the four files, 0 failed, each file green alone and from any working directory. Section order inside a concern unchanged (some rows read the state the previous row left). The world include lives in `tests/harness/lib/` (CI's loop never runs it alone); the four harnesses sit at the top of `tests/harness/` so the loop and the `for f in tests/harness/*.php` line in AGENTS.md and CONTRIBUTING.md pick them up unchanged. The source-text pins (regexes on `class-pqm-n8n.php`) stay in the concern whose code they pin. Plain PHP, no framework. `php -l` on every touched file. The old file is deleted in the same change.

**Never:** Change plugin code. Change what a check asserts, or its label beyond nothing. Touch the other 15 harnesses or `lib/wp-stubs.php` (if the world include needs something from the bootstrap that is missing, HALT and say so).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Each file alone | `php tests/harness/n8n-<concern>-harness.php` | Its sections' checks pass, summary line, exit 0 | A failure exits 1 as today |
| Sum | The four summary lines | 584 passed, the per-file counts recorded in Implementation Notes | — |
| Cross-section value | A section that read a value an earlier section defined (`$setup_map`, `$adopted_map`, `$older_report`, `$hand_nodes`, `$template`, `$strip`, `$gate`) | The value comes from the world include, built from constants, identical | — |
| Key-leak scan | Each file's `$replies` at its end | One `key_leak_scan()` call per file, same condition as today's scan | — |
| CI loop | `tests/harness/*.php` | Four new files run, the old one is gone, `lib/n8n-world.php` never runs alone | — |

</frozen-after-approval>
## Code Map
Line numbers are today's `tests/harness/n8n-api-harness.php` (post build 1).
- `tests/harness/lib/n8n-world.php` (new) -- everything before the first section plus every definition later sections share: :56-188 (ABSPATH, `tw` world literal, the harness-specific stubs incl. `add_option`/`race()`, `wp_cache_*`, `wp_remote_*`, `WP_Application_Passwords`, `wp_sprintf`, the `$wpdb` class), :189-192 (`require lib/wp-stubs.php`, the plugin), :195-271 (KEY, OLD_KEY, BASE, UA, `resp()`, LIST_OK/ROUTE_OK/ROUTER_404, `site()`, `post()`, `get_state()`, `forget()`, `calls()`, `calls_signed()`, `unchanged()`, `without_failed()`, MANUAL_SENTENCE, `$replies = []`), :274-276 (`register_routes()`, `tw.caps`, `$gate`). Then the per-concern constants and pure helpers, moved as blocks: provisioning :499-607 (PROVIDER_KEY … PRO_OK, `created()`, `wired()`, `with_path()`, `stored_workflow()`, `connected()`, `provision()`, `body()`, `linked()`, `untouched()`, `lock_free()`, `pro_map()`, `$template`, `$template_names`, `$strip`), rollback :736-800 (N8N, DEL, CREATE_ALL, UNDO_*, CLEAN, LEFT, `timed_out()`, `on_instance()`, `rollback_ordered()`, `revoked()`, `nothing_saved()`, `plain()`), :935-936, :1000, :1034-1035, diagnosis :1100-1134 (LIST_READ … PING_OK, `$setup_map`, `$miswired_map`, `connect_ping()`, `diagnosed()`, `one_primary()`, `state_of()`, `without_body()`), adoption :1272-1314 (HAND_*, READ_HAND, HAND_MAP_AT, `hand_list()`, `hand_made()`, `proposal()`, `adopt_post()`, `refused()`, `error_json()`, `$hand_nodes = wired(HAND_NAMES)`), managed :1499-1523 (ROTATED … PATCHED, `$adopted_map`, `managed()`, `rotate()`, `token_now()`), :1688-1704 (PUT_WF … UPDATED, `$older_report`, `update()`, `trigger_path()`), :1912-1929 (REMOVED_* … PLAIN_404, `start_over()`, `started_over()`), :2105-2113 (KEY_SENTENCE …, `refusal_stored()`). Keep `tw.uuid` out of the include (Rotation sets and unsets it, :1505/:1680). The per-section accumulators (`$provision_runs`, `$diagnosis_calls`, `$status_blocks`, `$adoption_calls`, `$rotation_runs`, `$update_runs`) stay in their sections. Add `key_leak_scan(array $replies, string $src): void` holding today's :2261-2270 checks verbatim (the three `check()` calls), called last by each file.
- `tests/harness/n8n-client-harness.php` (new) -- sections :273-492 (Routes and permission … `PQM_N8n::request()`) and :2104-2260 (Story 74: Check again, route probe, 401 recorded, after refusal, mis-wired, one predicate) — the connection state is the client's concern; its sections call `rotate()`, `update()`, `proposal()`, `start_over()` from the include. Ends with `key_leak_scan($replies, $src)`. ≈ 47 + 28 + 3 checks.
- `tests/harness/n8n-provisioning-harness.php` (new) -- :493-1098 (Provisioning route … Rollback … locks … Revoke) in order; "Provisioning: acceptance" :667 reads the state "Pro, fresh instance" :609 left, keep adjacent. Its leak scan :1076 stays. ≈ 93 + 3 checks.
- `tests/harness/n8n-adoption-harness.php` (new) -- :1099-1498 (Diagnosis, Adoption); "Adoption: after" :1485 restores `$adopted` set at :1438, both inside. The `propose_adoption` source pin :1488 stays here. ≈ 72 + 3 checks.
- `tests/harness/n8n-managed-harness.php` (new) -- :1499-2103 (Rotation, Update, Start over) in order; the `rotate_token`…`managed_blocked` and `update_workflow` source pins, the `start_over` pin :2000 stay here. ≈ 118 + 3 checks.
- `tests/harness/n8n-api-harness.php` -- delete.
- `.github/workflows/ci.yml` -- unchanged (glob). `AGENTS.md` managed block: the harness sentence names the four n8n files and `lib/n8n-world.php` (hand edit noted in the "Verified" comment). `memory/delivery-pipeline.md` -- one sentence after build 1's paragraph. `memory/n8n-provisioning.md` -- any line naming `n8n-api-harness.php` as a file points at the four (grep first).
- `_bmad-output/initiative-post-quality-manager/deferred-work.md` -- the build 2 entry is resolved by this plan: append "resolved by `story-harness-stub-bootstrap-and-split-plan-2.md`" to it, never delete it.

## Tasks & Acceptance
**Execution:**
- [ ] `tests/harness/lib/n8n-world.php` -- the world, moved not rewritten; `key_leak_scan()` -- one world for four files
- [ ] the four `tests/harness/n8n-*-harness.php` -- sections moved verbatim in order, `require __DIR__ . '/lib/n8n-world.php'` first, `key_leak_scan()` then `harness_exit()` last -- one file per concern
- [ ] `tests/harness/n8n-api-harness.php` -- delete -- the loop runs the four
- [ ] `AGENTS.md`, `memory/delivery-pipeline.md`, `memory/n8n-provisioning.md`, `deferred-work.md` -- records follow -- AC3 and the triad

**Acceptance Criteria:**
- Given the sweep, when it runs, then 19 harnesses pass, the four n8n files sum to 584, every other count equals build 1's
- Given `diff <(grep -o "check('[^']*'" old) <(grep -ho "check('[^']*'" new four)` on the labels, when compared as multisets, then the only additions are the three extra leak-scan labels
- Given each n8n file run from `/tmp`, when it loads, then it passes alone

## Implementation Notes
Agent-owned. Append-only.

- 2026-10-21, build 2 implemented (Fable 5.1 as the implementer subagent standing in for bmad-build's dispatch; run from the workspace root with the skill rendered by hand). The one file at 9d4a753 was cut by line range with a script copying ranges verbatim; the only rewritten lines are the include's relative paths (`require __DIR__ . '/wp-stubs.php'`, `dirname(__DIR__, 3)` for the plugin load, `$src_n8n` and the leak scan's `$src`/`$rest`) and the include's own tail (`PQM_REST_API::register_routes()`, `tw.caps = ['pqm_manage_settings']` — the state :281 leaves, which every file's rows read — and `$gate`). The client's "Routes and permission" section keeps its own two `tw.caps` lines and checks verbatim.
- Decisions beyond the Code Map: (1) `key_leak_scan(array $replies): void` reads `$src` and `$rest` itself; the plan's `string $src` signature left `$rest` (:2265, the REST class source the no-logging pin reads) without a home. Its body is :2261-2268 verbatim, section echo included. (2) The six accumulators (`$provision_runs`, `$diagnosis_calls`, `$status_blocks`, `$adoption_calls`, `$rotation_runs`, `$update_runs`) start empty in the include beside the helpers that fill them, not in their sections: the helpers `array_merge()` them, so the client's Story 74 rows (`proposal()`, `rotate()`, `update()`) and the managed file's `provision()` row (:2099) fataled without them. The leak scans that read them stay in their files. (3) Two shared values the Code Map missed moved to the include: `$oct13` (:1142, built from constants; the first diagnosis row and "Update: adopted" :1779 read it) and `$src_n8n` (:1488, the client source text the adoption, rotation, update and start-over pins read; story 74 reassigns it inline, verbatim). The pins themselves stayed in their concern as planned. (4) :599-606 (`$raw_template`, `$template_names`, `$expected_names` and the story 77 template-name `check()`) stayed in the provisioning file: a check cannot run in the include (silent by contract, and it would count four times); only `$template` :598 and `$strip` :607 moved. (5) `tw.uuid` (:1505) stayed in the managed file's Rotation section as planned; `$proposed` :1315-1318 and `$stored`/`$a_sentence` :1138-1139 stayed in the adoption file (read there only). (6) `ABSPATH` stays `__DIR__ . '/'` verbatim (now `tests/harness/lib/`; the plugin reads it only in the activator's `upgrade.php` require, never in a harness).
- Check counts. Before: `n8n-api-harness.php` 581 passed, 0 failed. After, each file alone, from the repo and from the session's scratchpad directory: `n8n-client-harness.php` 177, `n8n-provisioning-harness.php` 156, `n8n-adoption-harness.php` 90, `n8n-managed-harness.php` 167 — sum 590, 0 failed. The delta is +9, not the plan's +3: the "key never leaves the server" section is three `check()` calls (the replies scan and the two source pins on `class-pqm-n8n.php`), and the Code Map puts all three in `key_leak_scan()` called by each file (3 extra copies × 3 checks = 9). The frozen "581 + 3 = 584" counted the scan as one check; both figures cannot hold at once, and the Code Map's mechanism was kept (no check leaves any file). Owner's call: if 584 is wanted, the two source pins leave `key_leak_scan()` for the client harness (the concern whose code they pin) — a four-line change. The sweep: 19 harnesses green (plus the silent `fs-stub.php`), the other 15 at build 1's counts (caps 284, cron-gate 35, free-build 83, insert-block 66, license 27, quota 71, release-header 31, render-matrix 409, rest-posts 165, rest-reviews 65, sanitize-matrix 226, setup 141, template-failure 55, template-guard 62, voice 23). `php tests/harness/lib/n8n-world.php` alone: silent, exit 0, from the repo and from elsewhere. `php -l` clean on the five files.
- Label multiset. `grep -o "check('[^']*'"` on the old file gives 326 labels; `grep -ho` on the four new files plus the include gives the same 326 — the multiset diff (`diff <(uniq -c old) <(uniq -c new)`) is empty, the three leak-scan labels living once in the include. Against the four files alone, the diff shows those three labels as removals and nothing else. At runtime (the sorted `ok` lines of the old run against the four runs) the only difference is the three leak-scan lines appearing four times instead of once, the replies count inside the first label's text aside (278 → 51/52/83/92).
- Records: AGENTS.md line 34 (hand edit noted in the managed block's Verified comment), `memory/delivery-pipeline.md` (one sentence after build 1's), `memory/n8n-provisioning.md` (the four mentions point at the concern's file), `deferred-work.md` (the build 2 entry marked resolved). `tests/harness/free-build-harness.php:244` names the old file in a comment — left as is (the plan forbids touching the other harnesses); the plan-file mentions under `_bmad-output/` are historical records. Nothing committed; tree on `chore/n8n-harness-split`.

- 2026-10-21 (session, after review): the three patches applied directly by the session (two deletions and one word; the implementer was not re-engaged for them, a workflow deviation recorded here). Sweep after: 19 harnesses, 2,327 checks, 0 failed; n8n files 177/154/88/165 = 584; no reference to `n8n-api-harness.php` left outside `_bmad-output/`.
- 2026-10-21 (session, step 3 finish + diff read + matrix audit): the implementer flagged 590 against the frozen 584 — the Code Map (non-frozen, the session's text) had put all three checks of the old last section into `key_leak_scan()`, while the frozen block and the owner's decision count the replies scan only. Finished per the frozen block: `key_leak_scan()` keeps the replies check alone; the two source pins (no logging; one `wp_remote_request()`) run once at the end of `n8n-client-harness.php`. Counts after: client 177, provisioning 154, adoption 88, managed 165 = 584; the other 15 harnesses at build 1's counts; sweep total 2,327; label multiset of the old file vs the four files + include identical (326 literal labels); every n8n file passes from `/tmp`; `lib/n8n-world.php` alone exits 0 silently. Matrix rows all covered by the sweep itself. The implementer's three other deviations (accumulators start empty in the include; `$oct13` and `$src_n8n` moved to the include; the template-name check kept in the provisioning file) stand. One stale mention of the old file name remains in a comment of `free-build-harness.php` :244, which the plan's Never list kept out of reach: for the reviewer.

## Plan Change Log
Append-only.

- 2026-10-21, after review pass 1, finding 2 (bad_plan): the Boundaries' "Never: touch the other 15 harnesses" kept a stale comment in `free-build-harness.php` out of reach while the intent says the records follow the split. Amended: a comment in another harness that names the deleted file may be re-pointed (one word). Known-bad state avoided: a harness comment naming a file that no longer exists. KEEP: every section moved verbatim, the four files and the include as built, `key_leak_scan()` as the replies scan only with the two source pins once in the client harness, counts 177/154/88/165 = 584.

## Review Triage Log
Append-only.

- 2026-10-21, review pass 1 (lens: quick, pinned; 4 findings): verdicts 0 high / 0 medium / 3 low / 0 false / 0 maybe-false, plus 1 rejected. Route: 2 patch, 1 bad_plan resolved by a plan amendment without re-derivation (below), 0 defer, no code revert.
  1. `lib/n8n-world.php` :610 stale docblock above `key_leak_scan()` ("today's three checks") beside the correct one: **low**, verified (the session's own insert left two docblocks). Route **patch**: the stale line deleted.
  2. `free-build-harness.php` :244 comment names the deleted file: **low**, verified; the only reference left outside `_bmad-output/`. The plan's Never list ("touch the other 15 harnesses") excluded it, the intent (records follow the split) does not: route **bad_plan**; the Plan Change Log entry amends the Never list; the fix is the one-word comment change, applied directly instead of re-deriving a 4,800-line move (KEEP everything else).
  3. AC2 / Verification as literally written (label multiset: three additions, no removals) cannot hold with `key_leak_scan()` as one function (D4): the static label multiset is identical including the include, one removal without it; the +3 exists at run time. **Rejected** by the rule that a finding whose fix edits this build's plan is not patched; the owner accepts the run-time reading (584 = 581 + 3 at run time, labels identical) or edits the frozen AC.
  4. The five-line "Bug 82" header paragraph duplicated word for word in the include and the provisioning harness: **low**, cosmetic, verified. Route **patch**: the provisioning copy deleted (its header already names the world's stubs).


## Design Notes
(D1) Move, do not rewrite: the diff should read as cut-and-paste plus the include line, so "every check verbatim" is reviewable by eye. (D2) Shared values that a later concern reads (`$setup_map`, `$adopted_map`, `$older_report`, `$hand_nodes`, `$template`, `$strip`, `$gate`) are pure literals or built from constants, so the include defines them once; nothing that depends on a scenario's run crosses a file boundary. (D3) Story 74 sits with the client because it tests the connection state, even though it drives managed actions. (D4) `key_leak_scan()` is one function so the four copies cannot drift; +3 checks is the one accepted count delta. Skipped: renaming any check label; the n8n-specific `WP_REST_Request` differences (none, build 1 settled them). Checkpoint 1 (owner, 2026-10-21): approved and continued, full plan kept above the 1,600-token guideline for the block map. Workflow deviation: run from the workspace root with the skill rendered by hand; the branch `chore/n8n-harness-split` was created under the owner's go-ahead of 2026-10-21; this is the second plan file of ticket 78 (`-2`), the first is built and merged.

## Verification
**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 19 harnesses, 0 failed; n8n files sum 584; the other 15 at build 1's counts (caps 284, cron-gate 35, free-build 83, insert-block 66, license 27, quota 71, release-header 31, render-matrix 409, rest-posts 165, rest-reviews 65, sanitize-matrix 226, setup 141, template-failure 55, template-guard 62, voice 23)
- `php tests/harness/lib/n8n-world.php` -- expected: loads the plugin and exits 0 silently (no section runs)
- the label multiset diff from the acceptance criteria -- expected: three additions, no removals
- `php -l` on every touched PHP file -- expected: clean
