---
id: 144
type: bug
title: "The setup guide and first-run notices disagree with what is set up"
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

# The setup guide and first-run notices disagree with what is set up

## Description

The setup guide's progress and the Dashboard's first-run notices read a stored progress record that drifts from the site's real state. After Start over the guide still shows the first review done and can land on the wrong path; settings made outside the guide still show as steps to finish; a `score` result completes the setup with no Review; and the Dashboard can show "needs attention" and "You are all set" together. The guide and the notices should follow what is set up.

## Reproduction

1. Local WordPress 6.6, Free 4.0.5 provisioned against a disposable n8n 2.48.5 (story 38's N2a and N2b frames, 2026-11-19).
2. Run the first review, then Start over in the n8n connection section, then open the setup guide.
   - Actual: "Run your first review" stays ticked (progress `complete: true`); on a site that once chose Set up by hand, the page lands in state 3 "Setting up by hand." although a passing key is stored.
3. Pro 4.0.7 on a site migrated from 3.9.0 with the schedule on in Settings → Automation (2026-11-23):
   - Actual: the n8n Workflow page offers "Finish setting up: Blog Profile, Editorial Standards, scheduled reviews"; the guide opens on "Step 4 of 6" while listing 7 entries ("Next steps" counted).
4. Free 4.0.4 (N1, 2026-11-18): a `score` result counts as the first result, so the setup completes and the welcome notice goes with no Review scored; `first-review.js` names a "step 6" that no setup path has.
5. Pro 4.0.5 on the Free-variant workflow with no result stored (N4): the Dashboard shows the yellow "…needs attention before your first review" and the blue "You are all set — run your first review" together; the yellow line overstates, since Review and Takeaways run on the Free variant (deferred-work.md L204–206).
6. The cron nudge shows to Editors (ticket 16's design) while ticket 1's AC6 says never to non-admins, and its Dismiss is one site-wide option, so an administrator's dismissal hides it for Editors too (Pro walk 2026-11-17, P103; N4).
7. Expected: the guide, the "Finish setting up" link and the notices reflect the current connection, results and settings; one first-run message at a time; the step count matches the list.

## Cause Hypothesis

The progress record (`postquality_setup` progress) is written by the guide's own steps and read as truth; Start over and settings saved elsewhere do not update it, and `manual: true` outranks a passing key. The first-result check accepts any command. The two Dashboard notices read different signals (the workflow state and "a URL is saved") and never each other.

## Acceptance Criteria

1. **Start over resets the progress**
   **Given** a set-up site
   **When** the owner chooses Start over
   **Then** the guide shows no step done past the connection, and the page lands on the state the stored key gives
2. **Settings made elsewhere count**
   **Given** a Blog Profile, Editorial Standards or schedule saved in Settings (or migrated)
   **When** the n8n Workflow page and the guide load
   **Then** those steps show done and the "Finish setting up" link lists only what is missing
3. **Only a Review completes the first-review step, and one first-run message shows**
   **Given** a site whose only result is a `score`, or a site in Needs attention with no result
   **When** the Dashboard loads
   **Then** the first-review step stays open, and one first-run message shows, worded for what the variant can run
4. **The step count matches the list**
   **Given** the guide on any step
   **When** it shows "Step n of m"
   **Then** m counts the entries the list numbers, and no copy names a step no path has
5. **The cron nudge follows the owner's rule**
   **Given** the owner's decision on who sees the nudge
   **When** an Editor and an administrator load the Dashboard and one dismisses it
   **Then** the nudge shows as decided, and a dismissal hides it for that user only
6. **Tests cover the condition found and fixed**
   **Given** the harnesses and the JS tests
   **When** they run
   **Then** a test that fails on the defect covers Start over, a migrated schedule, a `score`-only site and the two notices
7. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behaviour already holds, with the evidence recorded in Notes — this supersedes 1 to 6

## References

- parent — none
- source — story 38's plan, Implementation Notes: the 2026-11-17 Pro walk (P103), N1 (2026-11-18), the 2026-11-19 frames N2a, N2b, N4, the 2026-11-23 licensed sitting (P054)
- deferred — `_bmad-output/initiative-post-quality-manager/deferred-work.md` L204–206 (bug 129's review)

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P2 — a re-setup can land on the wrong path and the notices contradict each other; the pages still work. Risk medium — one progress record read by the guide, the notices and the Dashboard.
- Unknown: whether Editors see the cron nudge (ticket 1's AC6 against ticket 16); the owner settles it before the build.
- Handoff: bug 143 also touches the page after Start over; build this one first or with it.
