---
title: 'The connection test proves the full loop, callback and token included'
type: 'feature'
ticket: '2'
created: '2026-10-21'
status: 'done'
baseline_revision: 'c488ddc7a20e8c8b291830b6ce0bb31499021837'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/memory/llm-options-architecture.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The test ping proves WP → n8n only; n8n → WP (the `/save-review` callback with `X-PQM-Token`) stays unproven until a real review. On a site n8n cannot call back, the review runs, the callback fails silently and the wizard waits without end (retrospective A8).

**Approach:** The `ping` command calls the site back through the production callback node, at a dedicated endpoint; the plugin waits a few seconds for that callback and reports exactly what it proved. Template v7; every caller of `ping_webhook()` (Test again, the wizard's connect and provision steps, rotation, update) gets the round trip for free.

**Decisions (owner, 2026-10-21):** D1 story 22 is folded into this story, not gating (noted in 22). D2 split: this build is goal (a), the round-trip ping; goal (b), the bounded first-review wait, is deferred (`deferred-work.md`). D3 the passing and wrong-token walks run on the owner's production site, by the owner; the plan carries the script and the harness rows (the Local sandbox cannot be called back).

**Design (agent):** D4 the ping item flows `Parse Input → Route Command → Ping OK → POST Result to WordPress`, so the callback uses the very node, header expression and timeout a review uses; the ping body carries `callback_url` (`…/post-quality/v1/ping/<id>`, id = 20 random chars) and `auth_token` like a review payload. D5 the wait is server-side inside `sent_ping()` (up to 8 s, filter `pqm_ping_callback_wait`, one read per 500 ms with the Options cache bypassed), only when the trigger replied the current version; the user keeps one request and one result line. D6 a received callback = proven (success); a refused one (wrong token, recorded only when its id matches the pending ping) or none within the wait = `ok: false`, recorded as the page's "needs attention" like any failed test.

## Boundaries & Constraints

**Always:** `__PQM_*__` placeholders only in the template; bump `PQM_WORKFLOW_VERSION`, Parse Input's stamp(s) and the trigger's `responseData` together (6 → 7) and re-derive the Free template; `permission_callback` on the new route (`check_webhook_token` semantics); the token in no reply or log; copy per `docs/brand.md` (say exactly what was tested); harnesses prove every matrix row; one `unreleased.md` line (the re-import/update it needs).

**Never:** no change to the trigger's `responseMode` or to any review branch; no client-side polling or new JS flow; no second token; no migration of stored URLs or reports; no first-review wait (goal b); no generator review (story 22's remaining leads).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Round trip proven | v7 reply, callback lands with the site's token and the pending id | `ok`, version 7 recorded via test; message: reaches n8n, credential right, up to date, **n8n called this site back: the round trip works** | — |
| Callback refused | v7 reply, callback with a wrong token and the pending id | `ok: false`, failure recorded; message: n8n reached the site but the callback's token is not this site's webhook token; fix in n8n's POST Result to WordPress node, then test again | endpoint answers 401/403, writes `refused` |
| No callback | v7 reply, nothing within the wait | `ok: false`, failure recorded; message names the wait, `rest_url()` as what n8n must reach (local/private/password-protected sites cannot be), the n8n execution log | — |
| Older workflow | v6 reply | unchanged older-version warning, no wait, no pending option left | — |
| Trigger refuses / 404 / transport error | as today | unchanged (story 35/67/71 messages), no pending option left | — |
| Callback for an unknown or stale id (>60 s) | right token, no matching pending | 404 `pqm_ping_unknown`, nothing written | — |
| Wrong token, no matching id | anyone POSTing the route | 401/403, nothing written | — |
| Wizard connect / provision / rotation / update | their pings | same outcomes through `ping_webhook()`; failed callback keeps the step open as a failed ping does | — |
| Free template | derived from v7 | Ping OK still wired to POST Result; `/ping` ignores `workflow_variant` | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/templates/n8n-workflow.json` — Parse Input ping branch (one line: `callback_url`, `auth_token`, `notification_url: ''`, `workflow_version: 7`; keep the multi-line stamp unique for `bin/derive-free-template.php`'s regex), stamp + `responseData` → 7, Ping OK passes the item through with `overall_score: 0, status: '', result: '{}'`, connection Ping OK → POST Result to WordPress. Then `php bin/derive-free-template.php`.
- `post-quality-manager/post-quality-manager.php:94` — `PQM_WORKFLOW_VERSION` 7.
- `post-quality-manager/admin/class-pqm-admin.php` — `sent_ping()` (L2132): write `pqm_ping` `{id, at}` (autoload off) before the POST, send `callback_url` + `auth_token`; in the up-to-date branch `await_callback()` → proven / refused / none; delete the option on every path. Cache-bypass read: `wp_cache_delete` + `notoptions` as `PQM_N8n::cache_lock()` (L903). `render_workflow_body()` `failed` copy "did not reach your workflow" → "did not pass" (L~1552).
- `post-quality-manager/includes/class-pqm-rest-api.php` — route `POST /ping/(?P<id>[A-Za-z0-9]{20})`: `check_ping_token()` = `check_webhook_token()`, on failure with a matching live pending id writes `refused`; `ping_callback()` writes `received` or 404. Header comment route list. `check_webhook_token` L303.
- `post-quality-manager/includes/class-pqm-activator.php` — `pqm_ping` on the uninstall list (setup-harness checks it).
- `src/setup/steps.js:132` — the connect step's muted line: drop "Your first review confirms the way back", say the ping proves the way back. `npm run build` not needed for tests; `admin/build` is CI's.
- Harnesses: `tests/harness/rest-reviews-harness.php` (version 6 → 7 checks L303–316, route + endpoint rows, Ping OK wiring, every `workflow_version: N` in Parse Input equals the constant), `tests/harness/template-failure-harness.php` (ping body through Parse Input → Ping OK → POST body: `command: 'ping'`, no `post_id`, `error_message: ''`), `tests/harness/template-guard-harness.php` (Free keeps Ping OK → POST Result), `tests/harness/setup-harness.php` L368–392 + `tests/harness/lib/n8n-world.php:92,440` (`wp_remote_post` stub performs the callback by `ping_response['callback']` ∈ received/refused/none; filter `pqm_ping_callback_wait` 0; `PING_OK`; "did not pass" rows L407, render-matrix L683/893/963).
- Docs: `docs/release-notes/unreleased.md` (one paragraph), `n8n/workflow-diagram.md` L49–74, `docs/policies/privacy.md` L14 + `readme-external-services.md` L12 (the test ping makes n8n call the site's REST API back), `memory/llm-options-architecture.md` (v7 line in the handshake paragraph), `docs/user-paths.md` A4 UV → CV after the owner's walk.
- Don't touch: `Has Notification URL?`/`Send Notification`, the review branches, `pqm_n8n_payload()`, `pqm_n8n_headers()`.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/templates/n8n-workflow.json` + `n8n-workflow-free.json` + `post-quality-manager.php` — v7 template with the callback path, derived Free file, constant — the contract.
- [x] `includes/class-pqm-rest-api.php` + `includes/class-pqm-activator.php` — `/ping/{id}` route, permission with refusal record, uninstall — the landing point.
- [x] `admin/class-pqm-admin.php` — pending id, callback fields, the wait, three outcomes, "did not pass" copy — the proof and its copy.
- [x] `src/setup/steps.js` — connect-step line — the copy drops the caveat.
- [x] `tests/harness/*` as mapped — every matrix row; `template-failure` runs the real ping path through node.
- [x] Docs + memory as mapped; `unreleased.md` line.

