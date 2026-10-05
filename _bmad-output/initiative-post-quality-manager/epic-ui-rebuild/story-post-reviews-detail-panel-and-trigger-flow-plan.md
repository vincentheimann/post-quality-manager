---
title: 'Post Reviews: detail panel and trigger flow'
type: 'feature'
ticket: '4'
created: '2026-10-05'
status: 'built'
baseline_revision: 'eceef0aa4a7fb35573abc871bbe1ec5b04464863'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick', 'quick']
review_loop_iteration: 1
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The React Post Reviews table is read-only: no detail (pillar scores, top edits, agent results, history), no way to run an agent, and no live update — the legacy panel/trigger/polling code is unreachable on this page and was the source of the F3 auto-resume bug, the F4 a11y gaps and three ad-hoc polling loops.

**Approach:** Add a per-row "Details" toggle opening an accessible `TabPanel` (Review with pillar meters + top edits + actions, one tab per agent, History), a trigger flow that calls `POST /trigger-review` directly (no confirm for a single run, per D4/F6), one shared 3-second polling hook that watches the list's new additive `in_flight` field and updates rows in place, and toasts in an `aria-live` region — all copy through `@wordpress/i18n`, gating from the `viewer` block.

## Boundaries & Constraints

**Always:** tabs are `@wordpress/components` `TabPanel` (keyboard + ARIA from the primitive), the row toggle carries `aria-expanded`/`aria-controls`; gating mirrors `viewer`: hidden command → no button, gated → disabled button with `LOCKED_TITLE` + visually-hidden `LOCKED_SUFFIX`, quota note only beside a rendered button for a user who can trigger; pillar meters are single-hue WP blue, magnitude only (DESIGN.md); polling is one hook, 3 s, driven by server state (`in_flight`), never by label text — it auto-resumes on page load for any in-flight command on any post; a completed run toasts the outcome (score + status for review), a failed run (status `error`, or empty status with empty result — what the shipped template actually posts) toasts a gentle, actionable message, never "finished successfully"; the `processing` placeholder is written once, server-side, only after an n8n 2xx; REST changes are additive; gate/quota/webhook *semantics* and the n8n template unchanged; legacy PHP/JS left in place (5.7 deletes); KISS.

**Decision (owner, 2026-10-05) — failed runs:** this story surfaces a failed run through the toast and the detail panel only (a failed row = status `error`, or empty status with empty result); the list keeps showing "Not analyzed" for it and the save-review contract, `LIST_STATUSES`, `STATUS_SEVERITY` and the chips are untouched. The kit gains an `error` badge now — "Could not complete", neutral gray, `dismiss` icon — which becomes visible once the n8n template sends `status: 'error'` (separate backlog ticket, recorded in `deferred-work.md`).

