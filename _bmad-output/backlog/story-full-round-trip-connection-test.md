---
id: 2
type: story
title: "The connection test proves the full loop, callback and token included"
parent: none
covers: [UV5]
after: [1, 6, 22, 31]
assignee: ""
refined: false
hitl: false
risk: medium
---

# The connection test proves the full loop, callback and token included

## Description

"Send test ping" verifies WP → n8n (reachability, workflow active) but not n8n → WP: the `save-review` callback with the `X-PQM-Token` stays unproven until a real review. A `ping` command that calls back to a dedicated endpoint validates the whole loop, and the UI copy drops its current one-way caveat.

## Acceptance Criteria

Verify: on a site with a correct token the test reports the round trip as proven; with a wrong token it reports that the callback was refused; the workflow template gains the callback path with `__PQM_*__` placeholders only.

## References

- requirements — docs/user-paths.md, A4 and UV5
- template — post-quality-manager/templates/n8n-workflow.json, the `ping` route
- copy — docs/brand.md (the result copy must say exactly what was tested)

## Notes

- A template change bumps the version handshake; older imports keep the one-way ping (known limitation in the initiative Notes).
- `after: [1, 6, 22, 31]` (validation, 2026-10-06): stories 1 and 6 check the current one-way ping copy this story rewrites; story 22's findings fold into this story; bug 31 also changes the template and bumps `PQM_WORKFLOW_VERSION`.
- Refinement input (epic retrospective, 2026-10-16, item A8): the n8n-provisioning epic's success-signal walk showed the failure this story exists for. On a site n8n cannot call back, n8n ran the review, the callback node failed and continued (the template's callback node continues on error), n8n reported the execution as success, no review was stored, and the setup wizard's first-review step waited without end. Two parts: (a) this story's round-trip ping as the real proof, which the wizard's connect step should run; (b) a small fix in the wizard, independent of the template bump: a bounded wait on the first review with a sentence naming the webhook URL and the site's reachability from n8n when nothing lands. Since the epic, the saved webhook URL is per site and the token is the one the plugin provisions, so the callback the ping proves is the one the workflow uses.
