---
title: 'A failed or removed setup leaves nothing behind in n8n, or says what it left'
type: 'bugfix'
ticket: 112
created: '2026-11-04'
status: 'built'
baseline_revision: '7fd29090b55fe5028d52ab6fa21f907daa781ea6'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/memory/n8n-provisioning.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Bug 112: on a cold n8n the setup's `POST /workflows` outlives the plugin's 10 s timeout. n8n then creates the workflow anyway. The setup says "Nothing was left in n8n.", and a retry adds a second workflow of the same name. Any timed-out create makes the same claim. Bug 114: rollback, Start over and the leftover cleanup send DELETE right after the unpublish. n8n 2.46.6 answers 409 "Workflow is still being unpublished" for about 0.4 s, so the workflow and its credentials stay and the blogger reads that something was left.

**Approach:** The workflow creation waits 30 s; every other call keeps 10 s. A creation that got no answer is looked up once by its exact name and this run's credential ids. When found, it is rolled back with the rest. When not found, a fingerprint is recorded, so the next setup looks again and removes the workflow first, and the message says it may still appear. A workflow DELETE answered 409 is retried, as the ping after activation is retried on 404 (bug 80).

## Boundaries & Constraints

**Always:** owner decision 2026-11-04: bugs 112 and 114 in one build (ticket 114 is covered by this plan), the plan kept whole (~2.9k tokens). The lookup is `GET /workflows?name=<the name sent>&limit=250&excludePinnedData=true`. n8n matches `name` as `LIKE %x%`, so a workflow counts only when its name is exactly the one sent and a node carries one of this run's credential ids, never the name alone: a hand-imported workflow carries the same name. `roll_back()` keeps its contract: only the ids it is given. The DELETE retry is bounded (up to 4 DELETE calls, 1 s apart, filter `postquality_unpublish_retry_wait`, harness 0), and only a 409 is retried. "Nothing was left in n8n." is said only when every create call of the run got an answer, or a timed-out workflow was found and removed, and the rollback removed everything. n8n's reply bodies are never repeated; keys are never printed. External facts go in `memory/n8n-provisioning.md`, and code comments name that file.

