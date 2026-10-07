---
title: 'Refactor sweep'
type: 'refactor'
ticket: '8'
created: '2026-10-05'
status: done
baseline_revision: '679df414057c7869a67aba0360e7574672c35e78'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/_bmad/custom/minimal-code-ladder.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Stories 5.1–5.7 and 5.10 left cleanup behind in their build records and review findings. A page imports from another page (`dashboard.js` → `post-reviews/detail-panel.js`), helpers and copy are duplicated between the two React pages, some exports and DB members have no callers, comments still point at shipped or deleted work, and some CSS is redundant.

**Approach:** One behaviour-preserving pass: shared code moves into `src/kit/`, dead code and stale comments go, and each item gets one of three outcomes: done, skipped with a one-line reason, or left for its own ticket. Cleanup only: scope that another story pushed out becomes a new story.

## Boundaries & Constraints

**Always:** Both React pages, Settings and the n8n Workflow page behave and look exactly as today, LTR and RTL. Grep every symbol before moving or deleting it. `src/kit/` imports no page module. Copy, i18n strings and text domain stay unchanged. KISS: move, rename or delete; no new behaviour.

**Never:** No change to any REST route, its args or its payload (the legacy `filter=needs_review` arg stays: it is a contract). No change to the DB schema, gate/quota/webhook semantics or the n8n template. No change to `deferred-work.md` entries or ticket files. Not the stored `error` status in filters/sort/counts, the template's failure status, NULL-last score sort, or a JS test runner (Jest): those wait on their own owner decisions.

**Decision (owner, 2026-10-05): lint tooling.** Fix it in this story: `"typescript": "~5.3.3"` devDependency (approved), `.gitattributes` `* text=auto eol=lf`, a `lint:js` script, a CI lint step, and the 40 lint errors fixed (REST-key `camelcase` via eslint-disable lines, not renames).

**Decision (owner, 2026-10-05): shared single-run trigger.** Both pages' single-run plumbing (`refreshCounter`/`pendingUntilKey`/`settlePending`, the run block, `onGiveUp`/`onSettled`) moves into one kit hook.

**Decision (owner, 2026-10-05): Post Reviews CSS.** Post-Reviews-only rules move out of `src/kit/style.css` into a new `src/post-reviews/style.css`; the kit keeps shared rules only.

**Decision (owner, 2026-10-05): WordPress floor.** `Requires at least` rises to 6.6 and the `react-jsx-runtime` guard and its notice are deleted.

**Decision (owner, 2026-10-05): plan size.** Kept whole above 1,600 tokens: one goal, and the exact line references make the moves safe.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Single run, Post Reviews | Details → Run Review | Same toasts, busy-until-refetch, Processing row, completion toast as 5.4 | — |
| Single run, Dashboard | Queue row → Review | Same as the 5.6 checklist item 6 | — |
| Bulk | 3 selected, confirm | Sequential triggers, per-post report as 5.5 | — |
| Gated / hidden | Free plan, view-only user | Same locked/hidden buttons on both pages | — |
| Built CSS | `npm run build` | Same set of rules in `style-index.css` and `-rtl.css`; only order and the redundant rules removed by this plan differ | — |

</frozen-after-approval>

## Code Map

Paths under `post-quality-manager/` (the shipped plugin) unless they start with `src/`, `tests/` or `.github/`.

