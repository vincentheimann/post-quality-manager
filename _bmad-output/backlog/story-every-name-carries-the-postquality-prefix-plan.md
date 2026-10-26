---
title: 'Every name the plugin defines in code carries the postquality prefix'
type: 'refactor'
ticket: '88'
created: '2026-10-26'
status: done
baseline_revision: '9b8ac0f143aaadb01669392906ba27b31d2ef424'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/memory/naming.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** WordPress.org's review team rejects prefixes of two or three letters, and every name the plugin defines in code starts with `pqm` / `PQM`; the admin page slugs, script handles and JS globals show `pqm` in the address bar and page source too.

**Approach:** One explicit rename table (old name → new name) covering only code names, applied as exact-token replacements across the plugin, `src/`, tests, `bin/` and the current-truth records; stored names keep `pqm` until story 91. A new harness proves no defined name starts with `pqm`. Decision (owner, 2026-10-26, option A over "rule only"): the current-truth records follow the table (`memory/`, `AGENTS.md`, `docs/brand.md`, `README.md`, `CONTRIBUTING.md`, `tests/i18n/README.md`); ADRs, `_bmad-output/`, `CHANGELOG.md`, past release notes and `PORTFOLIO.md` stay as written. Plan kept whole at ~2,200 tokens (owner).

## Boundaries & Constraints

**Always:** Rename by an explicit table, never a blanket `pqm`/`PQM` replace. Prefixes: classes `PostQuality_`, functions/hooks/actions/nonces/option group/query arg `postquality_`, constants `POSTQUALITY_`, slugs/handles `postquality-`, JS globals `postquality` camel case. The release-please marker stays on the version constant's line. The Freemius accessor is `postquality_fs()` and `bin/build-free.php` matches it.

**Never:** change a stored name (every option, user meta key, capability, the cron hook `pqm_cron_review_posts`, the table `pqm_reviews`, the form field names `pqm_settings[…]`) or any stored value; change a REST route, its namespace, the webhook payload, a `WP_Error` code in a REST response, `X-PQM-Token`, the template's `__PQM_*__` placeholders and node ids, the template version, `metadata.pqmInsert`; touch CSS classes, DOM ids, `data-pqm-*`, element expandos (`el.pqmBusy`), file names, `vendor/`; rename harness- or `bin/`-internal helpers (`PQM_FS_Stub`, `PQM_Stub_WPDB`, `pqm_close`…) unless they stand for a plugin name; rewrite ADRs, `_bmad-output/` records, `CHANGELOG.md`, past release notes or `PORTFOLIO.md`; add a redirect from old page addresses (owner, 2026-10-26).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Defined names | every plugin PHP/JS file outside `vendor/` | each class, function, constant, global, custom hook, AJAX/admin-post action, nonce action, option group, localized object, window global, handle, page slug and the Freemius menu slug starts with the new prefix | the prefix harness names each offender |
| Stored names | the same files | every stored-name literal of the baseline is still present, unchanged | the literal-set check lists a missing one |
| Upgrade in place | a site with settings, an n8n setup, reviews, a scheduled cron event | pages, a review, the cron run, the Account page work, reading the same stored names | — |
| Old page address | `admin.php?page=pqm-dashboard` | WordPress's own "not allowed" page, no redirect | — |

</frozen-after-approval>

## Code Map

