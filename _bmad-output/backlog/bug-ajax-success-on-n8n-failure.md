---
id: 18
type: bug
title: "AJAX and GET triggers report success when n8n answers non-2xx"
parent: none
covers: []
after: []
assignee: ""
refined: true
hitl: false
risk: medium
severity: P2
---

# AJAX and GET triggers report success when n8n answers non-2xx

## Description

Verified 2026-10-02 (epic retro, adversarial a12 / edge-case 7; re-checked at source `admin/class-pqm-admin.php:1409-1420`). `trigger_review_callback()` returns a `WP_REST_Response` with `success=false` when n8n answers 4xx/5xx (deliberately without consuming quota), but `ajax_trigger_command()` checks only `is_wp_error()`: it records a `processing` review row and calls `wp_send_json_success`, so the UI shows a success toast and a permanent "processing" entry for a trigger n8n rejected. The GET fallback notice path has the same blind spot.

## Reproduction

1. Point `n8n_webhook_url` at an endpoint answering HTTP 500 (or deactivate the n8n workflow so the webhook answers 404).
2. Trigger any command from Post Reviews (AJAX) or the GET fallback.
3. Observe: success toast/notice, a stuck "Processing…" row, quota unspent.

## Cause hypothesis

The non-2xx branch predates the epic and returns a response object, not a `WP_Error`; both admin wrappers were written against the error/exception shape only and never inspect `get_data()['success']`.

## Acceptance Criteria

1. **Given** n8n answers non-2xx, **when** a command is triggered via AJAX or the GET fallback, **then** the user sees an error naming the n8n status, no `processing` row is saved, and quota is unspent — covered by a harness case.
2. **Given** n8n answers 2xx, **when** a command is triggered, **then** today's behavior is unchanged (harness pins it).
3. Other valid outcome: if investigation shows the row-save is intentional retry bookkeeping, record that decision and fix only the toast — the evidence found says otherwise.

## Notes

- Sequencing: epic-ui-rebuild story 5.4 rebuilds the trigger UX and 5.7 may retire the AJAX wrapper — fix the shared REST/GET side regardless; coordinate the wrapper half with the epic to avoid patching code scheduled for deletion.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (a12/e7, action A11)