- `src/post-reviews/detail-panel.js:66,74` -- `canRender()` / `isGated()`: move to `src/kit/runs.js` (next to `isFailedRow`); importers: `detail-panel.js`, `post-reviews-table.js`, `dashboard.js:37`.
- `src/post-reviews-table.js:135` `inFlight(item, command)` + `src/dashboard/fix-next-queue.js:15` `inFlight(item)` -- same logic; one export in `src/kit/runs.js` (command param optional).
- REST path literals `'/post-quality/v1/posts'` (`post-reviews-table.js:120`, `dashboard.js:40`) and `` `/post-quality/v1/reviews/${postId}` `` (`kit/runs.js:196`, `detail-panel.js:333`) -- one constant/helper in `src/kit/runs.js`.
- `'Open in Editor'` (`detail-panel.js:221`, `dashboard/fix-next-queue.js:74`) -- one `OPEN_IN_EDITOR` string in `src/kit/copy.js`.
- `src/kit/status-badge.js:23` local `STATUS_NONE` (icon config) shadows the `copy.js:70` export of the same name -- rename to `NONE_BADGE`.
- Exports with no importer, drop `export` only: `kit/runs.js:120` `outcomeToast`, `kit/use-polling.js:26-27` `POLL_INTERVAL`/`GIVE_UP_AFTER`, `post-reviews/filter-bar.js:27,35` `SCORE_BANDS`/`findBand`, `post-reviews/result-view.js:17` `PILLARS`.
- `includes/class-pqm-db.php:143` `get_latest_review()` -- no caller anywhere: delete. `:170` `STATUS_SEVERITY`, `:395` `SCORED_STATUSES` -- used only inside the class: `private const`. Keep `status_order_expression()`, `priority_order_sql()` and `PRIORITY` public (the harness calls them).
- Stale comments to correct: `src/kit/copy.js:3-4`; `class-pqm-db.php:240` (the `$filter` doc should say it is kept for the REST contract only), `:353`; `src/kit/style.css:9-15,115-116` and `src/kit/score-badge.js:3-4` (`pqm-admin.css` is the live Settings/Workflow sheet, not legacy; `.pqm-yoast-badge` no longer exists); `src/kit/toasts.js:7-8` (two pages host toasts); `src/post-reviews-table.js:2` (stories 5.1–5.5, 5.10); `admin/class-pqm-admin.php:857,1024,1028` (banners and "settings page" → n8n Workflow page); `post-quality-manager.php:271`; `includes/class-pqm-rest-api.php:6,25,33,41,48,56,155` ("existing:" / "New:" / "moved here" changelog labels).
- CSS redundancies: `src/kit/style.css:506-509` repeats the base `.pqm-quota-note` at :60-62; `src/dashboard/style.css:94` `text-align:center` repeats kit :34; `admin/css/pqm-admin.css:61` `.pqm-copy-feedback{min-height:0}` is a no-op. Delete all three.
- Lint (decision): `npx wp-scripts lint-js` crashes because `@wordpress/eslint-plugin` peers `typescript >=5`, so npm installed `typescript@7.0.2` (`package-lock.json:21104`). TS 7 exports no `TypeFlags`, and `@typescript-eslint@6.21` supports `<5.4`. With TS 4.9 shimmed, lint runs and finds 40 real errors (32 `jsdoc/require-param`; 6 `camelcase` on REST keys `post-reviews-table.js:431-432`, `bulk-bar.js:44`; 1 `@wordpress/valid-sprintf` `kit/copy.js:56`; 1 `no-nested-ternary` `sortable-header.js:26`) plus about 2,977 CRLF prettier hits from `core.autocrlf=true` (index is LF). `package.json` has no lint script; `.github/workflows/ci.yml` (Node 22) does not lint.
- Trigger duplication (decision): `post-reviews-table.js:280-315,376-389,414-426` and `dashboard.js:94-127,156-191` both carry `refreshCounter` / `pendingUntilKey` / `settlePending()`, the same single-run block, an identical `onGiveUp` and an `onSettled` that differs only in its setter.
- Page CSS (decision): `src/kit/style.css:67-175,243-409,636-647` hold rules only Post Reviews renders (columns, filter bar, bulk bar, sort headers, detail row, tablenav). The Dashboard already has `src/dashboard/style.css`.
- WP floor (decision): `post-quality-manager.php:7` `Requires at least: 6.0` vs the `react-jsx-runtime` guard `admin/class-pqm-admin.php:453-457` ("needs WordPress 6.6 or newer"). The 6.0 floor is also stated at `README.md:37` and `AGENTS.md:8`, which also still says "vanilla JS + jQuery in the admin" (now: React app pages, jQuery on Settings/Workflow).

## Tasks & Acceptance

