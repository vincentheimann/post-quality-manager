---
title: 'The Notification URL never gets a report when a command finishes'
type: 'bugfix'
ticket: '97'
created: '2026-10-29'
status: 'built'
baseline_revision: '2b2b8b3bd61e58b165a14d729d3dcaa05b702ddd'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** In both workflow templates, "Has Notification URL?" and "Send Notification" read `$json.notification_url` and `$json.chat_message` after "POST Result to WordPress", whose output is WordPress's save-review reply `{success, id, message}`. So the Notification URL never gets the report that the readme and the privacy policy promise. The error returns of the "Parse … Result" nodes also carry no `chat_message`.

**Approach:** One n8n No Operation node, "Result Ready", collects the 9 result nodes and feeds "POST Result to WordPress". The notification branch reads its address and text from `$('Result Ready').first().json`, and the text falls back to `'❌ ' + message` when the item has no `chat_message`. The template goes to version 8, and the Free template is re-derived.

**Decisions (owner, 2026-10-29):**
- Keep the full plan, at about 2,300 tokens: one goal.
- The agent runs the proof in n8n on the owner's instance, with the n8n API key stored on the Local sandbox since 2026-10-20 (the owner revokes it later, chore 81). It uses separately named test objects only, and deletes them by id after the run.

## Boundaries & Constraints

**Always:** The Free template comes from `php bin/derive-free-template.php`, never from a hand edit. The new node's id is `pqm-node-112`. The stamps follow the story-2 checklist: the constant, Parse Input's two `workflow_version` stamps and the trigger's `responseData` change together, and the multi-line stamp stays unique for the derive regex. Both templates keep their placeholders (`__PQM_*__`). The callback node keeps `onError: continueRegularOutput`, so a refused save still reaches the notification branch, as today.

**Never:** No change to the save-review route, the webhook payload, the `{"text": …}` body shape, the readme or the privacy policy (the data that flows is unchanged). No new Settings copy. No edit to the owner's live n8n workflow.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Report sent | `score` or `review` finishes, Notification URL set | IF true; Send Notification posts to that URL with `text` = the node's `chat_message` | No error expected |
| Model error | a Parse node returns `error: true, message` and no `chat_message` | `text` = `'❌ ' + message` | Callback still carries `error_message` |
| Unknown command | Error Fallback item | `text` = its own `chat_message` (`'❌ ' + message`), unchanged | — |
| No address | `notification_url` is `''` | IF false; nothing posted | — |
| Ping | Parse Input sets `notification_url: ''` | IF false; the callback goes to `/ping/{id}` as today | — |
| Save refused | WordPress answers 4xx; the item continues on the regular output | the notification still goes out | unchanged `onError` |

</frozen-after-approval>

## Code Map

