---
title: 'Post Reviews: Yoast column and Pending marker'
type: 'feature'
ticket: '10'
created: '2026-10-05'
status: done
baseline_revision: 'd16f8f21bc1f4cf44851076d951138b6ac2bde89'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Story 5.7 stripped the legacy list's Yoast SEO column (SEO + readability badges while Yoast is active, Current Value in user-paths B2) and its "— Pending" title marker without porting them (owner decision 2026-10-05). The React Post Reviews table shows neither, so B2 lost its Yoast context and pending posts look published.

**Approach:** `GET /posts` gains, additively, item fields `wp_post_status` and `yoast` (`{seo, readability}`, each int or null when Yoast has no score; the whole field null while Yoast is inactive) plus a top-level `yoast_active` flag. The React table shows a "— Pending" marker after pending titles and, only while Yoast is active, a display-only Yoast SEO column with labelled kit badges.

## Boundaries & Constraints

**Always:** Additive only: existing item and top-level keys keep their order and values; new keys are appended. The `pqm_view_reviews` permission and arg sanitizing stay as they are. One postmeta cache prime per request while Yoast is active (no per-row query). Badge colours come from the kit's `pqm-score-*` thresholds. All new strings go through `@wordpress/i18n` and follow docs/brand.md. Column count (`colSpan`) stays right with and without Yoast and bulk.

**Never:** No change to the `post-score` route or its `(int)` Yoast reads (epic note, assumption 2026-10-05), the DB query, the n8n template, the Dashboard, sorting or filtering. The Yoast column is not sortable. No new npm or Composer dependency.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Yoast inactive | `WPSEO_VERSION` undefined | items `yoast: null`, `yoast_active: false`; no Yoast column, layout intact | — |
| Yoast scored post | linkdex `72`, content_score `45` | `yoast: {seo: 72, readability: 45}`; badges "SEO 72" (green) and "Readability 45" (orange) | — |
| Yoast unanalyzed post | both metas `''` | `yoast: {seo: null, readability: null}`; one grey "—" badge with tooltip "No Yoast analysis yet. Open the post in the editor to generate one." | — |
| One score missing | linkdex `80`, content_score `''` | `{seo: 80, readability: null}`; "SEO 80" green, "Readability —" grey | — |
| Pending post | `wp_post_status: 'pending'` | title followed by " — Pending" | — |
| Published post | `wp_post_status: 'publish'` | no marker | — |
| Empty / filtered list | Yoast active, no items | empty-state row spans every column | — |

**Decision (owner, 2026-10-05): plan size.** The plan is kept whole at about 2,100 tokens: one goal, and the server fields only matter once the table shows them.

**Decision (owner, 2026-10-05): matrix colour.** The "Yoast scored post" row read "SEO 72" (orange); corrected to green, as the kit thresholds give (71+ green), at the owner's request after review triage row 1.

</frozen-after-approval>

## Code Map

Paths under `post-quality-manager/` unless they start with `src/`, `tests/` or `docs/`.

