---
title: 'A failed provisioning leaves nothing behind'
type: 'feature'
ticket: '5'
created: '2026-10-14'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '302f56b8fef6df5c19203b9521c94f0e1f0531f6'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md', '{project-root}/_bmad-output/backlog/spike-n8n-provisioning-api-findings.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A refused step of `POST /n8n/provision` (entry 4) stops and leaves its credentials, its workflow and the Pro build's Application Password behind, and records nothing. A retry then duplicates them, and a workflow that n8n activated before the `activate` call timed out keeps the webhook path, so every retry fails at `activate`.

**Approach:** `PQM_N8n::provision()` keeps the ids it created during the run. On any failure after the first object exists, it rolls back in the forced order (deactivate, delete the workflow, then the credentials) and revokes the generated Application Password. The error names the step and says whether anything was left. A failed ping stays as entry 4 built it: everything kept, setup marked incomplete.

## Boundaries & Constraints

**Always:**
- Rollback touches only ids this run (or a recorded earlier run) created. It never looks objects up by name and never deletes anything it did not create.
- The workflow is deactivated before every workflow delete, whatever `activate` answered. Credentials are deleted only after the workflow delete succeeded.
- A 404 on a delete counts as removed (n8n 2.42.3: `404 {"message":"Not Found"}` for an unknown id).
- The Application Password is revoked on every failure after it was created, inside `is__premium_only()` (ADR 0014 markers).
- Errors keep `data.step` and never repeat n8n's reply body or the provider key.

**Never:**
- UI or policy texts (entry 6). Adoption or update (entries 8–9).
- Changes to `provision_n8n_callback()`'s ping flow, `ping_webhook()`, `save()`/`forget()`, the template or `PQM_WORKFLOW_VERSION`.
- Rollback after a failed ping.

**Decisions (owner, 2026-10-14, plan approved as presented):**
- **D1:** what a rollback cannot remove is recorded in a new option `pqm_n8n_leftovers` = `{base_url, workflow_id?, credentials[], at}` (autoload off, deleted on uninstall). The next `provision()` removes it first in the same order; still blocked → it creates nothing and answers 502 step `cleanup`; a record whose `base_url` differs from the connected one is dropped. (Option A of the open question; B, message only, and C, waiting in the request, declined.)
- **D2:** plan length kept (about 2,400 tokens).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| A credential is refused | webhook, Anthropic or WordPress `POST /credentials` non-2xx or WP_Error | DELETE each credential created before it; Pro: Application Password revoked. Zero plugin objects left | 502 `pqm_provision_failed`, `data.step` = `credential_<role>`, message says nothing was left |
| Template names an unknown credential | after the credentials | Same rollback | 500, step `link` |
| Workflow refused | `POST /workflows` fails | Credentials deleted, Application Password revoked | 502, step `workflow` |
| Activation refused | `activate` non-2xx | deactivate → DELETE workflow → DELETE credentials → revoke | 502, step `activate` |
| Activation timed out | `activate` WP_Error (may be active) | Same order: the deactivate is what lets the delete pass | 502, step `activate` |
| Object already gone | a DELETE answers 404 | Counted as removed | — |
| Rollback refused | workflow DELETE 409 (n8n still unpublishing) or n8n unreachable during rollback | Stops at the workflow (no credential deleted); Application Password revoked; `pqm_n8n_leftovers` stores the workflow and every credential id | 502, step of the original failure; message says some objects are still in n8n and the next try removes them first |
| Retry, leftovers removable | record for the connected `base_url` | deactivate → DELETE workflow → DELETE credentials, record deleted, then the normal run | — |
| Retry, leftovers still refused | same, DELETE 409 again | Zero creations, record updated to what remains | 502, step `cleanup` |
| Leftovers of another instance | record `base_url` ≠ connected | Record dropped, normal run | — |
| Ping fails | provisioned, ping non-2xx | Nothing deactivated, deleted or revoked; map stored; `steps.connect` false (entry 4, unchanged) | 200 `ping_ok:false` |
| Free build, activation refused | 2 credentials + workflow | deactivate → delete → 2 credentials; no Application Password call | 502, step `activate` |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `provision()` L312–404: the failure returns at L339 (Application Password refused: nothing exists yet, no rollback), L354 (credential), L365 (`link`), L386 (workflow), L391 (activate) route through one rollback path. `$created` (role ⇒ id) already exists; add the workflow id and the Application Password uuid. `wordpress_credential__premium_only()` L445: `create_new_application_password()` returns `[password, item]`; `item['uuid']` is what core's `WP_Application_Passwords::delete_application_password( $user_id, $uuid )` takes. Keep the uuid out of the credential body sent to n8n. `step_failed()` L485: its closing sentence ("In n8n, remove the Post Quality credentials…") becomes the rollback outcome. Docblocks L13–15 and L305–307 say "rollback is entry 5". Reuse `request()` L140.
- `post-quality-manager/includes/class-pqm-activator.php` -- uninstall `delete_option` list L43–50: add `pqm_n8n_leftovers`, named literally like its neighbours. `tests/harness/caps-harness.php` L466 asserts that exact list.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `provision_n8n_callback()` L1325: no change. A failed ping already keeps everything and sets `steps.connect` false (entry 4 review finding 1).
- `tests/harness/n8n-api-harness.php` -- the "n8n refuses a step" loop (around L533) asserts the opposite of this entry ("created objects stay (no DELETE)", the remove-by-hand sentence) and is rewritten. `WP_Application_Passwords` stub L79 needs `delete_application_password()` recording to `$GLOBALS['tw']`. `connected()`, `PRO_OK`, `created()`, `calls()`, `untouched()` and the leak scan are reused. Rollback answers are queued like any other call.
- `tests/harness/free-build-harness.php` -- the Free provisioning row L336–375 (scripted queue, `$ok` helper): add a Free activation-refused row.
- `memory/n8n-provisioning.md` -- the entry 4 line ("leaves what it created (rollback is entry 5)") and the rollback-order line L19.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- one private rollback over `{workflow_id?, credentials[]}` returning what it could not remove. One failure path used by every step after the first creation: revoke (premium block), roll back, record leftovers (D1), build the step error with the outcome. `LEFTOVERS_OPTION` constant; leftovers removed first after the provider-key check, before anything is created. Docblocks updated.
- [x] `post-quality-manager/includes/class-pqm-activator.php`, `tests/harness/caps-harness.php` -- uninstall deletes `pqm_n8n_leftovers`.
- [x] `tests/harness/n8n-api-harness.php` -- one row per matrix line (Pro). A helper derives what is left on the instance: ids answered by the create POSTs, minus ids deleted with 2xx/404. Assert the order (deactivate < workflow DELETE < every credential DELETE), the Application Password revoked with the created uuid, the leak scan extended to the rollback runs, and the ping row with no DELETE, deactivate or revoke.
- [x] `tests/harness/free-build-harness.php` -- the Free row.
- [x] `memory/n8n-provisioning.md` -- the rollback behaviour, the 404 rule, and the n8n 2.42.3 unpublish finding (Design Notes).

