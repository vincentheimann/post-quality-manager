---
title: "Provisioning small fixes: one run at a time, a tracked Application Password, the held-path and early-ping sentences (bugs 72, 73, 75, 80)"
type: 'bugfix'
ticket: '72'
created: '2026-10-20'
status: done
baseline_revision: 'b9d4f2801e1f9f0c308ee9fe30ca09475815135a'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/memory/n8n-provisioning.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Four defects of `PQM_N8n::provision()` and its REST callback, all filed from the n8n provisioning retrospective and story 67's walk: two runs started together both create a workflow and three credentials (the first set is orphaned with the Anthropic key inside, bug 72); the Application Password a Pro run creates is never revoked and the wizard does not say whose it is (bug 73); an activation refused with 409 because another workflow holds this site's webhook path says "Try again in a moment", which a retry repeats (bug 75); the ping right after activation meets n8n's registration window, answers 404 and sends the user to check a URL and an activation that are both right (bug 80).

**Approach:** One build over the same code and harness, decided by the owner on 2026-10-16 and 2026-10-19 ("72/73/75 as one build, fold 80 in"). A lock claimed atomically before the first n8n call and released on every exit; the password's uuid and user kept in the setup map, revoked by one helper from uninstall (and from story 71's Start over later); the 409-on-activate cause named with its two ways out; the post-provisioning ping retried twice over n8n's publication window, with an honest sentence when it still answers 404.

**Decisions taken at planning (owner to confirm at the checkpoint):**
- D1 (72): the claim is its own option `pqm_n8n_provisioning` (`PQM_N8n::LOCK_OPTION`, `add_option()`, autoload off), not a placeholder setup map: every reader of the map (`workflow_page_state()`, `public_state()`, `managed_blocked()`, GET /setup) stays truthful during the run. A refused claim answers the existing 409 `pqm_already_provisioned`. A lock older than 5 minutes is a run PHP killed mid-way and is taken over (comment names the ceiling).
- D2 (73): a later provisioning never reuses the password — WordPress stores only its hash, so the value cannot go into a new n8n credential. Revocation rides with the map's removal: uninstall now, story 71's Start over when it builds, both through `PQM_N8n::revoke_setup_password__premium_only()`. With a map, provisioning is refused, so "replaced by a later provisioning" has no path today; the helper is what that path will call.
- D3 (80): n8n master (`workflow.service.ts`, read 2026-10-20) publishes through an outbox when `useWorkflowPublicationService` is on (default): the activate reply returns before the consumer registers the trigger, and `requestDrain()` wakes the leader at once, so the window is usually under a second and at most one poll cycle. Retry figures: up to 3 pings, 2 s apart, 404 only; the wait behind filter `pqm_provision_ping_retry_wait` (the harness sets 0). Finding recorded in `memory/n8n-provisioning.md`.

## Boundaries & Constraints

**Always:** the rollback order and the leftovers record stay as entry 5 built them; `data.step` stays `activate` for every activation refusal; the provider key, the Application Password and its uuid are in no reply, no ping and no log; Pro code behind Freemius markers (`*__premium_only` functions, `if ( pqm_fs()->is__premium_only() ) { … }` callers, nothing else in the condition); the rotation and update pings keep one ping; copy per `docs/brand.md`; every touched harness row keeps its count honest (extend `n8n-api-harness.php`, `caps-harness.php`, `tests/js/setup-provision.test.js`).

