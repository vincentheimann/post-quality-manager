---
title: 'Post Reviews: bulk Review selected'
type: 'feature'
ticket: '5'
created: '2026-10-05'
status: done
baseline_revision: '01a72867c82540efcb59195635988e3962357e08'
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

**Problem:** Running Review on several posts means opening each post's Details and clicking Run, one post at a time. The owner chose to ship bulk "Review selected" in the first slice (DESIGN.md D4, CAP-2).

**Approach:** Add a checkbox column with select-all for the current page and a bulk bar ("Review selected (N)"). One confirmation then queues `POST /trigger-review` calls one after another, from the client. The server already enforces the gate and quota on each call. Each post's outcome (requested, or the server's refusal) is reported per post, and the 5.4 polling picks up every requested run as usual.

## Boundaries & Constraints

**Always:** no new REST route and no PHP change: the existing trigger endpoint is the per-post enforcement point (spec: prefer existing REST). The batch command is `review` only. The checkbox column and bulk bar render only when the viewer can run Review (same rule as the panel's `canRender`). A post whose review is in flight cannot be selected. Selection is page-local and clears on any query change (filter, sort, page) and when a batch starts. Triggers run strictly in sequence, each awaited. A refusal does not stop the queue: every selected post gets its own call and its own outcome. Each success re-arms polling (`restart`) and triggers a silent refetch, so rows switch to Processing as the queue advances. A batch keeps running if the user navigates (ids and titles are captured when the batch starts). Checkboxes carry `aria-label` "Select: {title}" (mockup). All copy goes through `@wordpress/i18n` per brand.md. KISS.

**Decision (owner, 2026-10-05) — outcome report:** per-post outcomes show in an inline, dismissible `Notice` under the bulk bar. It lists "{title}: Review requested" or "{title}: {server message}", fills in as the queue runs, and stays until dismissed. No per-post toasts for the trigger outcome. The 5.4 completion toasts from polling stay as they are.

**Decision (owner, 2026-10-05) — confirmation:** the one confirmation is a `@wordpress/components` `Modal` with Cancel and a primary "Review N posts" button, stating the count and, when a quota applies, the actions left. Not `window.confirm()`: the owner prefers WordPress components over browser natives by default.

**Never:** no bulk commands other than Review; no cross-page selection; no client-side quota pre-blocking (the server decides; the confirmation only states the count and remaining quota); no Dashboard (5.6); no legacy PHP/JS deletion (5.7); no `wp-data` dependency.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Select | tick 3 rows | Button "Review selected (3)", hint "3 selected — one confirmation, then they queue one by one." | No error |
| Select all | header box on a page of 20, 2 in flight | 18 ticked; header box checked; in-flight rows disabled | No error |
| Cancel | confirm dismissed | Nothing sent; selection kept | No error |
| Batch success | 3 posts, n8n 2xx each | 3 sequential calls; per-post "Review requested"; rows go Processing; completion toasts as they finish (5.4) | No error |
| Quota runs out | 5 selected, 2 actions left | Posts 1–2 requested; 3–5 each show the server's "Monthly limit reached…" message | 429 per post, reported, queue continues |
| Gate / config / n8n | 403 / 400 / 502 / `success:false` | That post shows the server message (or the 5.4 "could not start — HTTP n" copy) | Reported per post, queue continues |
| Navigate mid-batch | page change while running | Selection cleared; queue finishes; outcomes still reported by title | No error |
| No trigger right | `viewer.can_trigger` false or review hidden | No checkbox column, no bulk bar; table as in 5.4 | No error |

</frozen-after-approval>

## Code Map

- `src/post-reviews-table.js` -- owns selection: `selected` (Set of post ids) state, cleared in the fetch effect when `query` changes; `COLUMNS` becomes `6 + (canBulk ? 1 : 0)`. `PostRow` :191 gains a leading checkbox `<td>` (native `<input type="checkbox">`, matching the mockup and the WP list-table look) when `canBulk`. The header gets a select-all `<th>`. **Refactor `trigger()` :374-427** into `startRun( postId, command )`, which returns `Promise<{ ok, message }>` and does the API call, `restart`, `setDetailVersion` and silent refetch on success. `trigger()` keeps busy state and toasts on top of it; the bulk bar reuses it. `inFlight()` :125 decides which rows are selectable.
- New `src/post-reviews/bulk-bar.js` -- props: `selection` (`[{ post_id, title }]`), `viewer`, `startRun`, `onStart` (clears the selection). Owns `running` / `progress` / `results`. Renders the button + hint (mockup :229-230 and :384-391 copy), the confirmation `Modal` (`title`, `onRequestClose` = cancel; focus handling comes from the primitive), then the sequential loop (`for … await startRun`). Renders the per-post report as a `Notice` (`isDismissible`, `onRemove` clears `results`) holding a `<ul>` of outcomes.
- `src/kit/copy.js` -- only shared strings go here (none expected). Bulk copy lives in `bulk-bar.js`, the same way 5.4 kept the trigger copy in the table.
- `src/kit/style.css` -- `.pqm-bulk-bar` (reuse `.pqm-table-topbar` flex) and a narrow checkbox column `.pqm-col-select`.
- Reuse, do not rewrite: `canRender` logic (`detail-panel.js` :82-87; export it if needed), `useToasts`, `usePolling.restart`, `QuotaNote` data (`viewer.quota.remaining`).
- Untouched: `class-pqm-rest-api.php` (`trigger_review_callback` :383-493 already refuses per call: 403 gate, 429 quota, 400 not configured, 404 post, 502 n8n), `use-polling.js`, harnesses.

## Tasks & Acceptance

**Execution:**
- [x] `src/post-reviews-table.js` -- extract `startRun`, selection state, checkbox column, select-all, mount `BulkBar` -- single and bulk runs share one trigger path.
- [x] `src/post-reviews/bulk-bar.js` -- bar, confirmation, sequential queue, per-post report -- the feature.
- [x] `src/kit/style.css` -- bulk bar and checkbox column styles.
- [x] Manual pass of matrix rows 1–8 on the real site. No JS test runner exists in the repo, and no PHP changes, so there is no harness to extend.

**Acceptance Criteria:**
- Given 3 selected posts, when confirmed, then exactly 3 `trigger-review` requests go out one after another (network panel: each starts after the previous one resolves), and each post's outcome is visible by title.
- Given a single Run Review from Details after the refactor, then 5.4 behaviour is unchanged (toast, busy until the refetch, polling, completion toast).
- Given `npm run build` and the harness sweep, then all green.

## Implementation Notes

- `startRun()` resolves `{ ok, message }` and never rejects (two-argument `.then`, so only the API call's failure maps to a refusal). `trigger()` sets `pendingUntilKey` from `refreshCounter.current` right after `startRun` resolves: that is the silent refetch `startRun` just issued (same microtask chain, no polling tick can interleave).
- `canRender` is now exported from `detail-panel.js`; the table derives `canBulk` from it. The column count is `6 + ( canBulk ? 1 : 0 )`.
- Selection is a Set of ids; the effective selection is derived each render as "selected and not in flight", so a row that goes Processing (single run or polling) drops out of the count and its box unticks; an effect on `[ items ]` also removes in-flight ids from the Set, so the row does not come back ticked when its review completes. The modal's button row is `.pqm-confirm-actions` (legacy `pqm-admin.css` still owns `.pqm-modal-actions`). A separate `useEffect` on `[ query ]` clears the Set (the fetch effect also runs on `refreshKey`, which must not clear it).
- Progress is `results.length` of `batchSize`; the report `Notice` passes `spokenMessage` = the newest outcome line, so screen readers hear each outcome once and not the whole list on every update. It is not dismissible while the batch runs.
- Build: `index.asset.php` dependencies unchanged (`react-jsx-runtime`, `wp-api-fetch`, `wp-components`, `wp-element`, `wp-i18n`, `wp-url`); `async`/`await` compiles without a polyfill dependency.
- Skipped: indeterminate state on the select-all box (not in the matrix): add when partial page selection needs a visual cue.
- `npx wp-scripts lint-js` currently crashes in this checkout (`@typescript-eslint` / `ts-api-utils` load error, tooling, not code); lint was not run.
- Matrix test audit (step 03): no JS test runner exists and no PHP changed, so rows 1–8 have no automated test. They are covered by the pending manual site pass (last task), the same way 5.4 covered its UI matrix rows.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, 2026-10-05): 11 findings. 5 patch (all low except the first, medium), 1 defer, 5 rejected (2 false, 3 low).

- **medium / patch:** `post-reviews-table.js` selection. Ids stay in `selected` while a row is in flight, and the filter only hides them at render time. Verified: tick A, run A from Details, and A unticks while Processing. When the result lands, A is ticked again and back in the bulk count, so the next batch re-reviews it and spends quota without the user re-selecting it. Fix: prune in-flight ids from the Set whenever `items` change.
- **low / rejected:** `bulk-bar.js` `run()` does not re-check in-flight status before each call. The only way in is the user starting a queued post from Details mid-batch, or another tab doing so. The server also accepts duplicate single triggers from two tabs (pre-existing). The fix needs a live-items getter threaded into the loop, which is a new guard. Unlikely in everyday use.
- **low / patch:** `bulk-bar.js` Modal. `count` is live, and if polling drops it to 0, "Review 0 posts" stays enabled and confirming wipes the previous report. Verified: the primary button has no `disabled`. Fix: disable it at `count === 0`.
- **low / rejected:** the error early return in `post-reviews-table.js` unmounts `BulkBar` mid-batch, so outcomes are lost. Real, but it needs a REST failure on a non-silent fetch while a batch runs. The triggers still happen as confirmed, and the reload the error notice asks for shows Processing rows. Restructuring the error branch is more than a direct correction.
- **low / patch:** `bulk-bar.js` "Review selected (%d)" and "%d selected — … they queue one by one." use `__()` with a count. Verified: English reads "1 selected — then they queue", and plural forms can't be translated. Fix: `_n()`.
- **low / rejected:** untitled posts give an empty "Select: " label and ": Review requested". `GET /posts` returns the raw `post_title` (:643), but the trigger route only accepts publish/pending posts, which are titled in practice. The title cell itself is already blank for these (pre-existing). The fallback is a new branch for a rare case.
- **low / patch:** `style.css` `.pqm-modal-actions`. Verified: it collides with legacy `admin/css/pqm-admin.css:442` (`justify-content: center`), which is still enqueued, and breaks the rule in `memory/admin-ui-rebuild.md` about avoiding legacy class names. Fix: rename to `.pqm-confirm-actions`.
- **low / patch:** `memory/MEMORY.md` index line still says "(stories 5.2–5.4); bulk actions … pending". Verified. Fix: update the line.
- **low / patch:** `memory/lessons.md` has no entry for the owner's correction (the plan recommended `window.confirm()`, and the owner chose `Modal` by default). AGENTS.md requires one. Fix: append an entry (orchestrator, not the implementer: it records the planning session's mistake).
- **false:** `plan-fix-score-band-rest-500.md` frontmatter edit. This is the owner-approved cleanup from the same session (it clears the `tickets.py` "names no entry" warning) and is committed separately. It isn't part of 5.5's code.
- **low / defer:** the matrix and ACs have no automated check, and lint isn't run. Pre-existing: the repo has no JS test path (5.1–5.4 covered UI matrix rows with the manual pass), and `wp-scripts lint-js` crashes on `@typescript-eslint`. The manual pass remains this story's last task. Deferred as a JS test path + lint fix.

