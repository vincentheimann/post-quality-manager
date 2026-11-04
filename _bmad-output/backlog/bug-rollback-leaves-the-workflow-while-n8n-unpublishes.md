---
id: 114
type: bug
title: "A rollback or Start over leaves the workflow in n8n while n8n is still unpublishing it"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P2
estimate: ""
---

# A rollback or Start over leaves the workflow in n8n while n8n is still unpublishing it

## Description

When the plugin removes a workflow it published (a failed setup's rollback, or **Start over** with "remove from n8n" ticked), it sends `POST /workflows/{id}/deactivate`, then `DELETE /workflows/{id}` right after. On n8n 2.46.6 that DELETE answers 409 "Workflow is still being unpublished. Please try again in a few moments." every time, and the same DELETE passes a second later. The plugin takes the 409 as "could not remove": the workflow and every credential stay in n8n, the leftovers record names them, and the blogger reads "Some of what it created is still in n8n: the next try removes it first." (rollback) or "The setup was removed here. Some of it is still in n8n: the next setup removes it first." (Start over). A Start over is not followed by a setup on every site, so the objects can stay there for good.

The cause in code: `PostQuality_N8n::roll_back()` (`class-pqm-n8n.php`) sends the DELETE once, right after the deactivate, and stops at the workflow when it is refused; no credential is tried before the workflow is gone (the forced order, entry 5). `memory/n8n-provisioning.md` (the 2.42.3 unpublish finding, 2026-10-14) predicted the 409 from the source; the 2026-11-04 probe shows it is the usual answer on 2.46.6, not a rare race.

## Acceptance Criteria

1. **The workflow removed once n8n has unpublished it**
   **Given** a published workflow the plugin removes (a failed setup's rollback, Start over with removal)
   **When** n8n answers the DELETE with 409 "still being unpublished"
   **Then** the plugin removes the workflow and its credentials within the time the action promises, or records what is left and says so, never more than n8n's unpublish window requires (the wait settled at refinement, from n8n's source, as bug 80 settled its ping retry)
2. **Tests cover it**
   **Given** the harness sweep and a disposable n8n (`docs/local-verification.md`)
   **When** they run
   **Then** a harness row reproduces the 409 right after the deactivate and checks the outcome, and the walk shows Start over with removal leaving nothing named for the site in n8n

## References

- found by — _bmad-output/backlog/bug-update-workflow-fails-after-free-to-pro-plan.md (bug 113's probe, 2026-11-04)
- source — memory/n8n-provisioning.md (the 2.42.3 unpublish finding; the n8n 2.46.6 credential check line, its separate finding)
- related — _bmad-output/backlog/bug-provisioning-timeout-leaves-the-workflow-and-says-nothing-was-left.md (bug 112: another workflow left behind by a failed setup)
- code — post-quality-manager/includes/class-pqm-n8n.php (`roll_back()`, `fail()`, `start_over()`, `remove_leftovers()`, `LEFTOVERS_OPTION`)

## Notes

- Fact (bug 113's probe, 2026-11-04, a disposable n8n 2.46.6, public API): 3 of 3 tries, a DELETE right after `/deactivate` answered 409 "Workflow is still being unpublished. Please try again in a few moments."; the same DELETE a second later passed.
- Fact: today the next setup removes the recorded leftovers first, so a failed setup followed by a retry cleans up; Start over followed by no setup does not.
- Open question: retry the DELETE inside the same request (how long, how often), or poll the workflow's state, or keep the record and retry on the next page load; settled at refinement.
- Filed 2026-11-04 from bug 113's probe, unrefined.