Rename table (inventory on main 9b8ac0f; the implementer re-derives it with the same categories and adds any name found):
- Classes (9): `PQM_Activator`, `_Admin`, `_Blocks`, `_Cron`, `_DB`, `_Features`, `_License`, `_N8n`, `_REST_API` → `PostQuality_…`.
- Functions (25 + accessor): every top-level `pqm_*` in `post-quality-manager/` (`includes/pqm-functions.php` and the main file), incl. `pqm_fs` → `postquality_fs`; global `$pqm_fs`.
- Constants (8 `define`s): `PQM_PLUGIN_DIR`, `_PLUGIN_FILE`, `_PLUGIN_URL`, `_PRICING_URL`, `_REST_NAMESPACE`, `_SETUP_OPTION`, `_VERSION`, `_WORKFLOW_VERSION` → `POSTQUALITY_…` (values unchanged: `_SETUP_OPTION` still holds `'pqm_setup_progress'`).
- Hooks (4): `pqm_fs_loaded`, `pqm_is_pro`, `pqm_ping_callback_wait`, `pqm_provision_ping_retry_wait`.
- Actions/nonces: AJAX `pqm_test_connection`, `pqm_workflow_status` (`wp_ajax_` + `admin/js/pqm-admin.js` `action` values); admin-post `pqm_download_workflow` (hook, URL `action=`, nonce); nonces `pqm_admin_action`, `pqm_dismiss_cron_nudge`.
- Settings group `pqm_settings_group` (`register_setting`, `settings_fields`, filter `option_page_capability_pqm_settings_group`); the option `pqm_settings` stays.
- Slugs: `pqm-dashboard`, `pqm-posts`, `pqm-settings`, `pqm-workflow` in `admin/class-pqm-admin.php` (menu, `do_settings_sections`, `strpos($screen->id, 'pqm-workflow')` L116, URLs L129/671/1623), `includes/class-pqm-rest-api.php` L1081, `src/dashboard.js` L77/297, `src/post-reviews-table.js` L299, `src/setup/config-steps.js` L22, `src/setup/steps.js` L550; Freemius `menu.slug` in the main file L52. The SDK computes the menu slug at each load and stores none (`class-fs-admin-menu-manager.php` L160), so only the Account page address moves.
- Query arg `pqm_setup`: `PQM_Admin::SETUP_QUERY_ARG` and `src/setup/index.js` L50.
- Handles `pqm-admin`, `pqm-app` (incl. `wp_set_script_translations`, `wp_style_add_data`); JS globals `pqmAdmin` (`wp_localize_script`, `admin/js/pqm-admin.js`), `window.pqmCommandLabels` (inline script L247, `src/kit/copy.js`).
- `bin/build-free.php` L39 matches the literal `pqm_fs()->is__premium_only()`.
- Tests: `tests/harness/*` call the classes and functions (≈350 `PQM_N8n` alone); `tests/harness/fs-stub.php` defines `pqm_fs()`; Jest `tests/js/*` read `window.pqmCommandLabels`, `pqmAdmin`, `page=pqm-settings`. CI runs every `tests/harness/*-harness.php`.
- Records: `AGENTS.md` L18/22/43/47 (managed block; hand edit noted in its header, the 2026-10-21 precedent), `docs/brand.md` L128 row, `memory/` (≈260 code-name mentions, most in `n8n-provisioning.md`, `freemium-and-roles.md`), `README.md` (its capability table stays: caps are stored, `caps-harness.php` Row 7 parses it), `CONTRIBUTING.md`, `tests/i18n/README.md`, `bin/count-cost-example.py`.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/prefix-harness.php` (new) -- enumerate the matrix's defined-name categories (PHP via `token_get_all` and call-literal regexes, JS `window.` assignments and localized objects), assert the new prefix and no `pqm` in any case; run it on the baseline first and record that it fails -- AC1/AC2 proof.
- [x] Rename table + exact-token pass over `post-quality-manager/` (not `vendor/`), `src/`, `tests/`, `bin/build-free.php` -- the rename itself.
- [x] `docs/brand.md`, `AGENTS.md`, `memory/`, `README.md`, `CONTRIBUTING.md`, `tests/i18n/README.md`, `bin/count-cost-example.py` -- same table; brand row split into code names (new prefix), stored names (`pqm` until story 91), kept exceptions -- AC6.
- [x] `docs/release-notes/unreleased.md` -- one line: the plugin's admin pages moved to `admin.php?page=postquality-…`; update bookmarks -- DoD (a changed address existing sites meet).

**Acceptance Criteria:**
- Given the renamed tree, when the stored-name literal set of the baseline is compared with the new tree's, then none is missing or changed.
- Given the renamed source, when `bin/build-free.php` runs, then the free-build harness is green and the Free build carries no Pro code.

## Implementation Notes

- Plan approved by the owner 2026-10-26 (approve and continue). Commits, push and a draft PR are allowed this sprint (owner, 2026-10-26), overriding the house "user commits" rule. Implementer subagent on Fable 5.1 (`model: "fable"`, owner rule 2026-10-16).
- **Built 2026-10-26 (implementer subagent, branch `chore/postquality-prefix-code-names` on 9b8ac0f).** Harness first: `tests/harness/prefix-harness.php` on the baseline gave 3 passed, 13 failed, naming 66 offenders (9 classes, 25 free functions incl. `pqm_fs`, 8 constants, `$pqm_fs`, 4 hooks, 3 actions, 3 nonces, the option group, 2 handles, 2 JS globals, 4 slugs, 2 query args). After the rename: 16 passed, 0 failed. One harness fix on the way: `$wpdb` and `$post` are core globals the plugin reads, so they sit on a `CORE_GLOBALS` allow-list.
- Rename table re-derived from the tree (same categories as the Code Map; 24 helpers in `includes/pqm-functions.php` plus `pqm_fs`, not 25 + accessor) and applied by a scratchpad script with `read_bytes()`/`write_bytes()`, identifier boundaries for PHP/JS names, `[A-Za-z0-9_.-]` boundaries for slugs and handles (so file names, CSS classes and `_page_` hook suffixes were untouched; the four `_page_pqm-…` suffixes got their own entries), longest-first; the table stays out of the repository. Hand corrections after the pass: `src/dashboard.js` `className="pqm-dashboard"` restored (a CSS class, same spelling as the slug); the two page guards `strpos($screen->id, 'pqm-')` (L111) and `strpos($hook, 'pqm-')` (L144) in `class-pqm-admin.php`, which the Code Map did not list, became `'postquality-'` (the render-matrix harness caught both: 8 rows failed until then); two prettier wraps after the longer names (`src/kit/copy.js`, `tests/js/workflow-file-copy.test.js`); the Edit tool wrote `memory/agent-conventions.md` as CRLF once, restored to LF by bytes (lesson 2026-10-24).
- Beyond the plan's record list, the same table ran over three "recurring how" files that would otherwise contradict the code: `.claude/skills/release/SKILL.md` (`POSTQUALITY_VERSION` in the release checklist), `.github/workflows/release.yml` (a comment) and `_bmad/custom/bmad-code-review.toml` (the premium-wrapper lens text `if ( postquality_fs()->is__premium_only() )`, which would otherwise flag every premium block). ADRs, `_bmad-output/`, `CHANGELOG.md`, past release notes and `PORTFOLIO.md` untouched. Memory lines that record past events in code names (e.g. `workspace-layout.md`'s 2026-07-22 reorg "aligned to `PQM_VERSION` 3.0.0") now read with the new name, as the exact-token decision implies.
- Not renamed, by design: the i18n fixture `tests/i18n/post-quality-manager-de_DE-pqm-app.json` (file names stay; `release-header-harness.php` reads it by name). Its name no longer matches the handle WordPress derives it from, so `tests/i18n/README.md` now says to copy it as `post-quality-manager-de_DE-postquality-app.json` on the site. Owner call whether a `git mv` follows.
- AC1 proof beyond the harness row: the set of `pqm` tokens in the plugin tree (git-tracked, outside `vendor/`) before and after differs by exactly the 66 table names plus the `_page_pqm-posts` comment suffix, and gains none; every stored literal (29) is present.
- **Review fixes 2026-10-26 (nine findings, all applied):** `AGENTS.md` header carries the story 88 hand-edit note; the four custom DOM events are `postquality:workflow-status|setup-closed|workflow-refresh|confirm-action` (`src/kit/swap-status-block.js`, `src/index.js`, `src/setup/index.js`, `admin/js/pqm-admin.js`, three Jest suites; 7 prettier wraps of `removeEventListener` calls followed); the prefix harness gained Row 8b (event literals), the JS `OPEN_ARG` = PHP `SETUP_QUERY_ARG` check, a split of Row 10 (stored names, story 91) from Row 11 (permanent exceptions, incl. the six REST error codes named by the kept lists), a `postquality_` twin check over the stored names and a `WP_Error` codes-stay-`pqm_` check (21 rows); the kept-exception lists in `docs/brand.md`, `AGENTS.md`, `memory/naming.md` name the REST error codes, the element expandos, `pqm-execute-` and (AGENTS) `pqm_weekly`; wildcard mentions in the main file, the render-matrix harness, `memory/llm-options-architecture.md`, `memory/delivery-pipeline.md` and `docs/cost-example.md` say `postquality_*`; the release-notes paragraph names only the new addresses; `memory/naming.md` records the SDK's sticky opt-in notice keeping a pre-rename link (2.13.5 L7048–7068).
- Verification 2026-10-26: `php -l` clean on every touched PHP file; 20 harnesses, 0 failed (render-matrix 421, caps 284, free-build 84 — `bin/build-free.php` runs inside it, so the Free build with `postquality_fs()` is proven); `npm run test:unit` 174 passed; `npm run lint:js` clean; `npm run build` compiles; `git diff --stat -- post-quality-manager/vendor` empty; the plan's `git grep` prints nothing; no `.css` or `.json` file changed.

- Orchestrator check 2026-10-26: re-ran the Verification (20 harnesses 0 failed; prefix harness 16/0 here, 3/13 on the baseline worktree; Jest 174; lint:js clean; build compiles; vendor diff empty; the plan's grep empty; no CRLF). Token-set diff: exactly 71 names gone, no new pqm token, all 29 stored literals present (incl. the cron schedule `pqm_weekly`, which story 91 must move too). Matrix audit: Defined names = prefix harness rows 1-9; Stored names = row 10; Upgrade in place = the existing sweep (render matrix, REST, cron gate) passing on unchanged stored names plus row 10; Old page address = row 7 (no `page=pqm-` link left) and no redirect code exists. The i18n fixture name stays (frozen boundary: file names); offered to the owner as a follow-up.

- Orchestrator re-verification after the review patch round (2026-10-26): `php -l` clean; 20 harnesses 0 failed (prefix 21/0); Jest 174; `lint:js` and `build` exit 0; `vendor/` diff empty; the plan's grep (plus `pqm:` events) empty; no CRLF; token-set diff unchanged (71 gone, none new). Step 5: commit, push and the draft PR by the orchestrator on the owner's sprint grant (2026-10-26), overriding the house "user commits" rule and the step's "never auto-push".

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-10-26, lens `quick`).** Verdicts: high 0 · medium 2 · low 9 · false 0 · maybe-false 0. Routes: patch 8, reject 3 (one with its memory half patched).

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `AGENTS.md` managed block hand-edited with no note in its header | medium | patch | Real: L4 lists the story 77/78 notes only; the next `bmad-project-context` refresh would drop the new prefix rule. Add a dated hand-edit note. |
| 2 | Wildcard mentions name helpers that no longer exist (`pqm_*` helpers, `pqm_workflow_*`, "handle-named `-pqm-app.json`") | low | patch | Real: main file L99, `render-matrix-harness.php` L102, `memory/llm-options-architecture.md` L5/L13, `memory/delivery-pipeline.md` L37. The `pqm_*` capability mentions in `class-pqm-activator.php` / `class-pqm-features.php` / `caps-harness.php` stay (capabilities are stored names). |
| 3 | `docs/cost-example.md` L11/L14 cite `pqm_default_settings()` / `pqm_agent_max_tokens()` | low | patch | Real: both functions renamed; its companion script was updated. A current-truth record (option A). |
| 4 | Custom DOM events `pqm:workflow-status`, `pqm:setup-closed`, `pqm:workflow-refresh`, `pqm:confirm-action` keep `pqm` and no rule covers them | low | patch | Real: dispatched and listened on `document` in `src/kit/swap-status-block.js` and `admin/js/pqm-admin.js`; names the plugin defines in code, not in the Never list (CSS classes, DOM ids, `data-pqm-*`, expandos), so the approved intent reads one way: rename to `postquality:*`, and the prefix harness lists them. |
| 5 | The kept-exception lists omit `WP_Error` codes, element expandos, `pqm-execute-`; `AGENTS.md` omits the `pqm_weekly` schedule | medium | patch | Real: `docs/brand.md` L130, `AGENTS.md` L43; a reader applying "prefix every name" would rename ~40 REST error codes and break the REST contract the plan's Never list protects. |
| 6 | Prefix harness Row 10 checks one occurrence per literal; its docblock and list call permanent exceptions "until story 91" | low | patch | Real: a partial rename of a stored name passes. Split the list into stored names (story 91) and permanent exceptions, and assert no `postquality_` twin of a stored `pqm_` name exists in the tree. |
| 7 | Row 8 reads query args from PHP only; the JS `OPEN_ARG` is untested | low | patch | Real: `src/setup/index.js` L50 is `postquality_setup` today, but nothing ties it to `SETUP_QUERY_ARG`. Read JS `*_ARG` constants and check the two values match. |
| 8 | The release note prints `page=pqm-…` to users | low | patch | Real: `docs/brand.md` § Naming, "PQM never reaches a user … release notes"; the sentence also reads ambiguously. |
| 9 | A Freemius sticky opt-in notice stores its HTML with the old `page=pqm-dashboard` link | low | reject (memory patched) | Real: `add_sticky_optin_admin_notice()` (SDK L7048–7068) stores the link built from the menu slug in `admin_notices` storage, once per site. Met only by a site holding that notice from before the rename (pre-release test sites, no real users); the menu still reaches the page. Clearing it means writing SDK storage: left to story 91's upgrade if wanted. `memory/naming.md`'s "stores nothing" is corrected now. |
| 10 | A pre-rename build beside a post-rename build both load (two menus, no hand-off) | low | reject | Real: the dual-activation guard checks `postquality_fs`. Met only on a site mixing a ≤3.9.0 copy with a renamed one; before launch that is the owner's sites only, and no public build carries the old names. The fix would add a legacy-name guard kept forever. |
| 11 | AC4 (Plugin Check on the Free build) unverified; AC3 rests on harnesses | low | reject | A verification gap, not a code defect: CI is blocked by billing until 2026-11-01 (recorded in Verification); the owner's Local look is asked for at presentation. |

## Design Notes

Exact tokens, not substrings: `PQM_N8n` must not catch `PQM_N8n_X`, and `pqm_settings_group` is renamed while `pqm_settings` is not. Build the table from the inventory, sort longest-first, replace with word boundaries, and keep the table out of the repository (the prefix harness is the durable check). Write files with `read_bytes()`/`write_bytes()` so line endings stay LF (lesson 2026-10-24).

Skipped: renaming `WP_Error` codes and file names — add if a review asks for them.

## Verification

**Commands:**
- `php -l` on every touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*-harness.php; do php "$f"; done` -- expected: 20 harnesses, 0 failed (prefix harness fails on the baseline)
- `npm run test:unit` and `npm run lint:js` -- expected: green, 174 Jest tests or more
- `npm run build` -- expected: builds
- `git diff --stat -- post-quality-manager/vendor` -- expected: empty
- `git grep -n -E "\bPQM_(Activator|Admin|Blocks|Cron|DB|Features|License|N8n|REST_API|PLUGIN_|PRICING_URL|REST_NAMESPACE|SETUP_OPTION|VERSION|WORKFLOW_VERSION)\b|\bpqm_fs\b|pqmAdmin|pqmCommandLabels|page=pqm-|'pqm-(admin|app)'" -- post-quality-manager src tests bin ':!post-quality-manager/vendor'` -- expected: no output

**Manual checks (if no CLI):**
- Plugin Check on the Free build: CI is blocked by GitHub billing until 2026-11-01; it runs on this PR's first CI run after that, or on the owner's showcase site.
- Optional owner look on Local: each page, `?postquality_setup=1`, the Account page.
