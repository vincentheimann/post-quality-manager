---
title: "The n8n connection state tells the truth after the fact (story 74)"
type: 'feature'
ticket: '74'
created: '2026-10-20'
status: done
baseline_revision: '11be240d46a9104c6a3575f217fabc21478151f7'
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

**Problem:** The stored n8n probe is the last *passing* one and nothing ever replaces it: a key revoked in n8n leaves the page, the wizard and the island claiming connected, and Update, Rotate and the adoption offer answer "n8n answered HTTP 401. Try again in a moment." A key whose scopes exclude the setup's routes answers 403 on the route probes and is blamed on a proxy; a 401 there is "unexpected". Rotation PATCHes the webhook credential blind, so with the Claude nodes sharing it (the stored mis-wiring) a rotation stops every Claude call. Five spellings of "connected" agree only because failures are never stored.

**Approach:** the stored `probe` becomes the last probe *of the stored key*, passing or refused: a 401 from the stored key — on Check again, on any managed call, on the diagnosis — replaces it (`record_refusal()`), and every reader of "connected" asks one server-side predicate (`connected` in `public_state()`). The route-probe 401 maps to `key`, a JSON 403 there to the new cause `scoped` (manual path offered). Rotation refuses with zero calls while a mis-wiring sentence is stored. The island shows a stored refusal above the open form; the managed actions answer the stored refusal instead of "no key is set".