- `includes/class-pqm-rest-api.php` `list_posts_callback()` :614-687 -- after `$post_ids` (:644): `$yoast_active = defined( 'WPSEO_VERSION' )`; when active and ids exist, `update_postmeta_cache( $post_ids )` (legacy pattern). Append to each item `'wp_post_status' => (string) ( $row['wp_post_status'] ?? '' )` (the DB row already carries it, `class-pqm-db.php:324`) and `'yoast' => $yoast_active ? [ 'seo' => …, 'readability' => … ] : null` from `_yoast_wpseo_linkdex` / `_yoast_wpseo_content_score`, `''` → null else `(int)`, through one small private helper. Append `'yoast_active' => $yoast_active` after `viewer`. Extend the docblock with a story 5.10 line. Leave `post_score_callback()` :177-250 untouched.
- `src/kit/score-badge.js` -- gains an optional `label` prop (renders "`label` `value`") and an optional `title` prop passed through to the span. Existing callers (table score, `fix-next-queue.js`, `detail-panel.js` ×2) are unchanged.
- `src/post-reviews-table.js` -- read `data.yoast_active` into state. `PostRow`: after the title link inside `<strong>`, ` — <span className="post-state">Pending</span>` when `item.wp_post_status === 'pending'`; when `yoastActive`, a `<td className="pqm-cell-yoast">` after the score cell with a small local `YoastBadges` (both null → one titled `ScoreBadge score={ null }`; otherwise two labelled ScoreBadges with titles "Yoast SEO score" / "Yoast readability score"). Header: plain `<th scope="col" className="pqm-col-yoast">Yoast SEO</th>` after Score (legacy order). `columns` (:407) adds 1 when Yoast is active. Update the file header with a story 5.10 line.
- `src/kit/style.css` -- after the column widths (:62-86): `.pqm-col-yoast` width 130px, and `.pqm-cell-yoast .pqm-score-badge` compact and stacked (display block, width max-content, margin 2px 0, padding 2px 8px, font-size 11px, min-width 0), adapted from the legacy `.pqm-yoast-badge` rule.
- `tests/harness/rest-posts-harness.php` -- add `get_post_meta` and `update_postmeta_cache` stubs at WP's arity. Update the key-order checks :213-220 (append `wp_post_status`, `yoast`; `yoast_active` top-level). New rows: Yoast off (field null, flag false, no meta read), then `define( 'WPSEO_VERSION', … )` and Yoast on (scored, unanalyzed, one missing, cache primed once with the page's ids), and a `pending` fixture passing through.
- `README.md:39`, `docs/user-paths.md:71` (B2) -- drop the "removed … follow-up story" wording; state the column and marker are back (5.10).
- `memory/admin-ui-rebuild.md` -- replace the "Parity gaps accepted…" sentence with the 5.10 facts (new fields, column only while Yoast active, ScoreBadge `label`/`title`).
- Untouched: `templates/n8n-workflow.json` (it reads `wp/v2/posts`, never `GET /posts`), `class-pqm-db.php`, the Dashboard.

## Tasks & Acceptance

**Execution:**
- [x] `includes/class-pqm-rest-api.php` -- add `wp_post_status`, `yoast`, `yoast_active`, cache prime, helper -- the data the table needs, additive
- [x] `tests/harness/rest-posts-harness.php` -- stubs, updated shape checks, Yoast off/on + pending rows -- covers the matrix's server half
- [x] `src/kit/score-badge.js` -- optional `label` and `title` props -- one badge source for the Yoast pair
- [x] `src/post-reviews-table.js`, `src/kit/style.css` -- Pending marker, conditional Yoast column, colSpan, compact badge CSS -- the UI half
- [x] `README.md`, `docs/user-paths.md`, `memory/admin-ui-rebuild.md` (+ `memory/MEMORY.md` line if its summary names the gap) -- truth pass

**Acceptance Criteria:**
- Given the rest-posts harness, when run, then the new rows pass and every pre-existing check still passes (keys only appended).
- Given `npm run build`, `php -l` on touched PHP files and the full harness sweep, then all green.
- Given a real site with Yoast active then deactivated, when Post Reviews loads, then the matrix rows hold and the browser console shows no React key or prop warnings.

## Implementation Notes

