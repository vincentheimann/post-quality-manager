---
id: 142
type: bug
title: "Processing placeholders linger in History and some outcomes never show"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P2
estimate: ""
---

# Processing placeholders linger in History and some outcomes never show

## Description

Every run writes a `processing` placeholder before n8n answers. History keeps each placeholder as its own lasting "Processing…" entry beside the result and counts it, so five runs read "History (10)". A run whose callback never arrives stays Processing for good, and a run that ends before the list refreshes never shows its outcome. The user should see each run once, with its outcome, and no run should stay Processing forever.

## Reproduction

1. Local WordPress 6.6 with a disposable n8n 2.48.5 (story 38's N3 and N4 frames, 2026-11-19; the licensed sitting on Pro 4.0.7, 2026-11-23).
2. Run a Review and a Rewrite on a post, then open Details → History.
3. Actual: History lists a "Processing…" entry for each run beside its result ("History (10)" for 5 runs; "Rewrite Processing…" under "Rewrite Could not complete"); the table keeps those rows `processing`.
4. Stop n8n after it accepts a run, or let the callback be refused (a wrong token): the post reads Processing for good, leaves bulk selection, and Details stays disabled for 3 minutes after every load; nothing on the server expires the placeholder (deferred-work.md L214–216).
5. A run whose result is stored before the next list refresh never shows its toast, and the Dashboard does not refetch its average and trend; a fast failure of a command other than Review stays silent until Details is opened (deferred-work.md L217–219).
6. Insert into Post (Pro 4.0.7): the owner's Chrome saw no visible confirmation, only the live region's "Takeaways inserted into the post. The previous version is in Revisions." The code sends the message to the page's toast; whether the toast shows is to check.
7. Expected: one History entry per run with its outcome; a run with no callback ends in a stated failure after a bounded time; every outcome shows once.

## Cause Hypothesis

History renders every stored row of the post, placeholders included, and the placeholder row is never replaced or deleted when the result lands as a second row. Nothing on the server times a placeholder out. The outcome toast fires on the polling loop's transition from processing to done, which a result stored between two refreshes never makes. For Insert, the toast list may not render outside the polling path (not read).

## Acceptance Criteria

1. **One entry per run**
   **Given** a post with runs whose results were stored
   **When** History shows
   **Then** each run appears once with its outcome, and the count equals the runs
2. **No run stays Processing for good**
   **Given** a run whose callback never arrives
   **When** the bounded wait passes
   **Then** the post shows the run as failed with a reason that says n8n did not call back, and Details is enabled
3. **Every outcome shows once**
   **Given** a run whose result is stored before the next refresh, or an Insert into Post
   **When** the page learns of it
   **Then** the user sees its outcome once, and the Dashboard's figures update
4. **Tests cover the condition found and fixed**
   **Given** the harnesses and the JS tests
   **When** they run
   **Then** a test that fails on the defect covers History with placeholders, an expired placeholder, and a result stored between two refreshes
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behaviour already holds, with the evidence recorded in Notes — this supersedes 1 to 4

## References

- parent — none
- source — story 38's plan, Implementation Notes: W137 (2026-11-18), the 2026-11-19 frames N3 and N4, the 2026-11-23 licensed sitting (P167)
- deferred — `_bmad-output/initiative-post-quality-manager/deferred-work.md` L214–219 (bug 137's investigation and review)

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P2 — results land and can be read, but History misleads and a lost callback blocks a post with no way out but waiting. Risk medium — the polling kit and the in-flight queries are shared by Post Reviews, the Dashboard and the setup dialog.
- Open question: how long a placeholder lives before it is called failed, and whether the placeholder row is replaced or hidden.
