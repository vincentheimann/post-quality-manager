---
id: 128
type: bug
title: "The Dashboard shows Processing for a post where only a command other than Review runs"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# The Dashboard shows Processing for a post where only a command other than Review runs

## Description

When Key Takeaways (or any command other than Review) runs on a post, the Dashboard's Fix next queue shows that post as "Processing…", while Post Reviews keeps showing its last result, as story 5.4 decided: Processing means a review is running. A blogger reading the Dashboard thinks a review is under way and waits for a score that is not coming. Expected: both pages show Processing for the same posts, those with a review running.

## Reproduction

1. Story 38's clean Local site (WordPress 6.6), the Free build of release v4.0.0, a webhook URL saved that answers 200 and never calls back (the walk's listener, `docs/local-verification.md` steps 3 and 4), and one published post with no review.
2. As an administrator, run Key Takeaways on the post (Post Reviews → Details → Key Takeaways).
3. Open the Dashboard and read its queue within three minutes, then open Post Reviews.
4. Actual (story 38's Free walk and the owner's look, 2026-11-11): the Dashboard's queue row for the post reads "Processing…"; Post Reviews shows the post's last status, "Not analyzed".
5. Expected: the Dashboard row shows "Not analyzed" too.

Three minutes after the Dashboard first lists the post, it gives up waiting and the row shows the last status again; each reload starts the three minutes over.

## Cause Hypothesis

The queue row's badge reads Processing when the row is busy or when the server lists a review in flight for the post. The second test is already right: it asks for Review alone. The first is not about Review: the Dashboard calls a row busy from its own Review click until the list refetch after the start resolves, and while the shared polling hook awaits the post, and the hook awaits a post for any command in flight. Post Reviews feeds its badge from the review test alone and uses the awaited flag only to disable the Details toggle and the panel's buttons, as story 5.4 chose on purpose. So the defect is in what the Dashboard's badge reads, not in the polling hook or the server's `in_flight`.

## Acceptance Criteria

1. **Only a running review shows Processing on the Dashboard**
   **Given** a post in the Fix next queue with a command other than Review in flight and no review in flight
   **When** the Dashboard loads, and on each silent refetch while that command runs
   **Then** the post's badge shows its last result, the same status Post Reviews shows for it
2. **Tests cover the condition found and fixed**
   **Given** the JS unit tests
   **When** they run
   **Then** a test that fails on 4.0.0's queue and passes on the fix renders the queue for a post with only a non-review command in flight, and one for a post with a review in flight
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it runs on the current code
   **Then** the Dashboard already shows the post's last result, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 and 2

## Boundaries

- Must not change: a post with a review in flight, alone or beside another command, reads Processing on the Dashboard, the same status Post Reviews shows for it.
- Must not change: a refused Review start from a queue row shows its warning in the row (ticket 32) and the last result in the badge.
- Must not change: the Review button's disabled state while any command runs on the post (Post Reviews' panel disables its buttons the same way, story 5.4's "Processing row"); polling, its three-minute give-up and its toasts; Post Reviews; the server's `in_flight` and the REST responses.

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md L617 (the look's triage, defect (a)) and L625 (owner decision 7a)
- decision — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md L45 (matrix row "Processing row"), L55 (the row shows Processing when `in_flight` holds `review`) and L111 (why: a takeaways run must not override a review's badge; other commands show through the disabled Details and the panel) (story 5.4)
- decision — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md L80 and L127 (Review from a queue row: "requested" toast, then Processing) (story 5.6)
- blocks — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38; the fix ships in 4.0.1 before submission)

## Notes

- Decision (owner, 2026-11-11, 7a): this defect ships in patch release 4.0.1, before story 38's submission.
- Fact (source read 2026-11-11, the plugin tree of v4.0.0, equal to 78c8bc8):
  - `src/dashboard/fix-next-queue.js` L62–68: the badge is `processing` when `busy || inFlight( item )`; `busy` is the `isBusy` prop (L40), which also disables the Review button (L73); the button shows its own spinner while the start is pending (L74).
  - `src/dashboard.js` L253–256: `isBusy` is `awaited.has( postId ) || pendingPostId === postId`.
  - `src/kit/use-single-run.js` L53–61 and L108–120: `pendingPostId` holds from the click until the list refetch after the start resolves.
  - `src/kit/runs.js` L75–79: `inFlight( item, command = 'review' )` checks one command, Review by default.
  - `src/kit/use-polling.js` L49–57 and L110–125: `awaited` holds every post with any command in `in_flight` not yet given up; L144–152 give up after three minutes; each page load arms the keys afresh.
  - `src/post-reviews-table.js` L271–275: Post Reviews' badge reads `inFlight( item )` only; L294 and L640–644 use `awaited` for the Details toggle and the panel's `busy`.
  - `inFlight( item )` reads server state with no age limit, so a review placeholder that never receives its result reads Processing on both pages after the give-up and after every reload; that is the same on both pages and out of scope.
- Fact: on the Free build the only commands are Review and Key Takeaways, so every Key Takeaways run shows the defect; on Pro, Sources, SEO, Audit and Rewrite do too.
- Open question (for the owner): the window between a Review click on the Dashboard and the refetch after the start resolves. Today the row reads Processing there, and Post Reviews never shows Processing in that window; the Review button already shows a spinner. Keep it on the Dashboard only, or match Post Reviews? Story 5.6's plan (L80, L127) does not settle which comes first.
- Assumption: severity P3. The badge is wrong for a few minutes and no action is lost, since the Review button is disabled during any run on both pages.
- Assumption: risk low. The change stays in what the Dashboard's badge reads; the shared polling hook and Post Reviews do not change (Boundaries). A change to the shared kit would make it medium.
- Fact: the JS unit tests already render whole pages with Jest and jsdom (`tests/js/post-reviews-table.test.js`); no test covers the Dashboard queue today.
- Handoff: bugs 125, 129 and 130 also ship in 4.0.1; none of them touches the Dashboard's React code.
