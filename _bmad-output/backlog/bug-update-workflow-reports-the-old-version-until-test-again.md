---
id: 99
type: bug
title: "After Update workflow, the n8n Workflow page reports the old workflow version until Test again"
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

# After Update workflow, the n8n Workflow page reports the old workflow version until Test again

## Description

An administrator clicks **Update workflow** on the n8n Workflow page. n8n takes the new version, but the page still reads "Your workflow is version 7; this plugin needs 8", with "Version 7 · checked just now by a test" and **Update workflow** still the primary button. A **Test again** a moment later shows the green block at version 8. The update worked; the page says it did not, and invites a second update.

## Reproduction

1. The owner's production site, 2026-11-03, plugin 3.10.2 (template version 8), a workflow the plugin manages at version 7, the owner's self-hosted n8n.
2. n8n Workflow page → **Update workflow**.
3. Actual: the yellow block "Your workflow is version 7; this plugin needs 8.", "The plugin can update it in place…", "Version 7 · checked just now by a test.", with **Update workflow** and **Test again** (owner's screenshot, kept out of the repository).
4. **Test again**, seconds later: green, "Your workflow answers. Version 8 · checked just now by a test."
5. Expected at step 3: the green version 8 block directly.

## Cause Hypothesis

Updating republishes the workflow, and n8n answers the publish before the new version serves the webhook. This is the publication window recorded in `memory/n8n-provisioning.md` (bug 80, bug 84). Bug 84 made the ping after an update retry a 404 inside that window. Here the ping got a 200 from the previous version instead, and that answer is not retried: the ping reports version 7, and the page records it as "checked by a test".

## Acceptance Criteria

1. **The page shows the updated version after Update workflow**
   **Given** a managed workflow one template version behind, and n8n still serving the previous version for a moment after the update
   **When** the administrator clicks **Update workflow**
   **Then** the page shows the green block at the new version without a second click, or says the new version is not answering yet, never "needs N" with **Update workflow** offered again
2. **Tests cover the condition found and fixed**
   **Given** the test suite
   **When** it runs
   **Then** a test that fails on the defect and passes on the fix covers the reproduction (the ping after the update answering the old version first), and one covers each related case the fix touched
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behavior already holds, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 and 2

## References

- parent — none
- related — _bmad-output/backlog/bug-update-workflow-reports-a-404-it-just-caused.md (bug 84: the 404 of the same window, retried)
- source — memory/n8n-provisioning.md (the publication window, PUT republishes)
- found by — _bmad-output/backlog/story-production-site-runs-each-release-as-licensed-pro.md (story 98's walk)

## Notes

- Fact (story 98's walk, 2026-11-03): **Test again**, seconds after the update, recorded version 8; the owner's next command recorded it again ("version 8, via command"). So n8n served version 8 within seconds of the update.
- Fact (the sprint walk, 2026-11-04, a disposable n8n 2.46.6, Free → Pro): the ping sent right after Update workflow reported the Free variant as outdated; a second ping a moment later answered the Pro variant, up to date. The old version still answers for a short while after the PUT on the current n8n too, and the variant flips with it (bug 113's plan, Implementation Notes).
- Open question: whether a retry on the old version alone covers it, or the update should wait for the new version in another way. Settled at refinement, from the n8n source as for bug 80.
- Filed 2026-11-03, unrefined.
- Done 2026-11-05 (lands with the PR's merge): the open question was settled from the n8n 2.46.6 source. The PUT queues the new version and answers. The webhook runs the published-version row until the publication consumer moves it, and no public field tells when, so the ping is the only proof. The ping after a PUT or a provisioning now retries a passing answer from an older template like a 404 (3 pings, 2 s apart). A passed update forgets the stored report. An old answer that outlasts the retries is not recorded: "Workflow updated. n8n is still switching to the new version: press Test the connection in a few seconds." Walked on a disposable n8n 2.46.6: the first ping after a Free → Pro update ran the old Free version, and the retry ran the Pro one, so the page showed version 9 in one click.