**Never:** no retry of a create (n8n 2.46.6 honours an `Idempotency-Key`, but an older n8n that ignores it would create a second workflow); no `GET /credentials` and no credential lookup by name ("Anthropic - Claude" is shared by every site on an instance); no new REST route or argument; no template change; no `set_time_limit`. Skipped: a timed-out credential create is named in the message, not looked up; add when a site reports a duplicate credential. Skipped: Start over overwriting a fingerprint record (it needs a map, which a failed setup never stored); add when it is seen.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Cold creation | `POST /workflows` answers within 30 s | normal setup; the create request carries timeout 30, every other n8n call 10 | — |
| Timed out, found | create WP_Error; the lookup lists the exact name with a node carrying this run's credential id | roll_back(found id, run's credentials): deactivate, DELETE, credentials; no record | "Nothing was left in n8n." |
| Timed out, not found | create WP_Error; no match (or the lookup unanswered) | credentials rolled back; record `{base_url, credentials: [ids left], orphan: {name, credentials: [run's ids]}, at}` | "n8n did not answer in time and may still create the workflow: the next try removes it first." + "Try again in a moment." |
| Next setup, fingerprint | a record with `orphan` | lookup first; found → removed with the record's other ids; not found → `orphan` dropped; then the run | refused removal → today's 502 `cleanup` |
| Same name, not this run's | exact name, no node with the run's ids; or a "… copy" | never touched | — |
| Create refused | HTTP 4xx/5xx answer | no lookup; today's rollback | today's sentence |
| Credential create timed out | WP_Error on `POST /credentials` | no lookup; the others rolled back | ending: "n8n did not answer in time and may still create that credential: delete it in n8n if it appears." |
| Still unpublishing | DELETE 409, then 2xx | removed: rollback null, Start over `removed: true` | — |
| Stays refused | DELETE 409 four times | recorded as today | today's sentences |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `call()` L308, `'timeout' => 10` hard-coded L317 (add a per-call timeout, default 10); `request()` L268 (every n8n call goes through it; 401 → `record_refusal()`); `run()` L918: credentials loop L954–967, `linked_template()` L969 (its `name` is the name sent), create L973–977 (`fail('workflow', …)` without an id today), activate L979–995; `fail()` L1198 (revoke, `roll_back()`, record, endings L1213–1217); `roll_back()` L1234 (deactivate, then one DELETE in one condition L1240: put the bounded DELETE retry here, one place for its three callers); `removed()` L1265, `gone()` L1700; `remove_leftovers()` L1279; `start_over()` L1527 (writes the record L1550); `LEFTOVERS_OPTION` L85; `stopped()`, `label()`, `answer()` L1590–1690.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- the retry precedent `ping_after_activation()` L1658: `PROVISION_PINGS` L1176, `sleep( max( 0, (int) apply_filters( 'postquality_provision_ping_retry_wait', 2 ) ) )` L1665; `GET /setup` `leftovers` L1282 (unchanged).
- `src/setup/steps.js` L343 and `admin/class-pqm-admin.php` L1707 -- "An earlier setup left a workflow or credentials in n8n. Setting up removes them first." (unchanged: still true).
- `tests/harness/lib/n8n-world.php` -- FIFO `wp_remote_request` L90–96; `timed_out()` L406; `UNPUBLISHING` L405; `CLEAN`/`LEFT` L412–413; `rollback_ordered()` L448 (expects exactly one workflow DELETE: allow the retries); the retry filter set to 0 beside L50; keep `n8n_publish_refusal()` L102–125 (bug 113).
- `tests/harness/n8n-provisioning-harness.php` -- "Nothing was left" rows L302, L315, L329; timeouts L284 (credential, expects `CLEAN`: changes), L288; 409 rows L316–320, L333, L380–389; `rollback_ordered` L298, L312, L376; source pin L452–454.
- `tests/harness/n8n-managed-harness.php` -- Start over 409 rows L608–615, L643–652; L654 counts `self::roll_back(` = 3 (update it if the count changes).
- `docs/policies/privacy.md` L14 -- "If that setup fails, the plugin deactivates and removes the workflow and credentials it created…": add the name lookup after a timeout.
- `memory/n8n-provisioning.md` -- L11 (entry 5, "Skipped: an object whose create call timed out"), L20 (2.42.3 unpublish), L24 (bug 112), L25 (bug 114), L36 (rollback order), L46 (Start over).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `call()`/`request()` take an optional timeout (the create passes 30); a private lookup returns the id of the workflow created after a timeout, or null; `run()` uses it when the create got no answer; `fail()` takes the unanswered state and the fingerprint, writes `orphan` and picks the ending; `remove_leftovers()` looks a fingerprint up before `roll_back()`; `roll_back()` retries a 409 DELETE (constant + filter); docblocks name `memory/n8n-provisioning.md` -- the root causes, each in one place
- [x] `tests/harness/lib/n8n-world.php` -- the retry wait filtered to 0; helpers for a lookup answer (a list item with nodes and credential ids); `rollback_ordered()` accepts retried DELETEs -- the matrix rows
- [x] `tests/harness/n8n-provisioning-harness.php`, `n8n-managed-harness.php` -- one row per matrix line, the changed rows rewritten, the create's timeout 30 and the other calls' 10 asserted, no `GET /credentials` and no name-only match anywhere -- AC1–AC3 of 112, AC2 of 114
- [x] `docs/policies/privacy.md` -- the lookup by name after a timed-out creation, reads only -- the policy names every API call
- [x] `docs/release-notes/unreleased.md` -- one paragraph: a setup that times out on a slow or fresh n8n cleans up after itself, and Start over and failed setups remove the workflow on current n8n
- [x] `memory/n8n-provisioning.md` -- the six lines above rewritten; the 2.46.6 unpublish window and the `name` filter as one findings line