**Never:** a retry inside `PQM_Admin::ping_webhook()` or `sent_ping()` (the Test button, rotation and update ping once); a lookup of n8n objects by name; a Start over action or an other-instance gate (story 71); a REST route or payload change for the n8n workflow template; a WordPress self-call to validate the password (entry 4, D2); revoking a password the map does not name; `window.confirm()` or any new dialog in the wizard.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| 72 two runs | connected, no map; run B starts while run A is on its first POST /credentials (queue closure) | B: 409 `pqm_already_provisioned`, zero n8n calls, nothing written; A finishes; one workflow + 3 credentials; lock gone, map = A's | — |
| 72 failed run | A refused at any step, rolled back | lock option absent after the error; the next run is not refused | `pqm_provision_failed` as today |
| 72 stale lock | lock `{at: now − 6 min}`, no map | the run takes the lock over and proceeds | — |
| 72 early exits | key empty / refused / Anthropic unreachable / not connected / template missing | lock absent afterwards; the existing errors unchanged | as today |
| 73 map | Pro run passes, Application Passwords on | map gains `application_password: {uuid, user_id}`; GET /n8n/connection, GET /setup and the provision reply carry neither the uuid nor the password | — |
| 73 map, no passwords | Pro, `wp_is_application_passwords_available_for_user()` false | map without `application_password`, as today | — |
| 73 revoke | map with `application_password` | `revoke_setup_password__premium_only()` deletes `[user_id, uuid]`; a map without the key, or no map: no call | — |
| 73 uninstall | `PQM_Activator::uninstall()` on a map with a password | the password revoked before `pqm_n8n_setup` is deleted; every option still deleted, `pqm_n8n_provisioning` included | — |
| 73 failed run | rollback | the run's password revoked with the stored user id, as today (one helper) | — |
| 73 wizard | GET /setup on the Pro build | `application_password_user` = the current user's display name; absent on Free; the Set up the workflow step says n8n saves rewrites as that user through an Application Password made for it, revoked with the setup | — |
| 75 held path | activate answers 409 | 502 step `activate`; message: the setup stopped while activating the workflow because another active workflow on this n8n instance already listens on this site's webhook path · Nothing was left in n8n. · if that workflow is this site's, paste its production URL as the n8n Webhook URL and choose Let the plugin manage this workflow on the n8n Workflow page; otherwise remove it in n8n, then try again — no "Try again in a moment"; rollback as today | `pqm_provision_failed` |
| 75 other refusals | activate answers 400 / times out | the sentence of today (existing rows) | as today |
| 80 early 404 | ping 404 then 200 | 2 pings; reply `ping_ok` true, "Ping OK"; version recorded, no `failed`; `steps.connect` true; status_html `set-up` | — |
| 80 real 404 | ping 404 ×3 | 3 pings, waits 2 s each (0 in the harness); `ping_ok` false; message: set up and active, but its webhook did not answer yet (404) → Test again on the n8n Workflow page in a moment first → the editor-address hint second; `failed.message` = that sentence; `steps.connect` false; status_html `failing`; nothing rolled back | — |
| 80 other failure | ping 401 / 500 / WP_Error | 1 ping, today's generic sentence | — |
| 80 other pings | Test, POST /setup/connect, rotation, update | one ping each, sentences unchanged (existing rows) | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `provision()` L705 (gate L706, final `update_option()` L760); `fail()` L930 (revoke first, then rollback, then the three-part message with "Try again in a moment."); `wordpress_credential__premium_only()` L878 (`uuid` on the credential array); `revoke_application_password__premium_only()` L900 (uses `wp_get_current_user()`); `stopped()` L1031; `already_provisioned()` L682; options `SETUP_OPTION`, `LEFTOVERS_OPTION` near the top. Reuse `gone()`/`answered()`; `adopt()` L627 is the `add_option()` precedent.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `provision_n8n_callback()` L1408 (the single ping L1415–1418, the failed-ping sentence L1428–1431, `pqm_record_workflow_version` on pass); `setup_state()` L1070–1125 (`$state` keys; `is_pro` is the license, not the build). Don't touch `rotate_n8n_token_callback()` / `update_n8n_workflow_callback()`.
- `post-quality-manager/admin/class-pqm-admin.php` -- `sent_ping()` L2130–2185: add `'code' => $code` to the three non-2xx returns so the callback can tell a 404 apart; nothing else.
- `post-quality-manager/includes/class-pqm-activator.php` -- `uninstall()` L36–63: the Pro-only revoke before `delete_option( 'pqm_n8n_setup' )`, plus `delete_option( 'pqm_n8n_provisioning' )` (named literally, like the others).
- `src/setup/steps.js` -- `Provision` body copy L320–326; `state` comes from GET /setup. `tests/js/setup-provision.test.js` L380–450 for the shape of a test.
- `tests/harness/n8n-api-harness.php` -- stubs L80–135 (`apply_filters` L104 returns the value: make it read `$GLOBALS['tw']['filters'][$tag] ?? $value`; `add_option` L74; `WP_Application_Passwords` L113); `connected()` L540, `provision()` L548, `untouched()` L563, `revoked()` L734 (`[7, 'app-uuid']`), `plain()` L744, `nothing_saved()` L739; Entry 4 rows L560–680 (the "already provisioned" row L675), Entry 5 rollback rows L750–800 (`activate (refused)` 400 row stays), "the ping fails" row L857–869 (rewrite for the new sentence and the 3 pings), leak scan L871–878 (add the uuid). Queue answers may be closures (L99): run B inside run A's first credential answer.
- `tests/harness/caps-harness.php` -- requires L78–81 (add `class-pqm-n8n.php`), the uninstall row L440–465 (`tw_option_deletes` list asserted literally: add the lock option; add a `WP_Application_Passwords` stub + a map with a password and assert the revoke).
- `tests/harness/free-build-harness.php` -- L534 asserts the Free n8n client names no Application Password symbol; the state key check L321–323 (a Pro-only key must be absent on Free).
- `docs/release-notes/unreleased.md` -- one line under "Upgrading from an earlier version".
- `memory/n8n-provisioning.md` -- findings lines (L19–20 are the form): add the publication-outbox finding, dated, "n8n master read 2026-10-20".

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- add `LOCK_OPTION`; wrap `provision()`: claim (`add_option`; a lock older than 300 s overwritten), run the existing body as a private method, `delete_option( LOCK_OPTION )` on every WP_Error and after the map is stored; a refused claim → `already_provisioned()` -- bug 72 (D1)
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `wordpress_credential__premium_only()` also keeps `user_id`; `provision()` stores `application_password => [uuid, user_id]` in the map when the credential carries a uuid; `revoke_application_password__premium_only( int $user_id, string $uuid )` is the one deleter, `fail()` calls it with the run's values; new public `revoke_setup_password__premium_only(): void` reads the map and calls it -- bug 73 (D2)
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- in `provision()`, an activate answer of 409 passes its own cause sentence and `fail()` takes an optional `$then` replacing "Try again in a moment." (the `link` case unchanged) -- bug 75
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- `sent_ping()` returns `code` on the non-2xx branches -- bug 80
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- `provision_n8n_callback()` pings through a private `ping_after_activation( $url )` (3 attempts on 404, `sleep( apply_filters( 'pqm_provision_ping_retry_wait', 2 ) )` between); after the retries a 404 gets the new sentence and `pqm_record_workflow_failure()` with it; `setup_state()` adds `application_password_user` inside a Pro block -- bugs 80, 73
- [x] `post-quality-manager/includes/class-pqm-activator.php` -- uninstall revokes inside `if ( pqm_fs()->is__premium_only() ) { PQM_N8n::revoke_setup_password__premium_only(); }`, deletes the lock option -- bug 73
- [x] `src/setup/steps.js` -- the Pro sentence under the key field when `state.application_password_user` is set; `tests/js/setup-provision.test.js` one test (shown on Pro, absent without the key) -- bug 73 AC3
- [x] `tests/harness/n8n-api-harness.php` -- rows for every matrix line above, the filter-aware `apply_filters` stub, the rewritten "ping fails" row, the uuid in the leak scan; `tests/harness/caps-harness.php` -- the uninstall revoke row and the lock option in the deletes list -- all four
- [x] `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md` -- the upgrade line (uninstall now revokes the password; a site set up before this version removes it on the profile by hand) and the outbox finding -- DoD

