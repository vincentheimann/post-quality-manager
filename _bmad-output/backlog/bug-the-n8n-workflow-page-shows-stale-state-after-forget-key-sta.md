---
id: 143
type: bug
title: "The n8n Workflow page shows stale state after Forget key, Start over or a deleted workflow"
parent: none
covers: []
after: [144]
assignee: ""
refined: false
hitl: false
risk: medium
severity: P3
estimate: ""
---

# The n8n Workflow page shows stale state after Forget key, Start over or a deleted workflow

## Description

After Forget key, Start over, a refused key or a workflow deleted in n8n, the n8n Workflow page keeps saying things that are no longer true: it promises an update and a token rotation it cannot do, keeps a checked version for a workflow that is gone, hides the connection section, and shows no reply to Start over. A second setup started while the first runs is told the site is "already set up". The page should state the site's current state after each of these actions.

## Reproduction

Local WordPress 6.6, Free 4.0.5 set up by key against a disposable n8n 2.48.5 (story 38's N2a and N2b frames, 2026-11-19), unless stated.

1. Start a provisioning run, and start a second one while the first is still running on a site with no setup yet. Actual: 409 "n8n is already set up for this site. Choose Start over in the n8n connection section to set it up again." Expected: a sentence saying a setup is already running.
2. On a managed site, Forget key. Actual: the managed line still says the plugin "can update the workflow and rotate the webhook token from this page", while Update and Rotate token are off.
3. After Start over, Forget key. Actual: the page drops to state 1 and the n8n connection disclosure disappears; the refresh tries to focus an element that no longer exists; the kept address shows only inside the setup guide.
4. Start over (with or without removal in n8n). Actual: its reply message is shown nowhere.
5. A refused key on a provisioned site with an outdated report. Actual: the primary turns into Re-import the workflow and opens Set up by hand; the managed line still offers update and rotation; only the Notice says to replace the key.
6. Delete the workflow in n8n, then Test again. Actual: "Version 10 · checked 2 mins ago by a test." stays under the failure; the managed line offers to update and rotate a workflow that no longer exists.
7. A failed test (mis-wiring, P236/P244). Actual: its cause shows twice, as the outcome line and as the state sentence; the outcome line is amber while the block's accent is red.
8. Pro 4.0.7 (2026-11-23): the status line reads "Version 11 · checked 17 mins ago by a command", which reads oddly.
9. Expected: after each action the page shows only actions that can run, the facts that still hold, and the action's own reply once.

## Cause Hypothesis

The managed line and the status block are rendered from the stored setup map and the last test report, and these actions update the connection but not those two; the island's refresh re-reads a body that carries no outcome line. The 409 reuses the "already provisioned" refusal for the provisioning lock.

## Acceptance Criteria

1. **The page states the current state after each action**
   **Given** each case in the reproduction
   **When** the action completes and the page refreshes
   **Then** the page offers only actions that can run, shows no version for a workflow that is gone, keeps the connection section reachable, and shows the action's reply once
2. **A running setup is named as running**
   **Given** a provisioning run in progress on a site with no setup
   **When** a second run starts
   **Then** the refusal says a setup is already running
3. **Tests cover the condition found and fixed**
   **Given** the n8n harnesses and the JS tests
   **When** they run
   **Then** a test that fails on the defect covers each of steps 1 to 6
4. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behaviour already holds, with the evidence recorded in Notes — this supersedes 1 to 3

## References

- parent — none
- source — story 38's plan, Implementation Notes: N1 (2026-11-18), the 2026-11-19 frames N2a and N2b, the 2026-11-23 licensed sitting
- design — `_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/` (story 67's states)

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P3 — each state can be recovered from and nothing is lost; the copy misleads. Risk medium — the island's refresh is shared by every connection flow.
- Open question: the wording of "checked … by a command" (step 8).
