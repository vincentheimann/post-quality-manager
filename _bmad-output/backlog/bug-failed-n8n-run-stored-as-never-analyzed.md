---
id: 31
type: bug
title: "A failed n8n run is stored as 'never analyzed' instead of a failed review"
parent: none
covers: []
after: []
assignee: ""
refined: true
hitl: false
risk: medium
severity: P2
---

# A failed n8n run is stored as 'never analyzed' instead of a failed review

## Description

When a command fails inside the n8n workflow — the model API answers an error, the agent's JSON cannot be parsed, the rewrite save fails, or the command is unknown — the post goes back to looking as if it had never been analyzed. The user sees no failure, no message, and no reason; the Post Reviews list has a "Could not complete" state for exactly this, but it never lights up. A failed run should be stored as a failed review with a message the user can read.

## Reproduction

1. Import the bundled template and trigger a review on a post with the Anthropic credential pointing at an invalid key (or any model error).
2. Watch n8n: the HTTP Request node continues on error, the parse node produces no result, and `POST Result to WordPress` sends `status: ''` with `result: {}` and no `error_message`.
3. Observe in WP Admin: the post shows "Not analyzed" and no error anywhere. Expected: a failed-review row with the reason, which the kit already renders as "Could not complete".

Same outcome for an unknown command (Error Fallback node) and for a failed rewrite save.

## Cause Hypothesis

The template's failure paths set `error: true` on the item but the `POST Result to WordPress` body only forwards `post_id`, `command`, `overall_score`, `status`, `result` and `workflow_version`. The plugin's `save_review_callback` stores a failure only when `status` is `error` or `error_message` is present, so the empty status is saved as a normal review with no score. The contract is right on the plugin side; the template never speaks it.

## Acceptance Criteria

1. **A failed run is stored as a failed review**
   **Given** an imported template and a command whose model call, JSON parse, rewrite save or command lookup fails
   **When** the workflow posts its result back
   **Then** the review is stored with status `error` and a message naming what failed, and Post Reviews shows the post as "Could not complete" with that message in the detail panel
2. **A successful run is unchanged**
   **Given** a command that completes
   **When** the workflow posts its result back
   **Then** the stored review is byte-identical to today's
3. **The template version handshake flags the fix**
   **Given** a site with the previous template still imported
   **When** the plugin is updated
   **Then** the n8n Workflow page flags the import as outdated, as it does for every template-version bump
4. **Tests cover the condition found and fixed**
   **Given** the REST harness
   **When** it runs
   **Then** a case posts the failure-path body and asserts the stored `error` status and message
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current template
   **Then** the failed review is already stored, with the evidence recorded in Notes — this supersedes 1–4

## References

- deferred — _bmad-output/initiative-post-quality-manager/deferred-work.md, the story 5.4 entry (owner decision 2026-10-05: fix the template in its own ticket)
- template — post-quality-manager/templates/n8n-workflow.json, nodes `Error Fallback` and `POST Result to WordPress`; the six model HTTP Request nodes continue on error
- plugin — post-quality-manager/includes/class-pqm-rest-api.php, `save_review_callback` (`error_message` handling)
- version — post-quality-manager/post-quality-manager.php, `PQM_WORKFLOW_VERSION`

## Notes

- Filed 2026-10-06 from the deferred-work entry; the plugin side stays as it is (decision 2026-10-05).
- Stories 25 and 30 change the same nodes (second provider path, free-model rate limits) and need this failure path to exist first.
- Open question: whether the HTTP Request nodes keep "continue on error" with the error read from the item, or switch to n8n's error output; settle at the plan checkpoint, the template diagram follows.
