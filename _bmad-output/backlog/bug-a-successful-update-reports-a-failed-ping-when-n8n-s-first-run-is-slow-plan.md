---
title: 'A successful update reports a failed ping when n8n''s first run is slow'
type: 'bugfix'
ticket: '138'
created: '2026-11-19'
status: 'built'
baseline_revision: '613bca077a8795bed2871eef22ee6b379f3a049e'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/memory/n8n-provisioning.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Right after a publish (provisioning, Update workflow), the plugin pings the new workflow and waits 8 s for n8n's callback; a callback that arrives later counts as a failure, so a working update reads "did not call this site back… a local, private or password-protected site cannot be called back" and the page turns red (story 38, P030: n8n 2.48.5's first run after the PUT took 8.2 s, later runs 0.1–0.2 s).

**Approach:** In the ping that follows a publish only, a late callback from the current workflow gets another chance before it counts; the Test button and the rotation keep their one ping.

## Boundaries & Constraints

**Always:** Only `ping_after_activation()`'s callers change (provisioning, the update). A site n8n really cannot call back still ends with the callback sentence, within a stated ceiling. Bug 80's 404 window and bug 99's older-version retry keep working as today.

**Never:** No change to `PING_WAIT` for the Test button, `POST /setup/connect` or the rotation; no change to the callback route, the template, the payload or the copy of existing sentences; no new option.

**Decision (owner, 2026-11-19):** a late callback (`callback` `none`) from the ping after a publish is retried like bug 80's 404 window, within `PROVISION_PINGS` (3 pings, 2 s apart). Accepted cost: a site n8n cannot call back waits about 3 × 8 s + 4 s ≈ 28 s before the callback sentence (today 8 s), and the first ping's late callback meets a replaced pending id (404).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Late first run after an update | update; ping 1 callback `none`, ping 2 `received` | updated, ping_ok, "Workflow updated. Ping OK…", version recorded | No error |
| Free answers, then a late first run (P030) | Pro update; old Free answer, then `none`, then `received` | as above, 3 pings | No error |
| Late first run after provisioning | provision; `none` then `received` | provisioned, ping_ok, setup step `connect` true | No error |
| Unreachable site | every ping's callback `none` | 3 pings, then today's callback sentence, `ping_ok` false | Failure on record, as today |
| Refused callback | callback `refused` | one ping, today's refused sentence | No retry (a wrong token is not slowness) |
| Test button, rotation | callback `none` | one ping, today's sentence | Unchanged |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` L1676-1704 -- `ping_after_activation()`: the retry loop (`PROVISION_PINGS` 3, L1201; 2 s via `postquality_provision_ping_retry_wait`); `$window` today = 404 or `answered_older()`. The fix lives here; update the docblock's ceiling line. Callers: `provision_n8n_callback()` L1649, `update_n8n_workflow_callback()` L1889 (its `switching` branch L1890-1894 stays as is).
- `post-quality-manager/admin/class-pqm-admin.php` L2299 `PING_WAIT`, L2345-2373 `ping_reply()` (the wait, then `callback` `none` with the sentence at L2370), L2406 `awaited_callback()` -- read only, unless the decision is option 2.
- `includes/class-pqm-rest-api.php` L592 `ping_callback()` -- a late callback whose pending id was replaced answers 404 (`pqm_ping_unknown`); keep it.
- `tests/harness/lib/n8n-world.php` L53-55, L131-135 -- `ping_callback` is one state for every ping; add a per-ping queue beside `ping_queue` (call order, the single state once empty).
- `tests/harness/n8n-provisioning-harness.php` L571-580 -- the "callback none: one ping" row changes with the fix; the source check at L582-584 keeps the retry wait in the REST class only.
- `tests/harness/n8n-managed-harness.php` L519-600 -- bug 99's update rows; add the late-callback rows next to them.
- `memory/n8n-provisioning.md` -- add the 2.48.5 finding (first run after a public-API PUT 8.2 s, later 0.1–0.2 s) and the frame's other n8n facts, dated.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- add callback `none` to `$window` in `ping_after_activation()`; the docblock names the late callback and the ≈ 28 s ceiling -- the fix
- [x] `tests/harness/lib/n8n-world.php` -- per-ping callback queue -- the matrix needs `none` then `received`
- [x] `tests/harness/n8n-provisioning-harness.php`, `tests/harness/n8n-managed-harness.php` -- one row per matrix line; the changed `none` row -- fails before the fix, passes after
- [x] `memory/n8n-provisioning.md` -- the dated n8n facts -- knowledge triad

