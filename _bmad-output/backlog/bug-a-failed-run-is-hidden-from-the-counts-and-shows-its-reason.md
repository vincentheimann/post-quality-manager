---
id: 141
type: bug
title: "A failed run is hidden from the counts and shows its reason as raw JSON"
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

# A failed run is hidden from the counts and shows its reason as raw JSON

## Description

When a post's latest run fails, the post drops out of every Dashboard tile and Post Reviews chip and falls to the end of Fix next, so the one post that needs attention is the hardest to find. Its reason, when shown, is n8n's raw error string: escaped JSON with a request id. A failed Rewrite's reason is shown nowhere, and the plugin's give-up sentence sends the user to an n8n execution list that marks the failed run "success". A failed run should count and rank as one, and say what went wrong in plain words.

## Reproduction

1. Local WordPress 6.6, Free 4.0.5 provisioned against a disposable n8n 2.48.5 (template v11), the Anthropic credential holding an invalid key (story 38's N3 frame and W137 walk, 2026-11-18/19).
2. Run a Review on a post. The run fails at Anthropic and the callback stores `error`.
3. Actual:
   - Post Reviews reads "All 7" while the four chips add to 5; the Dashboard tiles leave the post out; Fix next ranks it after Not analyzed, so with five other posts to fix it leaves the queue.
   - Details shows the reason as `401 - "{\"type\":\"error\",\"error\":{\"type\":\"authentication_error\",\"message\":\"invalid x-api-key\"},\"request_id\":\"req_…\"}"`.
   - A failed Rewrite (Pro 4.0.5, N4): no tab shows its reason; History says only "Could not complete"; the toast says to check the n8n execution log.
   - n8n 2.48.5 lists the failed executions as "success" (the template routes the error to the callback).
4. Also seen: a failed-latest row in the old shape (empty status, score 0) shows a "0" badge while the post reads "Not analyzed" (T2, planted in story 5.4's failure shape); the Free template's score report prints "N/A/10" for an unscored metric (N1).
5. Expected: the failed post counts and ranks as needing attention; the reason reads as one plain sentence ("Anthropic refused the API key: invalid x-api-key"); a failed Rewrite's reason is reachable; the give-up sentence does not point at a list that says "success".

## Cause Hypothesis

- Counts: the list's chips, the Dashboard tiles and the Fix next ranking group by the latest row's status, and `error` has no slot in any of them (deferred-work.md L21–23 asks the owner which: a chip, a severity slot, a badge).
- Reason: the template's Parse nodes read `resp.error?.error?.error?.message` first; n8n 2.48.5 puts the reason in `resp.error.message` as a string holding the API's JSON, so the fallback stores the whole string.
- Rewrite: the result view has no Rewrite tab, and History renders only the status label.

## Acceptance Criteria

1. **A failed run counts and ranks**
   **Given** a post whose latest run stored `error`
   **When** the Dashboard and Post Reviews load
   **Then** the post appears in a tile or chip the owner chose for failures, the chips add up to "All", and Fix next ranks it with the posts that need attention
2. **The reason reads plainly**
   **Given** a run that failed at the provider with a JSON error body
   **When** Details, History or the toast shows the reason
   **Then** it shows the provider's message as one sentence, without escaped JSON or a request id, for every command including Rewrite
3. **The give-up sentence and the score report hold**
   **Given** a run that failed or a metric with no score
   **When** the plugin gives up waiting or the Free template writes its report
   **Then** the sentence does not send the user to a list that marks the run "success", and no "N/A/10" appears
4. **Tests cover the condition found and fixed**
   **Given** the harnesses and the JS tests
   **When** they run
   **Then** a test that fails on the defect covers the counts with an `error` row, the reason parsed from n8n 2.48.5's error shape, and a failed Rewrite
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behaviour already holds, with the evidence recorded in Notes — this supersedes 1 to 4

## References

- parent — none
- source — story 38's plan (`_bmad-output/backlog/story-wordpress-org-directory-listing-plan.md`), Implementation Notes: N1, W137 (2026-11-18) and the 2026-11-19 frames N3, N4, T2
- deferred — `_bmad-output/initiative-post-quality-manager/deferred-work.md` L21–23

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P2 — the run did fail and the user can open n8n, but the post that needs attention is hidden and its reason is unreadable. Risk medium — the count and ranking queries every screen reads, and a template change that brings a re-import notice.
- Open question: where a failed post counts (its own chip, Needs revision, or a badge on its row); deferred-work.md L21–23 asks the same.
