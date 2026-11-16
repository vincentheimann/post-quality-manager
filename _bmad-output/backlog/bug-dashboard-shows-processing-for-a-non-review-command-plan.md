---
title: 'The Dashboard queue shows Processing only while a review runs'
type: 'bugfix'
ticket: 128
created: '2026-11-16'
status: done
baseline_revision: '9c2120de0ce3fc8ead2867d2afb89bae7e4c0265'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/backlog/bug-dashboard-shows-processing-for-a-non-review-command.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The Fix next queue's status badge reads Processing whenever the row is busy. A row counts as busy from a Review click until the start resolves, and while the polling hook awaits any command on the post. So a Key Takeaways run, or any other command, shows Processing on the Dashboard, while Post Reviews keeps the post's last result.

**Approach:** The badge reads only `inFlight( item )`, the server's review in flight, exactly as Post Reviews' badge does (story 5.4). `busy` still disables the row's Review button, and `isStarting` still shows the button's spinner. Owner decision (2026-11-16): the Dashboard matches Post Reviews, with no Processing between a Review click and the start. The button's spinner covers that wait.

</frozen-after-approval>

## Tasks & Acceptance

**Execution:**
- [x] `src/dashboard/fix-next-queue.js`: the `StatusBadge` reads `inFlight( item ) ? 'processing' : item.latest_status`. `disabled={ busy }` and `isBusy={ isStarting(…) }` stay.
- [x] `tests/js/fix-next-queue.test.js` (new): render `FixNextQueue` in jsdom and check three rows:
  - a post busy with `in_flight: [ 'takeaways' ]` shows its last status, and its Review button is disabled;
  - a post with `in_flight: [ 'review', 'takeaways' ]` shows Processing;
  - a post starting (busy, starting, nothing in flight) shows its last status with the button's spinner.

  The first and third fail on 4.0.0.

**Acceptance Criteria:**
- Given a post in the queue with only a non-review command in flight, when the Dashboard loads or refetches, then its badge shows the same last status Post Reviews shows (ticket criterion 1).
- Given `npm run test:unit`, when it runs, then the new test passes on the fix and its first and third rows fail with the old badge line (ticket criterion 2).
- The ticket's Boundaries still hold: a review in flight reads Processing; a refused start shows its row warning and the last result; the Review button is disabled during any run; polling, Post Reviews, the REST API and `in_flight` are unchanged.

## Implementation Notes

- Route oneshot: one changed expression and one new test file. There is no REST, schema or template change.
- The orchestrator implements it directly; this short route has no implementer subagent.
- Built 2026-11-16:
  - Badge: the condition is now `inFlight( item )` alone, with a short comment naming bug 128. `busy` and `isStarting` are untouched.
  - New test `tests/js/fix-next-queue.test.js`, 3 cases, rendering `FixNextQueue` alone. It mocks `@wordpress/components` and `@wordpress/api-fetch` (`runs.js` imports it) and sets `IS_REACT_ACT_ENVIRONMENT`, as the other suites do.
  - Against the old line (stashed), cases 1 and 3 fail ("Processing…" received, "Needs revision" expected) and case 2 passes; all 3 pass on the fix.
  - `npm run test:unit`: 13 suites, 214 tests green on three runs. A first run reported 1 suite failed with 185 tests passed and did not name the suite in the captured lines; it never recurred, so it is recorded as a possible flake.
  - `wp-scripts lint-js` is clean on both files.
- 2026-11-16, after #12 merged (264f08a; its CI 4/4 green): done on the owner's word, marked with `tickets.py mark`. The merge came after the 4.0.1 tag, so the fix shipped in 4.0.2 (#13; Freemius deployment 174834, pending).

## Review Triage Log

**Pass 1 (Quick, 2026-11-16):** 2 findings, both real, both low. One patch, one defer. The reviewer found no bug, no broken rule and no unmet criterion.

- **low, patch:** the Processing row's post 2 was also in the busy set, so it would read Processing on the old line too and could not tell which input made it so. Post 2 is now not busy (the hook gave up, or the page was reloaded), so the test proves that the server's `in_flight` alone shows Processing; its disabled assertion was dropped. The other half of the finding, a refused start with no test, is rejected: this change does not touch that path (ticket 32's Notice is unchanged), and adding the case would widen the test beyond this bug.
- **low, defer:** the ticket's Notes still list the owner's question on the click-to-start window as open, although the owner decided it on 2026-11-16 (Intent). A build never writes to a ticket file; the decision goes into the ticket's records at the done step (`deferred-work.md`).

## Verification

**Commands:**
- `npm run test:unit` -- expected: all suites pass, the new one included.
- `npm run lint:js` on the two files, if the repository defines it -- expected: clean.