**Acceptance Criteria:**
- Given a connected site without a map, when two provisioning requests overlap, then n8n ends with one workflow and three credentials and the second request is refused before its first n8n call.
- Given a Pro site provisioned by this build, when the plugin is uninstalled, then the user's profile no longer lists the "Post Quality Manager (n8n)" password.
- Given an activation refused with 409, when the wizard shows the error, then it names the held webhook path and the two ways out and does not say "Try again in a moment".
- Given a provisioning whose first ping answers 404 and second 200, when the wizard closes, then the page is in the set-up state with no failure on record.

## Implementation Notes

- 2026-10-20, planning: the session runs from the workspace root, so the `bmad-build` skill was rendered by hand (`render_skill.py`, route `full`) and its step files followed verbatim (workspace CLAUDE.md rule). The branch guard asked for a branch name with the owner away; `fix/n8n-provisioning-small-fixes` was derived from the intent and created from `main` at the baseline revision — rename at the checkpoint if wanted. The multi-goal gate: four tickets, kept as one build on the owner's standing decision (2026-10-16 agent lane, 2026-10-19 handoff).

- 2026-10-20, build (implementer subagent, Fable 5.1, standing in for the skill's dispatch): all nine execution tasks done on `fix/n8n-provisioning-small-fixes`, nothing committed. Harness counts: n8n-api 451 → 473, caps 282 → 284, setup 138 → 139, free-build 81 → 83; JS 124 tests green (one new). Code: `PQM_N8n::LOCK_OPTION` + `LOCK_TTL` (300), `claim()` and `run()` split out of `provision()`; the claim sits after the two cheap gates (map, connection) and before the template read, the Anthropic check and the leftovers cleanup, so a refused second run makes zero outgoing calls at all (D1 says "before the first n8n call"; placing it before the Anthropic call too costs nothing and keeps run B silent). `fail()` takes `?string $then`; the 409 activation passes the two sentences from Design Notes verbatim. `revoke_application_password__premium_only( int $user_id, string $uuid )` is the one deleter; `fail()` calls it with the run's `user_id` (kept on the credential array beside `uuid`), `revoke_setup_password__premium_only()` with the map's. `PQM_REST_API::ping_after_activation()` (private, `PROVISION_PINGS` = 3, `sleep( max( 0, (int) apply_filters( 'pqm_provision_ping_retry_wait', 2 ) ) )`) and `ping_failed_sentence()`; the 404-after-retries sentence is also what `pqm_record_workflow_failure()` stores. `PQM_Admin::sent_ping()` returns `code` on its three non-2xx branches only.
- 2026-10-20, build, one refinement inside the matrix's spirit: `application_password_user` is the display name only while `wp_is_application_passwords_available_for_user()` is true and `''` otherwise, so the wizard never promises an Application Password on a site that offers none (D1 of entry 4). The matrix row ("Application Passwords on" context) holds; the harness asserts both values. The wizard sentence sits inside the form, under the key field, as `<p class="pqm-muted">` (the dialog's existing muted-paragraph class), no new component.
- 2026-10-20, build, harness notes: `untouched()` now ignores the lock's claim/release pair and requires the lock absent (every early-exit row thereby asserts "lock absent afterwards"); the `template missing` early exit has no harness row (`pqm_workflow_json()` reads a bundled file; not simulated, as before). The two-runs row runs run B from inside run A's first `POST /credentials` closure and reads the lock while held. `wp_remote_post()` gained a `ping_queue`, `apply_filters()` reads `$GLOBALS['tw']['filters']`. caps-harness loads `class-pqm-n8n.php` and runs `uninstall()` twice (with and without a map; the cron-clear list is reset between). Verification: `php -l` clean on the four PHP files; every harness 0 failed; `npm run lint:js`, `npm run build`, `php bin/build-free.php` + free harness clean (results pasted in the handback). Manual checks (plan § Manual) remain for the owner on Local: the retried ping on a real n8n, the password on the profile and its disappearance on uninstall, the forced held-path 409.
- 2026-10-20, review fixes (coordinator's three findings): `run()` re-reads the setup map as its first statement under the lock (a run that finished between the gate and the claim is now refused instead of overwritten); the `claim()` docblock says honestly that `add_option()` is a read then a write, so the lock narrows bug 72's window to one database round trip rather than closing it (no further mechanism added); the `LOCK_OPTION`, `claim()` and `ping_after_activation()` comments no longer restate n8n's outbox or WordPress core's add_option semantics and point to `memory/n8n-provisioning.md` (publication outbox finding, 2026-10-20) per `memory/lessons.md` 2026-10-16. No new harness row: the harness's `add_option` stub is atomic, so the gap between the gate and the claim cannot be driven cheaply; n8n-api-harness re-run on the edited files only.

- 2026-10-20, agentic walk on the owner's Local sandbox (Pro zip of main 57766d6 installed over 3.9.0; REST calls with a temporary Application Password, WP-CLI through Local's PHP; the owner's n8n instance), after PR #153 merged. **Bug 73 passed:** the story 67 walk's password written into the map, `revoke_setup_password__premium_only()` removed it from the profile (1 → 0), a second call made no error; a real provisioning stored `application_password {uuid, user_id}` in the map and listed one "Post Quality Manager (n8n) 2026-10-20 …" password; the helper on that map removed it. **Bug 75 passed:** map and URL dropped with the objects kept, provisioning again answered 502 step `activate` with the exact held-path sentence and the two ways out, nothing left in n8n, no leftovers, the run's password revoked, the first run's workflow still the only one for the host. **Bug 80, retry not exercised:** the real provisioning passed on its first ping (2.3 s total, no retry wait), `ping_ok` true, state set-up, report version 6 without `failed`; the 404 window did not open this time, so the retry stays harness-proven. **Bug 72, finding:** two REST provisioning requests fired in the same millisecond with a bogus key both answered 400 `pqm_provider_key_invalid` in the same 6.35 s: both passed `claim()` (WordPress core's `add_option()` is a SELECT then an `INSERT … ON DUPLICATE KEY UPDATE`, which succeeds on an existing row; the triage log's residual, now observed on a real MySQL stack). The lock holds for requests seconds apart (two tabs), not for simultaneous ones; with a real key the second simultaneous run would meet n8n's path conflict at activation (bug 75's 409) and roll back, so no orphan either way. Options for the owner: accept (n8n's conflict is the backstop), or a follow-up that claims with `$wpdb->query( INSERT IGNORE … )` and checks the affected rows, the one atomic claim MySQL offers, about six lines plus a `$wpdb` stub in the n8n harness. Cleanup: every walk object removed by id (0 workflows and 0 credentials named for the sandbox host left), both passwords revoked, map/URL/report cleared; the n8n key stays (the owner's to revoke); the Anthropic key file in the private workspace folder is the owner's to delete (chore 81).

