---
id: 76
type: story
title: "Update workflow asks before replacing a workflow the Administrator made by hand"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# Update workflow asks before replacing a workflow the Administrator made by hand

## Description

On a site whose workflow was adopted rather than provisioned, Update workflow writes the bundled template over the workflow on one click: every node, connection and setting the Administrator added by hand is replaced, while the notice promises only that "your credentials and production URL stay". This story adds a confirmation for adopted workflows that says what the update replaces and what it keeps, and makes the notice copy promise no more than it keeps.

## Acceptance Criteria

1. **An adopted workflow asks first**
   **Given** a setup map stored by adoption
   **When** Update workflow is pressed
   **Then** a WordPress components confirmation says the plugin will replace every node and setting of the workflow with the bundled template, keeping its credentials, its webhook path and its name, and the update runs only on confirm
2. **A provisioned workflow does not**
   **Given** a setup map stored by provisioning
   **When** Update workflow is pressed
   **Then** the update runs as today, without a dialog
3. **The notice says what it keeps**
   **Given** the update notice on a managed site
   **When** it is shown
   **Then** its copy names credentials, path and name as what stays and says that the nodes are replaced
4. **Tests**
   **Given** the Jest tests of the workflow status block
   **When** they run
   **Then** they cover the confirm, the cancel and the provisioned path

## Boundaries

- Must not change: what the update writes to n8n, the ping after it, the version record.
- No backup of the previous workflow is taken (n8n keeps its own version history).

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A6 (adversarial 8; edge-case E6; spec reconciliation 3)
- spec — _bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md, Constraints ("never changed without explicit confirmation")
- precedent — memory/lessons.md, 2026-10-05 (dialogs use WordPress components by default)
- copy — docs/brand.md

## Notes

- Filed 2026-10-16 from the epic's retrospective. Story 67 rebuilds the page the button sits on; this story changes behaviour and copy, not placement, and can land before or after it.