- 2026-10-05: the helper is `PQM_REST_API::yoast_score( $post_id, $key )` (one meta → `?int`), called twice per item. The harness's Yoast-on rows run last because `WPSEO_VERSION` cannot be undefined once defined; they also cover an empty list with Yoast on (no cache prime) and a missing `wp_post_status` (reads as `''`). `memory/MEMORY.md` left as is: its admin-ui-rebuild line does not name the gap.
- Verification run: `npm run build` compiled; `php -l` clean on both PHP files; full harness sweep green (rest-posts 133 passed, 0 failed). `npx wp-scripts lint-js` cannot run in this checkout (`@typescript-eslint` plugin load error in node_modules, unrelated to this change). Manual site checks not run yet.
- Orchestrator re-check (step 03, against the diff): build exit 0, `php -l` clean, all 8 harnesses exit 0 with 0 FAIL lines (caps 173, cron-gate 24, license 21, quota 40, render-matrix 38, rest-posts 133, rest-reviews 24, sanitize-matrix 86).
- Matrix test audit: the server half of every row is covered by `rest-posts-harness.php` and ran green. Those checks cover the Yoast inactive, scored, unanalyzed and one-missing payloads, the pending and published `wp_post_status`, and the empty list with Yoast on. The render half of each row (badge text and colours, the tooltip, the marker, no column while Yoast is off, `colSpan`) is React output. The repo has no JS test path (deferred in 5.5), so the render half is covered only by the pending manual site pass, as in 5.3–5.7.
- 2026-10-05, owner-requested fold-in (bug found after the site pass): the Status badges overflowed the fixed 100px Status column into Analyzed (the table is `fixed`, so a column never grows; the width dated from the emoji-status layout). `src/kit/style.css`: Status 100 → 150px, Actions 180 → 130px (net width unchanged, still fits "Hide details"), and the badge may wrap inside its cell as a fallback for longer labels (translations, "Could not complete"). Owner follow-ups in the same change: the progress column header "Analyzed" became "Agents run" (it sat next to the "Not analyzed" status but shows which agents ran; the approved mockup said "Analyzed"); the Post column gets the room: the Post Reviews page drops the 1,200px `.pqm-wrap` cap (full width like core list tables, `:has(#pqm-post-reviews-root)`, so the Dashboard keeps it), Score 100 → 80px, and long title words wrap (`.pqm-cell-title`); Last reviewed 160 → 180px so date and time fit on one line. Then, at the owner's request ("apply best UX practices"), the widths were redone: all column widths in em (scale with zoom; the new `.pqm-table-scroll` box carries the headers' 14px so 1em matches), the table sits in a focusable, labelled scroll region with `min-width: 51em` so Post never drops under ~16em, and container queries drop the columns other places cover as room shrinks: Agents run under 93em, Yoast SEO under 75.5em, Last reviewed under 65em (worst-case breakpoints, bulk + Yoast on). The details toggle may wrap. Not done: letting the user bring a hidden column back (core's Screen Options toggles). Owner site check of the whole follow-up (resize order, 150% zoom, keyboard scroll, RTL) green 2026-10-05.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, 2026-10-05):** 3 findings: 0 high, 0 medium, 2 low, 1 false. 1 patched by the implementer, 2 rejected, none deferred.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | Matrix row "Yoast scored post" expects "SEO 72" orange, but `scoreClass()` gives green for 72 | low | reject | The bad outcome is in the plan, not the code: 72 ≥ 71 is green under the kit thresholds the Always constraint requires, and the legacy `score_class()` used the same cut. The fix edits this build's frozen matrix, so it's rejected here and raised to the owner at presentation (proposed: "SEO 72 (green)"). |
| 2 | AC 3 (real-site check, console free of React warnings) not run; `lint-js` can't run in this checkout | false | reject | No defect in the change. The plan's Verification routes the render half to the owner's manual site pass, as in 5.3–5.7, and the matrix test audit records that. The `lint-js` failure is a pre-existing `node_modules` plugin load error, and the build compiles the JS. |
| 3 | `docs/user-paths.md:71` (B2) states the column and marker as delivered before validation; B2 tags unvalidated deliveries "pending validation" | low | patch | Verified: the 5.6 note in the same section uses a dated "pending validation" tag. A direct wording correction; re-engaged the implementer. |

## Design Notes

**Ticket unknown (settled here, override at the checkpoint):** `ScoreBadge` gains optional `label` + `title` props rather than a new kit component wrapping `pqm-score-*`. That's two props on the existing source, and it keeps thresholds and colours in one place. The compact Yoast look is a cell-scoped CSS rule, not a variant prop.

**Pending marker:** it uses WordPress core's own `post-state` markup, exactly as the core Posts list renders "— Draft" (bold, inside `<strong>`). The legacy `.pqm-post-state` grey/normal override is not restored. Skipped: the custom de-emphasis. Add it back if the owner wants the legacy look.

Skipped: Yoast scores in the Dashboard queue or detail panel. Those belong to `epic-yoast-context`.

## Verification

**Commands:**
- `npm run build` -- expected: exit 0
- `php -l post-quality-manager/includes/class-pqm-rest-api.php tests/harness/rest-posts-harness.php` -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green, 0 FAIL lines

**Manual checks:**
- Real site (Yoast on): Post Reviews shows the Yoast SEO column after Score. A scored post shows two labelled coloured badges, and an unanalyzed post shows one grey "—" with its tooltip. A pending post shows " — Pending". Open Details on a row: the expanded row spans the full width. Deactivate Yoast and reload: no column, layout intact, Details still full width. Check with and without the bulk checkbox column (Admin vs a role without Review).

**Result (2026-10-05):** the owner walked the manual checks on the real site: Yoast on (scored, unanalyzed and pending posts, Details spanning the full width), Yoast off (no column, layout intact), with and without the bulk column, console open. All green. Story verify is met end to end. The frozen matrix row "Yoast scored post" was corrected afterwards (owner decision "matrix colour").