**Acceptance Criteria:**
- Given a v7 workflow whose callback reaches the site with the right token, when any caller pings, then the result says the round trip works and the page shows "Your workflow answers. Version 7 · checked just now by a test."
- Given the callback node sends a wrong token, when the owner tests, then the result says the callback was refused and where to fix it; the page needs attention.
- Given a v6 import, when pinged, then the older-version warning shows, and a managed site's Update workflow (entry 9) brings it to v7 in one click.
- Given the sweep, when run, then every harness in `tests/harness/` passes and Jest 149 stays green.

## Implementation Notes

Rendered by hand from the workspace root (BMad skills unregistered there, owner rule 2026-10-16): implementer and review lenses as subagents on Fable 5.1. Branch `feat/round-trip-connection-test` created by the agent under the owner's blanket authorization (commits and draft PRs without asking, never a merge).

**Build 2026-10-21 (implementer subagent, Fable 5.1; nothing committed):**
- Template v7: Parse Input's ping branch is one line carrying `callback_url`, `auth_token`, `notification_url: ''`, `workflow_version: 7` (the multi-line stamp stays the one `bin/derive-free-template.php` reads); trigger `responseData` 7; Ping OK spreads the item with `overall_score: 0, status: '', result: '{}'` and is wired to POST Result to WordPress. Free file re-derived; `PQM_WORKFLOW_VERSION` 7. The real node path (Node.js) gives the callback body `{command: 'ping', overall_score: 0, status: '', result: {}, workflow_version: 7, error_message: ''}`, no `post_id`.
- `PQM_REST_API`: route `POST /ping/(?P<id>[A-Za-z0-9]{20})`, `check_ping_token()` (= `check_webhook_token()`; a refusal naming a live pending id writes `state: refused`), `ping_callback()` (`received` or 404 `pqm_ping_unknown`; a pending older than `PQM_Admin::PING_LIVE` = 60 s counts as unknown). `PQM_Activator::uninstall()` deletes `pqm_ping`.
- `PQM_Admin`: `sent_ping()` writes `pqm_ping` `{id, at}` (autoload off) and deletes it in a `finally`; `ping_reply()` sends `callback_url` + `auth_token`; on a current-version reply `awaited_callback()` polls (filter `pqm_ping_callback_wait`, `PING_WAIT` = 8 s, one read per 500 ms, `wp_cache_delete` + `notoptions` cleared before each) and the branch returns proven (`ok`, through `diagnosed()`) / refused / none (`ok: false`, `version: null` as the contract says for a failed ping, recorded by `ping_webhook()` as the page's failure). The Free-variant-on-Pro reply and every older reply keep their warning without a wait. "did not reach your workflow" → "did not pass".
- `src/setup/steps.js`: the connect step's muted line says the ping proves the way back (no build run; `admin/build` is CI's).
- Harnesses: `lib/wp-stubs.php` gained a header-aware `WP_REST_Request`, `wp_cache_*`/`wp_generate_password` stubs and `pqm_harness_ping_callback()`, which plays n8n's callback through the registered route from each `wp_remote_post()` stub (`received`/`refused`/`none`; the filter answers 0 so no harness sleeps). Rows per matrix: setup-harness (proven body fields, pending written/deleted, refused + none keep the step open, older reply no wait, uninstall), render-matrix (proven, refused, none, the next ping clears, wait source check), rest-reviews (both stamps = 7, route + endpoint rows incl. stale/unknown/wrong-token), template-failure (ping through Parse Input → Ping OK → POST body, url + header expressions), template-guard (Free keeps Ping OK → POST Result), caps (uninstall list), n8n-world (`PING_OK` sentence, `writes()` without the ping's own option), n8n-managed (token-leak row allows `auth_token` in the ping body as a review payload carries it), n8n-provisioning (the no-sleep source check excludes `usleep`).
- Docs: `unreleased.md` paragraph; `n8n/workflow-diagram.md` (v7 bullet, Ping OK → CB); privacy.md + readme-external-services.md (the test sends the callback address and token, no post data; the workflow calls back once); `memory/llm-options-architecture.md` v7 in the handshake paragraph; `docs/user-paths.md` A4 UV bullet names the build (the UV → CV move waits for the owner's walk). Jest fixtures in `tests/js/setup-connect.test.js` and `workflow-status.test.js` follow the "did not pass" copy.

**Verification 2026-10-21:** `php -l` clean on every touched PHP file; `php bin/derive-free-template.php` then `template-guard` 63/0. Harness counts before → after: caps 284 → 284, cron-gate 35 → 35, free-build 83 → 83, insert-block 66 → 66, license 27 → 27, n8n-adoption 88 → 88, n8n-client 177 → 177, n8n-managed 165 → 165, n8n-provisioning 154 → 154, quota 71 → 71, release-header 31 → 31, render-matrix 409 → 416, rest-posts 165 → 165, rest-reviews 65 → 79, sanitize-matrix 226 → 226, setup 141 → 150, template-failure 55 → 61, template-guard 62 → 63, voice 23 → 23; 0 failed everywhere. `npm run test:unit` 149 passed; `npm run lint:js` clean.

**Deviations and leads (for the review lenses):**
- Review fix (same day): the connect route's prefix is "URL saved, but the test did not pass. %s" and provisioning's "Your workflow is set up and active in n8n, but the test did not pass. %s"; the editor-address hint stays only when the ping itself failed — `ping_reply()` marks the refused/none outcomes with `callback: 'refused'|'none'`, which `ping_failed_sentence()` reads (provisioning rows for both, no hint; Jest fixtures follow).
- Review fix: the wait runs before the Free-variant check on the Pro build, so a v7 Free template's callback claims the pending id (its state ignored, the Free warning unchanged) instead of meeting a 404 and failing the execution in n8n. The setup harness counts cache-bypassed reads of `pqm_ping` per branch (current 1, older 0, Free-on-Pro 1); `docs/user-paths.md` L123's parenthetical updated.
- `check_webhook_token()`'s logged-in bypass (`pqm_run_free_agents`) also passes the ping callback, as on `/save-review`; n8n's real callback is anonymous. The setup harness's refused/none rows therefore run with `pqm_manage_settings` only.
- The `writes` assertions in n8n-managed read `writes()` (n8n-world) which drops the ping's own `pqm_ping`/`-pqm_ping` pair; the setup harness's hoisted `delete_option()` stub only records deletions, so its rows count `tw_deleted` instead of a gone option.
- Two render-matrix rows that relied on `PQM_WORKFLOW_VERSION - 1 < 6` for the new-production-URL note now pin version 5, and a new row shows a version-6 report without the note.
- Not done, by the plan: no first-review wait (goal b), no migration, no client-side polling. Manual checks 1–4 (owner, production site) open.
## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, 2026-10-21): 4 findings — high 0, medium 1, low 3, false 0, maybe-false 0; all 4 → patch.**

1. medium → patch — `class-pqm-rest-api.php` L1320 and L1604: the connect route's "URL saved, but the ping did not get through. %s" and provisioning's "…but the ping did not get through. %s If your n8n serves webhooks on another address…" now precede the refused / no-callback sentences, so the first clause says the opposite of what failed (the ping got through; the callback did not), against the frozen Always "say exactly what was tested". Verified by reading both strings and the setup-harness rows pinning them. Patch: "the test did not pass" in both prefixes; the editor-address hint conditioned in copy on the ping not getting through.
2. low → patch — `class-pqm-admin.php` `ping_reply()`: on the Pro build a Free v7 template returns the Free-variant warning before `awaited_callback()`, the `finally` deletes the pending id at once, and the Free template's Ping OK → POST Result callback then meets a 404, so every test leaves a failed execution in n8n's log. Verified: the Free file keeps that connection (template-guard). Patch: wait before the variant check, the state ignored on that branch.
3. low → patch — matrix row "older workflow → no wait" is asserted only through copy that holds either way; with `pqm_ping_callback_wait` 0 a wait is invisible. Verified: both rows pass whether or not the wait runs. Patch: a counting `wp_cache_delete` stub in setup-harness, one row per branch (current: one read; older: none; Free on Pro: one read).
4. low → patch — `docs/user-paths.md` L123 still says "(2)'s round-trip test stays with story 2 — the first review proves the return path meanwhile" beside the A4 bullet that names the build. Verified by reading the line. Patch: the parenthetical names the build and the open owner walk.

## Design Notes

The wait bypasses the Options cache because `get_option()` caches a miss in `notoptions` for the whole request; without the bypass, a callback written by n8n's request is invisible to the waiting one. Ceiling, one comment: two concurrent pings share one pending slot, the earlier one reports "no callback"; add per-id options when a site reports it.

Copy drafts (brand: say what was tested, gentle, actionable):
- proven: "Ping OK — this site reaches n8n, the webhook credential is right, your workflow is up to date and n8n called this site back with the right token: the round trip works."
- refused: "n8n reached this site, but the callback was refused: it carried a token that is not this site's webhook token. In n8n, open the POST Result to WordPress node and set its X-PQM-Token header to {{ $json.auth_token }}, then test again."
- none: "n8n accepted the ping but did not call this site back within %d seconds. n8n must reach %s (this site's REST API): a local, private or password-protected site cannot be called back. Check the ping's execution in n8n, then test again."

## Verification

**Commands:**
- `php -l` on every touched PHP file; `node --check` on none (steps.js is a module: `npm run lint:js`) — expected: clean.
- `php bin/derive-free-template.php && for f in tests/harness/*.php; do php "$f" || break; done` — expected: every harness 0 failed, counts rise by the new rows only.
- `npm run test:unit` — expected: 149 passed.

**Manual checks (owner, production site, after the plugin build is installed; nothing is created, nothing to clean up):**
1. n8n Workflow page: the status block says "Workflow update available" (seen 6 < 7); click **Update workflow** (the first real template bump since entry 9) — expected: the block swaps to "Your workflow answers. Version 7 · checked just now by a test." with the proven sentence as the outcome line; in n8n the execution shows Ping OK → POST Result to WordPress answering 200.
2. **Test again** — expected: the same proven sentence within a few seconds.
3. In n8n, open the workflow, POST Result to WordPress → header X-PQM-Token value → type `wrong`, publish. **Test again** — expected: the refused sentence, the page "The last test just now did not pass: …", error accent. Restore the value to `={{ $json.auth_token }}`, publish, **Test again** — expected: proven again.
4. Optional, the wizard: Set up by hand → step 4 Save and test with the saved URL — expected: the proven sentence in the dialog's notice and the step ticked.
Record the outcome here with the environment named.

**Outcome (owner, 2026-10-22, the owner's production site, plugin = local zip of main ed06a8b):** all four checks passed as expected. Check 1: the update itself passed (the workflow in n8n at version 7), but the ping right after it answered 404 ("The workflow was updated in n8n. n8n responded with 404…"; the block red, still "Version 6"); Test again then passed: "Your workflow answers. Version 7 · checked just now by a test." — green, which on a v7 reply only a received callback records. The 404 is n8n's publication window after the PUT republishes the workflow (`memory/n8n-provisioning.md`, publication outbox finding): `update_n8n_workflow_callback()` pings once, where provisioning retries a 404 (`ping_after_activation()`, bug 80) — a follow-up bug, not a defect of this story's round trip. Checks 2–4 (Test again, the wrong token refused and restored, the wizard's Save and test) passed.
