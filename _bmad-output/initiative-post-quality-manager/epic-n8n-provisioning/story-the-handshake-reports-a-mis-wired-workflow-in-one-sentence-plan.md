---
title: 'The handshake reports a mis-wired workflow in one sentence'
type: 'feature'
ticket: '7'
created: '2026-10-15'
status: done
baseline_revision: '6932020430927412809a04cc3dbc7214db2b953d'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On 2026-10-13 n8n gave the owner's one Header Auth credential to every node on import. Every Claude call then failed, and the plugin had no way to tell: a ping only proves that the trigger answers.

**Approach:** When an n8n API key is stored, every successful test ping also reads the workflow through the API: through the setup map's id, or else as the active workflow whose Webhook node path equals the saved URL's last segment. It reads the published nodes, because those are what run. Two things count as mis-wiring: a Claude node carrying the trigger's credential, or a node that needs a credential and has none. The check stores one sentence naming the nodes, the credential and the fix; the status block shows it with **Check again** as the page's one primary action (EXPERIENCE.md, state 5).

## Boundaries & Constraints

**Always:** GET calls only; the workflow is found by id or by path, never by name (spec constraint). Nodes are told apart by type and parameters, never by their names, which the owner may change. A check that could not run (no key, n8n unreachable or refusing, no matching workflow) leaves the ping result and message unchanged: the handshake stays ping-only. The diagnosis never turns a passing ping into a failed one. The sentence follows `docs/brand.md` and n8n's own UI words at 2.42.3 ("publish").

**Never:** change anything on n8n; add a REST route or change a reply's shape; change the workflow template or `PQM_WORKFLOW_VERSION`; call n8n when the page renders (`ajax_workflow_status` stays read-only); rebuild the page layout (story 67).

## I/O & Edge-Case Matrix

`diagnose()` answers a sentence (mis-wired), `''` (clean), or `null` (could not run). The recorder stores a sentence or `''` from a test and keeps what is stored on `null` and on a command report. The check runs only for the saved webhook URL: a Settings-field ping of an unsaved URL is not diagnosed (with a setup map, the read would describe the provisioned workflow, not the URL pinged).

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| 2026-10-13 shape | Key stored, no setup map; the active workflow with path `pqm-execute` (v5) has published Claude nodes carrying the trigger's credential id | Sentence naming those nodes (`wp_sprintf_l`), the credential's name and the fix: give them the Anthropic credential, publish, check again | — |
| Missing credential | A node with `parameters.authentication` set and not `none` has no `credentials.*.id` | Sentence naming the nodes without a credential, and the fix | Shared is reported first; missing ones after the next check |
| Provisioned | Setup map exists, its workflow linked as `provision()` links it (Pro without Application Passwords: Update WP Post name-only, skipped per D1) | `GET /workflows/{id}` only, `''` | — |
| Draft fixed, not published | `nodes` clean, `activeVersion.nodes` mis-wired | Reported: the published version runs | `activeVersion` null → `nodes` |
| Disabled node | `disabled: true`, no credential | Not reported | — |
| No key stored | Key forgotten after a sentence was stored | `''`: the sentence clears on the next test, the handshake is ping-only | — |
| Could not run | Key stored; WP_Error, non-2xx, or no active workflow whose path matches | `null`: the stored sentence (or none) stays, ping message unchanged | Silent |
| Command report | A command reports the version while a sentence is stored | Sentence kept: only a test clears it | Download / URL change delete the report, as today |
| Fixed, Check again | The owner fixed n8n, published, and pressed **Check again** | `''` stored, status shows the version line | — |

## Decisions (owner, 2026-10-15)