**Decisions taken at planning (owner to confirm at the checkpoint):**
- D1: only a *refusal* replaces the passing probe (causes `key` and `scoped`, from the stored key on the stored address). A timeout, 500, 404 or proxy answer on a re-check keeps the passing probe and records `failed` as today (ticket Boundaries: those causes and their copy do not change).
- D2: a new key refused on Connect changes nothing stored (today's rule): the stored key was not the one refused.
- D3: the mis-wiring gate sits in `rotate_token()` only; `update_workflow()` stays available as the repair for a shared credential (it PUTs the template with the map's credential id on every node, as the 6.12 walk used it); the page is unchanged (Test again stays the primary fix for `miswired`). Owner decision 2026-10-20 at the checkpoint (option b, the recommendation; the full plan kept above the token ceiling).
- D4: a refused managed action and a refused Check again re-render the page: the REST error of `/n8n/token` and `/n8n/workflow` carries `status_html` in its data and `pqm-admin.js` swaps it on an error reply too, so Rotate and Update leave the page at once.
- D5: `probe` stays in `public_state()` (the island shows its message and date); `connected` is added beside it. `key_set` keeps meaning "a key is stored".

## Boundaries & Constraints

**Always:** state changes only when the plugin talks to n8n (no background re-check); a recorded refusal keeps URL, key and `failed`; nothing n8n answers is repeated in a sentence; copy follows docs/brand.md (actionable fix, no blame); Pro code untouched; the harness scans for the key as before.

**Never:** a change to the causes and copy for API-disabled, proxy, unreachable, missing-endpoint and the list-call 401; a change to the manual path or the wizard's steps beyond the predicate; a new option or REST route; a 403 on a *managed* call read as `scoped` (it stays `could_not()`: a WAF can answer it); deleting the stored key on a refusal (Forget is the owner's).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| re-check refused | stored key, probe passing; `POST /n8n/connection` without key → 401 on the list | stored `probe` = `{ok:false, cause:'key', message, at}`; `connected`, `rotatable`, `adoptable` false; URL, key, `failed` unchanged; reply's `probe` = the same | — |
| re-check, other failure | the same, n8n answers 500 / timeout / 404 | passing probe kept, `failed` recorded as today, `connected` true | — |
| new key refused | stored passing key; Connect with another key → 401 | nothing stored changes; reply `probe.cause` key, `connected` true | — |
| route 401 | list 200, `POST …/activate` → 401 | cause `key`, the existing key sentence, no manual path; recorded when it is the stored key | — |
| route 403, JSON | list 200, route probe → 403 with JSON | cause `scoped`: "This API key cannot create credentials or publish workflows: n8n limits what it may do. In n8n under Settings → n8n API, create a key without scope limits, then try again." + manual sentence; `failed` recorded; stored key refused → `probe` replaced | — |
| route 403, non-JSON | list 200, route probe → 403 HTML | `proxy`, as today | — |
| managed 401 | Rotate / Update / adoption read / diagnosis; n8n → 401 on the call | `request()` records the refusal; Rotate and Update answer 502 "The plugin could not finish {step}: n8n refused the API key. Create a new one in n8n under Settings → n8n API, connect again, then try again."; `diagnose()` returns null as today; adoption `could_not('read')` with the same cause | WP_Error, data carries `status_html` (D4) |
| after a refusal | stored refused probe; Rotate / Update / `GET /n8n/adoption` / provision | zero calls; 400 `pqm_n8n_not_connected` whose message is the stored probe's (not "No n8n API key is set") | WP_Error |
| mis-wired, rotate | report `miswired` sentence stored, managed | zero calls; 409 `pqm_workflow_miswired`: "The token was not rotated: the last test found the webhook credential on your Claude nodes, and a new token would stop them. {diagnosis sentence}" | WP_Error |
| mis-wired, update | the same, Update | allowed (D3) | — |
| one predicate | stored refused probe | `setup_state().n8n.connected` false, step `n8n` not ticked, `manual_reason` for `scoped` only; `pqm_manual_reason()` and `workflow_page_state()` read the same predicate; page state not `connected`, `managed` false | — |
| island, stored refusal | GET state `key_set` true, `probe.ok` false | error Notice "{probe.message} Checked {date}." above the open form; no summary, no Forget-only view, no adoption offer | — |
| island, failed POST | reply `probe.ok` false | `stored(data)` runs (the server may have changed); the failure Notice from `failIn` only while `connected` stays true | — |
| wizard | `state.n8n.connected` | `keyPasses()` reads it; step done follows | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `connection()` docblock L87: probe = last probe of the stored key; `public_state()` L120: add `'connected' => self::connected()` after `probe` (key order matters to the harness); new `public static function connected(): bool` replacing private `connected_key()` L1516 (rename; callers `managed_blocked()` L1243, `adoption_blocked()` L685, `start_over()`); new `public static function record_refusal( array $probe ): void` (writes `probe` only, `update_option( OPTION, …, false )`); `request()` L204: after `call()`, `if ( ! is_wp_error( $r ) && 401 === $r['code'] ) self::record_refusal( self::result( 'key', <key sentence> ) )` — extract the key sentence into `private static function key_refused_sentence()` shared with `failure()`; `failure()` L314: `$on_route && 401` → `result('key', …)` before the `! $on_route && 401` branch; `$on_route && 403 && null !== json` → `manual('scoped', …)` before the proxy branch; `not_connected()` L213: when a key is stored and its probe failed, message = `connection()['probe']['message']`; `rotate_token()` L1215: after `managed_blocked()`, read `pqm_workflow_report()['miswired']`, non-empty → `WP_Error( 'pqm_workflow_miswired', …, 409 )`; `answer()` (~L1470): a 401 array → "n8n refused the API key" so `could_not()` reads right, then the fix clause appended in `could_not()` for 401 only (one sentence, see matrix). Do not touch `probe()`'s list-401 path or the other causes.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `connect_n8n_callback()` L1387: after a failed probe, `if ( in_array( $probe['cause'], ['key','scoped'], true ) && $key === $stored['api_key'] && $origin === $stored['base_url'] ) PQM_N8n::record_refusal( $probe )` beside `record_failure()`; `setup_state()` L1093: `$key_ok = $n8n['connected']`; `rotate_n8n_token_callback()` L1635 and `update_n8n_workflow_callback()` L1677: on `is_wp_error`, `$error->add_data( [ 'status' => …, 'status_html' => PQM_Admin::render_workflow_body( $error->get_error_message(), 'error' ) ] )` through one small helper.
- `post-quality-manager/includes/pqm-functions.php` -- `pqm_manual_reason()`: the inline predicate → `PQM_N8n::connected()`.
- `post-quality-manager/admin/class-pqm-admin.php` -- `workflow_page_state()` L1472–1480: the inline predicate → `PQM_N8n::connected()`.
- `post-quality-manager/admin/js/pqm-admin.js` L465–469 -- the `!resp.ok` branch: when `resp.data.data && typeof resp.data.data.status_html === 'string'` swap it (the outcome line is inside), else `showOutcome` as today.
- `src/n8n-connection/index.js` -- `isConnected()` L85 → `!! data.connected`; `send()` L244: `stored( data )` before the early return, `failIn` only when `data.connected`; the Notice L758: add the `key_set && probe && ! probe.ok` branch (status `error`, same "Checked %s." suffix); adoption offer already follows `adoptable`.
- `src/setup/steps.js` L37 -- `keyPasses()` → `!! ( state.n8n && state.n8n.connected )`.
- `tests/harness/n8n-api-harness.php` -- Re-check row L489 ("re-check refused … unchanged") flips to recorded; the exact `public_state`/Forget arrays (L516, setup-harness L214/L310) gain `connected`; the key-order row L487; `$failures` L423: add route 401 → key, route JSON 403 → scoped (+ manual sentence, `failed`), route non-JSON 403 → proxy; new section "Story 74" with one row per matrix line using `managed()`, `rotate()` L1550, `update()` L1727, `adopt_post()` L1338, `diagnosed()` L1159, `start_over()`.
- `tests/harness/setup-harness.php` L307–335 -- `connected` in `n8n`; a stored refusal: `n8n` step false, `manual_reason` null (key) / probe (scoped).
- `tests/harness/render-matrix-harness.php` ~L750 -- stored refusal + map: no Rotate / Update on the page, status block not `connected`.
- `tests/js/n8n-connection.test.js` -- `CONNECTED` gains `connected: true`, `NOT_SET` `connected: false`; rows: stored refusal Notice + form; failed POST reply with `connected: false` drops the summary and calls `onChange`; `tests/js/setup-*.test.js` fixtures gain `connected`.
- `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md`, `_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md` ("Key rejected" row: route 401 and 403 rows), `docs/user-paths.md` A4 line -- DoD.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `connected()`, `record_refusal()`, the 401 hook in `request()`, the two route causes, `not_connected()`'s message, the rotation gate, the 401 sentence -- AC1–AC5
- [x] `post-quality-manager/includes/class-pqm-rest-api.php`, `includes/pqm-functions.php`, `admin/class-pqm-admin.php`, `admin/js/pqm-admin.js` -- the recording on Check again, `connected` everywhere, `status_html` on the two refusals -- AC1, AC5
- [x] `src/n8n-connection/index.js`, `src/setup/steps.js` -- the predicate, the stored-refusal Notice, `stored()` on a failed reply -- AC1, AC5
- [x] `tests/harness/n8n-api-harness.php`, `setup-harness.php`, `render-matrix-harness.php`, `tests/js/*.test.js` -- one row per matrix line -- AC6
- [x] `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md`, `failure-modes.md`, `docs/user-paths.md` -- DoD

**Acceptance Criteria:**
- Given a connected site whose key was revoked in n8n, when the Administrator presses Check again, then the section shows the refused-key sentence with its date, the form is open, and the page offers neither Update, Rotate nor the adoption offer until a probe passes.
- Given the same site, when Rotate is pressed before any re-check, then n8n gets the one PATCH, the page re-renders without Rotate and Update, and the outcome names the refused key, not "try again in a moment".
- Given a key that lists workflows but answers 403 on the route probes, when Connect runs, then the cause names the scoped key and the Workflow File section says so after a reload.
- Given a stored mis-wiring sentence, when Rotate is pressed, then n8n receives nothing and the sentence points to the diagnosis's fix.

## Implementation Notes

- 2026-10-20, planning: `bmad-build` rendered by hand (route `full`) from the workspace root, steps followed in order; branch `feat/n8n-connection-truth` created from `main` at 11be240 as story 71's precedent (rename at the checkpoint if wanted). Story 74 is unrefined (`refined: false`): its ACs are taken as written; D1–D5 above are the planning decisions for the owner's confirmation.
- 2026-10-20, build (implementer subagent, Opus 5.5, from the workspace root; no commit): all five tasks built as mapped. `PQM_N8n::connected()` (public, replaces `connected_key()`), `record_refusal()` (writes `probe` only, its four keys), the 401 hook in `request()`, `key_refused_sentence()` shared with `failure()` (one `401 === $code` branch now covers list and route), `scoped` on a route JSON 403, `not_connected()` answering a stored refusal's message, the mis-wiring gate in `rotate_token()`, the 401 sentence in `could_not()`; `connect_n8n_callback()` records the stored key's `key`/`scoped` refusal; `setup_state()`, `pqm_manual_reason()`, `workflow_page_state()` ask `connected()`; `PQM_REST_API::with_body()` adds `status_html` to the `/n8n/token` and `/n8n/workflow` errors, swapped by `pqm-admin.js` on an error reply; the island and `keyPasses()` read `connected`. Deviations and choices, each the smallest reading of the matrix:
  - `provision()`'s gate is `connected()` instead of "a key is stored" (matrix "after a refusal … provision"). `free-build-harness.php`'s provisioning fixture stored a key with `probe: null`, which the old gate let through; it now carries a passing probe (and its `WP_Error` stub gained `get_error_data()`/`add_data()`).
  - `could_not()`'s 401 sentence is one translatable string with the step only, not `answer()` spliced in, so "Create a new one" stays readable for translators; `answer()`'s 401 ("n8n refused the API key") serves `stopped()` in provisioning.
  - Island: a failed POST reply is stored (`onChange` called). While the reply is still `connected`, the success Notice keeps the previous passing probe (the reply's `probe` is this run's, the stored one is unchanged by D1/D2) and the cause goes to `failIn`. `failIn` also runs when no key is stored (`key_set` false): the stored-refusal Notice needs a stored key, so a first Connect refused would otherwise show nothing; the matrix line "failIn only while connected stays true" is read as "never beside the stored-refusal Notice".
  - Island: on the page mount (no `onChange`), a flip of `connected` dispatches `pqm:workflow-refresh` (focus `pqm-n8n-connection`), as an adoption and Start over do. AC1 needs Update and Rotate gone after a refused Check again and the Code Map named no mechanism for it; the flip back on a passing Connect brings them back (manual check "actions back"). The wizard re-reads GET /setup through `onChange` instead.
  - Harness: the three "the key refused (401)" loop rows (adoption read, rotation PATCH, update read) moved to the new Story 74 section with their new sentence and the recorded refusal (they write now, so the loops' zero-write `refused()` no longer fits them); the rotation's "a stored mis-wiring sentence: kept" row flipped in place to the 409 refusal; `refused()` ignores `status_html`, and `rotate()`/`update()` record errors without it for the leak scans (the body shows the page's own token row, as `without_body()` says for replies); the Re-check golden row flipped in place.
  - Skipped: re-reading the island's state after a refused adoption read (a 401 on GET /n8n/adoption is recorded server-side; the island keeps the summary until the next read or the page refresh) — add when a walk shows it confuses. Skipped: the island's managed line ("It can update the workflow and rotate the webhook token from this page") is not reworded while a refusal is stored — add with story 67's copy pass if the walk asks.
  - Risk to confirm at review: the rotation gate fires on any stored mis-wiring sentence (the three diagnosis shapes), while the frozen copy names "the webhook credential on your Claude nodes"; for the other-type and missing-credential shapes the first clause is not literally true.
  - Verification: `php -l` clean on the four PHP files and the touched harnesses; `node --check post-quality-manager/admin/js/pqm-admin.js` clean; harnesses all 0 failed — n8n-api 574 (531 before, a Story 74 section of 30 rows, the three route rows in `$failures` at 4 checks each, the mis-wiring D4 row), setup 141 (139), render-matrix 406 (403), free-build 83, caps 284, cron-gate 35, insert-block 66, license 27, quota 71, release-header 31, rest-posts 165, rest-reviews 65, sanitize-matrix 226, template-failure 55, template-guard 62, voice 23; `npm run test:unit` 8 suites, 139 passed (133 before; one early run showed two timing failures in workflow-status/workflow-file-copy that did not recur in four reruns); `npm run lint:js` clean after `--fix` (prettier only, in this story's test files; `pqm-admin.js` and `bin/zip.js` are outside its scope and untouched by it); `npm run build` compiled; `php bin/build-free.php <scratch>` then `free-build-harness.php` 83 passed. Manual checks (Local walk with a revoked key) not run.
- 2026-10-20, review fixes (implementer subagent): the n8n connection disclosure renders whenever a key is stored (`workflow_page_state()` `key_set`; a refused Check again on state 2 kept the island); `pqm_manual_reason()` counts `failed` only when not older than the stored probe (an earlier proxy record no longer becomes the reason after a key refusal; a scoped refusal writes both with one `at`); `diagnose()` answers null with zero calls while a stored key is refused (no drift of the refusal's date); the rotation refusal's first clause is shape-neutral ("the last test found the workflow's credentials mis-wired, so fix that first"), closing the risk noted above; stale "last passing probe" wording fixed in `record_failure()`, the island's state comments and memory entry 1. Rows: n8n-api 577 (diagnosis after a refusal, older proxy record, scoped reason), render-matrix 406 (the refusal row asserts the disclosure), setup 141, island Jest 44.

- 2026-10-20, after the review patches (session gates re-run by the session agent): 17 harnesses 0 failed (n8n-api 577, render-matrix 406, setup 141, free-build 83), Jest 139/139, lint clean, `npm run build` compiled, the Free build proven; `pqm-admin.js` carries only this story's 9-line edit. Plan marked built; no commit (house rule). Story 74's Local walk (plan § Verification, manual checks) not run from this build. Not part of this story's change set: `_bmad-output/backlog/story-image-prompt-agent.md` (story 83) and the initiative's O10 line, filed in the same session at the owner's request — commit them separately.

## Plan Change Log

## Review Triage Log

- 2026-10-20, quick lens (Fable 5.1, 7 findings): 1 high, 2 medium, 4 low; 0 false, 0 maybe-false. 5 → patch, 2 rejected, nothing deferred.
  - **high → patch.** `admin/class-pqm-admin.php` disclosure gate `'connection' => $kind !== 'not-set-up'`: a connected site without a saved URL (state 2) whose Check again is refused lands in `not-set-up` (the predicate is false), and the body swap drops the n8n connection disclosure — the island, its refusal Notice and the only Forget key vanish; the sentence is reachable only through the wizard. AC1 unmet on that page state. Fix: the disclosure renders whenever a key is stored (`key_set` on the page state); render-matrix row adjusted.
  - **medium → patch.** `includes/pqm-functions.php` `pqm_manual_reason()`: `failed` is kept on a refusal (Boundaries) and read as soon as `connected()` is false, so an older proxy/timeout record becomes the page's and the wizard's manual reason after a key refusal, contradicting the matrix ("`manual_reason` for `scoped` only"). Fix: `failed` counts only when it is not older than the stored probe (`failed.at >= probe.at`; the scoped case writes both with the same `at`); harness row.
  - **medium → patch.** `class-pqm-n8n.php` `diagnose()` gates on an empty key, not `connected()`: with a stored refusal every Test again sends one GET with the refused key and re-records the refusal with a fresh `at` (the island's "Checked" date drifts without a probe). Fix: a stored key that is not connected → null (the check could not run), zero calls; no key → '' as today.
  - **low → patch.** `rotate_token()` refusal copy (frozen matrix wording) says "found the webhook credential on your Claude nodes" for every stored sentence, untrue for the other-type and missing-credential shapes. Fix: shape-neutral first clause, the diagnosis sentence kept as the fix — a wording change to the frozen copy, recorded here for the owner (the implementer flagged it too).
  - **low → patch.** Stale docblocks: `record_failure()` ("last passing probe"), the island's `onChange` JSDoc and state comment (no `connected`), memory entry 1's "last passing probe" clause. Direct corrections.
  - **low → rejected.** A 401 mid-provisioning still ends "Try again in a moment." (`fail()`'s default `$then`): a key revoked during the seconds of a run is unlikely in everyday use and the fix adds a branch to `fail()`; `answer()` already names the refused key in the sentence.
  - **low → rejected.** Island: with a stored refusal, a new key that is refused shows this run's cause in the refusal Notice with "Checked now" although nothing was stored (D2). The run's cause is the more useful thing to show the owner after typing a new key; the mislabel is cosmetic and the fix adds client state.

## Design Notes

- `request()` is the one place a call with the stored key leaves, so the 401 hook there covers rotation, update, adoption, diagnosis and Start over's removal at once; `probe()` goes through `call()` with a key that may not be the stored one, hence the recording decision sits in `connect_n8n_callback()`, which knows both.
- `scoped` requires a JSON body because the list call just passed: a WAF that lets `GET /workflows` through and blocks `POST …/activate` is conceivable, but n8n's own refusal carries `{"message": …}` while a WAF page does not — the same oracle D2 uses for `missing_endpoint`.
- Golden row (harness): `site($passing_conn, [resp(401, ['message' => 'unauthorized'])]); post([]);` → `options[OPTION]['probe']['cause'] === 'key'`, `['api_key'] === KEY`, `public_state()['connected'] === false`, `['rotatable'] === false`.

## Verification

**CLI:**
- `php -l` on the four PHP files; `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 0 failed, n8n-api above 531 with the Story 74 section
- `npm run test:unit`, `npm run lint:js`, `npm run build` -- expected: green (restore `pqm-admin.js` / `bin/zip.js` if `--fix` reformats them)
- `php bin/build-free.php <scratch> && php tests/harness/free-build-harness.php` -- expected: green

**Manual checks (Local sandbox, agentic walk):**
- Revoke the sandbox's n8n key in n8n, press Check again: the section shows the refusal; Rotate and Update gone from the page; create a new key, Connect: summary back, actions back.
- With a passing key, press Rotate after revoking the key in n8n: the outcome names the refused key and the page re-renders without the managed actions.
