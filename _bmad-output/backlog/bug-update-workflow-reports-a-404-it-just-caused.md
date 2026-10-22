---
id: 84
type: bug
title: "Update workflow reports a failed test right after a successful update"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: true
hitl: false
risk: low
severity: P3
---

# Update workflow reports a failed test right after a successful update

## Description

An administrator clicks **Update workflow** on the n8n Workflow page. n8n accepts the new version, but the test ping that follows answers 404, so the page turns red: "The workflow was updated in n8n. n8n responded with 404. Check the URL and make sure the workflow is activated in n8n." A **Test again** a moment later passes. The update worked; the page says it did not, and sends the administrator looking for a fault that is not there.

## Reproduction

1. The owner's production site, 2026-10-22, plugin built from main 67879a0 (template version 7), a workflow the plugin manages at version 6, the owner's self-hosted n8n.
2. n8n Workflow page → **Update workflow**.
3. Actual: the block turns red with the 404 sentence above and still reads "Version 6 · checked 6 days ago by a test."
4. **Test again**, seconds later: green, "Your workflow answers. Version 7 · checked just now by a test."
5. Expected at step 3: the green version 7 block directly.

## Cause Hypothesis

Updating a workflow republishes it in n8n, and n8n answers the publish before its webhook is registered again (the publication window recorded for bug 80 in `memory/n8n-provisioning.md`). Bug 80 made the ping after provisioning retry a 404 for that window; the ping after an update still pings once, on the assumption that only provisioning activates a workflow.

## Acceptance Criteria

1. **An update reports the version it installed**
   **Given** a managed workflow one version behind and n8n still registering its webhook when the update returns
   **When** the administrator clicks Update workflow
   **Then** the page shows the updated version as answering, without a manual Test again, and a 404 that outlasts the window is still reported as today
2. **Tests cover the condition found and fixed**
   **Given** the harness sweep
   **When** it runs
   **Then** a row where the first ping after the update answers 404 and the next passes fails on the defect and passes on the fix, and a row where every ping answers 404 still reports the failure after the same bounded retries as provisioning
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the update already reports the installed version, with the evidence recorded in Notes — this supersedes 1 and 2

## References

- parent — none
- source — `_bmad-output/backlog/story-full-round-trip-connection-test-plan.md` (Manual checks outcome, 2026-10-22)
- related — `_bmad-output/backlog/bug-two-provisioning-runs-orphan-the-first-plan.md` (bug 80's retry)

## Notes

- Decision: owner, 2026-10-22 — file and fix right after story 2's production walk, before story 57.
- Assumption: the rotation's ping (`POST /n8n/token`) does not republish the workflow (it changes a credential only), so it keeps one ping; the builder confirms from `memory/n8n-provisioning.md` before leaving it out.