**Never:** no bulk select/checkbox column (5.5); no Dashboard (5.6); no Rewrite *tab* — Rewrite is a button in the Review tab's action row, its outcome lives in History and the toast (mockup); no per-command bespoke result layouts beyond Review — agent tabs use one structured renderer; no `core/notices` store or `wp-data` dependency; no confirm dialog for a single trigger.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| List in-flight | item whose latest `takeaways` row is `processing` | `in_flight: ["takeaways"]` on the item; `commands` unchanged | No error |
| Detail shape | `GET /reviews/7` | Same array as today plus per-row `overall_score` int, `reviewed_at_display` (site format); up to 50 rows | No error |
| Trigger success | `POST /trigger-review` `{post_id, command}`, n8n 2xx | One `processing` row inserted for that post+command; response unchanged (`quota_remaining`); AJAX path no longer double-inserts | No error |
| Trigger refused | gate / quota / not configured / n8n down | No `processing` row; WP_Error as today, toast shows the server message | 403/429/400/502 surfaced gently |
| Poll completes | in-flight post's latest row for the command is no longer `processing` | Row updated in place (score, status, pills, date); open panel refetches; toast "Review complete — score 72, Ready to ship." | No error |
| Poll sees failure | latest row has status `error`, or `''` with empty result | Row updated; toast "Review could not complete. Check the n8n execution log, then try again."; detail tab shows the stored error or "No result was returned." | No error |
| Poll gives up | a post stays in flight > 3 min | Polling for that post stops; toast "Still running in n8n — results will appear when it finishes." | No error |
| Gated / hidden | `viewer.gated_commands=[rewrite…]`, `hidden_commands=[…]` | Gated: disabled button with lock, title + sr-only suffix; hidden: no button, no quota note | No error |
| Processing row | item `in_flight` non-empty | "Details" button disabled while that post is in flight (mockup); buttons for that post disabled in an open panel | No error |
| Detail load fails | `/reviews/{id}` 4xx/5xx | Inline `Notice` "Could not load this post's reviews. Reload the page and try again." with the toggle still usable | No error |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` — `get_reviews_callback()` :325-339: cast `overall_score`, add `reviewed_at_display`, pass limit 50. Dates: stored `reviewed_at` is GMT (`current_time('mysql', true)`) — both here and in `list_posts_callback()`'s `latest_reviewed_display` use `get_date_from_gmt( $reviewed, $format )`, not `date_i18n( …, strtotime() )` (5.1 bug, fixed in both places; harnesses stub `get_date_from_gmt`). `trigger_review_callback()` :358-456: after the n8n 2xx branch :444-447 insert the `processing` row (moved from `ajax_trigger_command`); `list_posts_callback()` :560-633: add `in_flight` per item.
- `post-quality-manager/admin/class-pqm-admin.php` — `ajax_trigger_command()` :1489-1523: delete the insert at :1517 (now server-side in REST); nothing else.
- `post-quality-manager/includes/class-pqm-db.php` — **`latest_review_subquery()` :132-145 excludes placeholders** (`WHERE command = 'review' AND status <> 'processing'`): a post's `latest_*` is its last *result*, so a running review never changes list membership, ordering, or `counts` — `in_flight` is the only processing signal. `get_commands_for_posts()` :72-93 returns distinct commands; add `get_in_flight_for_posts()` (latest row per post+command with status `processing`, one query: join on `MAX(reviewed_at)` per post+command). `get_reviews_for_post()` :52-64 — reuse. Extend `tests/harness/rest-posts-harness.php` with one assertion that the subquery carries the exclusion.
- `src/post-reviews-table.js` — `PostRow` :107-135 gains an Actions cell (`Details`/`Hide details`, `aria-expanded`, `aria-controls`), colSpan 5→6; the row shows the Processing badge when `in_flight` contains `review` (StatusBadge `status="processing"`), otherwise its last result; state `openPostId`; a `refreshKey` in the fetch deps so polling/triggers can refetch the current query silently; the post stays *busy* (trigger buttons disabled) from click until the post-trigger refetch resolves, not just until the trigger response. Details is disabled only while the post is actively awaited by the hook (in flight and not given up). Toasts: `SnackbarList` announces by itself (`speak()`), so `<Toasts>` renders *outside* the `role="status"` "Updating…" line and adds no live region of its own — one host, no nesting.
- New `src/post-reviews/detail-panel.js` — fetches `/reviews/{id}`, derives `latest[command]` (first row per command) + history; `TabPanel` tabs Review / Takeaways / Sources / SEO / Audit / History(n), done marker "✓" on tabs with a row. Review tab: `PillarMeters` (structure/readability/engagement/credibility from `result_data[dim].score`), `<ol>` top edits, actions row (Open in Editor primary, Run/Re-run Review, Rewrite button, QuotaNote). Locked (plan-gated) button: `Button` with `aria-disabled="true"`, `className="pqm-locked"`, no-op `onClick`, `title={LOCKED_TITLE}`, `VisuallyHidden` suffix — focusable on every supported WP version without `__experimentalIsFocusable`/`accessibleWhenDisabled`. Agent tab: structured renderer + Run/Re-run button; gated copy "Available with Pro." / not-run copy "Not run yet — {Label} is free on every plan." History: `command · score · status · date` per row.
- New `src/post-reviews/result-view.js` — one recursive renderer for n8n result objects (keys humanized, arrays as lists, booleans ✓/✗, nested objects as sections; `audit.review` reuses `PillarMeters`). Shapes: template `Parse …` nodes (review :148, takeaways :216, sources :284, seo :352, audit :420).
- New `src/post-reviews/use-polling.js` — `usePolling(items, queryKey, handlers)` → returns the set of actively awaited post ids: one `setInterval` 3 s while any tracked post+command is awaited; tick = silent refetch; completion = the command leaves `in_flight` **or the post leaves the list on a silent refetch of the same query** (its result changed membership — settle it, `onSettled` fetches `/reviews/{id}` for the outcome); a `queryKey` change (user navigated: filter/sort/page) resets tracking and forgets. Per-key start time, 3-min give-up (`onGiveUp`, the post is no longer awaited → Details returns). Auto-resume = initial `in_flight` from the first list response.
- New `src/post-reviews/toasts.js` — local state + `SnackbarList` inside `role="status" aria-live="polite"`, auto-remove 5 s. Copy per matrix; brand.md.
- `src/kit/copy.js` — `LOCKED_TITLE`/`LOCKED_SUFFIX` :76-88 (unused until now), `commandLabel()`, `statusLabel()` (+ `error` → "Could not complete"); add `runLabel(command)` ("Run Review" / "Re-run Review" …). `src/kit/status-badge.js` — `error: { icon: 'dismiss', variant: 'neutral' }`. `src/kit/style.css` — detail row, tablist spacing, `.pqm-pillars` grid + `.pqm-meter` (mockup :86-88), actions row, lock icon, toast wrap.
- `tests/harness/quota-harness.php` — drives `trigger_review_callback()`; add a `$wpdb->insert` stub and assert the `processing` row only on 2xx. New `tests/harness/rest-reviews-harness.php` (copy the `rest-posts` stubs): `get_reviews_callback` shaping, `in_flight` derivation, 50-row limit.
- Legacy reference only: `admin/js/pqm-admin.js` `renderPanel` :396-450, `pollForResult` :703-756, `updateTableRow` :768-821.

## Tasks & Acceptance

**Execution:**
- [x] `class-pqm-db.php` -- `get_in_flight_for_posts()` -- server truth for polling and auto-resume.
- [x] `class-pqm-rest-api.php` -- `in_flight` on list items; `reviews` shaping + limit 50; `processing` insert after n8n 2xx -- additive, one write path.
- [x] `class-pqm-admin.php` -- remove the AJAX-side `processing` insert -- no double rows for the legacy caller.
- [x] `src/kit/copy.js` + `style.css` -- `runLabel()`, detail/meter/toast styles.
- [x] `src/post-reviews/toasts.js`, `use-polling.js`, `result-view.js`, `detail-panel.js` -- the panel, trigger, polling and toasts.
- [x] `src/post-reviews-table.js` -- Actions column, detail row, `refreshKey`, wires polling + toasts + panel.
- [x] `tests/harness/quota-harness.php` + new `rest-reviews-harness.php` -- matrix rows 1–4.

**Acceptance Criteria:**
- Given a post with a review, when Details is opened, then the tabs are keyboard-operable (arrow keys, correct `role`/`aria-selected`), the Review tab shows four meters and the top edits, History lists every row.
- Given a user who can trigger, when Run Review is clicked, then a toast confirms, the row shows Processing with Details disabled, and within 3 s of n8n's callback the row updates in place and a completion toast names score + status — without a page reload; after a reload mid-run the row resumes polling on its own (any command, any locale).
- Given `npm run build` and the harness sweep, then all green; `/posts` keys and `viewer` byte-identical apart from the additive `in_flight`.

## Implementation Notes

**2026-10-05 — derivation 2 (built).** Two details the Code Map left open, resolved while building:
- `usePolling( items, listKey, handlers )` returns `{ awaited, restart }`, not a bare set. `listKey` is the REST path of the query the *current items answer* (table state `loadedKey`, set in the same batch as `items`), not the live query — keying on the live query would, on a page/filter change, re-add the old items' flights and then "settle" them against the new list (a false completion toast). `restart( postId, command )` drops a tracked (possibly given-up) key after a successful trigger so the post-trigger refetch arms it with a fresh start time; without it a stale placeholder that the user re-runs would never be polled again (the command never leaves `in_flight` between the old and the new placeholder).
- Busy-until-refetch is keyed, not timed: `refetch()` returns the `refreshKey` it dispatches, the trigger stores it in `pendingUntilKey`, and only the list response carrying `refreshKey ≥ pendingUntilKey` (success or failure) clears `pendingPostId` — a polling response that resolves in between cannot end the busy state early.
- Tab "✓" marker renders only for a `done` latest row (not for `processing` or a failed row), with an `aria-hidden` glyph and a visually-hidden "(has a result)".
- `rest-reviews-harness.php` pins the site timezone to Europe/Zurich so the GMT→local conversion is actually exercised (09:30 GMT → 11:30); `rest-posts-harness.php` keeps UTC and asserts the `status <> 'processing'` exclusion in the items, count and counts queries.
- `wp-scripts lint-js` is broken in this checkout (`@typescript-eslint` load error, pre-existing); verification relied on `npm run build` (all JSX parsed/minified) plus the PHP sweep.

## Plan Change Log

**2026-10-05 — loopback 1 (bad_plan).** Trigger: the review finding that the `processing` placeholder, being the post's newest `review` row, becomes its `latest_*` and knocks the post out of any filtered/sorted/paged view on the first refetch, where the hook forgot it (no update, no toast). Amended (outside the frozen block): `latest_review_subquery()` excludes placeholders so list membership is stable through a run; the row's Processing badge comes from `in_flight`; the hook settles a post that leaves the list within the same query and resets on query change; Details re-enables after give-up; busy lasts until the post-trigger refetch; one live region (SnackbarList self-announces); `get_date_from_gmt` for both display dates; locked button via `aria-disabled`. Known-bad state avoided: a placeholder that changes a post's status/score/order; nested `role="status"`; a permanent Details lock from a stale placeholder.
**KEEP** from derivation 1 (reference diff saved at `C:/Users/vhe/AppData/Local/Temp/claude/c--Git-Post-Quality/459b2903-3d47-47cc-9c9d-d2f072f9a857/scratchpad/5-4-derivation-1.diff` — reuse freely what this list names): `get_in_flight_for_posts()` SQL and its harness rows; the `processing` insert inside the n8n-2xx branch of `trigger_review_callback()` + the AJAX removal + the quota-harness `$wpdb->insert` stub and assertions; `get_reviews_callback()` shaping (int score, display date, limit 50, raw string on bad JSON) + `rest-reviews-harness.php`; `result-view.js` (generic renderer + exported `PillarMeters`); `detail-panel.js` structure (TabPanel, latest-per-command, `isFailedRow()`, state copy, gating, History) except the locked-button prop; `toasts.js` `useToasts()` minus the wrapper live region; `copy.js` `runLabel()`, `FREE_COMMANDS`, `error`/`rewritten` labels; the kit CSS using the new class names that dodge legacy `pqm-admin.css`; the `outcomeToast()` copy; the `refreshKey` + silent-refetch mechanism.

## Review Triage Log

Pass 1 (quick lens, 2026-10-05): 6 findings — 1 bad_plan (loopback), 5 patch-grade folded into the re-derivation. Verdicts: medium 3, low 3.

- **medium / bad_plan** — `src/post-reviews-table.js` trigger → silent refetch + `use-polling.js` "left the list: forget": the server-side `processing` placeholder is the post's newest `review` row, so `latest_review_subquery()` makes it the post's `latest_status`/`latest_score`/`latest_reviewed`. Verified: on any pressed chip (`lr.status = …`, `IS NULL`), any score band (score 0 fails all bands), `orderby=score`, or page ≥ 2 of the default sort, the post leaves the current query on the first refetch; the hook drops it, `onSettled` never fires, the row and open panel vanish. Breaks AC 2 from the "Not analyzed" chip — the entry point the empty state points at. Root cause is the Design Note "poll the list" not accounting for the placeholder changing list membership → plan amended (see Plan Change Log).
- **medium / patch (in re-derivation)** — stale `processing` row locks Details forever: nothing expires the placeholder, the template's callback node has `onError: continueRegularOutput` (a rejected `/save-review` leaves it as the latest row), and legacy AJAX wrote the same row, so such rows may already exist. Details disabled on every load + 3 min of polling on every load. Verified. Plan now: Details disabled only while *actively awaited*; after give-up the toggle returns.
- **medium / patch** — `get_reviews_callback()` `reviewed_at_display`: stored values are GMT (`current_time('mysql', true)`), `date_i18n($f, strtotime($gmt))` formats them as local → off by the site offset. Pre-existing in 5.1's `/posts` (same code) and now duplicated; both switch to `get_date_from_gmt()`.
- **low / patch** — nested live regions: `<Toasts>` renders its own `role="status"` inside the table's `role="status"` host, and `Snackbar` already `speak()`s its message — toasts announced two or three times. Plan now: one host, `SnackbarList` self-announces.
- **low / patch** — `trigger()` `.finally(setPendingPostId(null))` clears the busy flag before the post-trigger refetch returns; a second click in that window double-triggers and consumes two quota actions. Plan now: busy until that refetch resolves.
- **low / patch** — `__experimentalIsFocusable` is deprecated since WP 6.6 (`accessibleWhenDisabled`), and neither name exists across the plugin's WP 6.0 floor. Plan now: locked button is `aria-disabled` with a no-op, no version-dependent prop.

Pass 2 (quick lens on derivation 2, 2026-10-05): all pass-1 fixes confirmed landed; 6 new findings — 3 patched, 3 rejected. Verification after patches: `php -l` clean, rest-posts 79/79, rest-reviews 24/24, build clean.

- **false** — give-up toast prefixes the command label ("Review is still running in n8n — …") where the matrix wrote "Still running in n8n — …": same meaning, the label makes it actionable; the matrix states behaviour, not verbatim copy.
- **low / rejected** — `get_in_flight_for_posts()` has no `id` tiebreak: a result landing in the same second as its placeholder keeps the post in flight until give-up. Needs sub-second n8n+Claude latency; the same pattern pre-exists in `latest_review_subquery()`; Details returns after give-up so nothing is locked.
- **low / patch** — `status=processing` filter went dead with the placeholder exclusion but stayed in `LIST_STATUSES` and the JS `STATUSES` whitelist. Removed from both (deep link `?status=processing` now falls back to no filter).
- **low / patch** — `ResultMeta`/History rendered "Not analyzed" for a done row with empty status. Badge now hidden when status is empty and the row is not failed.
- **low / rejected** — row shows Processing only for `review` in flight; other commands signal through the disabled Details + the panel's running state. Overriding a real review badge with Processing for a takeaways run would be worse; a per-pill running state is 5.5's territory (bulk).
- **low / patch** — given-up keys were re-armed with a fresh 3-min window on every navigation. `usePolling` now remembers given-up keys for the page's lifetime; only `restart()` (a new trigger) clears one.

## Design Notes

Why poll the list, not `/reviews/{id}`: one request per tick regardless of how many posts are in flight (5.5 queues several), the list already carries everything a row renders, and `in_flight` makes completion a server fact — the F3 text-match resume and the "always `review`" assumption disappear together. For that to hold, a running review must not move the post: the placeholder is excluded from `latest_*` (membership, order and counts stay put until a *result* lands), and a post that then leaves the current query is a completion, not a loss. "In flight" on the client means *actively awaited*: after the 3-minute give-up the toggle returns so a stale placeholder (rejected callback, killed execution, legacy rows) can always be replaced by a new run. Why the `processing` insert moves into REST: the React page calls REST directly (no `pqmAdmin` bridge); keeping the insert in the AJAX handler would leave REST callers without the placeholder the polling depends on; cron does not use this callback, so nothing else changes. Failure detection covers the template's real behaviour (empty status + `{}` on fallback), not only the `error_message` branch no node sends.

## Verification

**Commands:**
- `npm run build` -- expected: exits 0; new deps extracted (no `wp-data`)
- `php -l` on the three touched PHP files -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green incl. the new reviews harness and the quota-harness processing-row checks

**Manual checks (if no CLI):**
- Real site, free-plan admin: open Details → tabs by keyboard; Run Review → toast, Processing row, Details disabled, row updates in place with completion toast; reload mid-run → polling resumes; Rewrite shows locked with the Pro title; free-tier Author: quota note beside the buttons and decrements after a run; `pqm_view_reviews`-only user: Details works, no buttons, no quota note; translated site (any locale): auto-resume still works.
