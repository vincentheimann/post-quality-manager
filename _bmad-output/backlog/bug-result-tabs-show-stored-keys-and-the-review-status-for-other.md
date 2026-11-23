---
id: 145
type: bug
title: "Result tabs show stored keys and the Review status for other agents"
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

# Result tabs show stored keys and the Review status for other agents

## Description

The detail panel's result tabs print some stored values as they are stored (`needs_sources`, `needs_revision`, "Seo") instead of labels, and each tab shows a Review-style status pill beside the agent's own score, which reads as if each agent set the post's status. A Takeaways run toasts a review status, and posts audited by cron still read "Not analyzed". Each tab should speak for its own agent, in labels.

## Reproduction

1. Pro 4.0.7 licensed, a post with a Sources result (story 38's sitting F, 2026-11-23): Details → Sources. Actual: "Overall verdict" shows `needs_sources`. Each tab (Takeaways 50, Sources 60) shows the "Needs revision" pill beside its score.
2. Pro 4.0.5 (N4, 2026-11-19): Details → Audit. Actual: the Audit tab's Review part shows `needs_revision`, and its SEO part is headed "Seo".
3. Free 4.0.5 (N3): run Takeaways. Actual: the toast reads "Takeaways complete — score 50, Needs revision." (the check's fixed 80/50 score and a review status).
4. Pro 4.0.5 (N4): Automated Reviews set to Audit; after the cron run the audited posts still read "Not analyzed · Never" in Post Reviews' list and counts.
5. Expected: stored values show as labels; each tab's status (if any) is its agent's own, worded for that agent; a cron Audit counts as a review of the post, or the list says why it does not.

## Cause Hypothesis

The result view humanizes keys but not values, and title-cases "seo". The status pill and the toast reuse the review status scale for every command. The list's state and counts read Review rows only.

## Acceptance Criteria

1. **Labels, not stored keys**
   **Given** results whose fields hold stored values or the key "seo"
   **When** a tab shows them
   **Then** each shows its label, and SEO reads "SEO"
2. **Each tab speaks for its agent**
   **Given** a post with results from several agents
   **When** a tab or a run's toast shows a status
   **Then** the status is that agent's own, worded for it, or no status shows
3. **A cron Audit is reflected**
   **Given** posts audited by Automated Reviews
   **When** Post Reviews loads
   **Then** the list reflects the audit as the owner decides
4. **Tests cover the condition found and fixed**
   **Given** the JS tests
   **When** they run
   **Then** a test that fails on the defect covers a stored value, the SEO heading and a non-Review status
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behaviour already holds, with the evidence recorded in Notes — this supersedes 1 to 4

## References

- parent — none
- source — story 38's plan, Implementation Notes: the 2026-11-19 frames N3 and N4, the 2026-11-23 licensed sitting (P054)
- deferred — `_bmad-output/initiative-post-quality-manager/deferred-work.md` L146–148

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P3 — the results are right; their labels mislead. Risk low.
- Open question: which status and score a non-Review agent shows, and whether a cron Audit marks a post as analyzed.
