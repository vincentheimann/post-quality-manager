---
title: 'Workflow version status the user can trust without running a command'
type: 'bugfix'
ticket: ''
created: '2026-10-08'
status: 'done'
baseline_revision: '69ba9793cda0c1bb95c5420121e70b741d800fd9'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context: ['{project-root}/docs/brand.md', '{project-root}/memory/llm-options-architecture.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The n8n Workflow page says "Workflow update available." based on `pqm_workflow_version_seen`, and only a finished command's result callback updates that option. After re-importing the latest template, the warning stays (it is wrong) until a real command runs. The test ping can't clear it because the webhook answers before n8n reports any version.

**Approach:** Never draw a conclusion from data the user has since made stale. (1) Serving the template (Download, or GET /workflow for Import from URL) forgets the stored version, so the page shows "not checked yet". (2) The test ping becomes a real handshake: a v5 template's Webhook Trigger answers with `{"ok":true,"workflow_version":5}`, and the plugin records that version. (3) The status line says when the version was checked and how (a test ping or a command). The page refreshes its status block in place after a ping, so it never contradicts the ping message.

**Decisions (owner, 2026-10-08, review pass 1):** (D1) The open page re-reads the status from WordPress whenever its tab regains focus, so a download, an import from URL or a finished command that happened while the user was away shows up without a reload. This read never pings n8n. (D2) A ping answered by an older workflow counts as connected but is shown as a warning, never as a success: on the n8n Workflow page's test result and in the setup dialog, whose connect step still ticks. (D3) The code built in pass 1 stays; the fixes go on top of it.

## Boundaries & Constraints

**Always:** Record a ping's version only when the pinged URL is the saved `n8n_webhook_url`; the Settings page can test an unsaved URL. Old imports (v1–v4) keep working: their result callbacks still report, and a version-less 2xx ping reply means "an older workflow" and never claims the credential was checked. One PHP renderer produces the status block, both on page load and in AJAX/REST responses. Bump `PQM_WORKFLOW_VERSION` and the Parse Input stamp to 5 together. Copy follows `docs/brand.md`.

**Never:** No "Dismiss" link. No automatic ping on page load (each ping is an n8n execution). No new n8n node; the trigger's built-in response option does the job. No change to the callback payload or the cron dispatch.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Fresh / just served | option absent | "Your n8n workflow: not checked yet — send a test ping once it is imported and active." No notice | — |
| Download or GET /workflow | any stored report | option deleted → as above | Template missing: error as today, option untouched |
| Ping, v5 reply | 2xx, body `workflow_version: 5`, saved URL | Stored `{version 5, via test, at now}`. Message: reaches n8n, credential right, workflow up to date. Status block shows version 5, "checked just now by a test ping", no notice | — |
| Ping, older reply | 2xx, body without version (v1–v4) | Stored `{version 0, via test}`. Message: reaches n8n, workflow is an older version, re-import it. Notice shows "an older version" | Non-JSON body counts as version-less |
| Ping, unsaved URL | Settings field ≠ saved URL | Message as above, nothing stored, no `status_html` | — |
| Ping fails | 401/403/404/other/WP_Error | Messages unchanged, nothing stored | as today |
| Result callback | `workflow_version: N>0` | Stored `{N, via command, at now}` | N=0: untouched |
| Legacy option | int N (pre-v5 plugin) | Read as `{N, via command, at 0}`: notice for N<5, no time shown | — |
| Tab regains focus | n8n Workflow page open, report changed meanwhile (served, pinged elsewhere, a command reported) | Status block replaced with the current server render; no n8n request | Read fails (network, expired nonce, 403): block left as is, nothing shown |
| Older reply, shown | 2xx without a current version | Page test result and setup dialog notice use the warning style; dialog connect step ticks | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/post-quality-manager.php:30` -- `PQM_WORKFLOW_VERSION` 4→5. Add `pqm_workflow_report(): ?array` (reads the option, normalizes a legacy int, returns null when absent) and `pqm_record_workflow_version(int $version, string $via)` next to `pqm_workflow_json()` (~L340).
- `post-quality-manager/templates/n8n-workflow.json` -- Webhook Trigger `options`: `{"responseData": "{\"ok\":true,\"workflow_version\":5}"}` (n8n's Webhook node: `responseData` option, string, shown for `responseMode: onReceived`; a string body goes out via `res.send` as is, confirmed in n8n source `webhook-request-handler.ts`). Parse Input `workflow_version: 4` → 5.
- `post-quality-manager/admin/class-pqm-admin.php` -- `render_workflow()` L1091-1129: move the notice and the version line into a new `render_workflow_status(): string` wrapped in `<div class="pqm-workflow-status">`. `download_workflow()` L1262: forget the report before serving. `ping_webhook()` L1322: parse the body, choose the message from the replied version (no longer from the stored option), and return `version` (int|null). `ajax_test_connection()` ~L1385: record when URL === saved, send `{message, status_html}`.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `save_review` L414-419: use `pqm_record_workflow_version(..., 'command')`. `workflow_callback()` L624: forget the report. `setup_connect_callback()` L1019: record (the URL was just saved) and add `status_html`.
- `post-quality-manager/admin/js/pqm-admin.js` L304-306 -- `resp.data` becomes a string (errors) or `{message, status_html}`; replace `.pqm-workflow-status` when present.
- `src/setup/steps.js` L429-437 -- same replacement after connect (one DOM line; the block is outside the island).
- Pass 2 additions (D1, D2): `PQM_Admin::ajax_workflow_status()` registered as `wp_ajax_pqm_workflow_status` beside `wp_ajax_pqm_test_connection` (`post-quality-manager.php:64`); same nonce (`pqm_admin_action`, `pqmAdmin.pageNonce`) and capability (`pqm_manage_settings`) as `ajax_test_connection()`; answers `{status_html}`. `pqm-admin.js`: on window `focus`, when `.pqm-workflow-status` exists, POST that action and swap the block; failures are silent. Ping replies carry `outdated: bool` (`ping_webhook()` returns it: a 2xx whose version is below `PQM_WORKFLOW_VERSION`); `ajax_test_connection()` and `setup_connect_callback()` pass it on. `showTestResult()` gets a warning state: `is-warning` class in `post-quality-manager/admin/css/pqm-admin.css` beside `.is-success`/`.is-error` (L62-64). `src/setup/steps.js` `Connect` (L429-437): `status` = `error` when not `ping_ok`, `warning` when `outdated`, else `success`.
- Harnesses to update: `render-matrix-harness.php` (L384-390 notice, L455-478 ping), `rest-reviews-harness.php` (L247-292 records, version 4→5), `setup-harness.php` L198, `template-failure-harness.php` L107/147 (stamp 5). The `uninstall.php` delete stays.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/post-quality-manager.php` -- version 5 + the two helpers -- one reader and one writer for every caller.
- [x] `post-quality-manager/templates/n8n-workflow.json` -- trigger `responseData` + stamp 5 -- the handshake.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- status renderer, forget on download, ping version + messages, AJAX payload -- the page tells the truth.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- callback writer, forget on GET /workflow, connect records + `status_html`.
- [x] `post-quality-manager/admin/js/pqm-admin.js`, `src/setup/steps.js` -- swap the status block in place.
- [x] `tests/harness/*` -- cover every matrix row; extend the harnesses above, no new file unless one is clearly cleaner.
- [x] `memory/llm-options-architecture.md` -- handshake now v5: ping reply, report shape, forget-on-serve.
- [x] Pass 2: `post-quality-manager.php`, `class-pqm-admin.php`, `class-pqm-rest-api.php`, `pqm-admin.js`, `pqm-admin.css`, `src/setup/steps.js` -- focus refresh (D1) and the warning state (D2), per the Code Map's pass 2 line.
- [x] Pass 2: `tests/js/workflow-status.test.js` (new, jsdom, loads `pqm-admin.js` like `settings-tabs.test.js`) -- the ping swaps the block on success with `status_html`, leaves it on an error string and without `status_html`, shows the warning style when `outdated`; focus re-reads and swaps; a failed read leaves the block. Harness rows for `ajax_workflow_status()` (capability, nonce, payload) and `outdated` in both ping replies.
- [x] Pass 2: `memory/admin-ui-rebuild.md` L15 -- replace the pre-v4 ping sentence with the reply-version rule (review finding 1).

**Acceptance Criteria:**
- Given the page shows "Workflow update available.", when the user sends a test ping to a v5 workflow, then the notice disappears without a reload and the line reads version 5, checked just now by a test ping.
- Given the setup dialog's connect step pings a v5 workflow, when the dialog closes, then the status block under it already shows the new state.
- Given the page shows "Workflow update available." and the user downloads the template in that tab, when they come back to the tab after importing, then the block reads "not checked yet" without a reload.
- Given `npm run build`, then the setup island bundle still builds and `npm run lint:js` passes.

## Design Notes

The report option keeps its name, `pqm_workflow_version_seen`, and changes from int to `['version' => int, 'via' => 'test'|'command', 'at' => int]`. Version 0 means an older workflow that does not report its number. No migration: the reader normalizes the legacy int.

Skipped: recording the version from a command's dispatch reply. The result callback already reports it, and both writes in one run would make the status flicker. Add it if callbacks prove unreliable.

## Verification

**Commands:**
- `php -l` on every touched PHP file -- clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- all green
- `npm run test:unit && npm run lint:js && npm run build` -- green
- `node --check post-quality-manager/admin/js/pqm-admin.js` -- clean

**Manual checks (if no CLI):**
- On Local: re-import the v5 workflow and send a test ping → the notice clears in place. Ping a v4 import → "older version" message and the notice.

## Implementation Notes

- 2026-10-08: the implementation subagent stopped on an API connection error after finishing every planned file (its last step was the v5 bullet in `n8n/workflow-diagram.md`). The orchestrator re-ran the full Verification: everything green.
- 2026-10-08, pass 2: built on top of pass 1 (D3). The implementer flagged the setup dialog's helper line ("A ping that gets through proves … the webhook credential is right") as untrue for an older workflow; the orchestrator reworded it to "A ping answered by an up-to-date workflow proves …". Full Verification re-run by the orchestrator: 14 harnesses, Jest 23/23, lint, build green.

## Review Triage Log

### Pass 1 (quick), 2026-10-08: high 0 · medium 3 · low 2 · false 0 · maybe-false 0

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | `memory/admin-ui-rebuild.md` L15 still says a pre-v4 import's 2xx proves reachability only | low | patch | Verified: the line describes the removed stored-option branch; `ping_webhook()` now decides from the reply body. Direct text correction. |
| 2 | The in-place swap (AC1, AC2) is checked only as source text, never run in jsdom | medium | patch | Verified: `render-matrix-harness.php` greps for `outerHTML = data.status_html`; `tests/js/` has only `settings-tabs.test.js`. AGENTS.md asks for a Jest test for rows only a browser can show. |
| 3 | "Template missing → option untouched" is never run, only checked by source order | low | reject | Verified the code is correct: `delete_option` follows the null check in both `workflow_callback()` and `download_workflow()`, and a harness check locks that order. Running the path needs new stubs for a constant-bound file path; unlikely to regress silently. |
| 4 | After Download (or an import from URL), the open page keeps "Workflow update available." until a reload or a ping | medium | intent_gap | Verified: the Download link is a plain attachment navigation, so the page never re-renders. A user who returns to the open tab after re-importing sees the original complaint. The plan does not settle how the open page learns of it. |
| 5 | Setup dialog: a version-less 2xx (every v1–v4 import) shows the re-import advice styled as success and ticks the connect step | medium | intent_gap | Verified: `setup_connect_callback()` returns `ping_ok: true`, and `Connect` maps that to `status: 'success'` and `onConnected(url, true)`. The plan does not say whether an outdated workflow counts as connected. |

## Plan Change Log

- 2026-10-08, review pass 1 → loopback 1 (intent_gap, findings 4 and 5). Amended: Intent decisions D1–D3 (owner), two matrix rows, a pass 2 Code Map line, three pass 2 tasks, one acceptance criterion. Known-bad state avoided: an open n8n Workflow tab still saying "Workflow update available." after the user re-imported, and an outdated-workflow ping reported as a success. KEEP (D3, no revert): everything built in pass 1 — the report helpers and their option shape, the v5 trigger reply, forget-on-serve in both serving paths, `render_workflow_status()` as the single renderer, the saved-URL-only recording rule, and the harness coverage; pass 2 only adds to it. Patches carried: finding 1 (memory line) and finding 2 (jsdom test), now pass 2 tasks.

### Pass 2 (quick), 2026-10-08: high 0 · medium 2 · low 0 · false 0 · maybe-false 0

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 6 | The setup dialog's in-place swap (AC2) and warning state (D2) are checked only as source text; `tests/js/workflow-status.test.js` loads only `pqm-admin.js` | medium | patch | Verified: the harness greps `steps.js`. `Connect` is reachable as `STEPS[…].Body` (exported), so a jsdom render needs no new public surface. Remainder of finding 2. |
| 7 | The stored report survives a webhook URL change, so the page can describe the workflow at the old URL | medium | patch | Verified: `on_settings_updated()` (the `updated_option` hook, which `save_webhook_url()` also fires) only reschedules cron; a failed connect ping records nothing. Fixed in the one hook every URL save goes through: forget the report when `n8n_webhook_url` changes. Same stale-data class as forget-on-serve; the Approach admits one reading. |

Patches 6 and 7 applied by the implementer (`tests/js/setup-connect.test.js`; `on_settings_updated()` forgets the report on a URL change, hook at 3 args). Orchestrator re-ran the full Verification: 14 harnesses, Jest 26/26, lint, build green. Nothing deferred.
