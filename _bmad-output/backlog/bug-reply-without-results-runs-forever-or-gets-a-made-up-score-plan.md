---
title: 'Bug 121 — a reply without results is stored as an error and always reports back'
type: 'bugfix'
ticket: '121'
created: '2026-11-10'
status: 'draft'
route: 'full'
route_source: 'pinned'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/backlog/bug-reply-without-results-runs-forever-or-gets-a-made-up-score.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On template v10, a Claude reply that parses but holds no results makes Takeaways and Audit throw after their `try` (the run ends, no callback, the post stays `processing`), and makes Review, Sources and SEO store a success with a made-up score (0, 50, 55).

**Approach:** Each of the five Parse … Result nodes checks, right after its parse, the fields its score and status come from; a reply lacking one is stored as an error naming the agent. Every read after that check runs guarded, so no reply shape can end the run before the callback. Template version 11; the Free template is re-derived.

## Boundaries & Constraints

**Always:** the same fix shape in all five nodes; the Free template comes from `php bin/derive-free-template.php`, never a hand edit; replies with results store exactly what v10 stores (the bug 117 reply-shape rows stay green, only version pins move); the new message follows `docs/brand.md`; the ticket's criteria 1–5 are the acceptance.

**Never:** change the prompts, the two extraction lines (bug 117), the Rewrite branch, the callback or notification nodes, connections or `onError`; add a node; check values outside a prompt's set (a Sources verdict outside its list keeps 50, an SEO assessment of "good" keeps 55: ticket Assumption).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| No results | `{}`, `I cannot review an empty post, so I return {}.`, `{"error": "no content"}`; five nodes (Pro), Review and Takeaways (Free) | error item, message "<Agent> Agent returned no usable result. Run the command again."; callback `error_message` = message; notification "❌ " + message | never a throw |
| A required field missing | Review without `status`; Audit's `review` without `overall_score`; Takeaways without `count_ok`; Sources without `overall_verdict`; SEO without `metadesc_assessment` | the same error item | — |
| A display list missing | Takeaways with its four score fields, no `rewritten_items` | stored as a result (score from the checks), no rewrite listed | no throw |
| A display field of the wrong type | e.g. Review `top_edits: "x"`, Sources `claims: [null]` | the same error item | the guarded reads catch it |
| Replies with results | every shape v10 accepts | unchanged: score, status, result, body | — |
| Not JSON | prose without braces | "<Agent> Agent returned invalid JSON." as today | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/templates/n8n-workflow.json` -- Parse Review/Takeaways/Sources/SEO/Audit Result: after the parse `try/catch` (keep it), the report-building code reads `r`. Score/status fields: Review `overall_score`, `status`; Takeaways `block_found`, `position_correct`, `count_ok`, `items_are_specific`; Sources `overall_verdict`; SEO `keyphrase_assessment`, `metadesc_assessment`; Audit `review.overall_score`, `review.status` (each named in its Build node's prompt, checked 2026-11-10). Unguarded today: Takeaways `r.rewritten_items.forEach`, Audit `rv.status`. Version stamps: Webhook Trigger `responseData` `{"ok":true,"workflow_version":10}` (L10), Parse Input `workflow_version: 10` (L29). Error items already reach the callback (`error_message`) and the notification through Result Ready.
- `post-quality-manager/templates/n8n-workflow-free.json` -- derived.
- `post-quality-manager/post-quality-manager.php` L95 -- `POSTQUALITY_WORKFLOW_VERSION` 10 → 11.
- `tests/harness/template-failure-harness.php` -- `run_code()` runs a node's real `jsCode` from the Pro template (`$NODES`); `parse_refs()`, `post_body()`, `notification()`, `text_reply()`; `$review`, `$takeaways`, `$sources`, `$seo` (L284–300) are valid objects to start from; `$variants` (L364) loads the Free template. Version pins L104, L113, L116, L119–120, L163.
- `tests/harness/rest-reviews-harness.php` L312 -- the `is 10` pin.
- `tests/harness/free-build-harness.php` L337 -- matches `workflow_version: \d+`; no change expected.

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/templates/n8n-workflow.json` -- in the five Parse nodes: the required-field check after the parse; the report-building reads guarded (missing display lists as empty, any other throw caught into the same error item); stamps 10 → 11 -- criteria 1–2.
- [ ] `post-quality-manager/templates/n8n-workflow-free.json` -- run `php bin/derive-free-template.php` -- never hand-edited.
- [ ] `post-quality-manager/post-quality-manager.php` -- version 11 -- criterion 3 through the existing handshake.
- [ ] `tests/harness/template-failure-harness.php` -- pins → 11; a section "Replies without results (bug 121)" covering the matrix rows on the Pro template's five nodes and the Free template's two (a way to run a node from a given template), checking the item, `post_body()` and the notification text; run the new rows once against the baseline: they must fail there -- criterion 4.
- [ ] `tests/harness/rest-reviews-harness.php` -- pin → 11.
- [ ] `docs/release-notes/unreleased.md` -- a paragraph: workflow version 11 stores a reply without results as an error; Update workflow.
- [ ] `n8n/workflow-diagram.md` -- version 11 (L50, L89) and one bullet beside bug 117's for the check.
- [ ] `memory/llm-options-architecture.md` -- the handshake list gains v11; the reply-shapes paragraph says bug 121 checks the result fields in v11.
- [ ] `memory/lifecycle.md` -- the n8n row: template version 11 in use (harness-run; the live run at the release).
- [ ] `docs/local-verification.md` -- "A disposable n8n": the provider-key bullet and step n8n 6 say the agent provisions with the owner's key read from its file in a `wp eval-file` script, never printed, `status_html` and `webhook_url` dropped from any output; typing it in the dialog stays the owner's alternative (owner, 2026-11-10).

**Acceptance Criteria:**
- Given a site whose workflow reports 10, when the plugin with version 11 loads, then the n8n Workflow page says the workflow needs updating (the existing handshake rows, pins at 11).

## Implementation Notes

## Plan Change Log

## Review Triage Log

## Design Notes

The check, not a new node: an error item already travels Result Ready → callback → notification, so returning one is the whole fix. "Lacks" means `undefined` or `null` (a number sent as a string still passes, as today). Message: "<Agent> Agent returned no usable result. Run the command again." (`docs/brand.md`: errors say what to do). Skipped: checking values against the prompt's set — add when a site meets one.

## Verification

**Commands:**
- `php bin/derive-free-template.php && git diff --stat` -- expected: the two templates, the plugin file, the harnesses and the records only.
- `for f in tests/harness/*.php; do php -d sys_temp_dir="C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/f2f96085-9354-473d-ba80-e2f5849942d5/scratchpad" "$f" || break; done` -- expected: every harness green; temporary files only in that scratchpad.
- `npm run test:unit` -- expected: green; `php -l` on each touched PHP file.

**Manual checks:**
- At the release (hitl): all five commands once each on version 11, on the showcase against a disposable n8n; the owner activates the license before and deactivates it after; the agent provisions with the key from its file. Each stores its result.
