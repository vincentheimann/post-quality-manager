---
title: 'Post Reviews: list, filters, sorting'
type: 'feature'
ticket: '3'
created: '2026-10-05'
status: 'done'
baseline_revision: '44cf780a3c5f976ddd62b7744037032c75d66ca0'
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

**Problem:** The React Post Reviews table (5.1/5.2) loads the 100 most recent posts with no way to narrow or order them — the triage friction DESIGN.md F6 names (no status or score filter, 3-of-7 sortable columns, fixed page) is still there, and the Dashboard tiles (5.6) have no filtered view to land on.

**Approach:** Extend `GET /posts` (read-only, additive) with a `status` filter incl. "not analyzed", a `min_score`/`max_score` band, `orderby=status` (severity order) and a per-status `counts` block; the table gains the mockup's filter bar (status chips with counts, score select, quota note right), sortable column headers with `aria-sort`, and 20-per-page pagination. Filter/sort/page state lives in the URL query string so tiles and links can deep-link to a filtered view.

## Boundaries & Constraints

**Always:** filtering, sorting and paging are server-side through the existing route (one request per state change; `total`/`pages` stay truthful); every new REST param is clamped like the existing ones — never an error; status chips use the kit's status labels (`src/kit/copy.js`, one source shared with StatusBadge); chips are buttons with `aria-pressed`, headers carry `aria-sort` and WP's `sortable`/`sorted` classes (native look, F4 closed); the legacy `?filter=needs_review` link from the PHP Dashboard keeps working until 5.6 replaces it; all copy through `__()`, per `docs/brand.md`; the 5.1 item shape, the five pre-existing routes and the 5.2 `viewer` block are unchanged; KISS.

**Decision (owner, 2026-10-05):** score bands align to the plugin's thresholds, not the mockup's fake-data cut-offs — *Below 41* (`max_score=40`), *41–70* (`min_score=41&max_score=70`), *71+* (`min_score=71`) — so a band equals a ScoreBadge color and "71+" equals "not needs-review".

