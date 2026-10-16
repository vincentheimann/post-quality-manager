---
id: 74
type: story
title: "The n8n connection state tells the truth after the fact: a revoked key, a scoped key and a mis-wired trigger stop the managed actions"
parent: none
covers: [UV4]
after: [71]
assignee: ""
refined: false
hitl: false
risk: medium
---

# The n8n connection state tells the truth after the fact: a revoked key, a scoped key and a mis-wired trigger stop the managed actions

## Description

The connection section reports what the last passing probe said and never records a later refusal: a key revoked in n8n leaves the page claiming connected, and Update workflow, Rotate and the adoption offer answer "n8n answered HTTP 401, try again in a moment", the wrong fix. A key whose scopes exclude what the setup needs answers 403 on the route probes and is reported as a proxy or firewall. And the token rotation rewrites the webhook credential without reading the workflow, so when the Claude nodes share that credential (the mis-wiring the diagnosis reports), rotating it breaks every Claude call. This story makes the probe's causes and the connected state follow what n8n last answered, and makes the managed actions refuse with zero calls while a mis-wiring sentence is stored.

## Acceptance Criteria

1. **A failed re-check replaces the passing probe**
   **Given** a stored key whose last probe passed
   **When** Check again, a managed action or the diagnosis gets a 401 from n8n
   **Then** the stored probe records the refusal, the section shows the refused-key cause with its fix, and the managed actions and the adoption offer are hidden until a probe passes again
2. **A route-probe 401 is a refused key**
   **Given** a key that lists workflows but is refused on a route probe
   **When** Connect runs
   **Then** the cause is the refused key, not an unexpected answer
3. **A scoped key is named**
   **Given** a key that answers 403 on the route probes
   **When** Connect runs
   **Then** a new cause says this key cannot create credentials or publish workflows and asks for an unscoped key, and the manual path is offered
4. **Rotation and update wait for a clean workflow**
   **Given** a stored mis-wiring sentence
   **When** Rotate the webhook token or Update workflow is pressed
   **Then** the action is refused with zero n8n calls and a sentence pointing at the fix the diagnosis gave (Update workflow stays available when the sentence is the one it repairs; settled at the plan)
5. **One connected predicate**
   **Given** the setup dialog, the n8n Instance section and the server
   **When** they decide whether the key is connected
   **Then** all read one server-side answer that requires a passing probe, so a stored failed probe never counts as connected anywhere
6. **Harness**
   **Given** the n8n API and setup harnesses
   **When** they run
   **Then** rows cover each of the above, including a stored probe that failed

## Boundaries

- Must not change: the probe's existing causes and their copy for the API-disabled, proxy, unreachable and missing-endpoint cases; the manual path.
- Does not add a background re-check; the state changes only when the plugin talks to n8n.

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A4 (adversarial 4; edge-case E4, E5, V1, V3; duplication 4)
- failure modes — _bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md (the causes table this story extends)
- copy — docs/brand.md

## Notes

- Filed 2026-10-16 from the epic's retrospective. After story 71 because the gates it changes are the ones 71 makes instance-aware.
- Open question: whether Update workflow should be allowed on a mis-wired workflow as the repair (the 6.12 walk used it that way and it worked) or refused like rotation; decided at the plan checkpoint.