**Acceptance Criteria:**
- Given every touched PHP file, `tests/harness/*.php` and `npm run test:unit`, when they run, then `php -l` is clean and 0 failed.
- Given the sprint's walk on a disposable n8n (after bug 113's build), when Start over with removal runs and when a setup is stopped by a timeout, then n8n holds nothing named for the site afterwards, and the messages match the matrix.

## Implementation Notes

- Implemented by the implementer subagent (2026-11-04), from this plan alone. `request()`/`call()` take an optional `int $timeout = 10`; `CREATE_TIMEOUT = 30` and `UNPUBLISH_DELETES = 4` are class constants; the lookup is `orphan_id()` (placed after `created_id()`, outside the adoption and managed harnesses' source-pin regions); `fail()` gained `bool $unanswered` (a credential create without an answer) and `?array $orphan` (the workflow fingerprint), positional (the codebase uses no named arguments). `roll_back()` still has three callers (`n8n-managed-harness.php` L654 unchanged).
- Endings, where the matrix leaves a combination open: an orphan recorded beside credential ids the rollback could not remove says only the orphan sentence ("… the next try removes it first."; the record holds both, and the wizard's leftovers notice shows). A credential create without an answer whose rollback also left ids says "Some of what it created is still in n8n: the next try removes it first." then the credential sentence; "Nothing was left in n8n." is never said in either case.
- Review fix (coordinator, 2026-11-04): `orphan_id()` answers the id, `false` when n8n listed no match, `null` when the lookup got no answer (a WP_Error, a 401, a 5xx). `run()` records the fingerprint in both non-id cases; at the next setup only an answered list without a match drops it, and an unanswered lookup keeps the record unchanged and stops with the 502 `cleanup`, nothing created (rows: the lookup timed out, answered 401). The in-plugin texts follow `docs/policies/readme-external-services.md`: `readme.txt` § External services and `PostQuality_Admin::privacy_policy_content()` name the read. Ticket 114 got a stub plan pointing here.
- Harness: rows per matrix line in `n8n-provisioning-harness.php` (cold creation; timed out found / not found ×4 variants + ids left + n8n quiet; the next setup's lookup found, not found, refused, another instance, one row fed the record a real run wrote; create refused rows assert no lookup; the credential timeouts' ending; 409 then 2xx ×3 including the published 409; 409 ×4 rows; a 500 and a WP_Error on the DELETE not retried) and `n8n-managed-harness.php` (Start over 409 then 2xx → `removed: true`; the three 409 rows now 4 DELETE calls). Cross-run checks: every create 30 s, every other call 10 s, no `GET /credentials`, no second `POST /workflows`; source pins on the lookup and the one `sleep()`. Mutation check (each reverted, the class restored byte-identical): name-only match, ids-only match, no retry, a 10 s create, no lookup at the next setup, retry on any code — each failed rows.
- Orchestrator (2026-11-04): the implementer subagent and its patch round ran on Opus 5.5 (`memory/model-split`); the session ran from the workspace root, the workflow rendered by hand. Built on a local branch stacked on bug 113's 7fd2909, kept unpushed until #240 merged (no force push), then rebased onto main. Review pass 1 patch round (same implementer): `orphan_id()` three-way (id / false / null) with the next setup keeping an unanswered fingerprint; `readme.txt` § External services and `privacy_policy_content()` name the lookup (9,324 bytes); ticket 114's stub plan; memory L24/L25 say harness-proven, walk pending. Re-run: every harness 0 failed (provisioning 216, managed 206), `npm run test:unit` 174 passed, `php -l` clean, LF only.

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-11-04, Quick lens on Opus 5.5; diff since 7fd2909):** high 0 · medium 1 · low 6 · false 1 · maybe-false 0.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | At the next setup, a fingerprint lookup without an answer (timeout, 401, 5xx) is taken as "no match": the record is deleted and a duplicate workflow can follow | medium | patch | Real: `orphan_id()` returned null for `! answered()` and for no match; `remove_leftovers()` then rolled back `(null, [])` and deleted the record. Patched: three-way result; an unanswered lookup keeps the record and stops with today's 502 `cleanup` (two new rows: timed out, 401). The sub-claim that an answered "no match" right after a 30 s timeout proves nothing is the approved matrix row ("not found → orphan dropped"). |
| 2 | A reverse proxy's non-JSON 5xx on the create still ends "Nothing was left in n8n." while n8n may keep creating | low | defer | Pre-existing, and the frozen matrix row "Create refused" keeps an HTTP answer on today's path. Needs a proxy timing out under 30 s (nginx 60 s, Cloudflare 100 s fire after the plugin). Entry in `deferred-work.md`. |
| 3 | A connection refused, DNS or TLS failure reads "n8n did not answer in time and may still create…" (n8n never got the request) | low | reject | Real wording gap in an uncommon case (n8n down between the connection check and the setup; the cause sentence names the cURL error first). Telling a timeout from a refused connection means a branch on the transport's error text, which differs by transport. |
| 4 | `CREATE_TIMEOUT = 30` can meet a wall-clock `max_execution_time` (Windows) before `fail()` runs | low | reject | On Linux, PHP does not count the HTTP wait toward `max_execution_time`; the worst case was already about 160 s of 10 s calls; `set_time_limit` is excluded by the frozen Never. |
| 5 | `readme.txt` § External services and `privacy_policy_content()` do not name the new read (`docs/policies/readme-external-services.md`: all three stay in step) | low | patch | Direct correction; patched in both. |
| 6 | Ticket 114 has no plan record, so `tickets.py` keeps it in backlog | low | patch | A stub plan for 114 follows this plan's status; `tickets.py find` reads it as `built`. |
| 7 | Plan AC2 (the walk) unmet; memory says "fixed" before it | false (AC) / low (wording) | reject / patch | The AC schedules the walk in the sprint's sitting; the plan stays `built` until it passes. The memory wording now says harness-proven, walk pending. |

## Design Notes

n8n 2.46.6 source (2026-11-04): the DELETE 409 "still being unpublished" reads the published-version mapping row, which the outbox consumer removes after trigger teardown. Unpublish wakes the consumer at once (`requestDrain()`), so the window is normally under a second, bounded by the 15 s poll (`N8N_WORKFLOW_PUBLICATION_OUTBOX_POLL_INTERVAL_MS`) when the wake misses. No public field tells a client that teardown is done: `active` and `activeVersionId` are already cleared. A never-published workflow deletes at once; a published one answers a different 409 ("Cannot delete a published workflow"), which the bounded retry also covers before recording it. `POST /workflows` loads each node's class from disk on first use (the cold cost) and completes even after the client has gone. `GET /workflows?name=` is `LIKE %name%` (no escaping), sorted by id, at most 250 per page, and each item carries its nodes. The stored node keeps `{id, name}` for a credential id that exists; after that credential is deleted the stale id stays.

Probe on the disposable n8n 2.46.6 (2026-11-04, 6 tries, one of them after 20 s published): DELETE passed on the second call, 0.38–0.48 s after the deactivate. A GET shows `active: false` and no `activeVersionId` before and after. The `name` filter returned the exact name and a "… copy". Each item carried nodes with credential ids.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed
- `npm run test:unit` -- expected: green
- `php -l` on each touched PHP file -- expected: no syntax errors

**Manual checks (if no CLI):**
- The sprint walk (`docs/local-verification.md` "A disposable n8n"): Start over with removal → n8n lists no workflow or credential for the site; the timeout path through the harness only, unless a fresh n8n reproduces the slow first create.