**Acceptance Criteria:**
- Given the harnesses, when they run on the fix, then every row passes and the new rows fail on the baseline revision.
- Given a Local Pro site updated from a Free setup against a fresh disposable n8n, when Update workflow runs, then it reports success (story 38's P030 re-walk on the release).

## Implementation Notes

- **Iteration 1 (2026-11-19, the implementer, a subagent standing in for `bmad-build`'s implement step; the session runs from the workspace root, so the skill was applied by hand):**
  - `ping_after_activation()`: `$window` also holds for `'none' === ( $ping['callback'] ?? '' )`; only `ping_reply()`'s current-version branch sets `callback` `none`, so the retry covers a late callback from the current workflow only. `refused` is not retried. The docblock names the late callback (bug 138, 8.2 s on 2.48.5), the 404 a replaced pending id answers, and the ceiling 3 × 8 s + 2 × 2 s ≈ 28 s. No change to `PING_WAIT`, `ping_reply()`, `ping_callback()`, the template or any sentence.
  - `tests/harness/lib/n8n-world.php`: `tw.ping_callbacks`, one scripted callback per ping in call order, `ping_callback` once empty; reset by `connected()` (so by `managed()` too).
  - Matrix rows: late after an update (n8n-managed), Free → late → in time on Pro (n8n-managed, P030), late after provisioning (n8n-provisioning), unreachable after an update (n8n-managed, 3 pings) and after provisioning (the provisioning `none` row, changed from one ping to three), refused (provisioning's existing row, now pinned to one ping; a new update row), rotation and `POST /setup/connect` with no callback (n8n-managed, one ping each), the Test button (render-matrix's existing `none` row now counts one ping).
  - Baseline proof: with `class-pqm-rest-api.php` at `613bca0`, n8n-managed fails 4 rows (late, P030, unreachable, and the late row's record check) and n8n-provisioning 2 (the changed `none` row, late after provisioning); the refused, rotation, `/setup/connect` and Test button rows pass on both, as they pin unchanged behaviour.
  - Memory: the 2.48.5 line (the ticket's reproduction facts, dated 2026-11-19) and the two retry lists in `memory/n8n-provisioning.md`.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, 2026-11-19):** high 0, medium 1, low 2, false 3, maybe-false 0.

| Finding | Verdict | Route | Evidence |
|---|---|---|---|
| AC 1 met (php -l, harnesses, new rows fail on 613bca0) | false | rejected | Not a defect: the lens confirms the acceptance criterion. |
| AC 2 (the Local Pro-over-Free walk) not recorded | false | rejected | AC 2 is story 38's P030 re-walk on the release that ships the fix; it cannot run before the release, by the plan's Verification. |
| Caller comments (`provision_n8n_callback()` docblock L1624-1626, `update_n8n_workflow_callback()` docblock L1868-1870 and inline L1894-1895) still list only the 404 and the replaced version | low | patch | Read: both describe the retry without the late callback that `ping_after_activation()` now retries; a reader of the callers gets the old list. |
| Ticket 138 has no ticket file on this branch | false | rejected | The ticket is in PR #37 (`chore/138-ping-after-update`, dbd2ff4), as bug 137's was filed before its fix; `tickets.py find` resolves it there. |
| No `docs/release-notes/unreleased.md` line | medium | patch | Definition of Done and precedent: bug 137's fix (9e1af92) added its line in the fix PR; a site n8n cannot call back now waits about 28 s before the sentence, a change existing sites meet. |
| Ceiling leaves out the ping's 10 s request timeout; a wall-clock `max_execution_time` (PHP on Windows) could cut the loop | low | rejected | A late callback (`none`) needs a 2xx answer, so its request time is short; a request that times out is a WP_Error, not retried. Local's php.ini sets 1200 s; on Linux the limit counts CPU time, not sleeps. A `set_time_limit` guard adds a branch for a case not shown to occur. |

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed
- `php -l post-quality-manager/includes/class-pqm-rest-api.php` -- expected: no syntax errors
- `npm run test:unit` -- expected: green (no JS change)

**Manual checks (if no CLI):**
- Story 38's licensed sitting on the release (P030 re-walk with P161): Free provisioned, Pro over it, Update workflow → success; `debug.log` clean.
