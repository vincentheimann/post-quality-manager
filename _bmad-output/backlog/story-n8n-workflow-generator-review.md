---
id: 22
type: story
title: "The n8n workflow generator is reviewed end to end with the plugin"
parent: none
covers: [UV5, O4, O5]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The n8n workflow generator is reviewed end to end with the plugin

## Description

A review of the n8n workflow generator — the bundled template, the site-value injection, and both delivery paths (settings-page download and REST import-from-URL) — against the plugin it talks to. It checks three things: the plugin and the generated workflow agree on every contract between them (trigger payload from `pqm_n8n_payload()`, the commands, the `save-review` callback, `post-score`, token headers, `llm` fragments, error and non-2xx handling); n8n and WordPress best practices are applied (credentials, secrets, error workflow, retries and timeouts, node versions, idempotency); and no bugs or bottlenecks exist (per-post fan-out, payload size, cron batch × agent calls, rate limits, long-running Claude calls vs webhook timeouts). The output is a findings document; fixes are sliced into tickets, not built in this story.

## Acceptance Criteria

Verify: a findings document lists every plugin↔workflow contract point with its status (consistent / drift / bug), each best-practice and bottleneck finding with severity and evidence (file + line or node name). The owner has reviewed it, and every accepted finding is either a new backlog ticket or folded into an existing one (stories 2, 4, 14).

## References

- generator — post-quality-manager/post-quality-manager.php (`pqm_workflow_json()`, `pqm_n8n_payload()`)
- delivery — post-quality-manager/admin/class-pqm-admin.php (`download_workflow()`, `render_import_url()`), post-quality-manager/includes/class-pqm-rest-api.php (`/workflow` route, `check_workflow_token()`)
- template — post-quality-manager/templates/n8n-workflow.json
- diagram — n8n/workflow-diagram.md
- adjacent backlog — story 2 (full round-trip connection test), story 4 (chat-provider webhook contract doc), story 14 (command whitelist and tiers)

## Notes

- Owner request (2026-10-05): "ensure it is in a well ecosystem with the WordPress plugin, that best practices are applied and that no bug or bottleneck are identified."
- Starting leads, not yet verified: injection replaces only `__PQM_SITE_HOST__`, so check what else the user must wire up by hand after import (credentials, callback URL, token) and whether that matches the wizard copy. The import URL puts the webhook token in a query string, so check whether it ends up in server and proxy logs.
- Story 4's contract doc and this review read the same Code nodes; doing this first gives story 4 a verified contract to document.
- Decision (`/prioritize` 2026-10-08): covers UV5/O4/O5 — it unblocks stories 2, 4 and 25; ranked first after `epic-delivery-pipeline`.
