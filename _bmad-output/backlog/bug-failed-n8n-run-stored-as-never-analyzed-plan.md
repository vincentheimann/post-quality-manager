---
title: 'A failed n8n run is stored as a failed review with a readable reason'
type: 'bugfix'
ticket: '31'
created: '2026-10-06'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'fd2d59aa543b0a4da9cb591a99adfd7041ccc431'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** When a command fails inside the n8n template (model API error, unparseable agent output, rewrite save failure, unknown command), `POST Result to WordPress` sends `status: ''`, `result: {}` and no reason. The plugin stores it as a normal, scoreless review, so the post looks never analyzed and "Could not complete" never shows. Two more defects sit on the rewrite failure path. An unparseable rewrite still reaches `Update WP Post (Rewrite)`, which PATCHes the post to `status: pending`, unpublishing it. A failed save is checked through `wp.code`, but "continue on error" puts it in `wp.error`, so it is reported as `rewritten`.

**Approach:** Make the template speak the contract the plugin already has (`error_message` → stored status `error`, decision 2026-10-05: the plugin side stays). Every failure item carries `error: true` plus a plain `message` naming what failed, including the model API's own error text, and the POST body forwards it as `error_message` only when `error` is true. A failed rewrite never PATCHes the post. Bump the template version so outdated imports are flagged.

