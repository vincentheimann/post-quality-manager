---
title: 'Dashboard: queue, tiles, sparkline'
type: 'feature'
ticket: '6'
created: '2026-10-05'
status: done
baseline_revision: '3486e81778d69b8412520a85e936be50cd8d4f9f'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/docs/brand.md'
  - '{project-root}/_bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/mockup.html'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The Dashboard is a PHP-rendered report (four stat cards, three linking to the unfiltered list) that never says what to fix next (DESIGN.md F1, user-paths B2).

**Approach:** Rebuild the Dashboard as a React page on the existing bundle: a Fix-next queue of 5, stat tiles that deep-link to filtered Post Reviews views, an average-score sparkline, and the existing contextual nudges. Data: the queue **extends `GET /posts`** with `orderby=priority` (reusing its items, `in_flight`, `viewer` and `counts` — the tiles' numbers), plus **one small read route `GET /dashboard`** for the average score, the 8-week trend and the nudge flags.

## Boundaries & Constraints

**Always:** Queue priority v1 = status severity (Major revision → Needs revision → Not analyzed), then score ascending, then staleness (oldest review first; never-reviewed posts: oldest post first), then post ID — Ready-to-ship posts never enter the queue. Per-row Review runs through the same trigger + polling + toast path as Post Reviews (one shared source, no copy duplicated). Review is shown only to viewers who can run it (`canRender( viewer, 'review' )`). Status = icon + label + color via the kit. All copy through `@wordpress/i18n`, per brand.md. Average score and trend count only posts whose latest review result is ship / needs_revision / major_revision, over published + pending posts — the same population as the Post Reviews list.

**Never:** No change to gate, quota, trigger or webhook semantics, nor to the n8n template. No new filter on Post Reviews. No chart library or new dependency. No `window.confirm()` or other browser-native dialogs. Settings and n8n Workflow pages untouched. Legacy `render_dashboard()` is not deleted here (story 5.7), only unrouted.

## Decisions (owner, 2026-10-05)

- **Tiles mirror the chips:** Average score (→ Post Reviews `?orderby=score&order=asc`), Major revision, Needs revision, Not analyzed, Ready to ship — each opens exactly one existing chip (`?status=…`) and shows that chip's count. No Post Reviews change.
- **Sparkline = catalog average as of each week's end** (8 points, the last one equals the Average score tile).
- **Plan kept whole** at ~2,450 tokens: one page replaced outright; splitting off the sparkline/nudges would ship a Dashboard that drops today's nudges.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Queue order | `GET /posts?orderby=priority&per_page=5` | ORDER BY priority rank, score ASC, reviewed ASC, post date ASC, ID ASC; `order` ignored | — |
| Fewer than 5 to fix | 2 attention posts, rest ship | Queue shows 2 rows (ship rows dropped client-side) | — |
| Nothing to fix | every post ships | "Nothing to fix next — every analyzed post is ready to ship." | — |
| Trend on known rows | reviews spread over 8 weeks | each point = average of each post's latest result as of that week's end; last point = `average_score` | — |
| Failed latest result | latest row status `''`/`error` | that post excluded from the average at that point | — |
| No history | no analyzed posts | `average_score: null`, all points null; tile shows "—", sparkline says there is no history yet | — |
| Not connected | empty webhook URL | only the "Connect n8n" call to action; button only for `pqm_manage_settings` | — |
| Cron nudge | pro, admin, cron off, ≥1 analyzed, not dismissed | Notice with "Enable scheduled reviews" + "Dismiss" (dismiss URL carries a nonce) | dismiss handled by the PHP shell as today |
| REST down | either fetch fails | Notice error, no blank page | gentle copy with next step |
| Permission | user without `pqm_view_reviews` | `GET /dashboard` 401/403 | `permission_callback` |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-db.php` -- `STATUS_SEVERITY` + `status_order_expression()` (L163–206) are the pattern for a new `priority_order_sql()`; `get_all_posts_with_scores()` order map (L268–311) gains `priority`; `latest_review_subquery()` excludes `processing`. New `get_score_trend( int $weeks = 8 )`. `get_dashboard_stats()` stays (legacy, 5.7 deletes).
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `list_posts_callback()` orderby whitelist (L609); `register_routes()` (L22–80); `check_view_reviews()`; `display_date()` (L333) for point labels.
- `post-quality-manager/admin/class-pqm-admin.php` -- `register_pages()` (L28–47) routes Dashboard; `enqueue_assets()` / `enqueue_posts_app()` (L116–229) gate on hook; `render_posts_app()` (L514–545) becomes the shared shell; legacy `render_dashboard()` (L405–504) holds the nudge rules + dismiss handler + copy to port.
- `src/post-reviews-table.js` -- `startRun`, `onSettled`, `onGiveUp`, `outcomeToast` (L132–192, L374–456): the trigger path to share. `src/post-reviews/use-polling.js`, `toasts.js`: reused as-is.
- `src/post-reviews/detail-panel.js` -- `canRender()` (L82) for the Review button.
- `src/kit/` -- StatusBadge, ScoreBadge, `statusLabel`, `commandLabel`, `STATUS_NONE`, `style.css`.
- `tests/harness/rest-posts-harness.php` -- wpdb stub with queued results and recorded SQL; SQL is not executed.
- Legacy CSS (`admin/css/pqm-admin.css`) still loads: avoid `.pqm-card`, `.pqm-stat-*`, `.pqm-stats-grid`, `.pqm-dashboard-cta`.

## Tasks & Acceptance

**Execution:**
- [x] `class-pqm-db.php` -- add `PRIORITY` rank list + `priority_order_sql()`; `orderby=priority` uses it in place of `col dir`; add `get_score_trend()` (one query of non-processing `review` rows for listable posts, PHP walk over week ends; ceiling comment: loads every review row — upgrade to stored weekly snapshots if catalogs grow) -- queue + sparkline data
- [x] `class-pqm-rest-api.php` -- accept `priority` in the orderby whitelist; register `GET /dashboard` (`check_view_reviews`) returning `average_score`, `trend[{label, average}]`, `connected`, `can_manage_settings`, `cron_nudge`, `cron_nudge_dismiss_url` (only when the nudge shows) -- one read route
- [x] `class-pqm-admin.php` -- capture the Dashboard hook; enqueue the bundle on it; `render_app_shell( $title, $root_id )` shared by Post Reviews and a new `render_dashboard_app()` (which keeps the nudge-dismiss handler); generic build/WP-version notice copy -- PHP ships only the shell
- [x] `src/kit/runs.js` (new) + move `use-polling.js`, `toasts.js` into `src/kit/` -- `requestRun()`, `outcomeToast()`, requested / give-up copy shared by both pages; `post-reviews-table.js`, `bulk-bar.js` updated, behavior unchanged -- one trigger source
- [x] `src/dashboard.js` (new) + `src/dashboard/{stat-tiles,fix-next-queue,sparkline}.js` + `src/dashboard/style.css` -- the page; `src/index.js` mounts it on `#pqm-dashboard-root` -- CAP-1
- [x] `tests/harness/rest-posts-harness.php` -- priority ORDER BY + whitelist rows; `/dashboard` registration, permission, flag matrix, trend walk on known rows (as-of, failed-latest exclusion, empty site) -- matrix coverage
- [x] `memory/admin-ui-rebuild.md`, `memory/MEMORY.md`, `docs/user-paths.md` (B2) -- record the Dashboard build

**Acceptance Criteria:**
- Given known review data, when the Dashboard loads, then the queue lists up to 5 non-ship posts in the v1 order and each row shows its status badge, score, last-review date, Review (if allowed) and Open in Editor.
- Given a queue row, when Review is clicked, then the row shows Processing, polling refreshes the queue, and an outcome toast appears — same copy as Post Reviews.
- Given any tile, when clicked, then Post Reviews opens with the matching chip pressed (or sorted by score for the average tile) and the tile number equals the chip count.
- Given a translated site, then every Dashboard string renders translated.

## Implementation Notes

- Matrix test audit (step 03): the server-side rows (queue order, trend walk, failed-latest exclusion, no history, connected/admin flags, cron-nudge matrix, permission) are covered by `rest-posts-harness.php` (120 checks, all green). The client-only rows (fewer than 5 → ship rows dropped, nothing-to-fix copy, REST-down Notice, Connect button only for admins) have no automated test: the repo has no JS test path (deferred in 5.5) — covered by the pending manual site pass, as in 5.3–5.5.

## Review Triage Log

**Pass 1 (quick lens, 2026-10-05):** 8 findings — high 0, medium 3, low 4, false 0, maybe-false 0, rejected 1 (low), deferred 1.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | Not analyzed tile/chip count > rows its list shows (failed-latest `''`) | medium | patch | `count_posts_by_status()` and `priority_order_sql()` map `''` to none; `status=none` matched `IS NULL` only (5.3 origin, exposed by the tile). Patched: none clause `(lr.status IS NULL OR lr.status = '')` + harness check. Consistent with the owner decision that a failed run lists as "Not analyzed". |
| 2 | `error` latest status ranks 99 with ship in the queue | low | reject | `save-review` would store it, but the template never sends `error` today (its failure fallback is `''`, memory/admin-ui-rebuild.md); unlikely in use, and a consistent fix spans counts + filter + priority — belongs with the existing deferred-work item that lights up the `error` badge once the template sends it. |
| 3 | Queue toasts "Review complete." for a run still in flight when the post is pushed out of the 5-row window | medium | patch | Reproducible: bulk-review many unanalyzed posts, open the Dashboard — results moving posts to Major/Needs push in-flight `none` rows out; usePolling settles on "left the query", `/reviews/{id}` newest row is the `processing` placeholder (score 0, not failed) → "Review complete.". Patched: `settledToast()` resolves null on a `processing` row; callers skip the toast. |
| 4 | Fallback toast "open Details" points to a control the Dashboard lacks | low | patch | Only on a failed `/reviews` fetch, but the fix is a direct copy correction. Patched: page-neutral copy. |
| 5 | Sparkline tooltip offset on RTL sites | low | patch | Built `style-index-rtl.css` flips `translate(-50%…)` to `50%`; inline `left` and SVG are not mirrored. Patched: wp-scripts strips comments before the RTL pass, so `/*rtl:ignore*/` cannot work — the `translate(-50%, -130%)` moved into the tooltip's inline style; neither built stylesheet carries a `translate`. |
| 6 | Kit (`src/kit/runs.js`) imports `isFailedRow` from a page module | low | patch | Dependency direction kit → page: a 5.7/5.8 change to `detail-panel.js` would break the Dashboard through the kit. Direct move. Patched: `isFailedRow` lives in `runs.js`, detail-panel imports it; `isEmpty`/`isObject` moved with it (result-view and detail-panel import them from the kit), so `src/kit/` imports no page module. |
| 7 | Average score tile list starts with unanalyzed posts (`ORDER BY latest_score ASC` puts NULL first) | medium | defer | Pre-existing 5.3 score sort; the tile follows the owner decision literally. Deferred (deferred-work.md). |
| 8 | `$_GET['_wpnonce']` unsanitized in `render_dashboard_app()` | low | patch | Ported from legacy; AGENTS.md sanitize-all rule; direct correction. Patched: `sanitize_text_field( wp_unslash( … ) )`. |

## Design Notes

Tiles reuse the `counts` block, so a tile number equals its chip by construction (5.3 built `counts` for this). Sparkline: inline SVG polyline (single series, WP blue, endpoint dot + direct label, `role="img"` + `aria-label` "from X to Y"), hover tooltip from React state like the mockup; null points are skipped; fewer than 2 points → text instead of a chart. Cards, notices and buttons are `@wordpress/components` (`Card`, `Notice` with `actions`, `Button`).

Skipped: tile delta ("▲ +3 this month") — add when the owner asks. Skipped: the mockup's "Reviews this month" tile and per-row reasons beyond status + review date (no data source / no matching filter). Skipped: "See all" link under the queue — the tiles cover it.

## Verification

**Commands:**
- `npm run build` -- expected: clean build, `build/index.js` + `style-index.css`
- `node --check` on touched JS is covered by the build (JSX); `php -l` on the three PHP files -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f"; done` -- expected: every harness green

**Manual checks (if no CLI):**
- Real site: seed known statuses/scores/dates, confirm the queue order; click each tile and check the pressed chip + count; run Review from the queue; check the not-connected and cron-nudge states.

**Result (2026-10-05):** owner walked the checklist below on the real site — all green, nothing to add. It covers every matrix row and AC, plus a 5.5 regression pass (5.6 rewired the shared trigger / polling / toast path). Kept as the script for the end-of-project agentic re-run on Local (backlog story 9).

Dashboard checklist (5.5 regression = the 5.5 plan's matrix rows 1–8 + single-run AC):
1. Not connected (webhook URL empty): only the "Connect n8n" card; button for admins only, an explanatory sentence otherwise.
2. Five tiles: Average score, Major revision, Needs revision, Not analyzed, Ready to ship. Each status tile opens Post Reviews with that chip pressed; chip count = tile number — including with a failed-latest (empty status) post under Not analyzed.
3. Average score tile → Post Reviews `orderby=score&order=asc` (never-reviewed posts first: known, deferred).
4. Queue: ≤ 5 posts, Major → Needs → Not analyzed, then score asc, oldest review, oldest post; never a Ready-to-ship post. Rows: score, title, "Last reviewed …"/"Never reviewed", status badge, Review, Open in Editor.
5. 1–4 posts to fix → only those; none → "Nothing to fix next — every analyzed post is ready to ship."
6. Review from the queue: "requested" toast → Processing → ~3 s refresh → completion toast (score + status); tile and sparkline update. Reload mid-run (also with a non-English user locale) → Processing resumes and completes.
7. Bulk-review ~10 unanalyzed posts, then open the Dashboard: no "Review complete." toast for a review still running.
8. View-only user: no Review button in the queue.
9. Sparkline: "Average score — last 8 weeks", endpoint label = Average tile; hover tooltip "Week ending {date} — average {n}" centred over the point (also RTL); < 2 weeks of history → "No score history yet…".
10. First-review nudge (connected, nothing analyzed, viewer can run Review). Cron nudge (pro, admin, cron off, ≥ 1 analyzed, not dismissed): Enable → Settings cron section; Dismiss → gone for good; absent on free, for non-admins, with cron on.
11. REST unreachable → "Could not load the dashboard…" Notice, never a blank page.
