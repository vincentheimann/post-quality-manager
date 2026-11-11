---
title: 'The success-signal walk passes and the records say so'
type: 'chore'
ticket: '12'
created: '2026-10-16'
status: done
baseline_revision: '9cf6f03061bad88a93f68c6d6afafe3b3dc27bb5'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Entries 1–11 are merged and harness-verified, but the epic's own success signal (a fresh site reaches its first review with three values typed and no n8n UI visit) has never run end to end, a forced provisioning failure has never hit a real instance, and the records still describe the pre-epic setup: `docs/user-paths.md` A4 lists seven manual steps, ADR 0015's Consequences stop at "committed to", ADRs 0009 and 0010 do not say the amendment landed, and entry 5's `unknown` says the spike settled a behaviour n8n's source shows is timing-dependent.

**Approach:** The owner walks the wizard in the browser on the environment they named; the agent prepares the sandbox, drives the API-side checks and the WP-CLI reads, forces the failure, repeats the HITL items still open from entries 6.7–6.10 there, and then rewrites the records so they say what was built and what the walk proved.

## Boundaries & Constraints

**Always:** Records only, no plugin code; the walk runs on a zip of main `9cf6f03` already installed on the sandbox. The owner types the three values and presses the buttons; the agent reads, never types a secret. The n8n API is read for verification and written only to mis-wire the sandbox's own workflow (6.7) and to clean up this walk's objects at the end; nothing the production site uses is touched (its workflow, credentials and setup map are not this walk's). The owner's domains stay out of every repo file: "the owner's Local sandbox" and "the owner's n8n instance". Every claim in the records names the environment and the date.

**Never:** No Live Link or tunnel to make the callback land; the line that cannot pass on a `.local` host is recorded as not proven, not simulated. No template version bump (6.9 is exercised by setting the stored version to 5 on the sandbox). No edit of the spec, the epic's Done list, or the frozen blocks of other plans. No `tickets.py mark` (the done chore does it).

- Decision (owner, 2026-10-16): the environment is the owner's Local sandbox (a copy of their production site, Pro build of main, Freemius premium code on, Application Passwords available) connected to the owner's own n8n instance; "empty project" is read as "no plugin objects for the sandbox's host", which the deterministic names (`… - <host>`) make checkable. It is a dry run: the first review cannot land there because the instance cannot reach a `.local` address.
- Decision (owner, 2026-10-16, checkpoint): A4 moves to Current Value for the setup only (connect, provision, forced failure, adoption, update, rotation: owner-verified on the Local sandbox against their n8n instance); the first review landing on a fresh site stays Unrealized Value until a reachable fresh install exists.
- Decision (owner, 2026-10-16, checkpoint): no Free-build second pass; the Free template's provisioning stays harness-covered (`free-build-harness.php`) and is recorded as harness-only.
- Decision (owner, 2026-10-16, checkpoint): the agent reads the n8n key the owner typed from the sandbox's `pqm_n8n_api` option with WP-CLI for the verification calls; it never enters a repo file or the chat.
- Decision (owner, 2026-10-16, checkpoint): the plan is kept whole at about 2,500 tokens (one records-only deliverable).
- Decision (owner, 2026-10-16): every HITL item still open from the epic runs on the sandbox too (6.4 real-site provisioning, 6.5 forced failure, 6.6 wizard, 6.7 setup-map diagnosis and a hand-made mis-wiring, 6.8 adoption of a map-less workflow, 6.9 Update workflow, 6.10 rotation).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Connect | wizard step n8n: base URL + expiring key typed | "Connected to n8n.", key hint shown, `pqm_n8n_api.probe.ok` | a cause sentence names the failure, manual path offered |
| Provision | Anthropic key typed once | 3 credentials + 1 active workflow named for the host in n8n, map `wizard_created: true`, ping OK version 6, `rewrite_ready: true`; key in no option or log | 502 `pqm_provision_failed` + step |
| First review | wizard's first-review step on a post | the run starts (webhook 200); the result never lands (callback to `.local`) | recorded as not proven on this environment |
| Forced failure | map + saved URL deleted on the site, wizard run again | n8n refuses `activate` (path in use); rollback deletes the new workflow then 3 credentials; the API lists only the first run's 4 objects; the error names the step and ends "Nothing was left in n8n." | if n8n answers 409 "still being unpublished", leftovers recorded and the next run removes them (settles entry 5's `unknown`) |
| Mis-wiring (6.7) | the trigger's credential PUT onto one Claude node by the agent; Test again | one sentence naming the node, the credential and the fix; Test again the one primary | — |
| Update workflow (6.9) | stored version set to 5; Update workflow pressed | PUT keeps ids and path, ping reports 6, mis-wiring gone, workflow active | 502 `pqm_update_failed` |
| Adoption (6.8) | map deleted; offer → Manage this workflow | map `wizard_created: false`, no n8n write | 409 / 404 per gate |
| Rotation (6.10) | Rotate the webhook token | old token 403, new 200, credential `updatedAt` moved, the workflow's not | 502 `pqm_rotation_failed` |
| Cleanup | walk done | this walk's workflow and credentials deleted via API, Forget key, the sandbox's Application Passwords revoked | leftovers named in the record |

