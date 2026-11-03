---
id: 105
type: story
title: "The workflow template is checked against the current n8n release"
parent: none
covers: [UV5]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The workflow template is checked against the current n8n release

## Description

The workflow template was last proven on the owner's n8n 2.42.x, and n8n is at 2.46.4. Release 2.43.0 blocks publishing a workflow whose required node inputs are unconnected, and later releases changed public API behaviour around credentials and errors. This story checks that the plugin's provisioning, update and commands still work on the current n8n release, and fixes the template if not.

## Acceptance Criteria

1. **Provisioning still publishes the template**
   **Given** a current n8n release
   **When** the plugin provisions the Pro and the Free templates
   **Then** each workflow is created, published and pinged, with no publish refusal
2. **Update and commands still work**
   **Given** a provisioned workflow on that release
   **When** **Update workflow** runs and a command is triggered
   **Then** the update and the command's callback succeed as on 2.42.x
3. **The result is recorded**
   **Given** the check
   **When** it ends
   **Then** `memory/lifecycle.md` and `memory/n8n-provisioning.md` name the n8n version proven, and any template change carries a template version bump and a release-notes line

## References

- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the n8n rule)
- decision — docs/adr/0015-plugin-provisions-n8n-via-public-api.md
- source — memory/n8n-provisioning.md
- record — memory/lifecycle.md

## Notes

- Fact (`/lcm`, 2026-11-03): n8n@2.46.4 is the latest. Release-note lines relevant to the plugin:
  - 2.43.0 "Block publishing workflows with unconnected required node inputs";
  - 2.45.0 "Log errors before returning generic 500 in Public API";
  - 2.46.0 "Accept expressions in number, boolean, and options credential fields" and "Advertise the OAuth resource challenge on every public API 401".
- hitl: the check runs on the owner's n8n instance or a test instance. The owner gives a temporary API key and revokes it after (chore 81).
- Open question: which n8n the owner's instance runs today, and whether to update it first.