**Execution:**
- [x] `src/kit/runs.js`, `src/kit/copy.js`, `src/kit/status-badge.js`, `src/post-reviews/detail-panel.js`, `src/post-reviews-table.js`, `src/dashboard.js`, `src/dashboard/fix-next-queue.js` -- move `canRender`/`isGated`/`inFlight`, the REST paths and `OPEN_IN_EDITOR` into the kit; rename `NONE_BADGE` -- no page imports a page, one source per helper
- [x] `src/kit/runs.js`, `src/kit/use-polling.js`, `src/post-reviews/filter-bar.js`, `src/post-reviews/result-view.js` -- drop the unused `export`s
- [x] `includes/class-pqm-db.php` -- delete `get_latest_review()`, make the two constants private, fix comments
- [x] Stale comments in the files the Code Map lists -- comment-only edits
- [x] `src/kit/style.css`, `src/dashboard/style.css`, `admin/css/pqm-admin.css` -- delete the three redundant rules
- [x] `package.json`, `package-lock.json`, `.gitattributes`, `.github/workflows/ci.yml`, `src/**` -- `typescript ~5.3.3` devDependency via `npm install -D`, `* text=auto eol=lf`, `"lint:js": "wp-scripts lint-js src"`, a CI lint step in the node job, the 40 errors fixed -- lint runs locally and in CI
- [x] `src/kit/` new hook file (e.g. `use-single-run.js`), `src/post-reviews-table.js`, `src/dashboard.js` -- one hook owns the single-run plumbing; each page passes only its version setter/refetch -- one trigger source; bulk (`startRun`) unchanged
- [x] `src/post-reviews/style.css` (new), `src/kit/style.css`, the Post Reviews entry that imports it -- move the Post-Reviews-only rules, same order inside the moved block -- kit holds shared rules only
- [x] `post-quality-manager.php:7`, `admin/class-pqm-admin.php:453-457`, `README.md:37`, `AGENTS.md:8` -- floor 6.6; delete the guard branch and its notice; doc lines updated -- one stated floor
- [x] `memory/admin-ui-rebuild.md` -- record the kit's new exports and hook, the Post Reviews stylesheet, the 6.6 floor and the lint script; `memory/MEMORY.md` line if its summary changes

**Acceptance Criteria:**
- Given the branch, when grepping `src/`, then no file under `src/dashboard*` imports from `src/post-reviews*` (or the reverse), no `src/kit/` file imports a page module, and `get_latest_review`, `.pqm-yoast-badge` and "stories 5.3–5.6 later" have no hits.
- Given `npm run lint:js` locally and in CI, then it exits 0.
- Given `npm run build`, `php -l` on touched PHP, `node --check post-quality-manager/admin/js/pqm-admin.js` and the harness sweep, then all are green with the same check counts as before (caps 173, cron-gate 24, license 21, quota 40, render-matrix 38, rest-posts 133, rest-reviews 24, sanitize-matrix 86).
- Given the real site, when the owner walks the matrix, then nothing differs from today (owner pass; 5.9 re-walks it too).

Skipped: unused SELECT columns `slug`/`post_type`/`latest_command` in `get_all_posts_with_scores()`. They're harmless; drop them when the query is next touched. Skipped: the three status lists (`STATUSES`, `CHIP_STATUSES`, `STATUS_TILES`), which differ on purpose (order/extras). Skipped: `Object.hasOwn` guards (rejected in 5.2, owner may override). Note: deferred-work's 5.2 `error` badge entry is already resolved by 5.4 (`status-badge.js:19-20`, `copy.js:78-79`); the file is append-only, so 5.9 or the owner closes it.

## Implementation Notes

- Kit: `src/kit/runs.js` exports `POSTS_PATH`, `reviewsPath()`, `canRender()`, `isGated()`, `inFlight( item, command = 'review' )`; `copy.js` exports `OPEN_IN_EDITOR`; `status-badge.js` uses `NONE_BADGE`. No file under `src/dashboard*` imports `src/post-reviews*` or the reverse; `src/kit/` imports only kit files.
- Hook: `src/kit/use-single-run.js` — `useSingleRun( items, listKey, { onStarted, onSettled } )` returns `refreshKey`, `settlePending( key )`, `awaited`, `pendingPostId`, `startRun`, `trigger`, `toasts`, `removeToast` (it owns `useToasts` and `usePolling`). Post Reviews passes its detail-version bump as both callbacks (as before: a run start and a completion both refetch the panel); the Dashboard passes only `onSettled` (its `/dashboard` refetch), so it sends no extra request on a trigger. Post Reviews' "silent" refetch flag became "the effect saw a new `refreshKey`": every refetch the hook issues was silent before too.
- Lint: after the LF conversion, 94 `prettier/prettier` hits were real formatting, not CRLF; they were fixed with `wp-scripts lint-js src --fix` (whitespace and line wrapping only). The 40 rule errors were fixed by hand: JSDoc `@param` lines, `eslint-disable camelcase` blocks on the REST keys (`setBand`, the bulk batch map), `runLabel()` as two `sprintf()` calls with the same strings, and `sortable-header.js` unsorted class `'desc'` (what the nested ternary always gave).
- `package-lock.json`: `typescript` 7.0.2 → 5.3.3 drops the 20 `@typescript/typescript-<platform>` packages TS 7 pulled in.
- Comments: `class-pqm-admin.php` agent-labels doc (Settings page + n8n Workflow page preview) and `render_import_url()` doc (n8n Workflow page); the `Workflow Generator (admin-post download)` banner is still accurate and stayed. `render_app_shell()` doc drops the "core too old" case with the guard.
- Verification: `npm run lint:js` exit 0; `npm run build` green; sorted rule diff of `style-index.css` and `-rtl.css` vs the pre-change build shows only the two removed redundancies (123 → 122 rules; `.pqm-panel-actions .pqm-quota-note` gone, `.pqm-queue-row .pqm-score-badge` without `text-align`); bundle i18n strings and translator comments are identical (108 strings); `php -l` clean on the four touched PHP files; `node --check post-quality-manager/admin/js/pqm-admin.js` clean; harness sweep green with the AC counts (173 / 24 / 21 / 40 / 38 / 133 / 24 / 86); AC grep: no hits. The owner's real-site walk (LTR/RTL) is still to do.
- 2026-10-05, after review (owner-approved): triage row 1 resolved — `AGENTS.md:8` now reads "Settings and n8n Workflow use vanilla JS (`admin/js/pqm-admin.js`, no jQuery)". No `bmad-project-context` source to update: a refresh re-verifies each block line against the code, so the corrected fact survives it. The `deferred-work.md` entry is resolved by this change.