</frozen-after-approval>

## Code Map

- The owner's Local sandbox (a Local site, not in the repo; its path, host and the WP-CLI invocation live in the session's scratchpad) -- WP 7.1.3, plugin 3.9.0 = main 9cf6f03 installed 2026-10-16 15:45, `pqm_settings.n8n_webhook_url`, `pqm_workflow_version_seen`, `pqm_setup_progress` deleted; no `pqm_n8n_api`, `pqm_n8n_setup`, `pqm_n8n_leftovers`. WP-CLI runs with Local's own PHP binary and the site's `php.ini` (the system PHP lacks `mysqli`). Host slug `pqm-execute-<host-slug>`; `pqm_fs()->is__premium_only()` true (three credentials expected).
- `post-quality-manager/includes/class-pqm-rest-api.php` L148–L210 -- the routes the checks read: `GET /n8n/connection`, `POST /n8n/provision`, `GET|POST /n8n/adoption`, `POST /n8n/token`, `POST /n8n/workflow`; the agent calls them only for reads, with an Application Password created by WP-CLI on the sandbox; every button press is the owner's.
- `post-quality-manager/includes/class-pqm-n8n.php` -- `provision()`, `fail()`/`roll_back()`, `removed()`/`gone()`, `diagnose()`, `propose_adoption()`/`adopt()`, `rotate_token()`, `update_workflow()`, `credential_names()`: what each walk line exercises; no change.
- `post-quality-manager/templates/n8n-workflow.json` -- the Webhook Trigger answers `{"ok":true,"workflow_version":6}` itself (`options.responseData`), so the ping passes on the sandbox; `POST Result to WordPress` posts to the payload's `callback_url` (`pqm-functions.php` L443), which is the `.local` address: the first review cannot land.
- `docs/user-paths.md` L36–L41 (A4) and the UV table L108–L116 -- A4's CV/UV rewritten for the key path (three values, no n8n UI visit, manual fallback by choice, adoption, update in place, rotation); the first review landing on a fresh reachable site and n8n Cloud stay UV; row 5 (round-trip test) unchanged.
- `_bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md` L28 -- "UV4 … pending validation" → validated on the sandbox 2026-10-16 up to the first review's landing.
- `docs/adr/0015-plugin-provisions-n8n-via-public-api.md` Consequences -- what was built (entries 1–11, PRs), what the walk proved and did not, the leftovers record and the 409 timing as learned facts.
- `docs/adr/0009-multi-provider-models-and-token-limit.md` Consequences and `docs/adr/0010-one-token-authenticates-both-directions.md` Consequences -- one line each: amended by ADR 0015 (policy wording "forwarded once, never stored" since entry 6; the rotate action exists since entry 10, PR #140), statuses unchanged.
- `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/tickets.toml` entry 5 `unknown` -- settled with what the forced failure showed (hand edit; `tickets.py` has no command for the field).
- `_bmad-output/initiative-post-quality-manager/deferred-work.md` L67–L72 -- `resolved:` lines on the entry 5 `unknown` entry and the 6.6 user-paths entry.
- `memory/n8n-provisioning.md` L3 ("Not built yet") and a new Entry 12 line; `memory/MEMORY.md` L19 -- the index line.
- The six sibling plans' `## Verification` (entries 5, 6, 7, 8, 9, 10 in this folder) -- one dated line each pointing to this plan's walk record; their own HITL lines stay.

## Tasks & Acceptance

**Execution:**
- [ ] this plan, `## Verification` -- the walk record, one line per matrix row with the observed values (ids and names of this walk's n8n objects, HTTP codes, option contents), the environment named as the sandbox -- the ticket's verify clause
- [ ] `docs/user-paths.md` -- A4 CV/UV rewritten, the UV table unchanged -- the 6.6 deferred entry
- [ ] `initiative-post-quality-manager.md` L28 -- UV4 validation state -- the initiative's claim list
- [ ] `docs/adr/0015-…md`, `0009-…md`, `0010-…md` -- Consequences amended as mapped -- ADR = why, kept current
- [ ] `epic-n8n-provisioning/tickets.toml` entry 5 `unknown` + `deferred-work.md` two `resolved:` lines -- the facts the walk settled
- [ ] `memory/n8n-provisioning.md`, `memory/MEMORY.md` -- Entry 12 line and index -- memory = what is true now
- [ ] the six sibling plans' Verification -- one pointer line each -- a reader of any entry finds the walk

**Acceptance Criteria:**
- Given the records, when a reader opens A4, ADR 0015 or the memory, they learn what was proven on 2026-10-16, on which environment, and the one line still unproven (a first review landing on a fresh internet-reachable site).
- Given the sandbox after cleanup, when its n8n instance is listed via the API, no workflow or credential named for the sandbox's host remains, and the sandbox stores no n8n key.

## Implementation Notes

- Sessions run from the workspace root (owner rule 2026-10-16): the bmad-build skill was rendered by hand and followed step by step; the walk is the owner's, the records are written by the session model (no implementer subagent for a records-only plan).
- Sandbox preparation (agent, before the walk): the Local site carried the production site's webhook token in its copied settings; a fresh UUID replaced it so the n8n credential would never hold production's secret. Plugin 3.4.0 → the Pro zip of main 9cf6f03 (`npm run zip`, `wp plugin install --force`); the stale webhook URL, version record and setup progress deleted.
- The owner's n8n UI stopped opening workflows during the walk (the editor chunk requested with a trailing slash, 404). Checked from the sandbox: every asset answers 200 at its real path, the instance and its API answered throughout; the owner attributed it to their host and the walk never needed the UI. Not a plugin matter.
- Beyond the Code Map: entry 6.4's plan also gets a pointer line (its Verification said the real-site check was entry 12's), and backlog story 67 is refined with the walk's UX observations at the owner's request (2026-10-16).
- The first review's silent end: the template's callback node continues on error, so n8n reports the run as success while the result never reached the site, and the wizard waited without end. Noted in story 67 and story 2's domain; no code in this entry.

## Plan Change Log

- 2026-10-16 (checkpoint 1): the owner approved with the recommended answers; three open questions written into the frozen block as decisions, the token gate accepted as keep.

## Review Triage Log

- 2026-10-16, quick lens (session model), 6 findings: high 1, medium 3, low 2, false 0, maybe-false 0. All routed patch, applied by the session (no implementer subagent in a records-only build).
  - high · patch · the plan's Code Map and Design Notes carried the sandbox's path, the Windows user name, Local's run id and the host slug; the frozen Always forbids owner domains in repo files, and the diff scan had missed the then-untracked plan file. Replaced by placeholders; the scan now includes untracked files.
  - medium · patch · the Consequences bullets appended to ADRs 0009 and 0010 rewrite accepted ADRs, which `docs/adr/README.md` forbids; the sanctioned marker is the Status line (ADR 0003 precedent). Moved to Status-line cross-links in 0009, 0010 and 0015; 0015's own Consequences keep the built/learned/proven bullets the ticket asks for.
  - medium · patch · user-paths A4 said the manual path was "owner-verified 2026-10-14 on production"; entry 2's plan records the agent's walk from the rendered page at the owner's request. Attribution corrected.
  - medium · patch · the story 67 bullet and the walk line called the Update workflow reload a product gap; `pqm-admin.js` swaps the status block a passing ping returns and `render_workflow_status()` reads the version just recorded, so a genuinely outdated workflow shows the button at once. Rewritten as the test artifact it was.
  - low · patch · the 6.7 pointer line left its plan's HITL question (does the single GET carry `{id, name}`) to inference; the quoted sentence named the credential, which the code only does with a name. Written out.
  - low · patch · the memory index said entry 11 done (PR #144) while the detail file's Entry 11 line (untouched on this branch) still named the branch; aligned with the text PR #145 writes, so the rebase resolves clean.

## Design Notes

Forcing the failure at `activate` by webhook-path collision is the one failure a real instance produces without touching n8n's configuration: the first run's active workflow holds `pqm-execute-<host-slug>`, a second workflow on the same path and method is refused on activation. It exercises the full rollback order (deactivate, delete workflow, delete three credentials) and the `removed()` 2xx-or-JSON-404 rule, and it is exactly the case n8n 2.42.3's publication outbox makes timing-dependent, so it settles entry 5's `unknown` by observation.

## Verification

**Commands:**
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` -- expected: green, counts unchanged (sanity: no code touched).

**Manual checks (if no CLI):**
- **HITL walk, 2026-10-16, on the owner's Local sandbox (a copy of their production site on Local, WordPress 7.1.3, Pro build of main `9cf6f03`, Application Passwords available) against the owner's own n8n instance (2.42.x, API enabled), named by the owner as a dry run.** The owner typed three values in the browser (the n8n URL, a dedicated API key expiring in 30 days, the Anthropic key) and pressed every button; the agent read the sandbox with WP-CLI and n8n through the plugin's own client (the stored key never left PHP), and wrote to n8n only to mis-wire the sandbox's workflow and to clean up. Results, one line per matrix row:
  - Connect: "Connected to n8n. Checked 10/16/2026, 4:04:36 PM."; `pqm_n8n_api.probe.ok` true, key hint shown. PASS.
  - Provision: three credentials (`Post Quality webhook - <host>`, `Anthropic - Claude`, `WordPress - <host>`) and one active workflow `Post Quality — <host>` on path `pqm-execute-<host-slug>` created at 14:09:08–09 UTC, every node linked by id (trigger → webhook, six Claude nodes → Anthropic, Update WP Post → WordPress); map `{wizard_created: true}`; one Application Password for the logged-in Administrator; webhook URL saved; ping OK, version 6 recorded; the Anthropic key in no option. PASS. (The owner lost the dialog's result message on a misclick; the status block carried the state.)
  - First review: execution 278 ran Webhook Trigger → Parse Input → Route Command → Build Review Body → Claude - Review Agent (13 s) → Parse Review Result → POST Result to WordPress → Has Notification URL?; the callback node got `getaddrinfo ENOTFOUND <sandbox host>` and continued (template `onError: continueRegularOutput`), so n8n reported success, no review row was stored, and the wizard kept waiting. NOT PROVEN on this environment, as planned: the landing needs an internet-reachable fresh site.
  - Forced failure: with the map and the saved URL deleted on the site, the second provisioning run created a second workflow and three credentials, n8n refused `activate` with HTTP 409 (the first workflow holds the path); the dialog said "The n8n setup stopped while activating the workflow (n8n answered HTTP 409). Nothing was left in n8n. Try again in a moment. Enter the key again."; afterwards n8n held exactly the first run's four objects, `pqm_n8n_leftovers` absent, the second run's Application Password revoked, the first run's kept. PASS (CAP-3 on a real instance). Entry 5's `unknown` settled: a workflow whose activation was refused was never published, so its deactivate and delete passed at once; the "still being unpublished" 409 window did not occur on this shape.
  - Mis-wiring, list path (6.7): with the URL restored and no map, Send test ping found the workflow by its webhook path, read its nodes, judged them clean, recorded version 6. PASS.
  - Adoption (6.8): the offer "Your workflow was set up by hand. Let the plugin manage it…" under the connected summary; the proposal "Found at your webhook URL: Post Quality — <host>, listening on /webhook/pqm-execute-<host-slug>." with the three roles and their credential names, "Nothing in n8n changes.", Manage this workflow / Cancel; after confirm "The plugin has managed this workflow since 10/16/2026." with Rotate the webhook token; map `{wizard_created: false, at 14:36 UTC}`; the workflow's `updatedAt` unchanged. PASS.
  - Mis-wiring, setup-map path (6.7): the agent PUT the webhook credential onto Claude - Review Agent; Send test ping showed "In n8n, the webhook credential "Post Quality webhook - <host>" is set on Claude - Review Agent instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again." as a notice-error with Test again the one primary. PASS (first run of the by-id read against a real workflow).
  - Update workflow (6.9): the stored version set to 5 by the agent to force the notice (test artifact: the owner's passing ping against the v6 workflow correctly re-recorded 6 and erased the first planted 5, so it was planted again and the page reloaded without a ping; on a genuinely outdated workflow the ping's swapped status block shows the button at once); after the reload both Test again and Update workflow were shown; Update workflow → "Workflow updated. Ping OK — this site reaches n8n, the webhook credential is right and your workflow is up to date. Your first review confirms the return path."; the workflow's `updatedAt` moved to 14:44:26 UTC, the Review node back on the Anthropic credential, ids and path kept, version 6 recorded, the mis-wiring sentence cleared. PASS (first real-instance PUT).
  - Rotation (6.10): "Token rotated. Ping OK — …"; the webhook credential's `updatedAt` 14:44:46 UTC, the workflow's unchanged; the webhook answered 403 to the old token and 200 `{"ok":true,"workflow_version":6}` to the new one. PASS.
  - Cleanup: deactivate 200, DELETE workflow 200 at the first try, DELETE each credential 200; Forget key; map, URL, version record and setup progress deleted; the walk's Application Password revoked. n8n holds no object named for the sandbox's host. PASS. The `local-agent` n8n key is the owner's to revoke (30-day expiry).
  - Not walked: the Free build (harness-only, owner decision); the manual path by choice (6.2, walked 2026-10-14 on production); the first review landing on a fresh site (above).
