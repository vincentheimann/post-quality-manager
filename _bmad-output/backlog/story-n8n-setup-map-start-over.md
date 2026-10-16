---
id: 71
type: story
title: "An Administrator can start the n8n setup over, and the plugin knows which instance its setup belongs to"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
---

# An Administrator can start the n8n setup over, and the plugin knows which instance its setup belongs to

## Description

Once the plugin has provisioned or adopted a workflow, its setup map can be removed only by uninstalling the plugin: a site that moves to another n8n instance, or whose workflow was deleted in n8n, is refused provisioning and adoption and keeps offering update and rotation against ids that no longer exist. This story adds a Start over action on the n8n Workflow page that clears the map, offers to remove the mapped objects on n8n, and makes the map remember the instance it was made on, so a connection to another instance stops the managed actions and says why.

## Acceptance Criteria

1. **Start over clears the map and offers the cleanup**
   **Given** a site with a setup map (provisioned or adopted) and a stored n8n key
   **When** the Administrator chooses Start over and confirms
   **Then** the map is gone, the page returns to the connected-but-not-set-up state, and before clearing it the plugin offered to delete the mapped workflow and credentials on n8n by their ids (never by name), in the rollback order, with what n8n refuses kept in the leftovers record
   **And** an adopted setup is offered the cleanup with a warning that the objects were made by hand, and declining it leaves n8n untouched
2. **The map remembers its instance**
   **Given** a map stored by provisioning or adoption
   **When** the Administrator connects a key for another n8n origin
   **Then** the managed actions (update, rotation) and the diagnosis by id stop with a sentence naming the instance the setup belongs to and pointing at Start over, and nothing is sent to the new instance with the old ids
3. **A gone workflow points at the way out**
   **Given** a map whose workflow n8n no longer has
   **When** a test ping or a managed action runs
   **Then** the sentence says the workflow is gone and offers Start over, and the stored mis-wiring sentence, if any, is cleared with the map
4. **Dropped leftovers are named**
   **Given** a leftovers record for a previous instance
   **When** provisioning runs on another instance
   **Then** the reply says the previous instance still holds the named objects and that the plugin will not touch them, instead of dropping the record silently
5. **Harness**
   **Given** the n8n API harness
   **When** it runs
   **Then** rows cover Start over with and without the cleanup, the cleanup's partial refusal, the other-origin gate, the gone workflow and the dropped leftovers

## Boundaries

- Must not change: what provisioning, adoption, update and rotation do on a map that matches the connected instance; the webhook URL and token; the manual path.
- Start over never deletes anything on n8n without the Administrator's confirmation, and never objects outside the map's ids.

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A1 (adversarial 1, 3, 12; edge-case E2, E3)
- decision — docs/adr/0015-plugin-provisions-n8n-via-public-api.md (the map, the rollback order, the leftovers record)
- page — _bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md (where a Start over sits: story 67's layout)
- copy — docs/brand.md

## Notes

- Filed 2026-10-16 from the epic's retrospective; both code lenses found the map's lifetime independently. Not ranked by `/prioritize` yet.
- Assumption: Start over lives in the n8n connection disclosure next to Forget key (story 67's layout); until story 67 builds, it sits in the n8n Instance section's managed block.
- Open question: whether Start over also forgets the n8n key (today Forget key keeps the map; the two actions stay separate unless the owner wants one).
