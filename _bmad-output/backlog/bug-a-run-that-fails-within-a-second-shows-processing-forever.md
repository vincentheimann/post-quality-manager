---
id: 137
type: bug
title: "A run that fails within a second shows Processing forever"
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

# A run that fails within a second shows Processing forever

## Description

When n8n reports a failed run within about a second of the trigger, for example because Anthropic refuses the key, the post never leaves "Processing…". Post Reviews disables Details after a reload, so the stored reason cannot be opened, and the setup dialog's first review gives up after three minutes with "No result came back…" instead of saying the review could not complete. The user should see "Could not complete" with the reason, as for a failure that takes longer.

## Reproduction

1. Local WordPress 6.6, Free 4.0.4, set up by hand against n8n 2.48.5 (story 38's N1 frame, 2026-11-18).
2. Give the two Claude nodes an Anthropic credential the API refuses (the frame's key answered 401 "invalid x-api-key").
3. Run Review on a post, from Post Reviews → Details or from the setup dialog's "Run your first review". n8n's model call fails in about 0.4 s and the workflow posts the error back at once.
4. Actual: the error row ("Review Agent could not get an answer from the model: 401 - … invalid x-api-key") is stored in the same second as the run's `processing` placeholder (10:41:08 and 10:48:16). Post Reviews shows "Processing…" and "Review is running in n8n — results appear here when it finishes.", Details is disabled after a reload, and the post's review list returns the placeholder before the error row. The dialog gives up after 181 s with "No result came back within 3 minutes…".
5. Expected: the post shows "Could not complete" with that reason, and the dialog says the review could not complete.
6. Seen 2 of 2 with Review, and once with the `score` command.

## Cause Hypothesis

The trigger writes a `processing` placeholder right after n8n's webhook answers, and the callback writes the result as a second row. Both carry the time to the second. The queries that pick a post's latest row and its in-flight runs take the newest second without breaking a tie, so a result in the same second as its placeholder can lose to it, and the run counts as in flight for good. The same holds if WordPress is slow enough that the result is stored before the placeholder. The dialog's wait reads the same latest-row order.

## Acceptance Criteria

1. **A fast failure shows as failed**
   **Given** a run whose failure is stored in the same second as its placeholder, or before it
   **When** Post Reviews, the Dashboard or the setup dialog read the post
   **Then** the run is not in flight, the post shows "Could not complete" with the stored reason, and Details opens it
2. **A fast success shows as done**
   **Given** a run whose result is stored in the same second as its placeholder
   **When** the same screens read the post
   **Then** the result shows, as for a slower run
3. **Runs that take longer are unchanged**
   **Given** a run whose result is stored a second or more after its placeholder
   **When** the same screens read the post
   **Then** what they show is unchanged from 4.0.4
4. **A newer run still counts as in flight**
   **Given** a post with a finished run and a newer run whose placeholder is the latest row
   **When** the screens read the post
   **Then** the newer run shows as running
5. **Tests cover the condition found and fixed**
   **Given** the harnesses and the JS unit tests
   **When** they run
   **Then** a test that fails on the defect and passes on the fix covers a result and its placeholder in the same second, and one covers a result stored before its placeholder
6. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the post already shows "Could not complete" with the reason, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 5

## References

- parent — none
- source — `_bmad-output/backlog/story-wordpress-org-directory-listing-plan.md`, pass list row P007 and Implementation Notes, the N1 entry (2026-11-18)
- logs — the N1 frame's results, session scratchpad (not committed): the two error rows and their placeholders, 10:41:08 and 10:48:16

## Notes

- Decision (2026-11-18, on the owner's standing rule for this pass): found in story 38's pre-production pass; it joins story 38's `after` and ships in a 4.0.x release before submission, as bugs 132 and 135 did.
- Fact: a wrong key in the provisioned (key) path is refused before anything is created, so this is reached mainly through a hand-made setup, a key revoked later, a rate limit or an overloaded API: any failure n8n reports within a second.
- Assumption: severity P2 — normal runs work; a fast failure loses its reason in the UI, and the reason can still be read in n8n's execution data. Risk medium — the queries every screen reads a post's state through.
- Open question: whether the Dashboard's queue and tiles, which read the latest row per post the same way, show the same tie; the plan checks each reader of the latest row.
- Done 2026-11-23 (owner's word): fixed by #33 (main 9e1af92); released in 4.0.5 (#34, notes #35); the Local walk passed (#36), and story 38's N3 frame passed P007 on 4.0.5 against a disposable n8n 2.48.5.