## Design Notes

Why client-side sequencing and not a bulk endpoint: CAP-2 asks for gate and quota per post with per-post refusals, which the single trigger endpoint already does. A bulk route would duplicate that enforcement and break "prefer existing REST". Sequential rather than parallel calls keep quota consumption honest: the server sees the quota after each spend, so a 429 lands exactly on the posts past the limit. Why the queue doesn't stop at the first 429: the spec says refusals are reported per post. Continuing is the literal contract and the simplest loop; an admin raising the limit mid-batch also works. Why selection clears on navigation: the selected ids might no longer be visible, so a confirmation would act on rows the user can't see.

## Verification

**Commands:**
- `npm run build` -- expected: exits 0, no new script dependency beyond the existing set
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green (unchanged)

**Manual checks (if no CLI):**
- Real site, free-plan Author with a low quota: select more posts than the remaining actions, then confirm. The first posts are requested and the rest each show the limit message. Rows switch to Processing and completion toasts follow. A `pqm_view_reviews`-only user sees no checkboxes and no bulk bar. Select-all skips in-flight rows. Changing page mid-batch clears the selection and the report still completes.

**Result (2026-10-05):** the owner walked the manual checks on the real site and reported all green. That pass covers matrix rows 1–8 and both browser ACs (sequential requests, single run unchanged). Story verify met end to end.

**Re-run (2026-10-05):** story 5.6 moved the shared trigger / polling / toast code into `src/kit/`; the owner re-walked matrix rows 1–8 and the single-run AC on the 5.6 build — all green.