**Decisions (owner's standing approval of default proposals, 2026-10-06):**
- The HTTP Request nodes keep "continue on error" (`onError: continueRegularOutput`). The error is read from the item (`$json.error`), not via n8n's error output: smallest change, no rewiring of six branches.
- The rewrite guard is in scope. It is the same failure path, it rides the same version bump, and it prevents a failed rewrite from unpublishing a post.

## Boundaries & Constraints

**Always:** successful runs post a body whose stored review is byte-identical to today's (`error_message: ''` is ignored by `save_review_callback`). Messages stay niche-agnostic and follow `docs/brand.md` (gentle, says what failed). Placeholders only, no site values. `PQM_WORKFLOW_VERSION` and the Parse Input stamp move together.

**Never:** change `save_review_callback` or the REST args. Switch nodes to n8n's error output. Touch the success-path formatting or the Has Notification / Send Notification nodes.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Model API error | Claude node returns an error item (`json.error`) | Parse node: `error: true`, `message` = "<Agent> Agent could not get an answer from the model: <API text>" | Stored `status: error`, `result.error` = message |
| Unparseable output | 2xx but not JSON / no delimiter | `message` = today's "returned invalid JSON" / delimiter text | Stored as error |
| Rewrite parse failure | Parse Rewrite Response `error: true` | New IF `Rewrite Parsed?` skips Update WP Post, goes to Format Rewrite Confirmation | Post untouched; stored as error |
| Rewrite save failure | Update WP Post returns `json.error` (or `code` / `data.status >= 400`) | `message` = "Could not save the rewrite to WordPress: <text>" | Stored as error |
| Unhandled command | Error Fallback (e.g. `apply`) | `message` names the command | Stored as error |
| Success | Any completing command | Body adds `error_message: ''` only | Stored review unchanged |

</frozen-after-approval>

## Code Map

- `post-quality-manager/templates/n8n-workflow.json` -- Parse Input (`workflow_version: 2`, L21), the five `Parse <Agent> Result` catch returns (L145/213/281/349/417), `Parse Rewrite Response` (L485), `Update WP Post (Rewrite)` (PATCH, `onError` L537), `Format Rewrite Confirmation` (L541: `d.error` first, then `wp.code` check), `Error Fallback` (L567), `POST Result to WordPress` body (L598). Connection `Parse Rewrite Response → Update WP Post (Rewrite)`. Copy the IF node shape from `Has Notification URL?` (if v2, `conditions.conditions[]`, boolean operator). Edit through a script that loads and re-dumps the JSON with the file's existing formatting, never by hand-escaping.
- `post-quality-manager/post-quality-manager.php:28` -- `PQM_WORKFLOW_VERSION` 2 → 3.
- `post-quality-manager/includes/class-pqm-rest-api.php:349-388` -- `save_review_callback`, read-only reference: `error_message` non-empty → `status ?: 'error'`, `result = ['error' => msg]`.
- `tests/harness/rest-reviews-harness.php` -- add stubs (`get_post`, `update_option`, `current_time`, `wp_json_encode`, `$wpdb->insert`/`insert_id`) and cases. Existing "Failed run rows" (L193) covers the read side only.
- `n8n/workflow-diagram.md` -- stale (Get Post Data, apply, help nodes that do not exist). Redraw it to the real nodes, with the failure edges into `/save-review`.
- `memory/llm-options-architecture.md` -- version handshake fact. Add the failure contract and v3.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/templates/n8n-workflow.json` -- the six Parse nodes read `resp.error` (description, else message) into `message`. `Format Rewrite Confirmation` treats `wp.error` as a failure and sets `message`. `Error Fallback` sets `message`. New IF `Rewrite Parsed?` between Parse Rewrite Response and Update WP Post: false-error → Update, true → Format Rewrite Confirmation. POST body adds `error_message: $json.error ? ($json.message || generic) : ''`. Parse Input stamps 3 -- the fix
- [x] `post-quality-manager/post-quality-manager.php` -- `PQM_WORKFLOW_VERSION` 3 -- AC3 handshake
- [x] `tests/harness/rest-reviews-harness.php` -- save_review_callback: failure body → stored `error` + message. Success body with `error_message: ''` → stored status/result as posted. Template contract checks: POST body forwards `error_message`, every failure Code node sets `message`, Update WP Post is reached only through `Rewrite Parsed?`, Parse Input stamp === `PQM_WORKFLOW_VERSION` -- AC4
- [x] `tests/harness/template-failure-harness.php` (new) -- proves each I/O matrix row by running the real node code: read the template, and for each relevant Code node run its `jsCode` through `node` (shell out; fail loudly if `node` is missing) with stubbed `$input.first().json` and `$('<Node>').first().json`. Then evaluate the POST body expression on the resulting item and assert `error_message` (failure rows: non-empty, names the agent or command; success row: `''`). Also assert the `Rewrite Parsed?` IF routes `error: true` away from Update WP Post -- matrix audit
- [x] `n8n/workflow-diagram.md` -- redraw to the real flow with failure paths -- the ticket's "diagram follows"
- [x] `memory/llm-options-architecture.md` -- failure contract line, version 3 -- durable fact

**Acceptance Criteria:**
- Given an imported v3 template and a command whose model call, parse, rewrite save or command lookup fails, when the workflow posts back, then the review is stored with status `error` and a message naming what failed (rendered by the kit as "Could not complete").
- Given a completing command, when the workflow posts back, then the stored review is byte-identical to today's.
- Given a site whose seen version is 2, when the plugin updates, then the n8n Workflow page flags the import as outdated.
- Given an unparseable rewrite, when the workflow runs, then the post is not PATCHed.

## Implementation Notes

- Template edited by a load/mutate/re-dump Node script (2-space JSON, file's line endings kept; round-trip verified byte-identical before editing).
- API text read as `resp.error` string, else `.description`, else `.message`, else "no details returned" (n8n's NodeApiError puts the API's own text in `description`). Same reader on `wp.error` in Format Rewrite Confirmation, falling back to `wp.message` for a `code` body.
- `Rewrite Parsed?` tests `={{ !$json.error }}` with the boolean "is true" operator (always a boolean, safe under strict type validation): true → Update WP Post, false → Format Rewrite Confirmation (its existing `d.error` check handles the item). Positions: IF at 372, Update shifted to 596, Format to 820.
- Error Fallback keeps an upstream `message` if one exists, else names the command ("This workflow has no step for the "apply" command. Download the latest workflow from the n8n Workflow page."); its chat_message now repeats that message. Generic POST fallback: "The workflow could not complete this command. Check the execution log in n8n."
- Also updated `memory/MEMORY.md` index line (failure contract, v3).
- Review round 1 fixes: Parse nodes check their Build node first (empty post → "<Agent> Agent: Post not found or empty."); error reader extended to the HTTP v4 StatusCodeError shape (`e.error` string, `e.error.error.message`, `e.error.message`, then `description` / `message`); Format Score failure keeps `...d`; Error Fallback drops the download advice; docs state the Parse Input boundary.

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 1 medium, 4 low, 1 false, 1 maybe-false. All non-false findings patched by the implementer, then verified (17/17 Code nodes parse, all 10 harnesses green).
- medium, patch: an empty post's Build-node error reached the Claude node, and the Parse node stored a model-API message instead of "Post not found or empty.". Parse nodes now surface the Build node's error first (harness case added).
- false (Parse Input part of finding 2): unknown commands and missing `post_id` can never be stored: `save-review`'s `command` enum and required `post_id` reject them by contract, which the intent keeps unchanged. Documented as the boundary in memory and the diagram.
- low, patch (Format Score part of finding 2): its "Post not found." item dropped `...d`, so it had no callback. Now spreads `...d` (harness case added).
- low, patch: the Error Fallback advice to re-download was wrong for `apply` (v3 has no apply branch). The advice was dropped.
- maybe-false → patch: n8n's HTTP Request v4 under continue-on-fail likely yields `{error: response.reason}`, a StatusCodeError with the provider body under `error.error`. The reader now also takes `error.error.error.message` / `error.error.message`. Settled for real only by the plan's manual check with an invalid key.
- low, patch: the memory and diagram overstated the contract ("every failure stored", body quoted without its fallback). Both corrected.
- low, patch: the static check's label overstated it. Relabelled to what it checks.

## Verification

**Commands:**
- `node -e "JSON.parse(require('fs').readFileSync('post-quality-manager/templates/n8n-workflow.json','utf8'))"` -- expected: valid JSON
- `node --check` on every Code node's `jsCode` written to a temp file (wrapped in an async function) -- expected: no syntax errors
- `php -l` on touched PHP; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green

**Manual checks (if no CLI):**
- Import v3 into n8n, trigger a review with an invalid Anthropic key: Post Reviews shows "Could not complete" with the API reason. Trigger rewrite with a broken model: the post stays published. The n8n Workflow page on a v2-seen site shows the update notice.
