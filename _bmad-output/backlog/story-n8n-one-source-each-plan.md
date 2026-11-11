---
title: 'Every n8n fact the plugin repeats has one source: names, headers, the connected state, the post-ping tail'
type: 'refactor'
ticket: '77'
created: '2026-10-21'
status: done
baseline_revision: '02deadd209d5220c9386eccb469c69b77983852b'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['_bmad/custom/minimal-code-ladder.md']
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The provisioning epic spelled the same n8n facts in several places (credential names, the token header, the origin parse, the connected predicate, the 2xx check, the post-ping tail, the webhook URL, one method literal), two comments contradict each other on n8n's save semantics, two docblocks promise what the plugin cannot enforce, and AGENTS.md does not name what the epic added. Every later change risks drifting one copy.

**Approach:** Second refactor sweep: define each fact once and read it everywhere else, correct the comments and docblocks, name the epic's additions in AGENTS.md. Behaviour unchanged; every existing harness assertion, Jest test and lint check passes untouched; the only count changes are the new pins that lock the single sources.

## Boundaries & Constraints

**Always:** Behaviour, routes, reply keys, error codes, HTTP statuses, options and n8n calls unchanged (the one addition is the provisioning reply's `webhook_url`, AC1). Keep the harness source regexes (Code Map § harness pins) satisfied: move helpers outside the spans they read. Template JSON keeps its literal names and header (JSON cannot read PHP); the harness that loads the client pins the literals to the PHP source. `php -l` on every touched PHP file, `node --check` on every touched JS file. Record the before/after harness counts in Implementation Notes.

**Never:** Split `PQM_N8n` into several classes. Change the copy of any user-facing sentence beyond moving the header name into a placeholder. Touch the Free template derivation. Reword the strings the de_DE fixtures translate (check `post-quality-manager/languages/` first). Edit AGENTS.md outside the managed block except to note the hand edit in the block's "Verified" comment.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Credential names | Template credentials with `__PQM_SITE_HOST__` | Equal `PQM_N8n::credential_names('__PQM_SITE_HOST__')`; admin rendering reads the same method | Harness check fails if a copy drifts |
| Header name | Template Header Auth name, `pqm_n8n_headers()`, REST `get_header`, copy rows | All read `PQM_N8n::TOKEN_HEADER`; template literal pinned equal | Harness check |
| Editor URL fallback | No stored base URL; webhook URL `http://host:5678/webhook/x` | `http://host:5678`, as today (no https rule on the fallback) | `''` when scheme or host missing, as today |
| Connected predicate | `probe.ok` false, `connected` false in the state reply | PHP reads `['probe']['ok']` once (`connected()`); JS islands read `connected`; refusal branch keys on `probe && !connected` | Same sentences as today |
| 2xx | 200, 204, 299, 300, 199 | `PQM_N8n::is_2xx()` true, true, true, false, false; all seven call sites use it | — |
| Post-ping tail | Ping ok / not ok / outdated / 404 after provisioning | Version recorded only when ok; `status_html` from `tone()`; provisioning 404 still records the failure | Reply keys and statuses equal to today |
| Provisioning reply | Provision succeeds | Reply carries `webhook_url` = saved URL; `onProvisioned` reads it instead of rebuilding | — |
| DELETE routes | `/n8n/connection`, `/n8n/setup` | `WP_REST_Server::DELETABLE`; harness stubs define it | — |

</frozen-after-approval>
## Code Map
- `post-quality-manager/includes/class-pqm-n8n.php` (1601 lines) -- the client. `credential_names()` :994 (source of item 1; callers :905, :1081, :1387). Header literals :907, :1296 → new `const TOKEN_HEADER`. `origin()` :215-228 (https-or-localhost rule; parse-and-rebuild inside) → add `origin_of(string $url): string` (scheme://host[:port] or '') used by `origin()` and by the admin fallback. `connected()` :197-201 is the single `['probe']['ok']` reader (harness pins exactly one, :2305) — keep. Inline 2xx at :328, :640, :1058, :1219, :1575 → `is_2xx(int $code): bool`. Comments: :429-430 ("a save changes only the draft") and :1348-1349 ("republishes… (spike 56)") → both name `memory/n8n-provisioning.md` (PUT republishes, seen 2026-10-16 on n8n 2.42.x). Docblocks :8-9 and :787-788 → "the plugin never logs the keys itself".
- `post-quality-manager/includes/pqm-functions.php` :496-509 `pqm_n8n_headers()` -- read the constant at :507.
- `post-quality-manager/includes/class-pqm-rest-api.php` (1724 lines) -- :166, :215 `'methods' => 'DELETE'` → `WP_REST_Server::DELETABLE`. :311 `get_header` → constant. :673 2xx → `is_2xx()`. Post-ping tails: `setup_connect_callback` :1265-1290, `provision_n8n_callback` :1504-1531 (keep the 404 `pqm_record_workflow_failure` branch), `rotate_n8n_token_callback` :1649-1667, `update_n8n_workflow_callback` :1691-1710; `tone()` :1581 → one private `finish( array $reply, ?array $ping ): WP_REST_Response` that records the version when the ping is ok, sets `status_html` via `tone()`, returns 200. :1502 webhook URL → add `'webhook_url' => $url` to the provisioning reply.
- `post-quality-manager/admin/class-pqm-admin.php` (2290 lines) -- :1843-1845, :1870, :1954 credential names → `PQM_N8n::credential_names( $host )`. :820, :1709, :1901, :2188 header name in copy → `%s` + constant (docblocks :2101 may name the constant). :2011-2024 `n8n_editor_url()` fallback → `PQM_N8n::origin_of()`. :2167 2xx → `is_2xx()`.
- `src/setup/index.js` :259-271 `onProvisioned` -- read `reply.webhook_url`; drop the rebuilt string. `src/n8n-connection/index.js` :207-208, :286 `probe && !probe.ok` → `probe && !connected`. `src/setup/steps.js` :38-40 `keyPasses()` stays the one JS reader of the key state.
- `tests/harness/n8n-api-harness.php` -- loads the client: add pins (template credential names == `credential_names('__PQM_SITE_HOST__')`; template Header Auth name == `TOKEN_HEADER`; provisioning reply carries `webhook_url`). Harness pins to keep satisfied: :1542 `propose_adoption` span, :1731 `rotate_token`…`managed_blocked` span (one `self::request( 'PATCH'`), :1951 `update_workflow` span (exactly `self::request( 'GET', $path )` and `self::request( 'PUT', $path, $body )`), :2054 three `self::roll_back(`, :2305 one `['probe']['ok']`, :2312 no `error_log`/`print_r`/`var_dump`, one `wp_remote_request(`, one literal `wp_remote_get( 'https://api.anthropic.com/v1/models?limit=1'`. `rest-reviews-harness.php` :388 exactly 3 `wp_remote_post(` and 3 `pqm_n8n_headers(` across rest-api, cron, admin.
- `WP_REST_Server` stubs: `n8n-api-harness.php` :225, `setup-harness.php` :102, `free-build-harness.php` :266, `rest-reviews-harness.php` :91, `quota-harness.php` :94, `rest-posts-harness.php` :96, `insert-block-harness.php` :160 -- add `const DELETABLE = 'DELETE';` wherever the REST class is loaded.
- `tests/js/setup-provision.test.js`, `n8n-connection.test.js` -- fixtures for `webhook_url` in the provisioning reply and the refusal branch.
- `AGENTS.md` managed block -- "Where things are": name `includes/class-pqm-n8n.php` (the n8n API client: probe, provisioning, rollback, adoption, update, rotation), the three options `pqm_n8n_api`, `pqm_n8n_setup`, `pqm_n8n_leftovers` beside `pqm_settings`, and `pqm-admin.js`'s REST call (`POST /n8n/workflow`, `X-WP-Nonce`). "Conventions": new REST routes declare `type` only and sanitize inside the callback (story 36 precedent; lesson 2026-10-05). Note the hand edit in the "Verified" comment.
- `memory/n8n-provisioning.md` :19 -- the finding the comments point at; add one line that story 77 unified the sources.

## Tasks & Acceptance
**Execution:**
- [ ] `post-quality-manager/includes/class-pqm-n8n.php` -- add `TOKEN_HEADER`, `origin_of()`, `is_2xx()`; route the five inline checks and `origin()` through them; fix the two comments and two docblocks -- one source per fact, truthful comments
- [ ] `post-quality-manager/includes/pqm-functions.php` -- `pqm_n8n_headers()` reads the constant -- header once
- [ ] `post-quality-manager/includes/class-pqm-rest-api.php` -- `DELETABLE` constants, `get_header` and 2xx through the client, `finish()` replacing the four tails, `webhook_url` in the provisioning reply -- tail once, reply carries the URL
- [ ] `post-quality-manager/admin/class-pqm-admin.php` -- credential names, header copy, editor-URL fallback, 2xx through the client -- admin reads, never restates
- [ ] `src/setup/index.js`, `src/n8n-connection/index.js` -- read `webhook_url`; refusal keyed on `connected` -- islands read the server's answer
- [ ] `tests/harness/*.php` -- `DELETABLE` in the stubs; new pins in `n8n-api-harness.php` -- lock the sources
- [ ] `tests/js/*.test.js` -- fixtures follow the reply -- Jest green
- [ ] `AGENTS.md`, `memory/n8n-provisioning.md` -- name the epic's additions; one memory line -- AC3

**Acceptance Criteria:**
- Given the sweep is done, grepping for `'X-PQM-Token'`, `'Anthropic - Claude'`, `>= 200 &&`, `/webhook/` and `['probe']['ok']` in PHP and JS finds each only at its source (template JSON and harness pins excepted)
- Given every harness, Jest and lint ran before and after, each passes; the only count deltas are the new pins, listed in Implementation Notes
- Given the two comments and two docblocks, they name the memory finding and say the plugin never logs the keys itself
- Given AGENTS.md, its managed block names the client, the three options, the page script's REST call and the in-callback sanitizing convention, with the hand edit noted

## Implementation Notes
Agent-owned. Append-only.

- 2026-10-21 (implementer subagent running as Fable 5.1, branch `chore/n8n-one-source-each`, uncommitted): sweep done as the Code Map lays it out. Client: `const TOKEN_HEADER`, `origin_of()` (parse-and-rebuild only, D2), `is_2xx()` (placed after `origin()`, outside every pinned span), the five inline 2xx checks and `origin()` routed through them, `credential_names()` made public, both comments and both docblocks reworded as AC3 asks. Functions: `pqm_n8n_headers()` reads the constant. REST: `WP_REST_Server::DELETABLE`, `get_header( PQM_N8n::TOKEN_HEADER )`, `is_2xx()`, one private `finish( array $reply, ?array $ping )` beside `tone()` replacing the four tails (the provisioning 404 branch stays in its callback as `if ( ! $ping['ok'] && 404 === … )`), `webhook_url` in the provisioning reply. Admin: `credential_names()`, the three header-name copy strings carry `%s` / `%2$s` + the constant (de_DE fixtures translate none of them, checked first), `n8n_editor_url()` falls back to `origin_of()`, `is_2xx()`.
- Decided: (1) `origin()` keeps its https-or-localhost rule as one `preg_match` on the rebuilt origin rather than parsing twice. (2) `src/n8n-connection/index.js` :286 keeps `data.probe && ! data.probe.ok` — the reply's `probe.ok` there means "the check just sent passed", not "connected": story 74 D1 answers `probe.ok` false with `connected` true on a failed re-check that keeps the stored key (harness :2194, three Jest cases pin it), so keying on `connected` changed behaviour and broke them. Only the `refusal` predicate at :208 reads `connected` now (its `! state.probe.ok` was redundant under `! connected && key_set`). The Code Map's "`probe && !connected`" was therefore applied once, not twice. (3) `webhook_url` on the JS side is `reply.webhook_url || ''`, so a reply without it still leaves a string in the state. (4) Two harness assertions had to change, not add, because of AC1: the exact key lists of the provisioning reply (`n8n-api-harness.php` :712 and :2150) now carry `webhook_url` after `workflow_id`; no other existing assertion was touched. (5) `cron-gate-harness.php` and `quota-harness.php` gained `require …/class-pqm-n8n.php`: they call `pqm_n8n_headers()` without loading the client, which now reads `PQM_N8n::TOKEN_HEADER` (`rest-reviews-harness.php` loads the whole plugin). (6) The template pin reads the raw `templates/n8n-workflow.json` (placeholders unfilled) because `pqm_workflow_json()` fills `__PQM_SITE_HOST__` in the harness too.
- New pins in `n8n-api-harness.php` (3): template credential names == `credential_names('__PQM_SITE_HOST__')` and a header parameter named `TOKEN_HEADER`; provisioning reply `webhook_url` == the saved URL; the token header, `'Anthropic - Claude'` and `>= 200 &&` spelled once each in the client and absent from the REST class, functions and admin.
- Harness counts before → after: n8n-api 577 → 580 (the three pins above); render-matrix 409, setup 141, template-guard 62, free-build 83, rest-reviews 65, caps 284, cron-gate 35, insert-block 66, license 27, quota 71, release-header 31, rest-posts 165, sanitize-matrix 226, template-failure 55, voice 23 — all equal, 0 failed. Jest 149 → 149 passed (3 provisioning mocks gained `webhook_url`). `npm run lint:js` clean (one prettier fix on the shortened `refusal` line), `npm run build` compiled, `php -l` and `node --check` clean on every touched file. AC1 grep: `'X-PQM-Token'`, `'Anthropic - Claude'`, `>= 200 &&`, `['probe']['ok']` and the `/webhook/` builder each at one source (docblocks and translators comments name the header unquoted, as Verification allows).
- Correction (review, 2026-10-21) to item (5): `cron-gate-harness.php` needed no `require` — it stubs `pqm_n8n_headers()` itself and `class-pqm-cron.php` never names `PQM_N8n`; the line was removed (35/35 without it). `quota-harness.php` keeps its `require`, but what needs the client there is `PQM_REST_API` :673 `PQM_N8n::is_2xx()` on the trigger route, not `pqm_n8n_headers()` (also stubbed); its comment now says so. Same review: AGENTS.md's `pqm-admin.js` clause reworded to the one `[data-pqm-post]` click handler (four routes, not one), the provisioning lock `pqm_n8n_provisioning` added as the client's fourth option, and memory entry 4's reply shape gained `webhook_url`.
- Workflow deviation: run from the workspace root with the implementer as a subagent; no commit, push or remote call made. Not done, for the owner: the manual Local pass (the wizard's paste-URL body after a failed provisioning ping now shows the reply's URL; the Settings and Workflow page copy renders unchanged).

- 2026-10-21 (session, step 3 diff read + matrix audit): the diff was staged and read in full; the implementer's three deviations stand (the :286 `probe.ok` read is a different fact from the connected state, story 74 D1; two exact key lists had to carry the new key; the raw template is the pin's source). Matrix audit: the 2xx row had no covering test, so one pin was added to `n8n-api-harness.php` (`is_2xx()` on 200/204/299/300/199 and `origin_of()` on a mixed-case host, a schemeless and an ftp URL); the editor-URL fallback row is covered by `render-matrix-harness.php`'s `192.168.1.20:5678` case. Counts after: n8n-api 581 (+4 over baseline: three implementer pins, one audit pin), every other harness equal, Jest 149, lint clean, `php -l` clean on the four PHP files.

## Plan Change Log
Append-only.

## Review Triage Log
Append-only.

- 2026-10-21, review pass 1 (lens: quick, pinned; 6 findings): verdicts 0 high / 2 medium / 3 low / 0 false / 0 maybe-false, plus 1 low deferred as pre-existing. Route: 5 patch, 1 defer, no loopback.
  1. AGENTS.md "Where things are": `pqm-admin.js` described as having one REST call `POST /n8n/workflow` — **medium**, verified: `class-pqm-admin.php` sets four `data-pqm-post` routes (`n8n/token`, `n8n/workflow`, `setup`, `setup/connect`) read by one click handler (`pqm-admin.js` :462, :502). Route **patch** (reword to the one handler), applied through the implementer; the edit sits inside the plan's approved AC4 task on the managed block and corrects a sentence this diff wrote, so the agent-context defer rule does not apply.
  2. AGENTS.md: the client's options listed as three, `LOCK_OPTION = 'pqm_n8n_provisioning'` omitted — **medium**, verified at `class-pqm-n8n.php` :90. Route **patch** (name the lock), same justification as 1.
  3. `memory/n8n-provisioning.md` :10 entry 4 reply shape lacks `webhook_url` — **low**, verified. Route **patch** (one key added to the line).
  4. `tests/harness/cron-gate-harness.php` :83 dead `require` with a false comment — **low**, verified: the harness stubs `pqm_n8n_headers()` at :55 and `class-pqm-cron.php` never names `PQM_N8n`. Route **patch** (delete the line; Implementation Notes item 5 corrected by an appended line).
  5. `tests/harness/quota-harness.php` :102 comment names the wrong dependency (the need is `PQM_REST_API` :673 `is_2xx()`) — **low**, verified. Route **patch** (comment).
  6. AC1's `/webhook/` grep still finds the placeholder example URL in `class-pqm-admin.php` :1782 and `src/setup/steps.js` :113, the hint at :1937 and the parser in the client — **low**, verified. The intent's webhook-URL fact is the composed saved URL (duplication map item 9: server saves, dialog rebuilt), which is now one source; the example URL is a copy string outside the ticket's items and predates this change. Route **defer** (deferred-work entry; story 67's copy pass is the natural home); the AC clause reads narrower than its grep, no plan edit made.

