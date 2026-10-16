---
id: 72
type: bug
title: "Two provisioning runs at once both succeed and the first run's workflow and credentials are orphaned in n8n"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P2
---

# Two provisioning runs at once both succeed and the first run's workflow and credentials are orphaned in n8n

## Description

Provisioning refuses to run when a setup map exists, but it checks the map before its first n8n call and stores the map after the last one. Two runs started before either finishes (the wizard and a second n8n Workflow page tab, or a double click) both pass the check, both create three credentials and a workflow, and the second map overwrites the first. The first run's objects, with the Anthropic key inside its credential, stay in n8n with no map and no leftovers record. Adoption already guards the same race by claiming the map atomically; provisioning does not.

**Reproduction:** on a connected site without a map, send two provisioning requests within the same few seconds (two tabs, or the REST route twice). Both answer provisioned; n8n lists two workflows named for the host and six credentials; the stored map holds the second run's ids.

**Cause hypothesis:** the map is written at the end of the run with an overwrite, where an atomic claim before the first n8n call would make the second run refuse at once.

## Acceptance Criteria

1. **The map is claimed before anything is created**
   **Given** a connected site without a map
   **When** two provisioning runs start within the same window
   **Then** one run proceeds and the other is refused as already provisioned before any n8n call, and n8n ends with one workflow and three credentials for the host
2. **A failed run releases the claim**
   **Given** a run that stopped at any step and rolled back
   **When** the next run starts
   **Then** it is not refused by the failed run's claim
3. **Harness**
   **Given** the n8n API harness
   **When** it runs
   **Then** a row drives two runs over the transport stub and asserts one refusal with zero n8n calls, and a row asserts the claim is gone after a rolled-back run
4. **The other valid outcome**
   **Given** the investigation shows the race cannot happen
   **When** the ticket is worked
   **Then** the evidence is recorded in the plan and no change is made

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A2 (adversarial 2; edge-case E1)
- adoption's claim — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-an-administrator-adopts-an-existing-manual-setup-plan.md (the atomic write provisioning should mirror)

## Notes

- Filed 2026-10-16 from the epic's retrospective; not reproduced on a live instance (the harness drives the transport stub). Severity P2: no user has met it, but the orphan holds a provider key.
