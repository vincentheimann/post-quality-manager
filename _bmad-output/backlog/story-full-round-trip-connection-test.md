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