## Design Notes
Decisions taken from the repository (no open questions): (D1) AC4's "equal counts" is read as "no existing assertion changed or removed"; new pins add to the counts and the delta is recorded. (D2) `origin_of()` carries the parse-and-rebuild only; `origin()` keeps its https rule on top, so the admin fallback keeps accepting any http host as today. (D3) The post-ping helper lives in the REST class beside `tone()`, not in the client, so no harness span moves. (D4) The template keeps literals; pins live in `n8n-api-harness.php` because the template guard is JSON-only and loads no class. (D5) Moving the header name into a `%s` placeholder changes four source strings; de_DE fixtures are checked first and left untouched if they translate one. Checkpoint 1 (owner, 2026-10-21): approved with the full plan kept above the 1,600-token guideline (single goal; the Code Map carries the line map) and D1's reading of AC4. Workflow deviation: run from the workspace root with the skill rendered by hand; the branch `chore/n8n-one-source-each` was created under the owner's blanket go-ahead of 2026-10-21.

## Verification
**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 16 harnesses pass; baseline n8n-api 577, render-matrix 409, setup 141, template-guard 62, free-build 83, rest-reviews 65, caps 284, cron-gate 35, insert-block 66, license 27, quota 71, release-header 31, rest-posts 165, sanitize-matrix 226, template-failure 55, voice 23
- `npm run test:unit` -- expected: 149 passed (plus any fixture-only additions)
- `npm run lint:js` and `npm run build` -- expected: clean
- `php -l` on each touched PHP file; `node --check` on each touched JS file -- expected: no syntax errors
- `git grep -n "X-PQM-Token" -- post-quality-manager src` -- expected: the constant definition, the template, and docblocks only
