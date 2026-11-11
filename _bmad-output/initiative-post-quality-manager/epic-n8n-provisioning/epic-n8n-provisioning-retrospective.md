---
epic: epic-n8n-provisioning
date: 2026-10-16
verdict: accepted-with-open-items
criteria: declared
headless: false
---

# Retrospective — epic n8n-provisioning

Run interactively on 2026-10-16 by the session model (Fable 5.1), the BMad retrospective skill rendered by hand (sessions run from the workspace root). Every finding carries a source; the lenses ran as subagents at the session model over the epic's code diff `40731c6..9cf6f03`, and each claim an action item relies on was re-read in the tree by the orchestrator (noted "verified" below). Paths are relative to the repository root; `N8N` = `post-quality-manager/includes/class-pqm-n8n.php`, `REST` = `post-quality-manager/includes/class-pqm-rest-api.php`, `ADMIN` = `post-quality-manager/admin/class-pqm-admin.php`.

## Epic summary

**Tickets** (build order per `tickets.toml`; `status` / `state` from `tickets.py status`):

| Ref | Title | Status | Range (baseline → next) | Commits |
|---|---|---|---|---|
| 6.1 | Tracer bullet: connect an n8n instance by key | done | 40731c6 → 286358c | 3 |
| 6.3 | Per-site webhook path (template v6) | done | 286358c → 3e0ea3b | 4 |
| 6.2 | Workflow File section as the manual path | done | 3e0ea3b → e50acab | 7 |
| 6.4 | Server-side provisioning | done | e50acab → 302f56b | 6 |
| 6.5 | Rollback of a failed provisioning | done | 302f56b → 15c42f8 | 3 |
| 6.6 | The wizard connects and provisions, or hands over | done | 15c42f8 → b212c4e | 8 |
| 6.7 | Mis-wiring diagnosis | done | b212c4e → f136b3a | 2 |
| 6.8 | Adopt an existing manual setup | done | f136b3a → 08b7c06 | 3 |
| 6.10 | Rotate the webhook token | done | 08b7c06 → 576b68b | 3 |
| 6.9 | One-click template update | done | 576b68b → 431bfac | 4 |
| 6.11 | Refactor sweep | built (done chore PR #145 open) | 431bfac → 9cf6f03 (inferred) | 1 |
| 6.12 | Success-signal walk and records | built (this branch, uncommitted at run time) | 9cf6f03 → HEAD, records only | 0 |

`pending_tickets` is empty: every ticket is `built` or `done`. Two are still at `built`, finished by the build and not yet called done (6.11, 6.12). Ranges follow each plan's `baseline_revision` ordered by ancestry (the 6.3 build started before 6.2's, 6.10's before 6.9's); the last two are inferred to HEAD. Twelve PRs merged (#106, #107, #109, #112, #117, #124, #127, #134, #137, #140, #142, #144) plus the fixes beside the epic (#129 wizard ticks, #138 n8n Instance states, the key hint, the update-notice primary action).

**Evidence inventory.** Available: the epic file (Done when declared, five criteria), the initiative's Requirements (UV4), the spec (`spec-n8n-provisioning/` with `failure-modes.md`, `brownfield.md`, the spike findings), twelve plans with Review Triage Logs and Verification sections, `tickets.toml` (no story files: entries were refined in the toml), the git evidence per range (`git_evidence.py`, no merges in any range since merges are rebase merges), `memory/lessons.md` (three entries dated inside the epic: 2026-10-13, 2026-10-14 ×2), the owner's walk records (production: 6.1, 6.2, 6.7, 6.8, 6.10; the Local sandbox: 6.12). Session logs: the agent's per-session hand-off notes (an agent-local memory file outside the repository, eighteen dated updates over the epic) were read for process lessons; they are not a repository artifact, so a reader cannot re-check them. Missing: no retrospective exists for the previous epic in the initiative's order (`epic-delivery-pipeline`); n8n's live answers were observed only where the walks went (2.42.x, one instance); `bmad-review` is not registered from the workspace root, so its three lenses ran inline as subagents at the session model over the full diff (no narrowing). Going-in concerns: the owner was invited before Phase 1 and had not answered when this document was drafted (Open questions).

**Change volume** (non-merge commits, added/deleted): `tests/harness/n8n-api-harness.php` +1,799/−139 (new, 1,660 lines, 92 functions); `N8N` +1,511/−291 (new, 1,220 lines, 50 functions); `tests/js/n8n-connection.test.js` +1,205/−146; `src/n8n-connection/index.js` +1,007/−333 (674 lines); `tests/js/setup-provision.test.js` +880/−36; `REST` +418/−72 (1,214 → 1,560 lines); `ADMIN` +341/−144 (1,802 → 1,999 lines); `src/setup/steps.js` −111 net (the manual steps removed). Code: 36 files, +8,500/−1,028.

## Findings

Dispositions: **fix now** (an action item below), **defer** (recorded with what would settle it), **accept** (a recorded deviation; later retros stop re-flagging it).

### Aggregate view — architecture delta (inline, full scope)

- A new class `PQM_N8n` (`N8N`, 1,220 lines, 50 functions) is the epic's centre: called from `REST` (25 call sites), `ADMIN` (4), `pqm-functions.php` (2), the activator (3) and four harnesses. It holds the HTTP client, the probe, provisioning, rollback, the diagnosis, adoption, update and rotation in one file; the sweep (6.11) chose to keep it one file because the harness reads it by source regexes (`story-refactor-sweep-plan.md` Design Notes). **Accept** for now; the duplication map below says where a split would pay.
- The vanilla page script gained its first REST call (`pqm-admin.js` `initUpdateWorkflow()` → `POST /n8n/workflow` with `X-WP-Nonce`, localized in `ADMIN:186-187`) beside the two AJAX handlers `AGENTS.md` describes for that page. Verified. **Defer** to story 67, which rebuilds the page.
- Three standalone options (`pqm_n8n_api`, `pqm_n8n_setup`, `pqm_n8n_leftovers`; `N8N:33,56,69`) beside the one `pqm_settings` array `AGENTS.md` "Conventions" describes; the spec requires the separate option for the URL and key only (`spec:53`), the other two come from plans 6.4 and 6.5 (D1). **Accept** (recorded decisions); `AGENTS.md` should name them (action A12).

### Aggregate view — duplication map (subagent, full scope; items 1, 2, 4, 7 verified by grep)

1. The three deterministic credential names are spelled in the template (`n8n-workflow.json:23,146,571`), in `PQM_N8n::credential_names()` (`N8N:774`) and again in `PQM_Admin::render_workflow_file()` / `render_wordpress_credential__premium_only()` (`ADMIN:1564-1565,1591,1694`). The template guard does not pin the names. **Fix now** (A11).
2. The `X-PQM-Token` header name: `pqm_n8n_headers()` (`pqm-functions.php:468-481`, headed "single source of truth") plus literals in `provision()` (`N8N:711`), `rotate_token()` (`N8N:1040`), `check_webhook_token()` (`REST:299`), the copy row (`ADMIN:1636`) and the template. **Fix now** (A11).
3. Origin parse-and-rebuild twice: `PQM_N8n::origin()` (`N8N:157-172`) and `PQM_Admin::n8n_editor_url()` (`ADMIN:1751-1764`). **Fix now** (A11).
4. The "key connected" predicate in five spellings: `connected_key()` (`N8N:1218`, key set and `probe.ok`), `pqm_manual_reason()` inline, `setup_state()` (`REST:1078`, key set and `!empty(probe)` without `ok`), `keyPasses()` (`src/setup/steps.js:38`, `key_set && probe`), `isConnected()` (`src/n8n-connection/index.js:67`, with `ok`); `provision()` and `diagnose()` test only a non-empty key. They agree today because `save()` stores passing probes only. **Fix now** (A11, with A4 which changes what gets stored).
5. The 2xx range check inlined seven times in `N8N` although `answered()` exists (`N8N:247,530,750,837,893,977,1190`). **Fix now** (A11).
6. The setup-map existence gate twice (`N8N:638,687`); the sweep recorded folding the gates as skipped. **Accept**.
7. The REST post-ping tail (`pqm_record_workflow_version(…)` + `status_html`) five times in `REST` (1248, 1427-1429, 1513-1514, 1548-1549 and the connect route). **Fix now** (A11).
8. The status-block swap twice (`swapWorkflowStatus()` in `pqm-admin.js` with focus handling; `swapStatusBlock()` in `src/kit/swap-status-block.js` without) and two renderers for sibling outcomes: Update workflow's result in a vanilla `.pqm-test-result` line, Rotate's in a React `<Notice>` (`src/n8n-connection/index.js:519`). **Defer** to story 67 (the page rebuild decides which side renders the status).
9. The webhook URL composed on both sides (`REST:1407` saves it; `src/setup/index.js:239` recomputes it from `base_url` and `webhook_path` because the reply omits it). **Fix now** (A11: the reply carries the URL).
10. The "Create one in n8n under Settings → n8n API" hint in three places (`N8N:306`, `REST:1363`, `src/n8n-connection/index.js:331`); the REST-rejection fallback sentence as a helper in one island and inline in the other. **Defer** to story 67's copy pass.

### Aggregate view — pattern divergence (subagent, full scope; items 3, 4, 8 verified)

1. REST arg sanitizing moved into callbacks for the six n8n routes (`REST:148-210` declare `type` only, sanitize at `REST:1355-1356,1402,1467`) where older routes declare `sanitize_callback` per arg; the precedent is `/setup/connect` (story 36). **Accept** (the 2026-10-05 lesson on PHP internals as sanitize callbacks made closures the safer form; name it in `AGENTS.md`, A12).
2. `'methods' => 'DELETE'` literal with a comment (`REST:164`) beside `WP_REST_Server::*` constants elsewhere. **Fix now** (A11, one line).
3. `WP_Error` codes without the `pqm_` prefix in `origin()` (`invalid_url`, `insecure`; `N8N:163,166`), which become probe `cause` values. **Accept** (they are causes, not REST codes; the harness pins them).
4. Message endings multiplied: "then try again." (pre-epic), "then test again." (`N8N:412,422,441`), "Try again in a moment." (`N8N:317,846,928,1002,1149,1156`), "Nothing was sent." The sweep kept the try/test pair on purpose (`story-refactor-sweep-plan.md` Design Notes). **Defer** to story 67's copy pass against `docs/brand.md`.
5. The re-check button named three ways: "Check again" (island), "Test again" (status block, renamed in 6.7), "Save and check" vs "Save and test". **Defer** to story 67.
6. Harness granularity: pre-epic harnesses are one file per concern; `n8n-api-harness.php` is one 1,660-line file with per-entry world builders (`connected()` :529, `hand_made()` :1052, `managed()` :1270); `render-matrix-harness.php` grew +382 to 1,273 lines. **Fix now** as a process item (A13).
7. Stub drift between harnesses the epic edited: `sanitize_text_field` is `trim(strip_tags())` in `n8n-api-harness.php:87` but `(string) $s` in `setup-harness.php:60` and `render-matrix-harness.php:59`; `get_option` reads `$GLOBALS['tw']['options']` in two and `$GLOBALS['tw_options']` in the third. **Fix now** (A13; the freemium retrospective's item A3, a shared stub bootstrap, is still open).
8. `src/n8n-connection/index.js` is one 674-line component with a `focusTo` state machine, a per-block failure map and a `call()` wrapper, while `steps.js` in the same epic keeps the older inline `.then(ok, err)` pattern. No inline styles anywhere in the epic (rule kept). **Defer** to story 67.

### Aggregate view — spec-to-implementation reconciliation (subagent; items 1, 7, 8, 10 verified)

1. CAP-4 narrowed: the spec says the plugin "lists the instance's workflows and credentials by metadata, proposes a mapping"; built `propose_adoption()` reads only the workflow at the saved URL's path and never calls `GET /credentials`. Recorded as D1 of the 6.8 plan. **Accept**; the spec and `memory/n8n-provisioning.md` line 25 still carry the old wording (A14).
2. "A connected site with a green handshake defaults to keep existing" (`spec:56`) became: a hand-made site stays on the manual path even with a passing key, adoption offered from the page island only. Recorded in `plan-wizard-ticks-on-a-hand-made-site.md` D1 and the 6.6 plan. **Accept** (A14 reconciles the spec).
3. "Never changed without explicit confirmation" (`spec:56`) vs one-click Rotate (recorded, 6.10 plan D3) and one-click Update workflow on an adopted workflow (no decision recorded in the 6.9 plan). Rotation: **accept**. Update on an adopted, hand-edited workflow: **fix now** (A6).
4. The Application Password self-call (`failure-modes.md:19`) dropped, the credential Pro-only and optional: recorded as 6.4 plan D1/D2 and in memory. **Accept** (A14 closes the spec's open question).
5. CAP-3 "verified through the API" shipped harness-only (6.5 review row 4 rejected as a plan edit) and was walked in 6.12 for one step (activation refused). **Accept**; the per-step real-instance forcing stays harness-only (Open questions).
6. CAP-5 built as a conditional primary action (Update workflow when `managed_blocked()` is null) with the re-import notice kept elsewhere; the re-import primary action came from a loose plan (`plan-update-notice-primary-action.md`, commit d131f1c) the epic file's Notes do not list. **Accept**; one line in the epic Notes (done chore, A15).
7. Workflow name: the spec says `Post Quality - <host>`; the template carries `Post Quality — <host>` (em dash) while the credentials use a hyphen; no guard or plan mentions it. **Fix now** (A14: reconcile the spec to the template, since adopted workflows keep their names anyway).
8. The epic file's Notes still carry the 6.1 assumption "the n8n version is read from `GET /rest/settings` (`versionCli`)" (`epic-n8n-provisioning.md:78`); the probe found no version is exposed to an API key, the spec was amended, the Note was not. **Fix now** (A15, the done chore).
9. Behaviour added beyond the spec, each with a record: the leftovers record (6.5 D1), the key hint (`plan-n8n-key-hint.md`), adoption's third refusal (Claude nodes on different credentials; 6.8 plan). **Accept**.
10. Overlapping failure rows: `failure-modes.md:8` (API disabled: 401 or 404) and `:11` (key rejected: 401) both claim 401; the code maps 401 → `key`, 404 → `api_disabled`, so a disabled API answering 401 is reported as a refused key. No plan names the overlap. **Fix now** (A14 reconciles the table to the code; A4 adds the route-probe 401 row).

### Lens — adversarial (subagent, 12 findings; 1, 2, 4, 7, 10 verified in the tree)

1. **The setup map outlives the instance; only uninstall clears it.** `provision()` (`N8N:687-689`) and adoption (`N8N:638-640`) refuse on any map; `forget()` (`N8N:121`) keeps it; the "workflow is gone" sentence (`N8N:1109-1110`) offers two refused actions; only `class-pqm-activator.php:50` deletes `pqm_n8n_setup`. A site that switches instance or loses its workflow is locked out of provision, adoption, update and rotation; the dead map also makes `diagnose()` return null forever, so a stored mis-wiring sentence can never clear (edge-case lens E2). **Fix now** (A1).
2. **Concurrent provisioning overwrites the map silently.** `N8N:761` stores with `update_option()` while `adopt()` (`N8N:625`) uses `add_option()` for exactly this race; two runs both pass the `:687` gate and the first run's workflow and credentials (Anthropic key inside) stay in n8n with no map and no leftovers record. No harness row. **Fix now** (A2).
3. **Leftovers on a previous instance are dropped without a word** (`N8N:991-998`: another `base_url` → record deleted). **Defer** into A1 (the instance-switch story says what happens to the old instance's objects).
4. **A revoked key is never recorded; the plugin keeps claiming connected.** The key failure is built with `result()` (`N8N:305-306`), not `manual()`, so `record_failure()` (`N8N:133-135`) returns early and the stored passing probe stays; `connected_key()` keeps offering Update, Rotate and Adopt, whose 401 is rendered as "n8n answered HTTP 401. Try again in a moment." **Fix now** (A4).
5. **Token rotation breaks in-flight runs**: `check_webhook_token()` (`REST:291-307`) compares the current token only; a review started before the rotation returns the old token and `/save-review` answers 403, the result lost without a notice. **Defer** (owner decision: a short grace window for the previous token, or copy that says "finish running reviews first"; Open questions).
6. **The Settings form re-saves a stale token or URL** over a rotation or provisioning (`ADMIN:375,369` write the posted fields; no version check). Story 67 moves the URL to the Workflow page; the token field should stop being posted. **Fix now** inside story 67 (A7).
7. **The provisioning Application Password is untracked and bound to whoever clicked.** The uuid is captured for the failure path only (`N8N:875`); the stored map keeps role → id (`N8N:754-761`), so nothing revokes the password on Forget, a later provisioning or uninstall, and it carries the wizard user's full capabilities (an Editor if the Roles tab granted `pqm_manage_settings`). **Fix now** (A3).
8. **One click replaces an adopted, hand-edited workflow with the template, unconfirmed** (`N8N:1129-1135`, `pqm-admin.js:424-435`, copy at `ADMIN:1527`). **Fix now** (A6).
9. Entries 7 and 9 assume opposite n8n save semantics in comments (`N8N:340-342` "a save changes only the draft" vs `:1086-1087` "n8n republishes a PUT workflow"). Settled by the 6.12 walk: the PUT republished (the workflow stayed active, the ping reported 6, `activeVersion.nodes` showed the repaired node). **Fix now** as a comment correction (A11) and a memory fact (A15).
10. **The stored base URL is a server-side request target with no network restriction**: any https host, `wp_remote_request()` not the `wp_safe_remote_*` family (`N8N:213`), fixed `/api/v1` paths, a status-and-JSON oracle in `failure()`; reach is `pqm_manage_settings`, grantable beyond administrators. **Defer** (owner decision: self-hosted n8n often lives on a private network, which `wp_safe_remote_*` would refuse; Open questions).
11. "Never in a log" is not something the client can enforce: both keys pass through `WP_Http` hooks that any HTTP logger records. **Accept** with a docblock correction (A11): the plugin never logs them itself.
12. Only the path, never the host, ties the saved webhook URL to the connected instance (`N8N:452-457,1068-1069`); the lens itself calls it a known hole because `WEBHOOK_URL` can differ from the editor origin. **Defer** into A1 (the map should remember its instance's `base_url`, which closes most of it).

### Lens — edge cases and verification gaps (subagent, 12 findings; E3, E7, E9 verified)

- E1, E2, E6 restate adversarial 2, 1 and 8 (two lenses, same defects: signal). Routed with them.
- E3. **A passing Connect to another origin keeps the old map**: `managed_blocked()` (`N8N:1059-1074`) compares the webhook path only, so `rotatable` stays true and Rotate/Update send the old instance's ids to the new one. Verified: the gate reads `setup.path` and the URL segment, never `base_url`. **Fix now** (A1: the map stores `base_url`; the gates compare it).
- E4. A route probe answering 403 (a scoped key) falls to the `proxy` cause, "a proxy or firewall in front of it is the likely cause". **Fix now** (A4).
- E5. `rotate_token()` PATCHes the webhook credential without reading the workflow; if the Claude nodes share it (the mis-wiring shape), the rotation rewrites it and breaks the Claude calls; the harness asserts rotation proceeds with the sentence stored. **Fix now** (A4: refuse rotation while a mis-wiring sentence is stored, zero calls).
- E7. Claude nodes on a credential of another type (n8n's native `anthropicApi`) are reported as "no credential is set" (`N8N:575-580`, whose comment names the case). **Accept**, copy candidate for story 67 (A7).
- E8. A second active workflow on the same webhook path makes `activate` fail and the owner reads "n8n answered HTTP 409 … Try again in a moment", which a retry repeats. Observed in the 6.12 forced failure: the step was right, the fix sentence wrong for this cause. **Fix now** (A5).
- E9. The page's n8n Instance island reads `/n8n/connection` once on mount and the wizard uses its own instance (`ADMIN:1374,1380`), so after the wizard connects, provisions or adopts, the page island still shows the old state and its offer answers 409. Verified: one root on the page, one inside the dialog, no shared state. **Fix now** inside story 67 (A7).
- V1. A 401 on the route probe (key revoked between the list call and the probes) is reported as `unexpected`, not `key`; no harness row. **Fix now** (A4).
- V2. Restates adversarial 3.
- V3. `setup_state()`'s `$key_ok` is a weaker predicate than `connected_key()`; agree today only because `save()` stores passing probes. **Fix now** (A11 with A4).

## Behavior verification

Exercised end to end, by the owner in the browser unless stated:

- **6.12 walk, 2026-10-16, the owner's Local sandbox (a copy of their production site, Pro build of main 9cf6f03) against their own n8n instance**, three values typed, no n8n UI visit: connect; provisioning (three credentials, one active workflow, every node linked by id, map, Application Password, ping version 6); a forced failure (activation refused with 409 for the held path → rollback left nothing, the Application Password revoked, no leftovers); the diagnosis on the list path (clean) and the setup-map path (a hand-made mis-wiring reported in one sentence); adoption of the map-less workflow (nothing on n8n changed); Update workflow (the first real PUT: ids and path kept, the mis-wired node repaired, version 6, workflow still active); rotation (old token 403, new 200); cleanup by id. Not proven: the first review landing on a fresh site (the instance cannot call a `.local` host back; n8n ran the review, the callback failed with ENOTFOUND, the template's callback node continued on error, n8n reported success and the wizard waited without end). Record: `story-the-success-signal-walk-passes-and-the-records-say-so-plan.md` § Verification.
- Production site walks: 6.1 (probe shapes, 2026-10-13), 6.2 (manual path, agent-run from the rendered page, 2026-10-14), 6.7 (list path, 2026-10-15, the owner's own mis-wiring reported), 6.8 (adoption, 2026-10-15, verified from the live state 2026-10-16), 6.10 (rotation, agent-run at the owner's request, 2026-10-16: a review landed through the return path), the n8n Instance section's browser checks (PR #138, 2026-10-16).
- Not exercised at runtime: the Free build's provisioning (harness-only, owner decision 2026-10-16); the manual path by choice in a browser (Copy clicks); the per-step forced failures other than activation (harness); the wizard on a site whose instance refuses the probe.
- Gates at the end of the epic: 16 harnesses green (2,149 checks), Jest 132 tests, lint and build clean; GitHub Actions blocked until 2026-11-01, so local gates were the merge evidence for PRs #140–#145.

## Previous-retro follow-through

The previous epic in the initiative's order is `epic-delivery-pipeline`; its folder holds no retrospective file, so there was nothing to follow through on from it. Two epics back, `epic-freemium-and-roles-retrospective.md` (2026-10-02) lists twelve items; not checked here as the rule names the previous epic only, except where this epic's evidence touched one: **A3 "shared harness stub bootstrap"** has no evidence of landing (the stub drift in pattern divergence 7 shows three harnesses with three stub spellings), and **A12 "CI"** landed (the CI workflow exists and ran until the minutes ran out; `plan-ci-minutes.md`).

## Action items

All proposed, not applied. Owner = the repository owner unless the item is agent-executable on approval. Remediation goes through `bmad-ticket` into the backlog; spec reconciliation through `bmad-spec`.

- **A1 — A setup map has a way out, and remembers its instance** *(story; agent drafts the ticket)*: a "Start over" action on the n8n Workflow page that deletes the map (and offers to delete the mapped objects on n8n, by id, with the leftovers record for what refuses); the map stores `base_url` and `adoption_blocked()` / `managed_blocked()` / `diagnose()` compare it, so a Connect to another instance stops the managed actions and says why; the "workflow is gone" sentence points at Start over; leftovers on a previous instance are named when dropped. Sources: adversarial 1, 3, 12; edge E2, E3.
- **A2 — `provision()` claims the map before creating anything** *(small fix)*: `add_option()` like `adopt()`, taken before the first n8n call, released on failure; a harness row for two runs. Sources: adversarial 2, edge E1.
- **A3 — The provisioning Application Password is tracked and revoked** *(small fix)*: keep the uuid (and the user id) in the map; revoke it in Start over, a later provisioning and uninstall; say in the wizard which user it belongs to. Source: adversarial 7.
- **A4 — Probe causes and the connected state tell the truth after the fact** *(story)*: a failed re-check (401 on the list call) replaces the stored passing probe so the page stops claiming connected; a 401 on a route probe maps to `key`, a 403 to a new `scoped` cause ("this key cannot create credentials or publish workflows: create an unscoped key"); rotation and update refuse with zero calls while a mis-wiring sentence is stored; `setup_state()` uses `connected_key()`. Sources: adversarial 4; edge E4, E5, V1, V3.
- **A5 — The activation refusal names its cause** *(small fix)*: when `activate` answers 409, the message says another active workflow on this instance already listens on this path and offers Start over or adoption instead of "Try again in a moment". Source: edge E8, observed in the 6.12 walk.
- **A6 — Update workflow on an adopted workflow asks first** *(small fix, copy from `docs/brand.md`)*: a `@wordpress/components` confirmation naming what the update replaces (every node and setting, credentials and path kept) when the map's `wizard_created` is false; the notice copy stops promising more than it keeps. Sources: adversarial 8, edge E6, spec 3.
- **A7 — Story 67 absorbs the page-state findings** *(refine story 67; the owner approved the target layout 2026-10-14)*: the page island re-reads its state when the wizard changes it (E9); the Settings form stops posting the token and the URL (adversarial 6); one status renderer for Update and Rotate (duplication 8); the copy pass over the message endings, the three button names, the three hint copies and the "no credential is set" sentence for another credential type (pattern 4, 5; duplication 10; edge E7); plus the owner's 2026-10-16 walk observations already filed in the story.
- **A8 — The first review's silent end** *(refine backlog story 2; small fix in the wizard)*: the template's callback node continues on error, so n8n reports success while the site never got the result and the wizard waits without end; a bounded wait with a sentence naming the webhook URL and the site's reachability, and story 2's round-trip test as the real proof. Source: the 6.12 walk (execution 278).
- **A9 — The success signal on a fresh reachable site** *(owner; a staging subdomain on the production host or a throwaway install)*: the one Done when line still unproven; one walk of Connect → Set up → first review, then the A4 line in `docs/user-paths.md` moves the landing from UV to CV. Source: 6.12 walk record.
- **A10 — The next release ships entries 7–11** *(owner; `/release`)*: 3.9.0 carries entry 6; the diagnosis, adoption, update and rotation are on main only. Done when 5 needs the release. Source: `CHANGELOG.md`, `delivery-pipeline` memory.
- **A11 — One source each** *(one refactor story, the sweep's second pass)*: `X-PQM-Token` from `pqm_n8n_headers()` or a constant; the credential names read from one place by the client and the page (and pinned by the template guard); `origin()` reused by `n8n_editor_url()`; one "connected" predicate on both sides (`connected_key()` exposed in `public_state()` as `connected`); `answered()` for every 2xx check; one post-ping helper in `REST`; the provisioning reply carries the saved webhook URL; `WP_REST_Server::DELETABLE`; the entry 7 comment on n8n save semantics corrected to what the walk showed; the "never logged" docblocks say "never logged by the plugin". Sources: duplication 1–5, 7, 9; pattern 2; adversarial 9, 11.
- **A12 — `AGENTS.md` truth pass** *(agent, on approval)*: name the three n8n options, the vanilla page's one REST call, the in-callback sanitizing convention for new routes, and the n8n client's place in "Where things are". Sources: architecture delta 2–3, pattern 1, 6.
- **A13 — Harness hygiene** *(agent, when the next harness lands; the freemium retro's A3 again)*: one shared WP-stub bootstrap for the four harnesses that now drift (`sanitize_text_field`, the options global); split `n8n-api-harness.php` by concern (client, provisioning and rollback, diagnosis and adoption, managed actions) with the world builders shared. Sources: pattern 6, 7.
- **A14 — Spec reconciliation** *(agent via `bmad-spec`, owner approves)*: CAP-4 as built (the workflow at the URL's path, roles from its nodes, no credential list); the "keep existing" default as built; the Application Password self-call dropped and the credential optional on Pro (closes the spec's open question); the workflow name as the template spells it; the failure-modes table's 401 rows split as the code maps them (401 → key, 404 → API disabled, route-probe 401 → key, 403 → scoped). Sources: spec 1, 2, 4, 7, 10.
- **A15 — Records closed in the done chore** *(agent, with the 6.12 done chore)*: the epic Notes line on the version assumption (entry 1) corrected; the loose `plan-update-notice-primary-action.md` named in the epic Notes; the memory line on adoption (CAP-4 wording) aligned with the build; a memory fact that n8n 2.42.x republishes a PUT workflow (seen 2026-10-16). Sources: spec 6, 8; adversarial 9; duplication note on memory line 25.

**Process lessons** (what would prevent the next ones):

- The two defects both code lenses found first (the map's lifetime, the provisioning race) sit at the boundary between entries 4, 5 and 8: each plan guarded its own write, none owned the option's lifecycle. A spec section "state the feature owns, and who clears it" would have named a Start over before entry 4 was built. (Proposed wording for the spec template, owner's call.)
- Entries 7 and 9 wrote opposite facts about n8n's save semantics into comments; the fact was in neither memory nor the spike findings. A rule for this repo: an observed behaviour of an external system goes into `memory/` once, dated and with the version, and code comments point there. (Add to `memory/lessons.md`, A15.)
- The HITL walks that waited for entry 12 (4, 5, 9) found nothing the harness had not; the Local-sandbox-plus-own-n8n environment made a full dry run cheap and safe (deterministic names per host, cleanup by id). Record it in the test-instances note as the standard walk environment, and plan the one walk Local cannot do (the callback) on a reachable site from the start (A9).
- The 6.12 review caught the owner's host in a plan file that the diff scan had missed because the file was untracked. The scan now runs with intent-to-add; make that the written step.
- Session continuity came from an agent-local hand-off file (eighteen updates); the repository holds no session log, so a reader cannot re-check the "why" of mid-epic turns (the stacked-PR conflict after a rebase merge that produced the "never stack" rule, the shared-tree block that produced the worktree habit). Either accept that gap or keep a short dated `_bmad-output/.../session-notes.md` per epic.

## Acceptance verdict

**accepted-with-open-items** (machine verdict; criteria declared in the epic file; no human decision recorded yet). `pending_tickets` is empty: all twelve tickets are `built` or `done`.

Against Done when:

1. *A fresh site reaches its first review with three values and no n8n UI visit* — walked on 2026-10-16 up to the review's landing, which the sandbox cannot receive; the return path is proven on the production site only. **Met except the landing** (open item A9).
2. *A forced failure at each step leaves zero plugin-created objects, verified through the API* — every step forced in the harness; one step (activation) forced on a real instance with the API-verified empty result. **Met** (per-step real forcing accepted as harness-only).
3. *On an adopted setup, update, rotation and diagnosis run without deleting or changing anything else, and the 2026-10-13 mis-wiring is reported in one sentence* — walked on production (6.7, 6.8, 6.10) and the sandbox (6.9). **Met.**
4. *With the API unavailable or declined, the Workflow File section carries a novice through the manual setup and states why* — built and checked from the rendered page (6.2). **Met** (browser Copy clicks not walked).
5. *A release ships the epic in both builds with the policy wording* — 3.9.0 shipped entry 6 with the policies; entries 7–11 are unreleased. **Not yet met** (open item A10).

Blocking findings: none the owner has not already met in use; A1–A4 are real defects in paths no user has walked yet (an instance switch, a double run, a revoked key) and are tracked above.

## Open questions

- The owner's going-in concerns were invited before Phase 1 and had not been given when this document was drafted; an answer may re-weight the findings.
- Adversarial 5 (rotation versus in-flight runs): accept a short grace window for the previous token, or copy only?
- Adversarial 10 (the base URL as a server-side target): keep any https host (self-hosted n8n on private networks), or refuse private ranges with an explicit opt-in?
- CAP-3 per step on a real instance: accept the harness as the evidence for the other steps, or force them once (a key without credential rights would force `credential_webhook`)?
- Where the retrospective itself lives: this file in the epic folder, committed with entry 12 or after it.
