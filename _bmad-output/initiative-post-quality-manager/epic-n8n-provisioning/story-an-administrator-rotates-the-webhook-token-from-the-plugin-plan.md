---
title: 'An Administrator rotates the webhook token from the plugin'
type: 'feature'
ticket: '10'
created: '2026-10-16'
status: done
baseline_revision: '8ebe115eed6a3be28df78777dae1eb5dab13f330'
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

**Problem:** The webhook token is one shared secret for both directions (ADR 0010) and there is no rotate action: changing it means editing the n8n Header Auth credential and the plugin's settings together, by hand, in step. The owner's token appeared in n8n execution output on 2026-10-13 and could only be rotated that way. The spec's CAP-6: a new token generated, written to the n8n credential and saved in settings in one action, the old one refused and the new one accepted on the next request.

**Approach:** On a site the plugin manages (a setup map, provisioned or adopted) with a passing n8n API key, the n8n Instance section's managed block offers **Rotate the webhook token**. The plugin generates a new token, PATCHes the map's webhook credential through the n8n API, saves the token in settings, pings the saved webhook URL and re-renders the status block. The PATCH is the only write on n8n; nothing else there is read or changed.

## Boundaries & Constraints

**Always:** PATCH first, save second (the ticket's open order, settled): a refused PATCH leaves the stored token and n8n untouched; the save is a one-key merge of `pqm_settings` (`webhook_token`), never the Settings form; the ping after the save proves the pair. The new token is `wp_generate_uuid4()`, as activation makes it. The action is offered and the route accepted only while a key with a passing probe is stored, a setup map exists and the saved URL's segment equals the map's `path` (entry 6.8's D3); otherwise zero calls and nothing written. The token travels in no REST reply and no error message; n8n's reply bodies are never repeated. The return path needs nothing: the template's save-review node sends back the `auth_token` of the payload it received, so every run after the save carries the new token both ways. The button is secondary (one primary per section, the status block's wins on the page). Copy per `docs/brand.md`: a state sentence, then its action.

**Never:** a confirmation dialog (CAP-6 says one action; rotating again is the undo); `GET /credentials`; a write to the Anthropic or WordPress credential or to the workflow; a change to the status block's PHP, `GET /setup`, the wizard's steps, the Settings token field or the Workflow File copy row (story 67 moves the token to a Webhook disclosure); a rotation for a hand-made setup without a map (the manual path keeps today's edit-in-two-places, documented in the Settings field description).

## I/O & Edge-Case Matrix

`POST /n8n/token` (`pqm_manage_settings`, no arguments) through `PQM_N8n::rotate_token()`; `GET /n8n/connection` gains `rotatable`. Map = `pqm_n8n_setup`; segment = the saved URL's path after `/webhook/` (`webhook_segment()`).

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Offer | Key with a passing probe, map, segment = map `path` | `rotatable: true`; the managed block shows the managed line and **Rotate the webhook token** | — |
| Not offered | No key, failing probe, no map, no URL, or segment ≠ map `path` | `rotatable: false`, no button | — |
| Rotate | Button pressed | One `PATCH /credentials/{map.credentials.webhook}` with `{data: {name: 'X-PQM-Token', value: <new>}}`; then `pqm_settings['webhook_token']` = new (one write); then a ping of the saved URL with the new token; reply `{rotated: true, ping_ok, message}` + `status_html` when the ping passed (version recorded, as provision does) | — |
| Refused at the gate | No key or failing probe; no map; segment ≠ map `path` | 400 `pqm_n8n_not_connected`; 409 `pqm_workflow_not_managed` (let the plugin set up or manage the workflow first); 409 `pqm_webhook_url_changed` (the saved URL no longer points at the managed workflow) | Zero calls, nothing written |
| PATCH refused | Non-2xx, or 2xx without JSON | 502 `pqm_rotation_failed`, `stopped('credential')`'s sentence ending "Try again in a moment." | Stored token unchanged, no ping |
| Credential gone | JSON 404 on the PATCH | 502 `pqm_rotation_failed`, a sentence saying the webhook credential the plugin manages is no longer in n8n: check the Webhook Trigger's credential in n8n | Stored token unchanged, no ping |
| n8n unreachable | WP_Error | 502 `pqm_rotation_failed`, `stopped()`'s sentence | Nothing written |
| Ping fails after | The saved URL does not answer, or answers 401/403 | `rotated: true, ping_ok: false`, message = the ping's cause, prefixed "The token was rotated in n8n and saved here." (a 401/403 then means the trigger carries another credential: say so) | Token kept on both sides |
| Leak scan | Every reply, error and ping of the section | The new token appears in no reply and no error; `pqm_settings` is written once, with only `webhook_token` changed | — |
| Free build | Same site on the Free build | Rotates the same way (provisioning is free, ADR 0003) | — |
| Forget key after | Key forgotten | `rotatable: false`, the button gone, the token stays as rotated | — |

## Decisions (owner, 2026-10-16)

Approved as proposed at the plan checkpoint; the full plan kept (~3,600 tokens, one goal).

- **D1 — placement until story 67:** the button lives in the n8n Instance section's managed block, under the managed line (option A); story 67 moves it into the Webhook disclosure.
- **D2 — order:** PATCH first, then the one-key save, then the ping (Boundaries).
- **D3 — no confirmation dialog:** one action, rotating again is the undo.

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `adoption_blocked()` L631 (the gate's shape to mirror: new private `rotation_blocked(): ?WP_Error` = key + passing probe (`not_connected()`), map present, `webhook_segment(saved URL) === $setup['path']`); `public_state()` L93 (+ `rotatable` = `null === self::rotation_blocked()`); `request()` L171 (the PATCH goes through it); `stopped()` L975 (the 502 sentence; add the `credential` step); `created_id()` / `removed()` L854–L949 (how a reply's code and JSON 404 are read); `SETUP_OPTION` map shape: `credentials.webhook` is a string id for both provisioning (L775) and adoption (L613). New public `rotate_token(): string|WP_Error` answers the new token after a passing PATCH, never stores it. Body: `['data' => ['name' => 'X-PQM-Token', 'value' => $token]]` (spike table: `PATCH /credentials/{id} {data}`, `httpHeaderAuth` schema `{name, value}`; provisioning's credential body L706 carries the same `data`).
- `post-quality-manager/admin/class-pqm-admin.php` -- `save_webhook_url()` L1791 (the one-key merge-save to mirror: new `save_webhook_token(string $token): void`, `update_option('pqm_settings', …)`); `ping_webhook()` L1830 (reads the token through `pqm_n8n_headers(pqm_get_settings())`, so it pings with the new one after the save; 401/403 branch L1873); `render_workflow_status()` L1438 (unchanged, rendered into `status_html`).
- `post-quality-manager/includes/class-pqm-rest-api.php` -- routes L170–L195 (`/n8n/provision`, `/n8n/adoption`): add `POST /n8n/token`, `check_manage_settings`, no args; new `rotate_n8n_token_callback()` modelled on `provision_n8n_callback()` (shown above L1369: ping, `pqm_record_workflow_version()`, `status_html` on ok); it never puts the token in `$reply`.
- `src/n8n-connection/index.js` -- `state` keys L86 (+ `rotatable`); `call()` L160 (busy, the block's failure Notice); the managed block L463–L467 (`adoption` when `managed`): add the button under the managed line, a success Notice in that block ("Token rotated. {ping message}"), focus stays on the button; on a passing reply call the status swap and `onChange(state)` is not needed (the wizard never mounts a managed site). Header comment L16–L24: one line for the rotation.
- `src/setup/steps.js` L47 `swapStatusBlock(html)` -- the one status-block swap, used by the wizard; move it to `src/kit/swap-status-block.js` (exported, dispatching `pqm:workflow-status` as today) and import it from both callers, so the island re-renders the status after a rotation (`src/kit/use-reimport-shown.js` listens to the event).
- `tests/harness/n8n-api-harness.php` -- stub `wp_remote_request` L84 (queue), `writes` L41, `body()`, `calls()`, `calls_signed()`, `connected($queue, $extra)` L521 (+ `$setup_map` L873 for a managed site), the leak scan over `$replies`; `wp_generate_uuid4()` is stubbed at L94 (`stub-uuid`, also `free-build-harness.php` L194): let it read `$GLOBALS['tw']['uuid'] ?? 'stub-uuid'` so the section rotates to a distinct `rotated-token-SECRET` the leak scan looks for. New "Entry 10" section: one row per matrix line, the route registration row as entry 8's (L1020).
- `tests/harness/free-build-harness.php` L434–L457 (the entry 8 Free row) -- one rotation row on the Free shape.
- `tests/js/n8n-connection.test.js` -- `CONNECTED` fixture L57 (+ `rotatable: false`), the managed-line test (`setup` set): button only when `rotatable`; click → POST `/n8n/token`; a refused reply in the adoption block's Notice.
- `tests/js/setup-provision.test.js` -- imports nothing new; the moved `swapStatusBlock` keeps its behaviour (the wizard tests cover the swap).
- `docs/policies/privacy.md` L14, L24 and `docs/policies/readme-external-services.md` L12, L26 -- one clause: when you rotate the webhook token, the plugin writes the new token into the webhook credential in n8n with the same key.
- `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md` (an Entry 10 line; the "ADR 0010 deferred the rotate action" fact becomes true) -- one line each.
- `post-quality-manager/templates/n8n-workflow.json` -- unchanged: the save-review node's `X-PQM-Token` header is `={{ $json.auth_token }}` (verified), the trigger's credential holds the value.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `rotation_blocked()`, `rotate_token()`, `public_state()` + `rotatable`, `stopped('credential')`, the credential-gone sentence -- the n8n write and its gates in the one client class.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- `save_webhook_token()` -- the page-local one-key merge-save beside `save_webhook_url()`.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- `POST /n8n/token` + `rotate_n8n_token_callback()` -- PATCH → save → ping → record → `status_html`, token in no reply.
- [x] `src/kit/swap-status-block.js`, `src/setup/steps.js`, `src/n8n-connection/index.js` -- the shared swap; the button, its Notices and the swap in the managed block.
- [x] `tests/harness/n8n-api-harness.php`, `tests/harness/free-build-harness.php` -- the Entry 10 section (every matrix row; the PATCH body asserted; `writes === ['pqm_settings']` after a pass and `[]` after a refusal; the new token in no reply) and the Free row.
- [x] `tests/js/n8n-connection.test.js` -- button only when `rotatable`; click → POST; refusal shown; a passing reply swaps the status block.
- [x] `docs/policies/*.md`, `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md` -- the lines named in the Code Map.

**Acceptance Criteria:**
- Given the owner's production site with its adopted map and a passing key, when they press **Rotate the webhook token** on the n8n Workflow page, then the status block says the workflow answers, a run started afterwards passes the trigger, and in n8n the webhook credential shows an updated time while the workflow's does not (hitl, on the environment the owner names).
- Given any run of the harness section, then the only n8n call is the one PATCH on the map's webhook credential id, with the plugin User-Agent and the key, and the new token is in no reply, error or stored option other than `pqm_settings['webhook_token']`.

## Implementation Notes

- Built 2026-10-16 by an Opus 5.5 implementer subagent from the plan (model split, 2026-10-15). Two deviations, both kept at review: `ping_webhook()` gained `bool $diagnose = true` and `diagnosed()` takes `?string`, so the rotation's ping (`false`) runs no diagnosis read, which the second acceptance criterion requires (a saved-URL ping otherwise reads the workflow through the n8n API, entry 7); the 401/403 ping result carries `refused: true`, which lets the rotation say the trigger uses another credential than the managed one. A consequence: a stored mis-wiring sentence stays after a rotation (no diagnosis ran); Test again clears it.
- Outside the Code Map, mechanical: `tests/harness/setup-harness.php` (two exact `public_state()` shapes gain `rotatable`), `tests/harness/render-matrix-harness.php` (its source check follows the swap to `src/kit/swap-status-block.js`).
- The gate order in `rotation_blocked()`: key, then map (409 `pqm_workflow_not_managed`, also for a map without `credentials.webhook`), then the path (409 `pqm_webhook_url_changed`, also when no URL is saved).
- Messages are built on the server: "Token rotated. {ping message}" / "The token was rotated in n8n and saved here. {cause}" / the other-credential sentence on 401/403. `stopped()` gained the `credential` step ("n8n did not confirm the change" for a 2xx without JSON).
- Verified before review: `php -l` clean; every harness green (n8n-api 390, free-build 74, render-matrix 366, setup 138; 2,074 checks in all); Jest 125; lint and build clean. Re-run by the session with the same counts.
- Review patches (pass 1, rows 1–4) applied by the same implementer: the reply carries `outdated` after `ping_ok` and the island warns on it; `stored()` clears the rotation outcome; the release-note sentence and the kit header fixed. Gates re-run by the session after the patches: every harness green (n8n-api 391, 2,075 checks in all), Jest 127, lint and build clean. No commit (house rule).

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, 2026-10-16; the lens ran twice, the first time on a truncated diff file holding only the new kit module): 6 findings: 0 high, 1 medium, 4 low, 1 false, 0 maybe-false. 4 patches, 0 deferred, 2 rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `src/kit/use-reimport-shown.js` header still names "the setup dialog's swapStatusBlock()" as the event's dispatcher | low | patch | Verified: the function moved to `src/kit/swap-status-block.js`, called by the dialog and the island. One comment line. |
| 2 | The release note tells a hand-made setup to change the token "in n8n and in Settings together"; the Settings field is `readonly` | low | patch | Verified at `class-pqm-admin.php` L802: no UI edits the token. The plan's Never bullet assumed a documented manual path that does not exist. Sentence rewritten: a hand-made workflow keeps its token; let the plugin manage it to rotate from here. |
| 3 | An outdated workflow's ping (`ok: true, outdated: true`, "…older version…") shows as a green success Notice after a rotation; the wizard shows the same state as a warning | medium | patch | Verified: the reply drops `$ping['outdated']` and the island picks success on `ping_ok`. Reply gains `outdated`; the island warns on it (harness and Jest rows added). The matrix's reply shape names three keys; a superset is still that shape. |
| 4 | The rotation outcome Notice survives Forget key, Save and check and Check again (the state changes, `rotated` is never cleared) | low | patch | Verified: `stored()` replaces `state` only. One line in `stored()`, a Jest assertion. |
| 5 | A refused PATCH reads "The n8n setup stopped while updating the webhook credential (…)": no setup is running | low | reject | Verified wording. Met only when n8n refuses the PATCH; the plan prescribed `stopped('credential')`, and a dedicated sentence adds a branch. Story 67's copy pass can reword `stopped()` per step. |
| 6 | AC1 (HITL) not met; whether n8n 2.42.3 accepts `PATCH /credentials/{id}` with `{data}` alone rests on the spike | false | reject | Not a code defect: the walk is the owner's, on their production site, after the build (as entries 7 and 8); carried to step 05 as open. The spike's table records the PATCH as observed live. |

## Design Notes

Why PATCH first: the plugin can always re-run the PATCH, but it cannot un-send a token to n8n; if the save failed after a passing PATCH (a local option write, practically never), the next ping answers 403 and the status block names the fix. Saving first would leave the site sending a token n8n refuses whenever the PATCH fails, the common failure.

Why no confirmation: nothing is lost by a rotation (both sides change together, and rotating again is the undo); a run in flight at that second is refused once and shows as a failed command, which the owner can rerun. `lessons.md` 2026-07-23 reserves confirmations for irreversible side effects.

Draft copy (the build writes the final): button *Rotate the webhook token*; success, *"Token rotated. Your workflow answers."* (the ping's message); credential gone, *"n8n no longer has the webhook credential the plugin manages. Check the Webhook Trigger's credential in n8n, then try again."*; URL changed, *"Your n8n Webhook URL no longer points at the workflow the plugin manages, so the token stays as it is."*

Skipped: refreshing the Workflow File copy row and the Settings field after a rotation — they show the new token on the next page load, and story 67 removes both for managed setups. Skipped: a 403-on-ping rollback (PATCH the old token back) — the status block names the fix and the pair is already consistent. Skipped: rotating the Pro WordPress Application Password — another credential, another story.

## Verification

**Commands:**
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` -- expected: every harness passes, no FAIL line.
- `npm run test:unit` and `npm run lint:js` -- expected: green.
- `php -l` on each touched PHP file -- expected: no errors.

**Manual checks (if no CLI):**
- HITL on the owner's production site (named by the owner; the adopted map from the 6.8 walk, a key stored): the managed block shows the button; press it; the status block re-renders with the version; `wp option get pqm_settings` shows the new `webhook_token`; in n8n the webhook credential's updated time changed and the workflow's did not; a Review on a post goes through. Record the walk here.
- **HITL walk, 2026-10-16, on the owner's production site (named by the owner), run by the agent at the owner's request, on a zip of main 75f367d the owner installed (Replace current with uploaded); the adopted map and the stored key from the 6.8 walk; REST calls with the project Application Password, reads with wp-cli over SSH, the n8n API read on the server with the stored key (never shown):** ping with the old token: 200 · `POST /n8n/token`: `{rotated: true, ping_ok: true, outdated: false}`, message "Token rotated. Ping OK — …", `status_html` "version 6, checked just now by a test ping" · `pqm_settings['webhook_token']` changed (36 characters, a new UUID) · old token: 403, new token: 200 on the webhook (CAP-6's success criterion) · in n8n the webhook credential's `updatedAt` is the rotation second (2026-10-16T11:32:26Z) and the workflow's `updatedAt` is unchanged (2026-10-15T13:05:14Z, `versionId` unchanged, still active); the Anthropic and WordPress credentials untouched · the setup map untouched · a Review on a post over `POST /trigger-review`: n8n answered 200 and two result rows landed through the return path within 15 seconds, the version record now `via: command`. Not walked by a human: the button in the n8n Instance section's managed block and its Notice (the route was pressed over REST); jsdom covers them. **Pass.**
- **Second walk, 2026-10-16, owner in the browser on their Local sandbox:** Token rotated, ping OK, the credential's `updatedAt` moved and the workflow's did not, old token 403, new 200 — the entry 6.12 walk of 2026-10-16 on the owner's Local sandbox against their n8n instance, recorded in `story-the-success-signal-walk-passes-and-the-records-say-so-plan.md` § Verification.
