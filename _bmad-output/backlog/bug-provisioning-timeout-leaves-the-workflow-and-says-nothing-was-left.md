---
id: 112
type: bug
title: "A setup that times out while creating the workflow leaves it in n8n and says nothing was left"
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

# A setup that times out while creating the workflow leaves it in n8n and says nothing was left

## Description

On a fresh n8n, the setup dialog stopped with "The n8n setup stopped while creating the workflow (n8n could not be reached: cURL error 28: Operation timed out after 10008 milliseconds with 0 bytes received). Nothing was left in n8n. Try again in a moment." n8n had in fact created the workflow after the plugin stopped waiting: "Post Quality – <site>", 16 nodes, unpublished, with no credentials (the rollback removed those). The plugin recorded no leftover, so a second try creates a second workflow of the same name beside the orphan, and the blogger was told there was nothing to clean up.

The cause in code: `PostQuality_N8n::request()` waits 10 s (`class-pqm-n8n.php` L312); when the `workflow` step times out, `fail()` has no workflow id, so `roll_back()` removes the credentials only and `fail()` answers "Nothing was left in n8n." (L1187–1195).

## Acceptance Criteria

1. **A timed-out creation is cleaned up or reported**
   **Given** the `workflow` step's request times out and n8n creates the workflow afterwards
   **When** the setup stops
   **Then** the plugin finds the workflow it asked for (by the name it sent) and removes it, or records it as a leftover the next try removes first; it never says "Nothing was left in n8n." while the outcome of the creation is unknown
2. **A slow first answer does not stop the setup**
   **Given** an n8n that takes longer than today's 10 s to answer its first workflow creation
   **When** the setup runs
   **Then** the creation call waits long enough for a cold instance (the value settled at refinement), and the dialog still answers within the time the setup promises
3. **Tests cover it**
   **Given** the harness sweep
   **When** it runs
   **Then** a row reproduces the timed-out creation (the request errors, the workflow exists afterwards) and checks the cleanup and the message

## References

- found by — _bmad-output/backlog/chore-template-checked-against-current-n8n-plan.md (story 105's walk, 2026-11-04)
- source — memory/n8n-provisioning.md (entry 5: rollback and leftovers)
- code — post-quality-manager/includes/class-pqm-n8n.php (`request()`, `provision()`, `fail()`, `roll_back()`, `LEFTOVERS_OPTION`)

## Notes

- Fact (story 105's walk, 2026-11-04, n8n 2.46.4 installed minutes before on Windows): the first-ever creation timed out after 10 s; the same template created in 0.05 s on the warm instance and in 0.66 s right after a restart. The cold cost was the first run of a fresh install (most likely the antivirus reading the new files), so real sites meet it on a first setup after installing or upgrading n8n, or on a slow host.
- Fact: the orphan was unpublished and carried no credentials; it was deleted by id during the walk.
- Open question: find the orphan by name only, or by name and creation time; and whether the rollback should also run when the creation answered nothing at all (connection refused).
- Filed 2026-11-04 from story 105's walk, unrefined.
- Done 2026-11-05: fixed by #242 (main ee0689f, built with bug 114), shipping in 3.11.2 (#243). The creation waits 30 s, and a timed-out one is looked up by its exact name and the run's credential ids; harness rows for AC1–AC3. The sprint walk on a disposable n8n 2.46.6 (2026-11-04) met no slow first creation, so the timeout path has no live run.
