---
title: 'One click updates the workflow to the bundled template'
type: 'feature'
ticket: '9'
created: '2026-10-16'
status: done
baseline_revision: '576b68b0a85aceabfb02a0267fb2ba7df87108fd'
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

**Problem:** When the plugin ships a newer workflow template, the status block says "Workflow update available" and its one fix is **Re-import the workflow**: download, import in n8n, re-pick every credential, unpublish the old one, publish, paste the URL. On a site the plugin set up or manages, every value that step asks for is already in the setup map. The spec's CAP-5: one click updates the workflow in place, credentials and path preserved, and the ping then reports the new version.

**Approach:** On a managed site (a setup map, provisioned or adopted, a passing n8n API key, the saved webhook URL on the map's path), the update notice's fix becomes **Update workflow**. The plugin reads the workflow once, renders the build's own template onto it (the map's credential ids on every credentialed node, the trigger on the map's `path`, the workflow's current name), PUTs it, pings, records the version and re-renders the status block. n8n auto-republishes a PUT workflow (spike 56 on 2.42.3): no unpublish, no re-import, no new URL.

## Boundaries & Constraints

**Always:** the fix is offered and the route accepted only under the same gate as the token rotation (6.10): a key with a passing probe, a map with a `workflow_id`, the saved URL's segment equal to the map's `path` (6.8 D3); otherwise zero calls and the **Re-import the workflow** fix as today. Two n8n calls at most: `GET /workflows/{id}` (the current credential names and the workflow's name), then `PUT /workflows/{id}` with the four keys provisioning posts (`name`, `nodes`, `connections`, `settings`), never `active`. Credentials are linked by the map's ids per role (trigger → `webhook`, Claude nodes → `anthropic`, the `httpBasicAuth` node → `wordpress`); a role the map lacks leaves that node's credential name-only, as provisioning's D1 and adoption's D2 do (Rewrite stays off, the reply says so on Pro). The trigger's `path` is the map's, not `pqm_webhook_path()` (an adopted workflow keeps its own path, CAP-4). The build's template is the one PUT (`pqm_workflow_json()`: Free on Free, Pro on Pro, ADR 0014). The ping after the PUT is the ordinary saved-URL ping (6.7's diagnosis included, GET only). One primary action in the block, by today's rule. Copy per `docs/brand.md`: the problem in one sentence, then the action.

**Never:** a change to n8n beyond that PUT (no credential write, no activate, no delete); a second page of 250 or a lookup by name; `PQM_WORKFLOW_VERSION` or the templates; a change to the Workflow File steps, the wizard, `GET /setup` or the n8n Instance island; the Settings page; story 67's rebuild. A hand-made setup without a map keeps **Re-import the workflow**.

## I/O & Edge-Case Matrix

`POST /n8n/workflow` (`pqm_manage_settings`, no arguments) through `PQM_N8n::update_workflow()`. Offered = the gate passes (the predicate behind `public_state()['rotatable']`, shared). Notice = the status block's update notice (older version) and, on Pro, the Free-variant notice.

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Offered | Outdated report (or Free variant on Pro), gate passes | The first notice ends with **Update workflow** (`button.button-primary.pqm-update-workflow`, a result line beside it); no `.pqm-reimport` in the block | — |
| Not offered | Outdated, gate fails (no key, failing probe, no map, URL on another path) | **Re-import the workflow** as today | — |
| Up to date | Report current | No notice, no button, as today | — |
| Mis-wired first | A stored mis-wiring sentence and outdated | Test again stays the one primary; Update workflow renders secondary (as Re-import does today) | — |
| Update | Button pressed | `GET /workflows/{id}` then `PUT /workflows/{id}` with the rendered template: every template node's `credentials[type]` = `{id: map id, name: the name that id carries in the read workflow, else ''}` for a role the map has, name-only otherwise; trigger `parameters.path` = map `path`; `name` = the read workflow's name; `settings` and `connections` from the template. Then the saved-URL ping, `pqm_record_workflow_version(…, 'test', …)`, reply `{updated: true, ping_ok, outdated, message}` + `status_html` on a passing ping (+ `rewrite_ready` on Pro) | — |
| Refused at the gate | As the rotation's three refusals | 400 `pqm_n8n_not_connected`; 409 `pqm_workflow_not_managed`; 409 `pqm_webhook_url_changed` | Zero calls, nothing written |
| Workflow gone | JSON 404 on the GET | 502 `pqm_update_failed`: the workflow the plugin manages is no longer in n8n; set it up again or manage the one at your webhook URL | No PUT, nothing written |
| Read refused | Non-2xx, non-JSON 2xx, WP_Error on the GET | 502 `pqm_update_failed`, `stopped('read')`'s sentence + "Try again in a moment." | No PUT |
| PUT refused | Non-2xx, non-JSON 2xx, WP_Error on the PUT | 502 `pqm_update_failed`, `stopped('update')`'s sentence + "Try again in a moment."; the version record untouched (the block still offers the update) | n8n's body never repeated |
| Ping fails after | The saved URL does not answer, or 401/403 | `updated: true, ping_ok: false`, message = "The workflow was updated in n8n." + the ping's cause; no version recorded, no `status_html` | — |
| Rewrite unwired (Pro) | Map without `wordpress` | The Update WP Post node keeps a name-only credential; `rewrite_ready: false` and the message says Rewrite stays off until a WordPress credential is set on that node | — |
| Free build | Same site on Free | PUTs the Free template, the same way | — |
| Leak scan | Every reply and error | The n8n key and the token in no reply; the PUT body carries no secret (credential ids and names only) | — |

## Decisions (owner, 2026-10-16)

Approved as proposed at the plan checkpoint; the full plan kept (~3,800 tokens, one goal). Implementer: Fable 5.1 (the owner's choice for this entry, departing from the 2026-10-15 model split).

- **D1 — the trigger keeps the map's path**, never `pqm_webhook_path()`.
- **D2 — the read before the write:** one GET for the credential names and the workflow's name; the name is kept.
- **D3 — the HITL waits for the next template version bump** (entry 12 or the first bump): harness-only until then.

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `rotation_blocked()` (end of file, 6.10): rename `managed_blocked()`, used by `rotate_token()`, the new `update_workflow()` and `public_state()['rotatable']` (keep that key; add `updatable` = the same predicate so the harness and the block read one name — or read the predicate from PHP only; the build picks the smaller). `provision()` L681–L775: the template decode (`json_decode(pqm_workflow_json())` as objects), the name→id link loop L739–L752 and the four-key POST body L756–L761 are the model; extract the "render the template with these ids" part into one private `linked_template(array $ids_by_role, array $names, string $path): array` both callers use (provisioning passes its created ids and names; the ids there are by name today — keep its behaviour, the harness pins it). `request()` L171; `answered()` L523, `stopped()` L975 (+ `update` step: "updating the workflow"; 2xx without JSON: "n8n did not confirm the update"); `workflow_at()` / `published_nodes()` / `trigger()` / `is_claude()` L441–L497 (node roles by type: reuse `is_claude()` and the `httpBasicAuth` test from `propose_adoption()` L541–L600 to read the current names per role from the GET). The JSON-404 reading of `rotate_token()` (the credential-gone branch) is the model for "workflow gone".
- `post-quality-manager/includes/pqm-functions.php` -- `pqm_workflow_json()` L323: add `?string $path = null` (default `pqm_webhook_path()`), the one place `__PQM_WEBHOOK_PATH__` is filled; `pqm_record_workflow_version()` L380.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- the `/n8n/token` route (L196) and `rotate_n8n_token_callback()` (L1486): add `POST /n8n/workflow` + `update_n8n_workflow_callback()` beside them, the same shape (ping with the default diagnosis, `outdated` in the reply, `status_html` + record on a passing ping, `rewrite_ready` under `is__premium_only()`).
- `post-quality-manager/admin/class-pqm-admin.php` -- `render_workflow_status()` L1438: `$reimport` L1488 becomes the fix chosen by the gate: `<p><button type="button" class="button button-primary pqm-update-workflow">Update workflow</button> <span class="pqm-test-result" aria-live="polite"></span></p>` when `PQM_N8n` says the site is managed, else today's link; the mis-wiring rule (primary only while `$miswired === ''`) unchanged; the version<6 "new production URL" sentence shows only with the re-import fix. The block renders no secret. `wp_localize_script` L183: add `restUrl` (`rest_url(PQM_REST_NAMESPACE . '/')`) and `restNonce` (`wp_create_nonce('wp_rest')`), the first REST call from the page script.
- `post-quality-manager/admin/js/pqm-admin.js` -- the delegated `.pqm-test-connection` click L290–L336 is the model: a delegated `.pqm-update-workflow` click (disable, "Updating the workflow…" in its result line, `fetch(pqmAdmin.restUrl + 'n8n/workflow', {method: 'POST', headers: {'X-WP-Nonce': pqmAdmin.restNonce}, credentials: 'same-origin'})`, a REST error's `message` shown as an error, success → `showTestResult(…, !!ping_ok, message, outdated)` then `swapWorkflowStatus(status_html)`); i18n keys `updating`, `networkError` reused. `node --check` after.
- `tests/harness/n8n-api-harness.php` -- the Entry 10 section (L1248–L1320: `managed()`, `rotate()`, `token_now()`, `refused()`, the leak scan) is the model for an "Entry 9" section: one row per matrix line; `stored_workflow('wf1', wired())` L514 answers the GET (free names via `wired([...])`, `with_path()` for an adopted v5 path); assert the PUT body: four keys only, every credentialed node's `{id, name}`, the trigger's path, the name from the read; `body(1)` reads it; the provisioning rows (L676–L860) must stay green after the extraction.
- `tests/harness/render-matrix-harness.php` L616–L662 -- the update-notice rows: add the managed variants (Update workflow as the one primary; secondary under a mis-wiring sentence; Re-import when the gate fails); `one_primary()`.
- `tests/harness/free-build-harness.php` L434–L476 -- one update row on the Free shape (the Free template PUT, no `wordpress`).
- `tests/js/workflow-status.test.js` -- the page script's status rows: one test for the Update workflow click (fetch mocked: the REST call, the result line, the swap and the `pqm:workflow-status` event).
- `docs/policies/privacy.md` L14, L24 and `docs/policies/readme-external-services.md` L12, L26 -- one clause: when you update the workflow, the plugin reads it and writes the new template onto it with the same key.
- `docs/release-notes/unreleased.md` (the existing "A re-imported workflow has a new production URL" line stays for hand-made sites; one new paragraph), `memory/n8n-provisioning.md` (Entry 9 line; `llm-options-architecture.md`'s handshake line gains "or Update workflow").

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/pqm-functions.php` -- `pqm_workflow_json(?string $path = null)` -- the path becomes a parameter.
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `managed_blocked()`, `linked_template()`, `update_workflow()`, `stopped('update')` -- the read, the render, the PUT; provisioning rerouted through the shared render.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- `POST /n8n/workflow` + callback -- PUT → ping → record → `status_html`.
- [x] `post-quality-manager/admin/class-pqm-admin.php`, `post-quality-manager/admin/js/pqm-admin.js` -- the fix per gate in the status block; the delegated click and the REST call; `restUrl` / `restNonce`.
- [x] `tests/harness/n8n-api-harness.php`, `tests/harness/render-matrix-harness.php`, `tests/harness/free-build-harness.php`, `tests/js/workflow-status.test.js` -- every matrix row; the PUT body asserted; the provisioning rows unchanged.
- [x] `docs/policies/*.md`, `docs/release-notes/unreleased.md`, `memory/*.md` -- the lines named in the Code Map.

**Acceptance Criteria:**
- Given a managed site whose report is older than the bundled template, when the owner presses **Update workflow**, then the status block says the workflow answers with the bundled version, the workflow in n8n is still published on the same production URL, every credentialed node still carries its credential, and a Review goes through (hitl, on the environment the owner names, at the next template version bump: no bump exists today, so the walk is entry 12's or the first bump's).
- Given any run of the harness section, then the n8n calls are one GET then one PUT on the map's workflow id, the PUT body holds the four keys with the map's ids on every credentialed node and the map's path on the trigger, and no reply carries the key or the token.

## Implementation Notes

- Built 2026-10-16 by a Fable 5.1 implementer subagent (the owner's choice for this entry) in a scratchpad worktree, from the plan. Deviations kept at review: `linked_template()` takes the decoded template object (the callers decode: provisioning keeps its "template missing" guard before the provider check, the update decodes with the map's path) instead of the plan's sketched `(ids_by_role, names, path)` signature, so provisioning's by-name linking and its pinned rollback rows stay as they were; `managed_blocked()` (the renamed gate, now public) also refuses a map without `workflow_id`; no new `public_state()` key (the status block asks the gate directly). `credential_names($host)` became the one source of the deterministic names.
- The status block's managed notice says "The plugin can update it in place: your credentials and production URL stay." and never the version<6 "new production URL" sentence.
- A passing update makes three n8n calls (GET, PUT, then the ping's diagnosis GET), as the plan's "ordinary ping" implies; the harness pins it.
- Verified before review: `php -l` and `node --check` clean; every harness green (n8n-api 449, render-matrix 378, free-build 77; 2,145 checks in all); Jest 131; lint and build clean. Re-run by the session with the same counts.
- Review patches (pass 1, rows 1–4) applied by the same implementer: the gate sentence action-neutral; the message shown in the swapped-in block (its result line, appended when none); both template placeholders JSON-escaped; the memory clause corrected. Gates re-run by the session after the patches: every harness green (2,149 checks, n8n-api 450), Jest 132, lint and build clean. No commit (house rule).

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, 2026-10-16): 4 findings: 0 high, 1 medium, 3 low, 0 false, 0 maybe-false. 4 patches, 0 deferred, 0 rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | The shared gate's `pqm_webhook_url_changed` sentence says "so the token stays as it is" and the update route shows it verbatim beside Update workflow | low | patch | Verified: `managed_blocked()` is shared since this entry and the result line prints a REST error's message. Reachable when the URL changes between the page render and the click. Sentence made action-neutral ("so nothing was changed"); the rotation row that pinned it updated. |
| 2 | On a passing ping the reply's message is written into the old block's result line, then the swap discards it: the confirmation, the outdated warning and Pro's "Rewrite stays off" sentence have no surface | medium | patch | Verified in `initUpdateWorkflow()` and the Jest test that locked it. The plan's Code Map prescribed that order (as Test again does), but its matrix puts information in `message`; a plan gap with one reading. Fix: after the swap, the message goes into the new block's result line, appended when the block has none; Jest adjusted plus an outdated case. |
| 3 | The map's path (and the host) are spliced into the template JSON by `str_replace` unescaped; an adopted path carrying `"` or `\` breaks `json_decode()` and the route blames a missing template | low | patch | Verified: `adopt()` stores the trigger's raw path; `webhook_segment()` passes it. Rare, but the fix is one line (JSON-escape both replacement values); a harness row with a `"` in the adopted path. |
| 4 | The rewritten handshake paragraph in `memory/llm-options-architecture.md` still names `.pqm-reimport` as the islands' source of truth; the hook reads `.button-primary` and a managed site renders no `.pqm-reimport` | low | patch | Verified at `use-reimport-shown.js` L20 (stale since entry 7, rewritten here). One clause corrected. |

## Design Notes

Why a GET before the PUT: n8n's node credential reference is `{id, name}`; the map holds ids only, and an adopted workflow's names are the owner's. The read also keeps the workflow's own name and settles "workflow gone" before anything is written. One GET, as the diagnosis already makes on every ping.

Why the map's path and not `pqm_webhook_path()`: an adopted v5 workflow listens on `pqm-execute`; rendering the template with the per-site path would move the trigger and break the saved URL (CAP-4 keeps the owner's path). A provisioned map's `path` equals `pqm_webhook_path()` anyway.

Why REST from the page script: every n8n action is a `/n8n/*` route the harness drives directly; one localized nonce makes the vanilla page script a REST client without `wp.apiFetch`.

Draft copy (the build writes the final): notice, *"Your workflow is version 5; this plugin needs 6."* then **Update workflow**; success, *"Workflow updated. {ping message}"*; failure, *"The n8n update stopped while updating the workflow ({cause}). Try again in a moment."* (the `stopped()` template reads "setup stopped" today: reword that template to "The n8n {step} stopped…"? No: keep `stopped()` and let story 67's copy pass fix the verb, as 6.10 decided.)

Skipped: a dry-run or a diff of what changes — the template is the plugin's. Skipped: updating a hand-made workflow without a map — adopt it first (6.8). Skipped: a `nextCursor` page — the read is by id. Skipped: un-publishing on failure — a refused PUT changes nothing; n8n's PUT is atomic.

## Verification

**Commands:**
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` -- expected: every harness passes, no FAIL line.
- `npm run test:unit` and `npm run lint:js` -- expected: green.
- `php -l` on each touched PHP file, `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: no errors.

**Manual checks (if no CLI):**
- HITL deferred to the next template version bump (entry 12 or the first bump after): on the owner's site with its adopted map, the notice offers **Update workflow**; press it; the status block reports the new version; in n8n the workflow is published, same URL, credentials on every node; a Review goes through. Until then, harness-only, as entry 4 was. Record here.
- **First real PUT, 2026-10-16:** with the stored version set to 5 by hand on the owner's Local sandbox, Update workflow rewrote the workflow in place (ids and path kept, a mis-wired node repaired, version 6 recorded, the workflow active) — the entry 6.12 walk of 2026-10-16 on the owner's Local sandbox against their n8n instance, recorded in `story-the-success-signal-walk-passes-and-the-records-say-so-plan.md` § Verification. The next template bump walks it unforced.
