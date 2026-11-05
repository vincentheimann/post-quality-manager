---
id: 113
type: bug
title: "Update workflow fails on a Pro site whose workflow was set up on the Free build"
parent: none
covers: [UV4, O2]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
severity: P1
estimate: ""
---

# Update workflow fails on a Pro site whose workflow was set up on the Free build

## Description

A blogger sets the workflow up on the Free build, buys Pro, installs the Pro build and clicks **Update workflow** to get the Pro commands. The update fails: "The plugin could not finish updating the workflow (n8n answered HTTP 400). Try again in a moment." Trying again repeats it. n8n's answer: `Node "Update WP Post (Rewrite)" uses invalid credential`.

The Free setup creates two credentials (webhook, Anthropic). The Pro template adds the WordPress credential for Rewrite. `update_workflow()` sends a template role the setup map does not have as a name-only credential (entry 6.9's design, "Rewrite unwired": the node keeps a name-only credential and Rewrite stays off). n8n 2.46.4 refuses that PUT on a published workflow. Entry 6.9 was harness-only on this path; no real n8n had seen it until story 105's walk.

## Acceptance Criteria

1. **Free to Pro updates**
   **Given** a workflow set up on the Free build, and the Pro build now installed
   **When** the administrator clicks **Update workflow**
   **Then** the workflow becomes the Pro template on n8n, with every Pro command answering, or the page says exactly what the blogger must do (one action), never "Try again in a moment" for a refusal that repeats
2. **The other roles a map can lack are handled the same way**
   **Given** an adopted or older setup whose map lacks a role the template needs
   **When** Update workflow runs
   **Then** it behaves as criterion 1 decides, proven on a real n8n
3. **Tests and a walk cover it**
   **Given** the harness sweep and a disposable n8n (`docs/local-verification.md`)
   **When** they run
   **Then** a harness row reproduces the name-only credential refused with n8n's 400, and the walk passes Free → Pro → Update workflow → Review on the current n8n

## References

- found by — _bmad-output/backlog/chore-template-checked-against-current-n8n-plan.md (story 105's walk, 2026-11-04)
- design — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-one-click-updates-the-workflow-to-the-bundled-template-plan.md (entry 6.9, row "Rewrite unwired (Pro)")
- decision — docs/adr/0014-two-builds-from-one-source.md; docs/adr/0015-plugin-provisions-n8n-via-public-api.md
- code — post-quality-manager/includes/class-pqm-n8n.php (`update_workflow()` L1386–1432, `credential_names()`, `linked_template()`, `provision()`'s WordPress credential and Application Password)

## Notes

- Fact (story 105's walk, 2026-11-04, n8n 2.46.4): Free build provisioned (webhook + Anthropic), Pro build installed over it, `POST /n8n/workflow` → 502 `pqm_update_failed`; the same PUT repeated through the plugin's own methods → n8n 400 `{"message":"Node \"Update WP Post (Rewrite)\" uses invalid credential"}`. A fresh Pro setup (three credentials) then updated without error.
- Not known: whether n8n 2.42.x accepted the name-only credential (entry 6.9 never ran on a real instance), so whether this is a regression of n8n or of the plugin's assumption.
- Open question: create the missing WordPress credential (and its Application Password) during the update, which needs the Pro gate and the site's consent as in provisioning; or send the template without that node's credential and keep Rewrite off; or ask for Start over.
- Fact (the same walk): the failed update wrote two PHP 8.5 deprecations, "Using null as an array offset is deprecated", at `class-pqm-n8n.php` L1419 (`$carried[ $ids[ $name ] ]` when a role has no id); the fix removes that path.
- Severity P1: it blocks the first thing a new Pro customer does after buying. Filed 2026-11-04 from story 105's walk, unrefined.
- Done 2026-11-05: fixed by #240 (main 0c00cca), released in 3.11.1. The sprint walk on a disposable n8n 2.46.6 (2026-11-04) passed Free → Pro build → Update workflow (Rewrite linked by id, `rewrite_ready: true`) → Review; the plan's Implementation Notes hold the run. Rewrite at run time waits for the licensed Pro-command walk (no license was active on the showcase).
