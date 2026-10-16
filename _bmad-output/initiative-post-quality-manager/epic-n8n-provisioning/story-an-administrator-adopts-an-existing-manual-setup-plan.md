---
title: 'An Administrator adopts an existing manual setup'
type: 'feature'
ticket: '8'
created: '2026-10-15'
status: done
baseline_revision: '2db32f40c6ca497d0be13a48163cd7427514c650'
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

**Problem:** A site whose workflow was set up by hand (the owner's production site is one) has no setup map, so the plugin cannot update that workflow in place (6.9) or rotate its token (6.10), and the wizard keeps calling it hand-made even with a passing key. The spec's CAP-4: adopt it without deleting or changing anything on n8n.

**Approach:** With a key stored and a webhook URL saved, the n8n Instance section offers **Let the plugin manage this workflow**. The plugin reads the active workflow listening on the saved URL's path (the read 6.7 already does), derives the credential mapping from the workflow's own nodes (the trigger's credential is the webhook one, the Claude nodes' the Anthropic one, Update WP Post's the WordPress one), shows it by name, and once the owner confirms stores the same setup map provisioning stores, with `wizard_created: false`. n8n is only read.

## Boundaries & Constraints

**Always:** GET calls only; every n8n reply is metadata (ids, names, types), never credential data. The workflow is found by the saved URL's path, never by name; credential names are shown, never matched. The map is stored only on the owner's confirm, and a confirm stores what n8n holds at that moment, refused when the workflow changed since the proposal. The map keeps the shape `provision()` stores, so entries 9 and 10 read one shape. **Map vs saved URL (6.7's finding 4):** the map names the workflow the plugin manages (its `path`); the saved URL names the workflow the site calls; the two agree at provisioning and at adoption. The diagnosis reads the map's workflow only while the URL's path equals the map's `path`, else the list read: the status block always describes what the site calls. Entries 9 and 10 act on the map's workflow and show their actions only while the paths agree. Copy per `docs/brand.md`.

**Never:** write to n8n (no POST, PUT, PATCH, DELETE); call `GET /credentials` or offer a pick list (D1); drop or rewrite an existing map (a map refuses adoption and provisioning alike, 409 as today); un-adopt (skipped, Design Notes); change the status block, the template, `PQM_WORKFLOW_VERSION`, the wizard's steps or `GET /setup`'s rules; rebuild the page (story 67).

## I/O & Edge-Case Matrix

`GET /n8n/adoption` answers the proposal, `POST /n8n/adoption {workflow_id}` stores the map. Both `pqm_manage_settings`. Reads: the list read of 6.7 (`GET /workflows?active=true&limit=250&excludePinnedData=true`, published nodes, disabled skipped); the trigger's `credentials.httpHeaderAuth`, each Claude node's `credentials.httpHeaderAuth`, the `httpBasicAuth` node's `credentials.httpBasicAuth`.

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Offer | Key stored with a passing probe, webhook URL saved, no map | `public_state()` gains `adoptable: true`, `setup: null`; the island shows the offer and its button | — |
| Not offered | No key, a failing key, no URL, or a map | `adoptable: false`; with a map, `setup: {wizard_created, at}` and the island's managed line instead of the offer | — |
| Proposal | The hand-made workflow at the URL's path, credential names free ("Header Auth account", "my claude key", "wp basic"), trigger path `pqm-execute` (v5) or custom | `{workflow: {id, name, path}, credentials: {webhook: {id, name}, anthropic: {id, name}, wordpress?: {id, name}}}` (+ `rewrite_ready` on Pro); one GET | — |
| Not adoptable | No key or failing probe; no URL saved; a map exists | 400 `pqm_n8n_not_connected`; 400 `pqm_webhook_url_missing`; 409 `pqm_already_provisioned` (provision's message) | Zero calls |
| No workflow | No active workflow whose trigger path is the URL's | 404 `pqm_workflow_not_found`: check the URL, or publish the workflow in n8n, then try again | — |
| n8n did not answer | WP_Error, non-2xx, non-JSON | 502 `pqm_adoption_failed`, `stopped()`'s sentence | n8n's body never repeated |
| Mis-wired | 6.7's two shapes on the trigger or a Claude node (shared credential; none) | 409 `pqm_workflow_miswired`, the 6.7 sentence (fix in n8n, publish, then try again) | — |
| Two Anthropic credentials | The Claude nodes carry different `httpHeaderAuth` ids | 409 `pqm_workflow_miswired`, a sentence naming the nodes: pick one credential on all of them, publish, then try again | — |
| Rewrite unwired (Pro) | Update WP Post (Rewrite) has no `httpBasicAuth` id, or the Free variant has no such node | Proposal without `wordpress`, `rewrite_ready: false` (D2); the map stores no `wordpress`, so 6.7's D1 then skips that node | — |
| Confirm | `POST {workflow_id}` = the proposal's id | Map stored: `{workflow_id, credentials: {webhook, anthropic, wordpress?}, path: <the trigger's path>, wizard_created: false, at}`; reply = `public_state()` (+ `rewrite_ready` on Pro); nothing else written (report, progress, settings untouched) | — |
| Changed since | Posted id ≠ the fresh proposal's, or the fresh read fails | 409 `pqm_adoption_changed`: n8n changed since you looked, look again; or the read's own error | Nothing stored |
| Wizard after | Map stored, URL's last ping passed | `GET /setup`: `provisioned` true, `manual` false, the key path with both n8n steps ticked (existing rules) | — |
| Diagnosis after | Test ping of the saved URL | `GET /workflows/{id}` (paths agree); the URL later changed to another path → the list read, map kept | — |
| Forget key after | Key forgotten | Map stays; `adoptable` false; the managed line stays; 6.9 and 6.10 need a key again | — |

## Decisions (owner, 2026-10-15)

Approved as proposed at the plan checkpoint; the full plan kept (~3,000 tokens).

- **D1 — mapping from the nodes, no pick list:** the spec's "lists credentials by metadata" is met by showing the workflow's own credentials by name; `GET /credentials` is not called. A workflow whose wiring the diagnosis would report is refused with that sentence: the owner fixes it in n8n first (6.7 proved that path on the owner's site). Names are never matched, so free names work (the ticket's verify).
- **D2 — Pro, Rewrite unwired:** adopted with Rewrite off (the map without `wordpress`), the reply saying so, as 6.4's D1 does for provisioning.
- **D3 — map vs URL:** the rule in Boundaries; one branch in `diagnose()`, a rule handed to 6.9 and 6.10.

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `diagnose()` L335 (the setup-map read at L344, where D3's path comparison goes: `$setup['path']` vs the URL's segment, the segment computed as `published_nodes_at()` L412 does), `published_nodes_at()` (extend to return the workflow's `id`, `name` and published nodes, so adoption and the diagnosis share one finder), the two shapes' loop L357–L373 (extract into one private method answering the sentence for given nodes, so adoption reuses it; `diagnose()`'s behaviour and its harness rows unchanged), `published_nodes()`, `trigger()`, `has_credential()`, `answered()`, `request()` L159, `stopped()` L791 (the 502 sentence), `public_state()` L84 (+ `setup`, `adoptable`; `adoptable` = key with a passing probe, `pqm_get_settings()['n8n_webhook_url']` non-empty, no `SETUP_OPTION`), `provision()` L497 (the 409 to reuse). New public `propose_adoption(): array|WP_Error` and `adopt(string $workflow_id): array|WP_Error` go here.
- Node facts (both templates, 6.7's plan): trigger `n8n-nodes-base.webhook` with `credentials.httpHeaderAuth`; Claude nodes `httpRequest` whose `parameters.url` contains `api.anthropic.com`, `credentials.httpHeaderAuth`; Update WP Post (Rewrite) `genericAuthType: httpBasicAuth`, Pro template only. Node `credentials` = `{type: {id, name}}` (confirmed on the owner's instance 2026-10-15).
- `post-quality-manager/includes/class-pqm-rest-api.php` -- routes L147–L178 (`/n8n/connection` GET/POST/DELETE, `/n8n/provision`): add `/n8n/adoption` GET + POST (`workflow_id` required, string) beside them, `check_manage_settings`; callbacks follow `connect_n8n_callback()` L1321 and `provision_n8n_callback()` L1369 (`rewrite_ready` under `pqm_fs()->is__premium_only()`); `setup_state()` L1048 reads the map as `provisioned` — unchanged.
- `src/n8n-connection/index.js` -- state from `GET /n8n/connection`; `send()` L89 (busy, failure Notice, `onChange`); the actions row L200–L232 (Connect primary unless `reimportShown`; Check again, Forget key secondary). Add: the managed line (`state.setup`), the offer paragraph + **Let the plugin manage this workflow** (secondary, `state.adoptable`), the proposal (replaces the form while open: workflow name and path, the three roles by name or "none: Rewrite stays off", **Manage this workflow** with Connect's variant rule, **Cancel**), errors in the existing failure Notice; after a confirm `setState` + `onChange` (the wizard re-reads `GET /setup`). `src/setup/steps.js` L383–L458 (Manual body) and L260 (`state.provisioned`): read only, unchanged.
- `tests/harness/n8n-api-harness.php` -- `site()`, `connected()` L498 (+ `$extra` options), `wired()` L464 (credential name → `{id, name}` or null for name-only), `with_path()`, `stored_workflow()`, `calls()`, `calls_signed()`, `$replies` (the leak scan), `LIST_READ`, `READ_WF`, `$setup_map` L861; the entry 1 reply-shape row L257 and `get_state()` fixtures gain the two keys. The "map wins over the URL" row L908 becomes D3's two rows (paths agree → `READ_WF`; differ → `LIST_READ`).
- `tests/harness/free-build-harness.php` L327, L369, L467 -- the Free shape's `connected()` rows: one adoption row (no Update WP Post node → no `wordpress`).
- `tests/harness/setup-harness.php` -- `GET /setup` rows; mirror the provisioned-map row for an adopted map.
- `tests/js/n8n-connection.test.js` -- `mount()`, `CONNECTED` / `NOT_SET` fixtures (add `setup: null, adoptable: false`), `typeInto()`, `submit()`.
- `docs/policies/privacy.md` L14, L24 and `docs/policies/readme-external-services.md` L12, L26 -- "the IDs of the workflow and credentials it created there" gains "or that you let it manage"; the site copy follows through story 68.
- `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md` -- one line each. `class-pqm-activator.php` L50 already deletes the map on uninstall.

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/includes/class-pqm-n8n.php` -- the finder returns the workflow (id, name, nodes); the wiring rules become one method over nodes; `diagnose()` gains D3's path check; `propose_adoption()`: the gates (key, URL, map), the read, the wiring check (D1; the two-credentials rule with its own sentence; D2 for `httpBasicAuth`), the proposal; `adopt($id)`: `propose_adoption()` again, id compared, `update_option(SETUP_OPTION, …, false)`; `public_state()` + `setup`, `adoptable`.
- [ ] `post-quality-manager/includes/class-pqm-rest-api.php` -- `/n8n/adoption` GET + POST, the two callbacks, `rewrite_ready` on Pro.
- [ ] `src/n8n-connection/index.js` -- the managed line, the offer, the proposal and confirm, per the Code Map; copy per brand (plain, the action named, "Nothing in n8n changes.").
- [ ] `tests/harness/n8n-api-harness.php` -- an "Entry 8" section, one row per matrix line, fixtures through `wired()` with free credential names and `with_path()`; assert every call of the section is a GET, the stored map's exact shape, `$replies` fed. `free-build-harness.php`, `setup-harness.php` -- the rows named in the Code Map.
- [ ] `tests/js/n8n-connection.test.js` -- offer shown only when `adoptable`; click → GET proposal rendered by name; confirm → POST with the id, the managed line, `onChange` called; a refused proposal shows its message in the failure Notice.
- [ ] `docs/policies/*.md`, `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md` -- the lines named in the Code Map.

**Acceptance Criteria:**
- Given a site set up by hand with a passing key and a saved URL, when the owner opens the n8n Instance section, presses **Let the plugin manage this workflow** and confirms what is shown, then `pqm_n8n_setup` holds the workflow's and credentials' ids with `wizard_created: false`, the n8n workflow's `updatedAt` is unchanged, the wizard shows the key path ticked, and a test ping reads `GET /workflows/{id}` (hitl, on the environment the owner names).
- Given any run of the adoption harness section, then no call other than GET reached the transport stub and no reply carried the n8n key.

## Implementation Notes

- One finder, `PQM_N8n::workflow_at()` = `{id, name, path, nodes}`, null when nothing listens there, a 502 `pqm_adoption_failed` WP_Error when n8n did not answer (the diagnosis reads both as "could not run"); workflows without an id are skipped. `webhook_segment()` is the URL rule both D3 and the finder use.
- The wiring rules are `miswiring($nodes, $skip_basic, $adopting)`: one full translatable string per sentence and ending (false = the diagnosis, its three sentences as on main, "then test again."; true = adoption, "then try again."). The diagnosis output and msgids are unchanged (review fix).
- Beyond the matrix: a workflow at the URL without a Claude node answers the 404 (not a Post Quality workflow); a trigger without a credential, or Claude nodes on another credential type, answer the 409 with the missing sentence. The 502 sentence ends "Try again in a moment.", and `stopped('read')` says "n8n did not answer with your workflows" for a non-JSON 2xx.
- D3: when the paths differ the map is ignored entirely, its D1 skip included (harness row).
- The offer never shows inside the setup guide: `adoptable` needs a saved URL, and the guide mounts the island only while none is saved. `sameConnection()` in `src/setup/index.js` compares `base_url`, `key_set` and `probe` only, so an adoption's `onChange` would not re-read GET /setup there; left unchanged as unreachable.
- A refused confirm closes the proposal (the offer shows again, to look again); a refused proposal keeps the form.
- Review fixes: `adopt()` writes with `add_option()` and answers 409 `pqm_already_provisioned` when a map appeared during its read (harness row); the changed-since message names the offer button; focus moves to the proposal when it opens, and on close to the offer button, else the managed line.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, 2026-10-15): 7 lens findings + 3 from the build's own report and the diff read: 0 high, 1 medium, 7 low, 2 false, 0 maybe-false. 4 patches, 1 deferred, 5 rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | AC1's HITL walk not recorded | false | reject | Not a code defect: the walk is the owner's, on their production site, after the build; carried to step 05 as open. AC2 verified met (22 adoption calls, all GET; 136 replies scanned). |
| 2 | `adopt()` compares the workflow id only: credentials swapped between proposal and confirm are stored unseen | low | reject | Real, but the Boundary says a confirm stores what n8n holds at that moment, which is what 6.9 and 6.10 must act on; one owner swapping a credential in the seconds between is unlikely, and the fix adds a POST parameter. |
| 3 | Check-then-write race: a `provision()` storing its map during the fresh GET is overwritten | low | patch | Verified: `adoption_blocked()`, then up to 10 s of GET, then an unconditional `update_option()`. Reachable only by a concurrent REST provision (the UI never offers it to a hand-made site with a URL), but the Boundary forbids rewriting a map and the fix is direct: `add_option()`, 409 on false. |
| 4 | The changed-since error says "Look again", a control that does not exist | low | patch | Verified: a refused confirm closes the proposal; the offer's button is Let the plugin manage this workflow. Copy names that button (brand: errors name their action). |
| 5 | Wrong-type credentials (Basic Auth trigger, `anthropicApi` Claude nodes) refused with "no credential is set" | low | reject | Verified in `propose_adoption()` (`credential_of(…, 'httpHeaderAuth')` null → `no_credential()`). Refusing is right (6.9 relinks by id as httpHeaderAuth); the copy misleads only on a node whose authentication the owner changed from the template's; a fix adds a branch and a sentence. |
| 6 | Pro site with a Free-variant workflow: "set a credential on Update WP Post (Rewrite)", a node that does not exist | low | reject | Verified: no `httpBasicAuth` node → no `wordpress` → that line. The status block already tells such a site to re-import the Pro workflow; telling the two cases apart needs a new reply field. |
| 7 | Keyboard focus falls to `<body>` when the proposal opens, on Cancel and on a confirm | medium | patch | Verified: the offer renders only while `! proposal`, the proposal only while open; nothing moves focus. Accessibility basics are never simplified away (ladder); 6.7 patched the same class. |
| 8 | The diagnosis and adoption sentences inject "test again" / "try again" as `%3$s` fragments | low | patch | Raised in the build's report, verified in the diff: translators get a fragment and a sentence missing its end (WordPress i18n: whole sentences). Direct fix: one full string per ending, `bool $adopting`; the diagnosis strings back to main's. |
| 9 | The setup guide would not re-read `GET /setup` after a confirm (`sameConnection()` compares `base_url`, `key_set`, `probe`) | false | reject | Raised in the build's report. Unreachable: the guide mounts the island only while no webhook URL is saved, and `adoptable` requires one. The Code Map's "the wizard re-reads" line was wrong, the code is not. |
| 10 | The policies name adoption's reads but not 6.7's diagnosis reads on a test ping | low | defer | Raised in the build's report. Pre-existing since entry 6.7 (PR #134): the policy lines never mentioned it; deferred-work entry. |

## Design Notes

Why the nodes and not `GET /credentials`: a pick list asks the owner to say which of their credentials is which; the workflow already says it, and the diagnosis already refuses the two shapes a hand-made import gets wrong. The spike's finding 3 ("names cannot be trusted") holds: names are only shown.

Why the confirm re-reads: the map must describe n8n as it is when stored, not as it was shown; one owner, one n8n, so a changed id is rare and the 409 is honest.

Draft copy (the build writes the final): offer, *"Your workflow was set up by hand. Let the plugin manage it, and you can update it and rotate the webhook token from here later. Nothing in n8n changes."*; proposal, *"Found at your webhook URL: {workflow name}, listening on /webhook/{path}. Webhook token: {name}. Anthropic: {name}. WordPress: {name}"* or *"WordPress: none — Rewrite stays off until a credential is set on Update WP Post (Rewrite)."*; managed line, *"The plugin set this workflow up on {date}."* / *"The plugin has managed this workflow since {date}."*; two credentials, *"In n8n, {nodes} use different credentials: pick one Anthropic credential on all of them, publish the workflow, then try again."*

Skipped: un-adopt (dropping the map) — add when a walk needs it; story 69's Danger Zone removes it with everything else. Skipped: adopting a workflow on another path than the saved URL's — the URL names what the site calls. Skipped: a second page of 250 workflows (6.7's ceiling). Skipped: a `base_url` in the map — the saved URL's origin may differ from the editor's (entry 4).

## Verification

**Commands:**
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` -- expected: every harness passes, no FAIL line.
- `npm run test:unit` and `npm run lint:js` -- expected: green.
- `php -l` on each touched PHP file -- expected: no errors.

**Manual checks (if no CLI):**
- HITL on the owner's production site (named by the owner; a hand-made setup with a key stored since 6.7's walk): the offer shows; the proposal names their workflow and credentials; confirm; `wp option get pqm_n8n_setup` shows the map; in n8n the workflow's updated time is unchanged; the setup guide shows Connect your n8n and Set up the workflow ticked; **Test again** reads the workflow by id (`GET /workflows/{id}`, 6.7's harness-only path, now real). Record the walk here. The ticket carries `hitl = false`; the done-chore may flip it to record the walk.
- **HITL walk, verified 2026-10-16 from the live state, on the owner's production site (named by the owner):** the owner had run the adoption on 2026-10-15 (map `at` 13:23 UTC); the agent read the results at the owner's request. `pqm_n8n_setup` stored: the hand-made workflow's id, the three credential ids (webhook, anthropic, wordpress), `path` = the per-site path, `wizard_created: false` · the n8n workflow's `updatedAt` (2026-10-15T13:05:14Z, read through the n8n API on 2026-10-16) predates the adoption: nothing on n8n changed · `GET /setup`: `provisioned: true`, `manual: false`, the key path with Connect your n8n and Set up the workflow ticked · the version record was written by a test ping 57 seconds after the confirm (`via: test`, version 6): with the map stored and the paths equal, that ping takes the by-id read (`GET /workflows/{id}`, pinned by the harness); n8n's side of that GET was not observed. The n8n Instance section's button checks are the loose plan's (`../plan-n8n-connection-states.md`), still the owner's. **Pass** on every check this entry owns.
- **(superseded) HITL walk: pending as of 2026-10-16.** The owner's walk on their production site (named by the owner), on a zip of main after PR #137 and the section fix PR #138, has not been reported yet. Record here when it is: `pqm_n8n_setup` stored after the confirm; the n8n workflow's updated time unchanged; the setup guide showing Connect your n8n and Set up the workflow ticked; **Test again** reading the workflow by id (`GET /workflows/{id}`, 6.7's setup-map path, its first run on a real site). The n8n Instance section's own checks are recorded in `../plan-n8n-connection-states.md`. The ticket's `hitl` was flipped to true in the done chore, since this criterion is the owner's walk.
- **Owner's walk in the browser, 2026-10-16:** the offer, the proposal (workflow found at the URL, three roles named, "Nothing in n8n changes."), Manage this workflow, the managed line; the map stored with `wizard_created: false`, the workflow untouched — the entry 6.12 walk of 2026-10-16 on the owner's Local sandbox against their n8n instance, recorded in `story-the-success-signal-walk-passes-and-the-records-say-so-plan.md` § Verification.