- `post-quality-manager/templates/n8n-workflow.json` -- the 9 nodes feeding "POST Result to WordPress" (`pqm-node-100`, `onError: continueRegularOutput`): Format Score, Parse Review/Takeaways/Sources/SEO/Audit Result, Format Rewrite Confirmation, Error Fallback, Ping OK. "Has Notification URL?" (`pqm-node-110`, IF v2, leftValue `={{ $json.notification_url }}`). "Send Notification" (`pqm-node-111`, url `={{ $json.notification_url }}`, body `text` `={{ $json.chat_message }}`). Version 7 sits at L10 (`responseData`) and L29 (two Parse Input stamps).
- `post-quality-manager/templates/n8n-workflow-free.json` -- derived; regenerate only.
- `bin/derive-free-template.php` -- L36–60 keeps nodes reachable from Webhook Trigger, so "Result Ready" stays in Free and the edges from paid nodes drop. L85–90 needs the callback body's `workflow_version: $json.workflow_version || 0, ` exactly once.
- `post-quality-manager/post-quality-manager.php:95` -- `POSTQUALITY_WORKFLOW_VERSION` 7 → 8.
- `tests/harness/template-failure-harness.php` -- `run_expr()` (L64) takes no node refs yet. Literal 7 at L100, 107, 109, 112, 114, 115 (L158's 7 is a post id: keep it). L113 checks `if_target('Ping OK', 0)`, and L119 notes that the item ends with `notification_url` `''`.
- `tests/harness/template-guard-harness.php:173-174` and `tests/harness/rest-reviews-harness.php:328-330` -- "Ping OK feeds POST Result to WordPress"; becomes "Ping OK → Result Ready → POST Result to WordPress". `rest-reviews-harness.php:312` holds the literal 7. `template-guard-harness.php:94` checks the `pqm-node-N` id form.
- `includes/class-pqm-n8n.php:506-570` -- `miswiring()` reads node types and credentials, not names: no change.
- `n8n/workflow-diagram.md` -- L50 and L66 say 7; Mermaid L69–90: every branch ends at `CB`, then `CB → HN → NOTIFY`.
- `docs/release-notes/unreleased.md` -- one bold-headed paragraph per change; the v7 one (L43) is the model for its last sentence.
- `memory/llm-options-architecture.md:13` -- version list ("v7 2026-10-21").

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/templates/n8n-workflow.json` -- add "Result Ready" (`n8n-nodes-base.noOp`, `pqm-node-112`, a free position left of the callback node); route the 9 connections into it and add Result Ready → POST Result to WordPress; set the IF leftValue to `={{ $('Result Ready').first().json.notification_url }}`, Send Notification's url to the same, and its text to `={{ $('Result Ready').first().json.chat_message || '❌ ' + $('Result Ready').first().json.message }}`; stamp version 8 in all three places -- one point that every path passes
- [x] `post-quality-manager/templates/n8n-workflow-free.json` -- `php bin/derive-free-template.php` -- drift check
- [x] `post-quality-manager/post-quality-manager.php` -- constant 8 -- handshake
- [x] `tests/harness/template-failure-harness.php` -- `run_expr()` gains an optional `$refs` argument; 7 → 8; new "Notification" rows for the matrix, each evaluating the IF and Send Notification expressions with `$json` = the save-review reply and `Result Ready` stubbed with the item; structural rows on both templates (every former POST feeder now feeds Result Ready, which feeds POST Result, which feeds the IF) -- AC5: red on version 7, green on the fix
- [x] `tests/harness/template-guard-harness.php`, `tests/harness/rest-reviews-harness.php` -- the Ping OK chain through Result Ready; 7 → 8
- [x] `n8n/workflow-diagram.md` -- version 8, a v8 bullet, the `RR` node in the chart
- [x] `docs/release-notes/unreleased.md` -- one paragraph in brand voice, ending with the v7 pattern for version 8
- [x] `memory/llm-options-architecture.md` -- v8 in the list, plus one sentence on Result Ready

**Acceptance Criteria:**
- Given the fixed Pro template, when the harness sweep runs with version 7's three notification expressions restored, then the new Notification rows fail; with the fix, every harness passes.
- Given the Free template, when `template-guard-harness.php` runs, then it equals the derivation and keeps Result Ready.

## Implementation Notes

- 2026-10-29, implementer subagent (Opus), from the plan alone. Built as planned: "Result Ready" (`n8n-nodes-base.noOp` v1, `pqm-node-112`, position [372, 800]) sits in the nodes list after Error Fallback; the 9 former POST feeders now feed it and it alone feeds POST Result to WordPress. The Free derivation kept it (Pro 31 nodes, Free 16; Free keeps 5 feeders: Format Score, Parse Review/Takeaways Result, Error Fallback, Ping OK). Version 8 in the constant, both Parse Input stamps and the trigger's `responseData`; the derive regex still finds one multi-line stamp.
- `template-failure-harness.php`: `run_expr()` takes `$refs`; `parsed_input()` takes optional extra body fields (to set `notification_url`). New "Notification" block per template (Pro and Free, both read from disk): 5 structural rows (Result Ready is a No Operation `pqm-node-112`; every result node the template has feeds it, 9 Pro / 5 Free; Result Ready alone feeds POST Result, which feeds the IF, whose true output is Send Notification; the callback keeps `continueRegularOutput`; the IF is string `notEquals ''`) and 9 expression rows covering every matrix row (score and review reports; score's missing post, review's model error and invalid JSON → `'❌ ' + message`; Error Fallback's own text; no address and ping → the IF reads `''`; a refused save → the report still goes out), each with `$json` = the `/save-review` reply (which carries its own `message`, so reading `$json.message` by mistake would fail) and Result Ready stubbed with the item. The rows check the evaluated values, not a model of n8n's IF.
- Deviation, Code Map: L158 of the failure harness holds two 7s; the post id stays 7, its `workflow_version === 7` became 8 (the row fails otherwise). `template-guard-harness.php` had no literal 7 to change.
- Deviation, small: in `memory/llm-options-architecture.md` the Template variant paragraph's "30 → 15 nodes" became "31 → 16 nodes since v8" (same file, now stale otherwise). `PORTFOLIO.md` (L24, L225) said "30 nodes": fixed to 31 in the review-fix round below.
- Review-fix round (2026-10-29, the orchestrator's list): the bug 97 release-note paragraph rewritten (the address is lost after the save; the outdated notice quoted as "Your workflow is version 7; this plugin needs 8." with Update workflow / Re-import the workflow); Format Rewrite Confirmation's error return now sets `chat_message: '❌ ' + d.message` (Pro only; the Free derivation is unchanged) with one Pro-only Notification row for a rewrite error; `deferred-work.md`'s Notification URL entry gained `ticket:` and `resolved:` lines; `docs/user-paths.md` A6's UV line gained a dated note; `PORTFOLIO.md` 30 → 31 nodes. template-failure 91 passed, template-guard 63, rest-reviews 79, 0 failed.
- AC5 red proof, run on scratch copies of the failure harness (repo files untouched): (a) the fixed templates with version 7's three notification expressions restored → 72 passed, 18 failed, exactly the 9 Notification expression rows on each template; (b) the baseline 2b2b8b3 templates → 55 passed, 35 failed (the new structural and expression rows, plus the version-8 stamp rows). With the fix: 90 passed, 0 failed.
- Verification: `php -l` clean on the main plugin file and the three harnesses; all 21 `tests/harness/*-harness.php` 0 failed (template-failure 90, template-guard 63, rest-reviews 79); `python -I tests/harness/local-wp-harness.py` 36 passed, 0 failed; `npm run test:unit` 10 suites, 174 tests passed; `git diff --stat` 9 files, all in the Code Map; no CR in any touched file. The run in n8n (Manual checks) is the orchestrator's and was not done here.

- 2026-10-29, orchestrator (Opus 5.5, session rule: every agent on Opus).
  - The session runs from the workspace root, so `bmad-build` was rendered by hand (`render_skill.py`, `--set workflow.route=full`) and followed step by step. The implementer subagent ran on Opus.
  - Bug 97's ticket was filed on this branch, as bug 84's was.
  - The run in n8n was done by the orchestrator after the build (Verification). Only `memory/llm-options-architecture.md` gained a clause from it: the observed n8n behaviour, with version and date (lessons, 2026-10-16).
  - `PORTFOLIO.md`'s "30 nodes" is left to the portfolio lane.

## Plan Change Log

## Review Triage Log

**Pass 1, 2026-10-29: Quick lens (Opus).** 6 rows: high 0, medium 2, low 4, false 0, maybe-false 0. Routes: 5 patch, 1 defer.

| # | Verdict | Route | Finding and evidence |
|---|---|---|---|
| 1 | medium | patch | `docs/release-notes/unreleased.md` L57 sends readers to "Workflow update available", which no code prints since story 67 (grep: 0 hits in `admin/`, `includes/`, `src/`). An outdated workflow now shows "Your workflow is version %1$d; this plugin needs %2$d." (`class-pqm-admin.php` L1586–1591) with **Update workflow** or **Re-import the workflow**. The paragraph also says the workflow reads Settings, but the plugin sends the address with each command, and "once the result is saved … even when your site refused the save" contradicts itself. Origin: the plan's task pointed at the v7 paragraph as the pattern. The fix is one paragraph. |
| 2 | low | patch | Format Rewrite Confirmation's `if (d.error)` branch sets `chat_message: d.message`, so the new fallback never adds `❌` and a rewrite error reaches the chat bare. The line predates this change (baseline has it), but this change exposes it: nothing was sent before. The diagram's v8 bullet states the rule correctly (only an item without `chat_message` gets `'❌ ' + message`). Direct fix: `'❌ ' + d.message`, plus a Notification row for a rewrite error. |
| 3 | low | patch | `deferred-work.md` L131–133 (the entry that became bug 97) has no `ticket:` or `resolved:` line, while the file's convention adds them (L5–7, L106–121). A later session would read the defect as open and unverified. |
| 4 | low | patch | `docs/user-paths.md` L56 (A6 UV) still says "no notification". The next `/prioritize` would rank UV3 on that. Fix: a dated note (v8 makes the Notification URL work; a digest and trend alerts stay open). No conversion to CV: nobody has used it, so it is not owner-verified. |
| 5 | low | patch | `PORTFOLIO.md` L24 and L225 say "30 nodes"; the Pro template has 31 since this change. Two numbers to correct. |
| 6 | medium | defer | `unreleased.md` L11, L13, L27, L41 and L43 quote "Workflow update available" too, gone since story 67 (2026-10-19). This predates the change, and the notes ship with 3.10.0. Written to `deferred-work.md`. |

## Design Notes

A No Operation node is n8n's own "do nothing" join, at rung 4 of the ladder: it gives the 9 paths one name to read from, so the expressions do not depend on which branch ran. `.first()` matches every Code node in these templates (one item per run). Notifying after a refused save is today's `onError` behaviour, kept: the chat then shows the agent's result, and the plugin's page shows the failed save.

Skipped: a richer error report (title and link in the error text) — add when a user asks for it.

## Verification

**Commands:**
- `php -l` on `post-quality-manager/post-quality-manager.php` -- clean
- every `tests/harness/*-harness.php` with `php`, and `python -I tests/harness/local-wp-harness.py` -- 0 failed
- `npm run test:unit` -- passes
- `git diff --stat` -- no file outside the Code Map

**Manual checks (if no CLI):**
- The run in n8n (ticket Reproduction step 3), done by the orchestrator after the build, not by the implementer:
  - Read the key from the sandbox's plugin settings with WP-CLI, and keep it out of every file.
  - Over the API on the owner's instance, create three test objects with a `PQM bug 97 test` prefix: a capture workflow (a webhook answering 201), a Header Auth credential, and the template under test with its placeholders filled. Send a named User-Agent.
  - Trigger `score` with `callback_url` and `notification_url` pointed at the capture.
  - Expected with version 7: 1 callback and 0 notifications, read from the capture's executions. With version 8: 1 and 1, and the notification's `text` is the score report.
  - Delete every object by id, and record the ids and the result here, never the instance's address.

**Verification 2026-10-29 (orchestrator):**
- Commands: re-run by the orchestrator: all 21 harness files 0 failed; `local-wp-harness.py` 36 passed; `npm run test:unit` 174 passed; `php -l` clean; both templates parse as JSON.
- Run in n8n, on the owner's self-hosted n8n 2.42.3. The key came from the Local sandbox (`pqm_n8n_api`) and appears in no file. For each template, a fresh capture workflow and two Header Auth credentials (the webhook token, plus a dummy Anthropic key never called); Pro's WordPress credential was left name-only. The template under test was created at v7 and activated, then PUT to v8 on the same id, as Update workflow does. Each version got one `score` trigger.

  | Template | v7: callbacks / notifications | v8: callbacks / notifications | v8 notification `text` |
  |---|---|---|---|
  | Free | 1 / 0 | 1 / 1 | the score report ("### 📊 Post Score Report", the test post's title and link) |
  | Pro | 1 / 0 | 1 / 1 | the score report |

  This confirms the defect on v7 and the fix on v8 in real n8n (ticket Reproduction step 3).
- After the review patches (Triage pass 1), run by the orchestrator: all 21 harness files 0 failed (template-failure 91, template-guard 63, rest-reviews 79); `local-wp-harness.py` 36 passed; `npm run test:unit` 174 passed; `php -l` clean; no CR in any touched file. The run in n8n predates patch 2, which changes only the rewrite error's `chat_message` in the Pro template. The new harness row covers it; the `score` path the run proved is unchanged.
- Cleanup, by id:
  - Workflows: `<n8n-id>`, `<n8n-id>` (Free) and `<n8n-id>`, `<n8n-id>` (Pro).
  - Credentials: `<n8n-id>`, `<n8n-id>`, `<n8n-id>`, `<n8n-id>`.
  - Executions: 10.
  - Every delete answered 200 except `<n8n-id>` (the Pro capture), whose DELETE right after its deactivate answered 409. A second deactivate followed by a delete 2 s later answered 200, and a GET then answered 404.
  - Final sweep: 0 of the instance's 17 workflows and 0 credentials carry the `PQM bug 97 test` prefix. The sandbox was only read.
