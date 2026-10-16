---
id: 75
type: bug
title: "A provisioning refused at activation because another workflow holds the webhook path says \"try again in a moment\""
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
---

# A provisioning refused at activation because another workflow holds the webhook path says "try again in a moment"

## Description

When n8n refuses to activate the new workflow because another active workflow on the instance already listens on this site's webhook path, the plugin rolls back correctly and says "The n8n setup stopped while activating the workflow (n8n answered HTTP 409). Nothing was left in n8n. Try again in a moment." A retry repeats the refusal. The cause is a second workflow for the same host, which happens when the template was imported by hand without pasting its URL, when the site was provisioned before and its map was lost, or when two installs share a host.

**Reproduction (observed 2026-10-16 on the owner's Local sandbox):** provision once, delete the setup map and the saved webhook URL on the site, provision again. n8n answers 409 on activation; the dialog shows the sentence above.

**Cause hypothesis:** the activation step's failure sentence is the generic one every step gets; the 409 on activation has one known meaning the message could name.

## Acceptance Criteria

1. **The cause is named**
   **Given** an activation refused with 409
   **When** the error is shown
   **Then** it says another active workflow on this n8n instance already listens on this site's webhook path and offers the two ways out: let the plugin manage that workflow (adoption, when it is this site's) or remove it in n8n, instead of "try again in a moment"
2. **Other refusals unchanged**
   **Given** an activation refused with any other status
   **When** the error is shown
   **Then** the sentence is the one it is today
3. **Harness**
   **Given** the n8n API harness
   **When** it runs
   **Then** a row drives a 409 on activation and asserts the new sentence, and the existing activation rows still pass

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A5 (edge-case E8)
- walk — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md, Verification (the forced failure's exact sentence)
- copy — docs/brand.md

## Notes

- Filed 2026-10-16 from the epic's retrospective; severity P3, copy only, the rollback itself is right.
