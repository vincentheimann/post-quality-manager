---
title: 'The workflow template is checked against the current n8n release'
type: 'chore'
ticket: '105'
created: '2026-11-04'
status: 'built'
baseline_revision: '93eec0bd933ce1ae4fbc9cde81bc95c680c22848'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/local-verification.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Both templates were last proven on n8n 2.42.x. n8n 2.43.0 refuses to publish a workflow whose required node inputs are unconnected, and nothing in CI would catch a template n8n refuses. The owner asked the agent to fix or mitigate the risk.

**Approach:** (1) The template guard harness mirrors n8n's publish checks for what the templates use, on both templates, so a refused template fails in CI. (2) One runtime proof on a disposable n8n 2.46.4 (npm `latest`; 2.47.0 is a beta) on this PC, against the showcase. (3) The proven version and a repeatable recipe go into the records, and the n8n 3 changes become a ticket. The template does not change unless the walk finds a defect.

## Boundaries & Constraints

**Always:** the guard runs in the existing `tests/harness/template-guard-harness.php` (plain PHP, no n8n, no network); the walk follows `docs/local-verification.md` (backup first, restore last, nothing site-specific in the repository); the disposable n8n lives in the session scratchpad, is started and stopped by its own PID, and is deleted after; the owner types every provider key; the disposable n8n and everything in it (workflows, credentials, the owner's provider key) go with its folder at the end.

**Never:** the owner's n8n instance; a Docker or WSL install; a committed script that installs n8n; a template change without a version bump and a release-notes line (ticket AC3); Pro paid commands (they need a licence; the Pro build without one provisions the Pro template and runs Review).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Guard on today's templates | Pro and Free templates | every new check passes on both | — |
| Unreachable node | a copy with one node no `main` connection reaches | the reachability check fails, naming the node | — |
| Dangling connection | a copy whose connection names a missing node | the connection check fails on both variants (today: Free only) | — |
| Unreviewed node type | a copy with a node type@typeVersion not on the reviewed list | the check fails, naming `type@typeVersion` and saying to read its inputs at the target n8n tag | — |
| Deprecated node | a copy with `n8n-nodes-base.function` | the deprecated-node check fails | — |
| Runtime: provision | Free build, then Pro build, on n8n 2.46.4 | each workflow is created, published and pinged; no 400 from `/activate` | a refusal is recorded with n8n's message and becomes the template fix |
| Runtime: update + command | a provisioned workflow | Update workflow (PUT republish) succeeds; one Review per build lands its callback | a failure is recorded and fixed before done |

</frozen-after-approval>

## Code Map

- `tests/harness/template-guard-harness.php` -- per-template loop (L49 on): add the checks there so both variants run them. Move the Free-only dangling-connection check (L179–188) into the loop. New constants: `REVIEWED_NODE_TYPES` (`type@typeVersion` → required input names, reviewed at `n8n@2.46.4`): `n8n-nodes-base.webhook@2`, `code@2`, `switch@2`, `httpRequest@4`, `if@2`, `noOp@1`, each `[]`; `DEPRECATED_NODE_TYPES`: `n8n-nodes-base.function`, `n8n-nodes-base.functionItem`, `@n8n/n8n-nodes-langchain.code`. Reachability: breadth-first over `connections[*]['main']` from the node of type `n8n-nodes-base.webhook`; a node with `disabled: true` counts as unreachable. Negative rows run the same check functions on in-memory copies (no fixture files).
- `memory/n8n-provisioning.md` -- one findings line, form of L19–22: the 2.43.0 check (`node-validation.ts` `getUnconnectedRequiredInputs`: only inputs declared as objects with `required: true`; core `main` inputs are strings, never required), applied on `/activate` and on a PUT republish, refusal = HTTP 400 "required inputs are not connected"; 2.46.4 proven by the walk; 2.47.0 (beta) source-identical on the plugin's paths.
- `memory/lifecycle.md` L11 -- the n8n row: template proven on 2.46.4 (date), 2.47.0 beta to re-run once `stable`; npm on win32 needs `--force` (n8n#43006).
- `docs/local-verification.md` -- a section "A disposable n8n": portable Node 24 zip first on PATH (the Code node's task runner spawns `node`), `npm install n8n@<version> --prefix <dir> --force` (n8n#43006, musl-only deps), `.env` (`N8N_USER_FOLDER`, `N8N_PORT=5688`, `N8N_RUNNERS_BROKER_PORT=5689`, `N8N_SECURE_COOKIE=false`, diagnostics, version notifications and templates off), owner via `POST /rest/owner/setup`, API key via `GET /rest/api-keys/scopes` + `POST /rest/api-keys`, start and stop by PID, teardown.
- `_bmad-output/backlog/story-n8n-3-readiness.md` -- new ticket 111 (109 and 110 are on PRs #234 and #235): the four n8n v3 rules (`packages/cli/src/modules/breaking-changes/rules/v3/`, announced in 2.46.4, not defaults in 2.47.0) against the plugin: Import from URL in the manual path, SSRF default ranges vs callbacks to private sites, Docker-only, the 60 s task runner timeout vs the Code nodes. Unrefined.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/template-guard-harness.php` -- the checks and negative rows above -- n8n's publish rules caught in CI
- [x] `docs/local-verification.md`, `memory/lifecycle.md`, `memory/n8n-provisioning.md` -- the recipe and the facts -- a repeatable check for `/lcm`
- [x] `_bmad-output/backlog/story-n8n-3-readiness.md` -- ticket 111 -- the v3 changes tracked
- [x] Walk (orchestrator, with the owner, after review): the disposable n8n 2.46.4; showcase backup; Free build: the owner runs the setup dialog (n8n URL and API key from the agent, the owner's Anthropic key), then Test, one Review, Update workflow; Pro build installed over: Update workflow (Pro template republished), Test, one Review; restore the showcase backup, install the Pro build, a fresh Pro provisioning (the owner types the key again), Test; stop n8n by PID, delete its folder; restore the showcase -- ticket AC1 and AC2

**Acceptance Criteria:**
- Given the walk, when it ends, then the n8n version proven, every step's result and the cleanup (objects deleted, PID stopped, folder deleted, showcase "same") are in Implementation Notes and the memory line.

## Implementation Notes

- Implementer (2026-11-04), the three file tasks; the walk is still open. `pqm_publish_checks($tpl, $reviewed)` returns the five checks (reachable, joined, reviewed, required, deprecated) as label + problem; the loop runs it on both templates, the Free-only dangling check is gone, and the negative rows run it on in-memory copies of both: an orphan node, a disabled node (reported as `<name> (disabled)`; the walk goes through it, since n8n passes data through a disabled node), a connection to a missing node, `httpRequest@4.2`, a Code node turned `n8n-nodes-base.function@1` (fails reviewed + deprecated), and a reviewed map giving `noOp@1` a required `ai_languageModel` input (the one row the matrix did not list: it exercises the required-input check, which no reviewed type triggers today). Each negative row asserts exactly which checks fail and that the message names the node or type. Template guard 84 passed (was 63), every harness 0 failed.
- The records say "pending the walk" where the plan expects the walk's result: `memory/lifecycle.md` (n8n row), `memory/n8n-provisioning.md` (the new 2.43.0 line, last sentence), and the recipe in `docs/local-verification.md` (A disposable n8n) is written from n8n's source at 2.46.4, not yet walked. `memory/delivery-pipeline.md`'s template-guard line also names the new checks (not in the Code Map; it enumerates the guard's checks).
- Facts found on 2026-11-04 that the walk should weigh: n8n 2.46.5 became `latest` and `stable` today (notes: AI Agent callout, credential resolution for parameter loading, named array keys in expressions, migration report); `workflow-validation.service.ts` and `node-validation.ts` are byte-identical across 2.46.4, 2.46.5 and 2.47.0. n8n@3.1.0 is a GitHub pre-release (2026-11-03) and npm holds no 3.x, so the npm recipe cannot run n8n 3 (ticket 111 notes it). SSRF protection is off by default in 2.46.4. Node.js on this PC is 22.14.0, so the portable Node 24 is needed (n8n 2.46.4 `engines`: `>=24.0.0`).

- **Walk, 2026-11-04 (orchestrator with the owner; run during the build, not after review: it exercises the templates and code of `main`, which this change does not touch, and the owner had the showcase running).** n8n **2.46.4** on a disposable instance in the session scratchpad (portable Node 24.22.2, checksum checked; `npm install n8n@2.46.4 --force --cache …`), the showcase backed up first (3.9.0 Free build, 30 reviews, 1 user).
  - Free build (3.10.2 source): connected by the agent through `POST /n8n/connection`; the owner's first setup stopped at "creating the workflow" (cURL 28 after 10 s) and said "Nothing was left in n8n", yet n8n held the created workflow (16 nodes, unpublished, no credentials) → **bug 112** filed, orphan deleted by id. The review also found n8n listening on every interface: n8n restarted with `N8N_LISTEN_ADDRESS=127.0.0.1`, the site reconnected to `http://127.0.0.1:5688`. Second setup: ping OK with the callback. Review on post 6 → result row (score 18, `major_revision`) through the callback in ~20 s. Update workflow → updated, ping OK.
  - Pro build installed over the Free setup: Update workflow → 502; n8n 400 `Node "Update WP Post (Rewrite)" uses invalid credential` (the Free map has no `wordpress` role; entry 6.9 sends it name-only) → **bug 113** filed (P1), with the two PHP 8.5 deprecations it wrote at `class-pqm-n8n.php` L1419.
  - Fresh Pro setup (showcase restored, Pro build installed, the owner typed the key again): created, published, ping OK with the callback; Update workflow → updated, ping OK; Review → result row in ~20 s.
  - The 2.43.0 publish check refused nothing in either template, on creation or on PUT. 2.46.5 became npm `latest` the same day; the validation files are identical in 2.46.4, 2.46.5 and 2.47.0 (`beta`).
  - Cleanup: every n8n object deleted by id (unpublish, then delete: `DELETE` answered 409 before), n8n stopped by its verified PID (node.exe, listening on 5688) both times, the folder with its secrets file and the walk scripts deleted; the showcase restored (plugin zip, database dump, `wp-config.php`, `debug.log` deleted): plugin list, tables/reviews/users/log and plugin files/`wp-config.php` all "same"; the walk folder and the Pro zip deleted. Three showcase reviews went to the AI provider on the owner's key, which only the disposable n8n's credential held.
  - Left for the owner: Windows showed a Defender Firewall prompt at n8n's first listen; the owner could only Cancel, which left two inbound rules named `node.exe` for the deleted scratchpad `node.exe` path (removing them needs an administrator; the owner's call).

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-11-04, Quick lens on Opus 5.5; diff since 93eec0b, before the walk's results):** high 0 · medium 3 · low 6 · false 1 · maybe-false 0.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | The plan's acceptance and ticket AC1–AC3 unmet; memory says "walk pending" | false | reject | The walk runs after the build by design (running now, 2026-11-04); its results replace the "pending" lines before the PR leaves draft. |
| 2 | Version to prove unclear: the recipe says "take `latest`" (now 2.46.5) while the intent and records name 2.46.4; "becomes `latest`" vs "once it is `stable`" | low | patch | Direct wording fix: the recipe takes the version the ticket names (by default npm `latest` that day); one wait condition. The walk's records name 2.46.4 and note 2.46.5. |
| 3 | The recipe's `npm install` sets no cache, so npm fills `%LocalAppData%
pm-cache`, outside the scratchpad and the teardown | medium | patch | Verified in the recipe text; breaks the doc's own scratchpad rule and the owner's deletion rule. Fix: `--cache "$N/npm-cache"`. (The walk's own install passed `--cache` and wrote nothing there.) |
| 4 | The disposable n8n listens on every interface (`N8N_LISTEN_ADDRESS` defaults to `::`) | medium | patch | Seen live in the walk: `0.0.0.0:5688` and `[::]:5688`; the walk restarted n8n with `N8N_LISTEN_ADDRESS=127.0.0.1` and reconnected the site to `http://127.0.0.1:5688`. Fix the recipe the same way (and `N8N_WEBHOOK_URL`, since n8n flags `WEBHOOK_URL` as deprecated). |
| 5 | The port check only prints "STOP" and the block starts n8n anyway | low | patch | Direct correction: the check exits non-zero before the start. |
| 6 | Teardown force-kills the PID from a file without checking it is still n8n | medium | patch | A reused PID would kill another process tree; the owner's rule allows stopping only what the session started by exact PID. Fix: confirm with `tasklist` (node.exe) and netstat (owns 5688) before `taskkill`, as the walk did. |
| 7 | The reachability check follows `main` only, so the AI sub-nodes the docblock invites would always read unreachable | low | patch | No template has AI nodes; direct correction of the docblock: reachability follows `main` only, and adding an AI node needs it extended. |
| 8 | `memory/local-verification.md` still says the round trip needs a real instance | low | patch | Direct correction: the disposable n8n recipe gives the full round trip (story 105). |
| 9 | The new doc section adds a second `### Variables` and its own steps 1–7 beside the walk's, and does not say to skip walk steps 3, 4 and 7 | low | patch | Walk step 4 writes `n8n_webhook_url` to the capture listener and would overwrite the provisioned URL. Fix: unique headings, its own step labels, and the skip list. |
| 10 | The guard's check list is written into `memory/n8n-provisioning.md` and `memory/delivery-pipeline.md` | low | patch | Direct correction: keep it in one place, the other points to it. |

Patch round (2026-11-04, the same Opus implementer): rows 2–10 fixed (`docs/local-verification.md` "A disposable n8n" rewritten in place with `--cache`, `N8N_LISTEN_ADDRESS=127.0.0.1`, a stopping port check, a verified-PID teardown, its own headings and the walk steps to skip; the harness docblock; `memory/local-verification.md`; one check list in `memory/n8n-provisioning.md`), plus the owner's README line (2026-11-04: the README names the local checks and the disposable n8n). Re-run: `template-guard-harness.php` 84 passed, 0 failed; `php -l` clean.

## Design Notes

Why the allow-list: n8n's required inputs depend on node parameters (an Agent's model input, a parser's autoFix), so a static list cannot evaluate them for AI nodes. Refusing any type@typeVersion nobody has reviewed sidesteps that: a new node type in the template fails until someone reads its inputs at the target tag and adds it.

The walk sends two or three showcase sample posts to the AI provider (a few cents) with the owner's key, typed by the owner into the setup dialog; the key reaches only the disposable n8n's credential, deleted with its folder.

## Verification

**Commands:**
- `php tests/harness/template-guard-harness.php` -- expected: 0 failing, the new rows listed for Pro and Free, each negative row failing its copy as designed
- `for f in tests/harness/*-harness.php; do php "$f"; done` -- expected: every harness 0 failing
- `php -l tests/harness/template-guard-harness.php` -- expected: no syntax errors