## Plan Change Log

## Review Triage Log

**Pass 1, 2026-10-20 (quick lens, Fable 5.1): 4 findings — high 0, medium 0, low 3, false 0, maybe-false 0; 1 rejected by rule.**

- `class-pqm-n8n.php` `provision()`/`run()`: the setup-map gate runs before `claim()` and is not re-checked under the lock, so a run that read "no map" just before another run stored its map and released the lock provisions a second set and overwrites the map — **low** (a microsecond window: one `connection()` read between the gate and the claim), **patch**: `run()` re-checks the map as its first statement (the pre-check kept so a set-up site writes nothing).
- `class-pqm-n8n.php` `claim()`: `add_option()` is not an atomic claim in WordPress core (a `get_option()` read, then `INSERT … ON DUPLICATE KEY UPDATE`, which succeeds on an existing row), so two requests inside that gap both proceed; the docblock's "without a read before the write" overstates it — **low** (the window shrinks from the six-call run to one DB round trip; two human requests cannot land inside it in everyday use), code change **rejected** (the proposed nonce re-read is defeated by the per-request options cache without a persistent object cache, and a raw `$wpdb` INSERT leaves the Options API: more machinery than the residual risk warrants); the docblock wording **patched** to say what the lock does and does not guarantee. Recorded for the owner in the summary.
- REST `ping_after_activation()` and the `LOCK_OPTION`/`claim()` docblocks restate an external system's behaviour in full (n8n's outbox publication, WordPress core `add_option()`), against `memory/lessons.md` 2026-10-16 — **low** (developer-only: the next session reads the comment, not the memory, and the two drift), **patch**: comments shortened to the local why, naming `memory/n8n-provisioning.md`.
- Plan L75–83: the execution tasks are unticked while the notes say they are done — **rejected** by rule (a finding whose fix edits this build's plan); the boxes are ticked at presentation as record hygiene.

Checked by the lens and found consistent: `code` reaches the callback unchanged through `ping_webhook()`; `PQM_N8n` is loaded before the uninstall hook; only `adopt()` and `run()` write the map, so `application_password` survives rotation and update; a passing retry writes a report without `failed`; Freemius markers hold on the Free build; `.pqm-muted` exists; the release note sits under the right heading.

Patches applied by the step-03 implementer (re-engaged) 2026-10-20; full verification re-run by the coordinator afterwards: 16 harnesses 0 failed (n8n-api 473, caps 284, setup 139, free-build 83), Jest 124, lint, build. Nothing deferred. No commit (house rule): the owner commits.

## Design Notes

- The lock, not a placeholder map (D1): `adopt()` can claim the map itself because its write is the last thing it does; `provision()` has six n8n calls between the gate and the write, during which a placeholder map would turn the page "set up" and `managed_blocked()` "not managed". The lock's `at` makes a PHP-killed run recoverable without story 71; comment: `// Ceiling: a run killed by PHP keeps the lock 5 minutes; story 71's Start over is the way out before that.`
- `fail( $step, $cause, $credentials, $created, $workflow_id = null, ?string $then = null )`: the message stays `[cause, left-or-clean, then]`; only the 409 activation passes `$then`. Keeps `data.step = 'activate'` so the wizard's retype rule and the rollback rows hold.
- The retry lives in the REST callback, not in `ping_webhook()`: provisioning is the one caller that just activated something. `ping_webhook()` records each failed ping of the saved URL; the final `pqm_record_workflow_failure()` with the callback's sentence overwrites the last one, and a passing retry's `pqm_record_workflow_version()` writes a report without `failed`, which is how the page reaches "set up".
- Copy (brand.md: gentle cause, actionable fix, concise). 75: "The n8n setup stopped while activating the workflow: another active workflow on this n8n instance already listens on this site's webhook path. Nothing was left in n8n. If that workflow is this site's, paste its production URL as the n8n Webhook URL and choose Let the plugin manage this workflow on the n8n Workflow page; otherwise remove it in n8n, then try again." 80: "Your workflow is set up and active in n8n, but its webhook did not answer yet (n8n responded with 404). Give n8n a moment, then press Test again on the n8n Workflow page. If it keeps answering 404, your n8n may serve webhooks on another address than its editor: copy the production URL from the Webhook Trigger in n8n and paste it as the n8n Webhook URL." 73 wizard (Pro): "n8n saves rewrites as %s through an Application Password created for it on that profile; the plugin revokes it when the setup is removed."
- Skipped: an adoption confirmed during a provisioning run (both write the map; the page offers one or the other) — add when a walk shows both offered at once. Skipped: revoking passwords of sites provisioned before this build (no uuid in their map) — the release note says where to remove it by hand.

## Verification

**Commands:**
- `php -l` on the four touched PHP files -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed, the n8n harness count up by the new rows
- `npm run test:unit` -- expected: all green, the new provision test included
- `npm run lint:js && npm run build` -- expected: clean (restore `pqm-admin.js` / `bin/zip.js` if `--fix` reformats them)
- `php bin/build-free.php <out>` then `php tests/harness/free-build-harness.php` -- expected: no `__premium_only` left, no Application Password symbol in the Free n8n client or activator

**Manual checks (if no CLI):**
- Owner, Local sandbox (Pro, against their n8n): provision from state 1; the step passes on the first or a retried ping (no 404 sentence); the profile lists one "Post Quality Manager (n8n)" password; uninstall → the password is gone. Force the held path (delete the map and URL by WP-CLI, provision again): the 409 sentence names the held path and the two ways out.