- **D1 — provisioned Pro site without Application Passwords:** when the setup map has no `wordpress` credential, the `httpBasicAuth` node (Update WP Post (Rewrite), name-only by entry 4's D1) is skipped by the missing-credential rule, so the site checks clean. Without a setup map, every rule applies.
- **D2 — mis-wiring found by the wizard's connect step (manual path):** the "connected, but warn" path of an older workflow: `outdated: true`, the message is the sentence, the step ticks, the dialog shows a warning; no React change.
- **D3 — plan size:** kept whole (~3,200 tokens) by the owner.

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `request()` (GET, `/api/v1` prefixed, WP_Error or `{code, json}`); `SETUP_OPTION` map `{workflow_id, credentials:{webhook, anthropic, wordpress?}}`; `connection()['api_key']`. New public `diagnose(string $webhook_url): ?string` goes here.
- `post-quality-manager/admin/class-pqm-admin.php` -- `ping_webhook()` L1814: the one ping, called by `ajax_test_connection()` L1886, `POST /setup/connect` and `POST /n8n/provision` (REST L1202, L1379), each then calling `pqm_record_workflow_version()`. `render_workflow_status()` L1437: notices + version line; `$reimport` (L1483) is today's one primary, used by the outdated and the Free-variant notices.
- `post-quality-manager/includes/pqm-functions.php` -- `pqm_workflow_report()` L352, `pqm_record_workflow_version()` L374.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- L1216 and L1395: pass `$ping['miswired']` to the recorder. L469 (`save_review`, a command's callback) is the "Command report" row's one producer: its three-argument call stays. No reply change.
- `post-quality-manager/admin/js/pqm-admin.js` -- `initTestConnection()` L282 binds each button at load, so a button inside the swapped status block loses its handler: delegate on `document` as `initReimportLink()` L386 does. `swapWorkflowStatus()` L345 dispatches `pqm:workflow-status`.
- `src/kit/use-reimport-shown.js` -- `shown()` = `.pqm-workflow-status .pqm-reimport` exists; the islands then turn their buttons secondary. Imported by `src/setup/index.js`, `src/setup/steps.js`, `src/n8n-connection/index.js` (the name stays). No test imports it; the fixtures in `tests/js/setup-provision.test.js` L762 and `tests/js/n8n-connection.test.js` L300 keep `button-primary` and stay green.
- `src/setup/steps.js` L85 -- `outdated` → warning. Read only, unchanged.
- n8n@2.42.3 source (read 2026-10-15: `public-api/v1/controllers/workflows.public.controller.ts`, spec `node.yml`, `parameters/limit.yml`): `GET /workflows/{id}` and `GET /workflows?active=true&limit=250&excludePinnedData=true` (250 = the maximum; `nextCursor` beyond) return draft `nodes` plus `activeVersion` (`{nodes, connections, …}` of the published version, or null). Node `credentials` = `{type: {id, name}}` (spec example; the hitl walk confirms the single-GET read).
- Node facts, both templates: trigger `n8n-nodes-base.webhook`, `parameters.path` (`pqm-execute` at v5, `pqm-execute-<host>` since v6), `parameters.authentication: headerAuth`, `credentials.httpHeaderAuth`. Claude nodes: `httpRequest`, `parameters.url` `https://api.anthropic.com/v1/messages`, `authentication: genericCredentialType`, `genericAuthType: httpHeaderAuth`. Update WP Post (Rewrite): `genericAuthType: httpBasicAuth` (Pro only). POST Result / Send Notification: no `authentication`.
- `tests/harness/n8n-api-harness.php` -- transport stub (`wp_remote_request` scripted replies, `wp_remote_post` ping stub, options store, writes log). Its passing provisioning rows (L494, L747, L768) and `tests/harness/free-build-harness.php` L372 run `connected()` and assert `calls() ===` exact lists: once the ping diagnoses, the `GET /workflows/{id}` lands in `calls`, so those rows need the GET scripted (a clean `activeVersion`) and listed. `tests/harness/rest-reviews-harness.php` L258 and L290 compare `pqm_workflow_report()` strictly: return `miswired` only when a sentence is stored, as `variant` does. `tests/harness/render-matrix-harness.php` L618–L834: status-block rows; the up-to-date and not-checked rows (L634, L636) render no primary. `wp_sprintf_l()` is reached through `wp_sprintf('%l', $names)`, unstubbed: n8n-api-harness needs a `wp_sprintf` stub. `tests/js/workflow-status.test.js` -- status-swap fixture, `OLD_BLOCK` etc.

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/includes/class-pqm-n8n.php` -- add `diagnose()`: no key → `''`; setup map → `GET /workflows/{id}`, else the active list filtered to the workflow whose published trigger `parameters.path` (trimmed of `/`) equals the saved URL's path after `/webhook/`; nodes = `activeVersion.nodes ?? nodes`, disabled skipped. Shared: Claude nodes (url contains `api.anthropic.com`) whose `httpHeaderAuth.id` equals the trigger's. Missing: `parameters.authentication` set and not `none`, no `credentials.*.id` (D1: the `httpBasicAuth` node is skipped when the setup map has no `wordpress` credential). Comment the ceiling: one page of 250 active workflows; the cursor is the upgrade path.
- [ ] `post-quality-manager/admin/class-pqm-admin.php` -- `ping_webhook()`: on a 2xx for the saved URL add `'miswired' => PQM_N8n::diagnose($url)` (`null` for any other URL); on a sentence, `message` = the sentence and `outdated` = true (D2). `ajax_test_connection()` passes it on. `render_workflow_status()`: a stored sentence renders first as a `notice-error` with the sentence (raw in the option, `esc_html()` once here), a `.button.button-primary.pqm-test-connection` **Check again** and no result span: the swapped block is the result; the re-import button then drops `button-primary` and keeps `pqm-reimport`. The Free-variant notice keeps its button secondary too.
- [ ] `post-quality-manager/includes/pqm-functions.php` -- recorder gains `?string $miswired = null` per the matrix; `pqm_workflow_report()` carries `miswired` only when a sentence is stored (as `variant`).
- [ ] `post-quality-manager/includes/class-pqm-rest-api.php` -- pass `$ping['miswired'] ?? null` at both recorder calls.
- [ ] `post-quality-manager/admin/js/pqm-admin.js` -- delegate the test-connection click on `document`; a button without a sibling `.pqm-test-result` (Check again) shows nothing but the swapped block. Settings page behaviour unchanged.
- [ ] `src/kit/use-reimport-shown.js` -- the selector becomes `.pqm-workflow-status .button-primary` (the doc says "the status block shows a primary action"); name, exports and importers unchanged.
- [ ] `tests/harness/n8n-api-harness.php` (+ the `connected()` rows there and in `free-build-harness.php`), `tests/harness/render-matrix-harness.php`, `tests/harness/rest-reviews-harness.php` -- one row per matrix line, fixtures built from `pqm_workflow_json()` of both builds with the ids rewired; assert no non-GET call reaches n8n, at most one `button-primary` in the block and one whenever a notice shows.
- [ ] `tests/js/workflow-status.test.js` -- **Check again** inside a swapped block pings, swaps again and writes nothing to a detached node.
- [ ] `docs/release-notes/unreleased.md` -- one line: with an n8n API key stored, a test ping also checks the workflow's credentials. `memory/n8n-provisioning.md` -- an "Entry 7 built" line.

**Acceptance Criteria:**
- Given the owner's production workflow, mis-wired again by hand, and a stored key, when a test ping runs, then the status block shows one sentence naming the Claude nodes, the shared credential and the fix; after the fix, a publish and **Check again**, the status shows the version line (hitl, on the environment the owner names).
- Given any state, then the status block holds at most one `.button-primary`, one whenever it shows a notice, and the islands' buttons are secondary whenever it does.

## Implementation Notes

- **HITL walk, 2026-10-15, on the owner's production site (named by the owner), run by the owner:** the branch build was on the site (version still reading 3.9.0). The owner's newly imported, published workflow had n8n's existing "Header Auth account" credential on all six Claude nodes, the 2026-10-13 shape again. The setup guide's **Save and test**, with the URL `…/webhook/pqm-execute-<host>`, answered with the sentence naming the six nodes and the credential, as a warning (D2). The owner picked the Anthropic credential in each node and published, and the next **Save and test** answered "Ping OK" with no warning. Confirmed on real n8n: the list read (`GET /workflows?active=true…`, no setup map) finds the workflow by its path and carries each node's `credentials` as `{id, name}`. Not exercised: the setup-map path (`GET /workflows/{id}`, no provisioned site yet) and the status block's button. Before that, a 404 came from a stale saved URL (`…/pqm-execute`, the v5 path), unrelated to this change. The owner then ran a review on a post: it landed, the round trip works again (reviews had failed since 2026-10-08).
- **After the walk (owner's test):** the status block's button was renamed from **Check again** to **Test again**, and the sentences now end "then test again". The walk showed the page then had two **Check again** buttons doing different things: the n8n Instance section's (re-checks the API key) and the notice's (re-pings). Tests follow; gates re-run green (1,963 harness checks, Jest 99, lint).

## Review Triage Log

Pass 1 (quick lens, 2026-10-15): 6 findings: 0 high, 2 medium, 3 low, 1 false, 0 maybe-false. 3 patches, 3 rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | AC1's HITL walk not recorded; single-GET `{id, name}` unconfirmed | false | reject | Not a code defect: AC1 is the owner's hitl walk, pending by design (ticket `hitl = true`); carried to step 05 as open. |
| 2 | A failed **Check again** shows nothing (`.pqm-test-result` absent, no `status_html` on an error reply) | medium | patch | Verified in `initTestConnection()`: `result` null, `showTestResult()` returns early, `swapWorkflowStatus(undefined)` no-op. Fix: a result span beside Check again; the plan's "no result span" held for a passing ping only. |
| 3 | A passing **Check again** drops keyboard focus to `<body>` | medium | patch | Verified: the button sits inside `.pqm-workflow-status`, whose `outerHTML` the swap replaces. Fix: focus the new block when the old one held focus. |
| 4 | With a setup map, a saved URL pointing at another workflow is diagnosed against the provisioned one | low | reject | Real but needs an owner to paste another workflow's URL after provisioning; spec orders map first; the fix adds a branch. Entry 6.8 (adoption) settles map vs URL. |
| 5 | A mis-wiring right after provisioning shows in a success notice in the dialog | low | reject | Provisioning links every credential by id, so the shapes cannot arise from it; a fix needs `outdated` in the provision reply (a shape change, forbidden) or a React change. |
| 6 | A 2xx without nodes on the id path returns `''` and clears a stored sentence | low | patch | Verified: `answered()` accepts `{}`, `published_nodes()` → `[]`, no trigger, `''`. Direct correction to the matrix ("could not run" → `null`): return `null` without a trigger. |

## Design Notes

Why the published nodes: in n8n 2.x a save changes only the draft; production runs `activeVersion`. An owner who fixes a node without publishing would otherwise be told "clean" while every call keeps failing. The fix sentence says "publish" for that reason.

Draft sentence (the build writes the final copy; every node name listed through `wp_sprintf('%l', $names)`, so WP's own ", " / ", and " separators, stored raw since n8n supplies the names, escaped once at render): *"In n8n, Claude - Review Agent, Claude - Takeaways Agent, Claude - Sources Agent, Claude - SEO Agent, Claude - Audit Agent, and Claude - Rewrite Agent use the webhook credential “Header Auth account” instead of your Anthropic one: give them the Anthropic credential, publish the workflow, then check again."* Missing: *"In n8n, Update WP Post (Rewrite) has no credential: add one, publish the workflow, then check again."*

Skipped: a notice that the check could not run — add when a walk shows owners miss it. Skipped: a trigger with `authentication: none` (the token is then ignored) — the ping passes, out of this ticket's two shapes; name it in the hitl record if met. Skipped: a diagnosis from a command's callback.

## Verification

**Commands:**
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` -- expected: every harness passes, no FAIL line.
- `npm run test:unit` and `npm run lint:js` -- expected: green.
- `php -l` on each touched PHP file, `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: no errors.

**Manual checks (if no CLI):**
- HITL (ticket `hitl = true`): on the environment the owner names, with a dedicated expiring n8n key, assign the webhook credential to the Claude nodes of the owner's production workflow, publish, and run a test ping: the sentence shows. Restore the Anthropic credential, publish, **Check again**: the status is clean. Record it with the environment named, and whether the single-GET read carried `{id, name}`.
- **Setup-map path, 2026-10-16:** the by-id read ran on the owner's Local sandbox after a hand-made mis-wiring of the Review node; the sentence named the node, the credential and the fix, Test again the one primary — so the single-GET read carried `{id, name}` on the node's credential, the open question of the HITL line above; the list path also passed clean on the same site — the entry 6.12 walk of 2026-10-16 on the owner's Local sandbox against their n8n instance, recorded in `story-the-success-signal-walk-passes-and-the-records-say-so-plan.md` § Verification.
