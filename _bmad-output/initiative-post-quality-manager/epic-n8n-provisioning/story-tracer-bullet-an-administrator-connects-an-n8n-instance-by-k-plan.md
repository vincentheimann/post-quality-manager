---
title: 'Tracer bullet: an Administrator connects an n8n instance by key'
type: 'feature'
ticket: '1'
created: '2026-10-13'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '3cfaf8ad6ff29f77c38a0c5c91328bec2f628a8c'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md', '{project-root}/_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md', '{project-root}/_bmad-output/backlog/spike-n8n-provisioning-api-findings.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The plugin cannot talk to the n8n public API, so every later provisioning story has no client, no stored key and no way to tell an owner why their instance cannot be provisioned.

**Approach:** An n8n API client (plugin User-Agent, `X-N8N-API-KEY`, a scriptable transport for harnesses) and an "n8n Instance" section on the n8n Workflow page: base URL plus a write-only key, a probe that shows "connected, version X" or names the cause and points to the manual path, and Forget. Built as a React island behind REST routes so entry 6's wizard reuses it unchanged.

## Boundaries & Constraints

**Always:** `pqm_manage_settings` on every route; the key never leaves the server (no REST reply, no page source, no log); the key is saved only after a passing probe, a failed probe leaves the stored URL and key as they were; base URL refused before any call unless HTTPS or `localhost` / `127.0.0.1` / `::1`; every n8n call sends the plugin User-Agent; own option `pqm_n8n_api` (autoload off, deleted on uninstall), never `pqm_settings`; ships in both builds (provisioning is free); `docs/policies/privacy.md` and `readme-external-services.md` state the stored key, the probe calls and the dedicated-expiring-key advice.

**Never:** create, change or delete anything on n8n; touch the setup wizard steps (entry 6) or the Workflow File section (entry 2); a Settings-form field for the key; encrypting the key at rest (ADR 0015 accepted the blast radius); a probe on page load.