## Plan Change Log

- 2026-10-05, CSS move scope: besides the listed ranges (`:67-175,243-409,636-647`), the kit's `.pqm-wrap:has(#pqm-post-reviews-root)` rule (`:20-24`) and the detail-panel block (`:414-616`: detail notes, tabs, pillar meters, top edits, result meta, action row, locked button, result renderer, history) also moved to `src/post-reviews/style.css`, in their original order, because only Post Reviews renders them and the frozen decision says the kit keeps shared rules only. Kit keeps: `.pqm-wrap`, score badge, quota note, status badge, progress pills, `.pqm-muted`, toasts.
- 2026-10-05, lint: the 94 non-CRLF prettier hits were auto-fixed (see Implementation Notes); the plan had counted every prettier hit as CRLF.

## Review Triage Log

**Pass 1 (quick lens, 2026-10-05):** 1 finding: 0 high, 1 medium, 0 low, 0 false. 0 patched, 1 deferred, none rejected. Orchestrator checks before the lens (step 03, against the diff): lint exit 0, build exit 0, `php -l` clean on the 4 PHP files, `node --check pqm-admin.js` clean, harness sweep green at 173 / 24 / 21 / 40 / 38 / 133 / 24 / 86; an independent source-level CSS rule comparison shows only the two intended redundancies removed, and of the rule pairs whose order changed, the only two overlaps are harmless (one wins on specificity, the other targets different elements on different pages); the shared hook reproduces both pages' old busy/silent/toast sequence.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `AGENTS.md:8` now says "Settings and n8n Workflow use jQuery"; neither does | medium | defer | Verified: `pqm-admin.js` is a vanilla IIFE on `DOMContentLoaded`, enqueued with deps `[]` (`class-pqm-admin.php:145-148`); no `jquery` anywhere in the plugin outside the build (only n8n `$(` expressions in the template). The wording came from this plan's Code Map. Misleads every agent session, which loads this file first. Fix edits an agent-context file, so the triage rule routes it to defer and the owner decides: proposed wording "Settings and n8n Workflow use vanilla JS". Logged in `deferred-work.md`. |

## Design Notes

Kit placement: helpers both pages need sit in `src/kit/runs.js` (the trigger/run module 5.6 created), copy in `copy.js`. That keeps the 5.6 rule that the kit never imports a page. The verification for the CSS rows: build before and after, then compare the sorted rule lists of `style-index.css` and `style-index-rtl.css`. The only differences should be the deleted redundancies.

Line endings: the index is already LF, so `.gitattributes` changes no committed content. After adding it, convert only the `src/` working-tree files to LF so local lint is clean (`git status` must show no extra diffs from this). Never `git reset --hard` or re-checkout the tree: other uncommitted work would be lost.

## Verification

**Commands:**
- `npm run lint:js` -- expected: exit 0
- `npm run build` -- expected: exit 0; sorted rule diff of `build/style-index.css` / `-rtl.css` vs the baseline shows only the removed redundancies
- `php -l` on each touched PHP file; `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green, counts as in the ACs
- the AC grep -- expected: no hits

**Manual checks:**
- Real site: the matrix rows on both pages, LTR and RTL, console open; Settings and Workflow unchanged.
