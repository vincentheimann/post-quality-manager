---
title: 'The plugin provisions n8n on the server side'
type: 'feature'
ticket: '4'
created: '2026-10-14'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '3f3ef21797e8dd2bbb52d87520e0d3eb8e13ef66'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md', '{project-root}/_bmad-output/backlog/spike-n8n-provisioning-api-findings.md']
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A connected n8n instance (entry 1) still needs the workflow imported, three credentials created and eight nodes linked by hand. The 2026-10-13 mis-wiring on the owner's site shows how that fails without any error.

**Approach:** Add one route that runs the whole setup server-side through `PQM_N8n::request()`: check the Anthropic key, create the credentials under their deterministic names, create the build's own workflow with every credentialed node linked, activate it, save the webhook URL, store the setup map, then ping. There is no UI: the wizard calls it in entry 6, once rollback (entry 5) exists.

## Boundaries & Constraints

**Always:**
- `pqm_manage_settings` gates the route.
- The provider key is used in two places only: the validation request and the credential body. It is never stored in an option, never put in a REST reply, never logged.
- The workflow comes from `pqm_workflow_json()` and the path from `pqm_webhook_path()`; neither is ever recomputed.
- Each build provisions its own template. The Free build creates no WordPress credential.

**Never:**
- Rollback or Application Password revocation (entry 5).
- UI or policy texts (entry 6).
- Adopting or updating an existing setup (entries 8–9).
- A template change or a `PQM_WORKFLOW_VERSION` bump.
- `POST /credentials/{id}/test`.
- Changes to `ping_webhook()`, `sanitize_settings()` or `PQM_N8n::save()`/`forget()`.
- A WordPress self-call to check the generated Application Password (D2).

**Decisions (owner, 2026-10-14):**
- **D1:** a Pro site where Application Passwords are unavailable is provisioned without the WordPress credential. Update WP Post stays unlinked, and the reply says Rewrite stays off until a WordPress credential is added by hand.
- **D2:** the generated Application Password is not checked by a self-call in this entry. Entry 5's failure reporting and entry 12's walk catch a host that strips `Authorization`.
- **D3:** plan length kept (about 2,300 tokens).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Pro, fresh instance | connected; valid key | Calls in order: models GET → 3× `POST /credentials` → `POST /workflows` (8 nodes linked) → activate. URL saved, map stored, ping. 200 `{provisioned:true, ping_ok:true, workflow_id}` | — |
| Free, fresh instance | same, Free build | 2 credentials (webhook, Anthropic); 3 nodes linked; no Application Password | — |
| Pro, no Application Passwords | `wp_is_application_passwords_available_for_user()` false | 2 credentials; 7 nodes linked, Update WP Post left name-only; reply carries `rewrite_ready:false` and a message | — |
| Invalid provider key | Anthropic answers 401 | Zero n8n calls, nothing saved | 400 `pqm_provider_key_invalid` |
| Anthropic unreachable | WP_Error or 5xx | Zero n8n calls | 502 `pqm_provider_unreachable` |
| Not connected | no `pqm_n8n_api` key | Zero calls | `pqm_n8n_not_connected` (entry 1) |
| Already provisioned | `pqm_n8n_setup` exists | Zero calls | 409 `pqm_already_provisioned` |
| n8n refuses a step | non-2xx from any n8n call | Stops; objects already created stay | 502 `pqm_provision_failed`, `data.step` names the step |
| Ping fails | computed URL unreachable (e.g. `WEBHOOK_URL` ≠ editor URL) | Everything kept, map stored | 200 `{provisioned:true, ping_ok:false, message}` pointing to the Webhook URL paste step |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `PQM_N8n`: add `SETUP_OPTION = 'pqm_n8n_setup'` and `provision()`. Reuse `request()` (L131: `/api/v1`, key header, UA, 10 s, redirection 0) and `connection()['base_url']` (L44). The 6.1 plan's rule: "later entries add methods here".
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `register_routes()` L28; add `POST /n8n/provision` next to `/n8n/connection` (L145), using `check_manage_settings`. Copy the save → ping → `pqm_record_workflow_version()` flow from `setup_connect_callback` (L1137–1171). `setup_state()['app_passwords']` (L1057) is the availability check.
- `post-quality-manager/includes/pqm-functions.php` -- `pqm_workflow_json()` L323 (build template, placeholders filled; credential refs are name-only), `pqm_webhook_path()` L313, `pqm_site_host()` L298, `pqm_n8n_headers()`.
- `post-quality-manager/admin/class-pqm-admin.php` -- `save_webhook_url()` L1757 (merge-save, rejects `/webhook-test/`), `ping_webhook()` L1795. Reuse as they are.
- `post-quality-manager/includes/class-pqm-activator.php` -- uninstall `delete_option` list L43–49: add the new option. `tests/harness/caps-harness.php` asserts that exact list.
- `tests/harness/n8n-api-harness.php` -- scripted `wp_remote_request` queue (`$GLOBALS['tw']`), plus `calls()`/`calls_signed()`. It has no `wp_remote_post` stub; copy one from `setup-harness.php`.
- `tests/harness/free-build-harness.php` -- boots the stripped tree (stubs L180–272, routes asserted L295): add the Free run.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `provision(string $provider_key): array|WP_Error`, steps in the matrix order:
  - Validate with `GET https://api.anthropic.com/v1/models?limit=1` (`x-api-key`, `anthropic-version: 2023-06-01`).
  - Pro only, inside an `is__premium_only()` block: create the Application Password for the current user.
  - Create the credentials. Webhook: `httpHeaderAuth` `{name:"X-PQM-Token", value:token}`. Anthropic: `httpHeaderAuth` `{name:"x-api-key", value:key}`. WordPress: `httpBasicAuth` `{user, password}`.
  - Link each node's `credentials[type]` to `{id, name}` by credential name. A template name nothing was created for is a `pqm_provision_failed` error, except the WordPress credential under D1, which stays name-only.
  - Send `{name, nodes, connections, settings}` (no `active`), then activate.
  - Store the setup map `{workflow_id, credentials:{webhook,anthropic,wordpress?}, path, wizard_created:true, at}`, autoload off.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- the route. Required arg `anthropic_key`, sanitized and never echoed. Then: provision → `save_webhook_url(origin.'/webhook/'.pqm_webhook_path())` → ping → record version → reply.