**Decisions (owner, 2026-10-13, best-practice answers):**
- D1 Plan size: keep the full plan (one goal, one lane; the table is the spec's CAP-1 failure modes).
- D2 Missing endpoint: route probe with ids that cannot exist, `POST /workflows/<unknown>/activate` and `PATCH /credentials/<unknown>`. Exists = a JSON answer in n8n's error shape (`message` field), 404 or 400. Missing = 405, or a 404 without that JSON shape. A non-JSON answer is classed as proxy or firewall, never as a missing endpoint. The "exists" shape is confirmed by the owner's probe on 2.42.3 before the harness fixes it.
- D3 Privacy page: this story updates `docs/policies/` only; the `postquality.com` copy is synced in entry 6, one site PR for both changes.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Connected | HTTPS URL, valid key, `GET /api/v1/workflows?limit=1` 200 JSON | URL + key + probe stored; "Connected to n8n <version>", key "set" | — |
| Version unreadable | `/rest/settings` not JSON or no `versionCli` | Connected, "version unknown" | Not a failure |
| Insecure URL | `http://n8n.example` | Refused, zero HTTP calls, nothing stored | Cause: HTTPS required |
| Key refused | 401 | Cause names the key, points to n8n Settings → n8n API | Stored key and URL unchanged |
| API disabled | 404 on `/api/v1/workflows` | Cause: public API disabled (or wrong address) + manual path | Stored key unchanged |
| WAF / proxy | 403, or a non-JSON body, or Cloudflare 1010 | Cause: a proxy or firewall in front of n8n + manual path | Stored key unchanged |
| Unreachable | WP_Error (DNS, TLS, timeout) | Cause carries the transport message | Stored key unchanged |
| Missing endpoint | Route probe (D2) answers 405 or a 404 without n8n's JSON shape | Cause: this n8n version lacks an endpoint, version hint + manual path | Stored key unchanged |
| Re-check | POST without key, key stored | Probe re-runs with the stored key | As above |
| Forget | DELETE | Key and probe removed, URL kept; "not set" | — |
| No right | user without `pqm_manage_settings` | 403 on every route | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` (new) -- `PQM_N8n`: option read/write, `request($method, $path, $body = null)` over `wp_remote_request()`, `probe($url, $key)` returning `{ok, cause, message, version, at}`. Static class like the other `PQM_*`. Later entries add methods here.
- `post-quality-manager/post-quality-manager.php:100-107` -- `require_once` the new class beside the others.
- `post-quality-manager/includes/class-pqm-rest-api.php:27` `register_routes()` -- `GET|POST|DELETE /n8n/connection`, `check_manage_settings` (L156). Reply: `{base_url, key_set, probe}`, never the key.
- `post-quality-manager/includes/class-pqm-activator.php:37-48` `uninstall()` -- `delete_option( 'pqm_n8n_api' )` named literally (harnesses load the class alone).
- `post-quality-manager/admin/class-pqm-admin.php:1356` `render_workflow()` -- `render_app_root('pqm-n8n-connection-root')` under the workflow status, before "Workflow File".
- `src/index.js` -- mount the island in `pages`. `src/n8n-connection/index.js` (new) -- the section: `TextControl` URL, password-type key field, Connect / Check again / Forget, `Notice` per cause; `@wordpress/components` + `apiFetch` (reuse the `src/setup/` patterns).
- `tests/harness/setup-harness.php:23-112` -- the stub pattern to copy; `:502-515` the uninstall row to extend.
- `tests/js/setup-connect.test.js` -- the jsdom + mocked components pattern for the new test.
- `docs/policies/privacy.md`, `docs/policies/readme-external-services.md` -- the n8n-key and API-call sentences.
- Do not change: `PQM_Admin::ping_webhook()`, `pqm_settings`, `sanitize_settings()`, `src/setup/steps.js`.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- client, option helpers, URL rule, probe with causes -- the boundary every later entry calls
- [x] `post-quality-manager/post-quality-manager.php` -- require the class
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- the three routes
- [x] `post-quality-manager/includes/class-pqm-activator.php` -- uninstall line
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- island root on the page
- [x] `src/n8n-connection/index.js`, `src/index.js` -- the section and its mount
- [x] `tests/harness/n8n-api-harness.php` (new) -- every matrix row through a scripted `wp_remote_request()` queue; every call asserts the User-Agent and the key header; the key absent from every reply; routes' permission
- [x] `tests/harness/setup-harness.php` -- uninstall row for `pqm_n8n_api`
- [x] `tests/js/n8n-connection.test.js` (new) -- connected, cause, Forget states; the key field is never prefilled
- [x] `docs/policies/privacy.md`, `docs/policies/readme-external-services.md` -- stored key, probe calls, dedicated key with an expiration date

**Acceptance Criteria:**
- Given a reload after connecting, the page shows the stored state without calling n8n.
- Given any route reply or the page source, the key string never appears.
- Given the Free build, the section and routes work as on Pro.

## Implementation Notes

- Built 2026-10-13 by the implementation subagent; verified on the orchestrator side: `php -l` clean, 16 harnesses green (`n8n-api-harness.php` 100 checks), Jest 44/44, `lint:js` clean, build compiles.
- Added beyond the plan: `redirection => 0` on every n8n call (a redirect would carry the key header to another address); a POST without a key re-uses the stored key for the stored address only, a new address without a key answers 400 with no call; the DELETE route uses the literal `'DELETE'` so five harness stubs need no `WP_REST_Server::DELETABLE`.
- Touched beyond the Code Map: `tests/harness/caps-harness.php` (its exact uninstall-option list), `tests/harness/free-build-harness.php` (proves the Free-build criterion), `memory/n8n-provisioning.md`.
- Statuses outside the matrix (500 JSON, 401 on a route probe) map to cause `unexpected`, still offering the manual path. A failed probe is not stored: a reload shows the last passing one (entry 2 may want the failed cause stored).

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens): 6 findings — medium 2, low 2, false 0, maybe-false 1, rejected low 1.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | `missing_endpoint` message tells an instance at or above 2.42.3 to update to 2.42.3 | medium | patch | Real: `failure()` never compares the version; the harness row asserts "n8n 2.42.3 lacks … Update n8n to 2.42.3 or later". Patch: drop the update advice when version ≥ tested. |
| 2 | Route probe sends no body or Content-Type; n8n's OpenAPI validator may answer 415, read as `unexpected` on a healthy instance | maybe-false (high if true) | defer | Cannot be settled without n8n 2.42.3: needs the real answer of `PATCH /credentials/<unknown>` without a body. The owner's HITL probe settles it; deferred-work entry written. |
| 3 | An unreachable host waits through two 10 s timeouts | low | reject | Real (two calls when `/rest/settings` already failed), but only on a firewall that drops packets (DNS failures return at once), and the fix adds a branch to `version()`'s contract. |
| 4 | `readme-external-services.md`: Forget key said to remove the address; the version read called "public API" | medium | patch | Real: `forget()` keeps `base_url`; the version is read from `/rest/settings`, the editor's endpoint. Policy text must be exact (Always constraint). |
| 5 | `memory/MEMORY.md` index line still says "not built" | low | patch | Real: the index is read at session start and contradicts `n8n-provisioning.md`; direct one-line correction. |
| 6 | `AGENTS.md` managed context block names one island on the n8n Workflow page and omits `src/n8n-connection/` | low | defer | Real drift; the fix edits an agent-context file (managed by `bmad-project-context`), so it is deferred. |

Patches 1, 4 and 5 applied by the implementation subagent; verification re-run on the orchestrator side: `php -l` clean, 16 harnesses green (`n8n-api-harness.php` 101), Jest 44/44, `lint:js` clean, build compiles. Findings 2 and 6 written to `deferred-work.md`.

## Design Notes

- React island, not PHP + AJAX: entry 6 mounts the same component in the wizard; the REST routes are the contract both use.
- Base URL = origin (scheme, host, port); a pasted editor path like `/home/workflows` is dropped. Skipped: n8n served under a sub-path (`N8N_PATH`) -- add when an owner needs it.
- Probe order: URL rule, then `GET <origin>/rest/settings` (no key, version only, any failure ignored), then `GET /api/v1/workflows?limit=1` with the key, then the route probe (D2).
- User-Agent: `PostQualityManager/<PQM_VERSION> (+https://postquality.com)`.
- Unverified until the owner's probe: the `/rest/settings` version read and the D2 "exists" shape. The harness scripts both as assumed; the HITL step corrects the harness if 2.42.3 answers otherwise.

## Verification

**Commands:**
- `php -l` on every touched PHP file -- no syntax errors
- `for f in tests/harness/*.php; do php "$f" || exit 1; done` -- every harness passes
- `npm run test:unit` and `npm run lint:js` -- pass
- `npm run build` -- builds

**Manual checks (if no CLI):**
- HITL, on the environment the owner names: their instance shows as connected with its version and the key as set; Forget returns "not set". Key from the session scratchpad, never the repo.