**Acceptance Criteria:**
- Given every forced-failure row, when the run ends, then the harness's instance model holds zero plugin-created credentials and workflows and the error names the step.
- Given the Free build, when it is stripped, then no Application Password symbol remains and the Free rollback row passes.

Skipped: finding an object whose create call timed out before n8n answered (no id): add a lookup by deterministic name when a walk shows such an orphan. Skipped: a machine-readable list of leftovers in the error: add when entry 6's wizard needs it.

## Review Triage Log

Pass 1 (2026-10-14, lens `quick`): 0 high, 2 medium, 1 low, 0 false, 0 maybe-false; 1 rejected (fix edits the plan). Every matrix row covered; both acceptance criteria met on the harness model.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | `removed()` counts any 404 as removed, so a proxy's non-JSON 404 on a DELETE yields "Nothing was left in n8n." with the objects still there | low | patch | The probe never touches the DELETE or deactivate routes; `failure()` already reads a non-JSON answer as a proxy. Unlikely, but the fix is one condition: a 404 counts only with a JSON body. |
| 2 | The `cleanup` error always says "Try again in a moment.", so a refusal that never clears (expired or revoked n8n key) gives no way out | medium | patch | `save()`/`forget()` keep the record; only removal, another base URL or uninstall clears it. Reconnecting with a valid key or deleting the objects by hand does clear it (2xx/404), but the message names neither. Wording fix. |
| 3 | On an unreachable instance the rollback waits 10 s per call and keeps trying every credential DELETE; the leftovers record is written only at the end, so a gateway timeout can lose it | medium | patch | `call()` timeout 10 s; a `workflow` failure + 3 timed-out credential DELETEs ≈ 40 s. Fix: stop at the first WP_Error and record the untried ids. |
| 4 | No forced failure ran against a real n8n, though the epic's risk note names "the forced-failure runs verified through the API (entry 5)"; the harness scripts a 200 DELETE after the timed-out activate where the 2.42.3 source shows a 409 for up to one outbox poll | medium | reject (fix edits the plan) | Real verification gap, raised to the owner at presentation as a walk on their instance; the code handles the 409 through D1. The ticket's `unknown` ("409 … confirmed by the spike") is stale: deferred-work entry. |

## Design Notes

- **n8n 2.42.3 source** (`packages/cli/src/workflows/workflow.service.ts`, `public-api/v1/controllers/workflows.public.controller.ts`, read 2026-10-14):
  - `/activate` and `/deactivate` are deprecated aliases of `/publish` and `/unpublish`. The plugin keeps the aliases for symmetry with entry 4.
  - Deactivating an unpublished workflow returns it with 200, so always deactivating is safe.
  - With `N8N_USE_WORKFLOW_PUBLICATION_SERVICE` (default true), DELETE on a published workflow answers 409 "Cannot delete a published workflow". Right after an unpublish it answers 409 "Workflow is still being unpublished. Please try again in a few moments" until the outbox poll, every 15 s by default, tears the triggers down. The spike's "200 after deactivate" is therefore timing-dependent, hence D1.
- Revoking the Application Password does not depend on n8n: it runs even when the n8n rollback is incomplete, because a leftover WordPress credential is useless once the run has failed.

## Verification

**Commands:**
- `php -l` on every touched PHP file -- expected: no syntax errors
- `for h in tests/harness/*-harness.php; do php "$h" || echo "FAIL $h"; done` -- expected: no FAIL
- `npm run test:unit` -- expected: green (no JS touched)
- **Forced failure on a real instance, 2026-10-16:** a second run with the map deleted hit `activate` 409 (path held), rollback left nothing in n8n, the Application Password revoked, no leftovers — the entry 6.12 walk of 2026-10-16 on the owner's Local sandbox against their n8n instance, recorded in `story-the-success-signal-walk-passes-and-the-records-say-so-plan.md` § Verification.