**Never:** no checkbox column, bulk action, detail panel or row actions (5.4/5.5); no Dashboard work (5.6); no change to gate/quota/webhook semantics or the n8n template; no new REST route; no client-side filtering or sorting of a partial page; no deletion of legacy PHP/JS.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Status filter | `status=needs_revision` | SQL `lr.status = %s`; only matching posts, `total` counts the filtered set | No error |
| Not analyzed | `status=none` | SQL `lr.status IS NULL`; never-reviewed posts only | No error |
| Unknown status | `status=weird` | Ignored (treated as no filter), like other bad params | No error |
| Score band | `min_score=41&max_score=70` | `HAVING latest_score BETWEEN` via prepared ints; posts with NULL score excluded | No error |
| Score band, open-ended | `min_score=71` only | `latest_score >= 71`; `max_score` absent means unbounded | No error |
| Status sort | `orderby=status&order=asc` | `ORDER BY FIELD(lr.status, 'major_revision','needs_revision','ship','processing')` then NULLs last; `desc` reverses | No error |
| Counts | any request | `counts: {all, ship, needs_revision, major_revision, none}` over all listable posts, independent of the active filters | No error |
| Filtered empty | status + band match nothing | Table body shows one line "No posts match these filters." with a "Clear filters" button; chips/counts still render | No error |
| Deep link | URL `?page=pqm-posts&status=major_revision&orderby=score&order=asc&paged=2` | First render fetches exactly that state; chips/select/headers reflect it | No error |
| Page overflow | `paged` beyond `pages` after a filter change | Page resets to 1 on any filter or sort change; a stale `paged` still renders the last available page's empty state, pagination shows page N of M | No error |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-db.php` — `get_all_posts_with_scores()` :127-224: `status` arg already does `lr.status = %s` :161-163 — add the `none` sentinel (IS NULL); `min_score`/`max_score` join `$having_clauses` (prepared ints) next to the `needs_review` clause :157-159; `$order_map` :169-173 gains `status` → `FIELD()` expression (severity order reusable by 5.6's queue). New `count_posts_by_status()`: one `GROUP BY lr.status` query over the same `$latest_subquery` + base `$where_clauses` (publish/pending posts).
- `post-quality-manager/includes/class-pqm-rest-api.php` — `list_posts_args()` :478-512 (+ `status`, `min_score`, `max_score`); `list_posts_callback()` :525-587: whitelist `status` against the four stored values + `none`, clamp scores to 0–100 ints (absent = null), `orderby` whitelist :532 += `status`; response += `counts` after `pages`, before `viewer` (5.1 harness asserts key order of the first three).
- `src/post-reviews-table.js` — current single fetch :57-67; the topbar slot :93-95 is where the filter bar goes; headers :97-115 become sortable; `total > items.length` note :133-145 is replaced by pagination. Column headers follow the mockup: Post, Score, Status, Analyzed, Last reviewed (sentence case).
- `src/kit/copy.js` + `src/kit/status-badge.js` — move the four status labels + "Not analyzed" into `copy.js` (`statusLabel()`), StatusBadge and the chips both consume it. Kit `style.css` gets chip, filter-bar and sortable-header rules (new `pqm-filter-*` class names; legacy `.pqm-filters` left alone).
- New `src/post-reviews/filter-bar.js`, `sortable-header.js`, `pagination.js` — page-local components (not kit: nothing else consumes them). Chips = `@wordpress/components` `Button` with `isPressed` (renders `aria-pressed`); score band = `SelectControl`; pagination = WP `tablenav-pages` markup (`displaying-num`, prev/next `Button`s, "page N of M").
- URL state: `window.location.search` → initial state; `history.replaceState` on change; params `status`, `min_score`, `max_score`, `orderby`, `order`, `paged` (WP's name); legacy `filter=needs_review` passes through to the request and shows as no chip pressed.
- `tests/harness/rest-posts-harness.php` — `PQM_Stub_WPDB` records every `prepare()` :86-103 and `last_var_sql`; `limit_args()` :127-134 finds the items query — assert the HAVING/WHERE/ORDER text and prepared args per matrix row; queue one extra `get_results` for the counts query in every existing case.
- `admin/class-pqm-admin.php` :465 — the legacy Dashboard card links `?filter=needs_review`: do not touch, just honour.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-db.php` -- `none` status sentinel, `min_score`/`max_score` HAVING, `status` orderby via `FIELD()`, new `count_posts_by_status()` -- server-side truth for filters, sort and chip counts.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- args + whitelist/clamp for the three new params and `orderby=status`; `counts` in the response -- additive payload, bad params clamp like today.
- [x] `src/kit/copy.js` + `src/kit/status-badge.js` -- `statusLabel()` as the one source; StatusBadge reads it -- chips and badges can never drift.
- [x] `src/post-reviews/filter-bar.js` -- chips (All + four statuses, counts as muted suffix), score `SelectControl` (Any + bands), QuotaNote right -- the mockup's filter row.
- [x] `src/post-reviews/sortable-header.js` + `pagination.js` -- `th[aria-sort]` with a button and WP sort classes; tablenav pagination -- native look, ARIA sort state.
- [x] `src/post-reviews-table.js` -- state (status, band, orderby, order, paged) ⇄ URL, refetch on change with a loading state that keeps the current rows visible, empty-filter state with Clear filters -- wires it together.
- [x] `src/kit/style.css` -- chip/filter-bar/sort rules -- extracted with the build.
- [x] `tests/harness/rest-posts-harness.php` -- matrix rows: status, none, unknown, band, open band, status sort, counts shape; existing cases still pass with the extra counts query queued.

**Acceptance Criteria:**
- Given the real site, when any chip, score band or column sort is chosen, then the table re-renders from a fresh REST response, the URL reflects the state, and reloading the page restores it.
- Given the PHP Dashboard's "needs review" card, when clicked, then the React page shows the `needs_review` set as before.
- Given `npm run build` and the harness sweep, then everything is green and the 5.1/5.2 response keys are byte-identical.

## Implementation Notes

- 2026-10-05 — Built as planned. Points worth knowing:
  - `PQM_DB::status_order_expression()` = `IFNULL(NULLIF(FIELD(lr.status, 'major_revision','needs_revision','ship','processing'), 0), 99)` — one expression so it plugs into the existing `ORDER BY {$order_col} {$order_dir}`; NULL and unknown statuses rank 99 (last ascending, first descending — "desc reverses" literally). `PQM_DB::STATUS_SEVERITY` and `STATUS_NONE` are public constants for 5.6.
  - The latest-review subquery moved to a private `latest_review_subquery()` shared by the list and `count_posts_by_status()` so chips and rows agree on "latest" by construction.
  - The legacy `needs_review` HAVING clause is now parenthesised — it would otherwise AND incorrectly with a score band (`a OR b AND c`). The 5.1 harness only asserted the `HAVING` keyword, so no existing assertion changed.
  - `counts.all` includes `processing` (and any unknown status); the four chip keys only count their own status. Processing has no chip (mockup); `status=processing` is still accepted by the route for deep links and then shows no chip pressed.
  - Score band "Below 41" is `min_score=1&max_score=40` (not `max_score=40` alone as the frozen decision reads): score 0 is the processing / error placeholder that ScoreBadge renders neutral, so a lower bound of 1 is what keeps "a band equals a badge color" true. The frozen block is left untouched; the owner may reword it.
  - Score band options map to `min_score`/`max_score` pairs; a deep link with a min/max combination that is not one of the three bands still filters the request but the select shows "Any" (custom combinations have no option — tiles only emit the three bands).
  - Sort headers use a plain `<button>` inside `th.sortable|sorted.asc|desc` with core's `sorting-indicator` spans; kit CSS mirrors core's `th.sortable a` rules (padding, focus ring, indicator flip) for the button since core only styles anchors. `aria-sort="none"` on the unsorted sortable headers, as in the mockup. Direction class follows core's `print_column_headers()` convention: sorted = current direction, unsorted = the opposite of what the next click produces (`sortable desc` when a click sorts asc).
  - Pagination uses `Button variant="secondary"` (not `.button`, to avoid mixing core and components styles) sized to 32px via kit CSS, and adds core's `one-page` class when `pages <= 1` so the item count sits where core puts it.
  - Refetch keeps rows visible: `aria-busy` + `.is-loading` dims the body, a `role="status"` line reads "Updating…"; stale responses are ignored via an effect cancel flag. The first render also canonicalises the URL (unknown values are dropped) through `history.replaceState`.
  - `node --check` cannot parse JSX; `npm run build` is the JS syntax gate (17.3 KiB entrypoint, `wp-url` added to the extracted dependencies).

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, 2026-10-05): 7 findings — 5 patched, 1 deferred, 1 rejected. Verification re-run after the patches: `php -l` clean, 7/7 harnesses green (rest-posts 73/73), `npm run build` clean.

- **low / patch** — `class-pqm-rest-api.php` score args + `score_bound()`: `absint` sign-flips negatives (`min_score=-5` → 5), not the 0–100 clamp the Code Map names; the harness enshrined it. Verified (`absint(-5) === 5`). Patched: `intval` sanitizer + `max(0, min(100, (int)))`; harness expects `[0, 100]`.
- **medium / patch** — `src/post-reviews-table.js` `setBand` kept the legacy `filter=needs_review`, which the DB ANDs with the band: "71+" from the Dashboard card is a guaranteed-empty query (`HAVING (NULL OR <71) AND >=71`). Verified in `class-pqm-db.php`. Patched: `setBand` clears `filter` like `setStatus`.
- **low / patch** — `src/post-reviews/pagination.js`: matrix row "Page overflow" promises "page N of M" for a stale `paged`; links rendered only when `pages > 1`, so `?paged=3` on a one-page site showed no indicator. Patched: links render when `pages > 1 || page > 1`.
- **low / patch** — `src/post-reviews/sortable-header.js`: unsorted-header direction class was the inverse of core's `print_column_headers()` convention (no visual effect with current core CSS, but the "WP's sortable/sorted classes" constraint means core semantics). Patched: emit the opposite of `nextOrder`.
- **low / patch** — `src/post-reviews/filter-bar.js`: "Below 41" (`max_score=40`, no lower bound) matched score-0 rows — the `processing` placeholder `PQM_Admin::ajax_trigger` inserts with score 0 (:1517) and `error` rows — which ScoreBadge renders as the neutral "none" color, contradicting the owner decision "a band equals a badge color". Patched: low band = `1–40`.
- **low / defer** — stored `error` status missing from `LIST_STATUSES`/`STATUS_SEVERITY`/counts: `status=error` is silently ignored, `error` rows fold into `counts.all` only, and the status sort ranks them with never-reviewed. Verified (`save_review_callback` :298). Pre-existing inventory gap already deferred by 5.2 (badge); the filter/sort side is logged in `deferred-work.md` for the same owner decision.
- **low / rejected** — `count_posts_by_status()` `COUNT(DISTINCT p.ID)` per status group: two `review` rows tied on the same `reviewed_at` second with different statuses would count a post twice. Real in theory; needs a same-second duplicate save, which no path produces in everyday use, and the fix (dedupe inside the subquery) adds complexity beyond a direct correction. The list query shares the subquery, so a tie would be a pre-existing oddity there too.

## Design Notes

Server-side everything: pagination with honest totals makes client-side filtering of a 100-row page wrong by construction, and `prefer extending existing REST responses` (spec constraint) covers the new params. `counts` are facet-style (filter-independent) so a chip's number means "posts in this status", the same number a Dashboard tile will show — tiles and chips must never disagree. Severity order for the status sort doubles as 5.6's queue primary key, so it lives in SQL once. Default sort stays `reviewed_at DESC` (legacy, 5.1 harness) — the mockup's pressed Score column is a demo state, not a default. "Analyzed" (pills) is not sortable: the mockup gives it no `aria-sort` and there is no natural order; D4's "all columns" reads as all data columns.

## Verification

**Commands:**
- `npm run build` -- expected: exits 0, extracted CSS updated
- `php -l` on both touched PHP files -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness green incl. the new matrix rows

**Manual checks (if no CLI):**
- [x] Post Reviews: each chip filters and shows its count; score band narrows; every data column sorts both ways with the arrow and `aria-sort` updating; page 2 of a 20+ site works; URL deep link restores the state; Dashboard "needs review" card still lands on the filtered list; on a `pqm_view_reviews`-only user everything works and no quota note appears.

**Result (2026-10-05):** PR #23 merged (`427dc39`); the owner walked the manual checks on the real site and reported all green — no failures, no bug tickets. The three UI matrix rows (filtered empty, deep link, page overflow) are covered by this pass. Story verify met end to end.