- [x] `post-quality-manager/includes/class-pqm-activator.php`, `tests/harness/caps-harness.php` -- uninstall deletes `pqm_n8n_setup`.
- [x] `tests/harness/n8n-api-harness.php` -- one row per matrix line (Pro), plus a key-leak scan: the provider key appears in no option and no reply; it is present only in the models request and the Anthropic credential body.
- [x] `tests/harness/free-build-harness.php` -- Free happy path: 2 credentials, no `httpBasicAuth`, the 3 Free nodes linked.
- [x] `memory/n8n-provisioning.md` -- the route, the setup map option, the provider-key rule.

**Acceptance Criteria:**
- Given a provisioned site, when `GET /n8n/connection` or `/setup` is read, then the provider key is in neither reply.
- Given the Pro build with Application Passwords available, when the run completes, then every node of the 8 that names a credential carries the created id and no node is left name-only.

## Review Triage Log

Pass 1 (2026-10-14, lens `quick`): 0 high, 4 medium, 1 low, 1 false, 0 maybe-false. No acceptance criterion unmet.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | `provision_n8n_callback` never writes `steps.connect` | medium | patch | `setup_connect_callback` sets `steps.connect = ping ok` (rest-api L1161–1163); without it `GET /setup` reports the connect step undone after a passing provisioning, and a failed ping leaves no incomplete mark (failure-modes "keep everything but mark setup incomplete"). Fix: the same three lines. |
| 2 | Two simultaneous runs both pass the 409 guard | low | reject | Needs a double submit or two admins within seconds; entry 6's busy state blocks the first, the second is rare. The fix is a lock (a guard on undemonstrated state). |
| 3 | A failed run records nothing, so a retry duplicates objects; a timed-out `activate` that n8n honoured makes every retry fail | medium | defer | Real, but the frozen intent leaves rollback to entry 5. Logged in deferred-work.md for entry 5 (deactivate after an `activate` WP_Error, revoke the Application Password). |
| 4 | D1 message blames a setting; on a non-HTTPS site the cause is HTTPS and "add one by hand" cannot work | medium | patch | `wp_is_application_passwords_available_for_user()` is false without HTTPS outside a local environment (rest-api L1066 comment). Reword on the existing admin copy (admin L1664). |
| 5 | `step_failed()` prints the raw step slug and no next step; a 2xx without id reads "HTTP 200" | medium | patch | User-facing copy against docs/brand.md (no jargon; errors with an actionable solution). `data.step` keeps the slug. |
| 6 | `pqm_n8n_not_connected` now carries status 400 | false | reject | No bad outcome: no caller outside `PQM_N8n` surfaces `request()`'s error through REST (grep), and 400 is the right status for a missing connection. |

## Design Notes

- Linking goes by credential name, not by type or node name. Both builds use `httpHeaderAuth` for two different credentials, and the template already holds the deterministic names after `pqm_workflow_json()` fills `__PQM_SITE_HOST__`.
- The setup map gets its own option because `PQM_N8n::save()`/`forget()` rewrite `pqm_n8n_api` whole, and `sanitize_settings()` rebuilds `pqm_settings` from the form.
- Anthropic is called with raw `wp_remote_get`, not the SDK: the plugin ships no Composer dependencies besides Freemius, and this is one unauthenticated-to-WordPress GET. `GET /v1/models` is confirmed in the Claude API reference (no beta header; 401 on a bad key).
- `WEBHOOK_URL` ≠ editor URL (spec open question b): the n8n public API exposes no webhook base (spike), so the URL is computed from the connected origin. A failed ping keeps everything (failure-modes) and points to the existing paste step.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors
- `for h in tests/harness/*-harness.php; do php "$h" || echo "FAIL $h"; done` -- expected: no FAIL
- `npm run test:unit` -- expected: green (no JS touched)
