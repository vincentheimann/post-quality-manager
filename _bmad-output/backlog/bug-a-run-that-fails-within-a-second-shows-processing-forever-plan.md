---
title: 'A run that fails within a second shows Processing forever'
type: 'bugfix'
ticket: '137'
created: '2026-11-18'
status: done
baseline_revision: 'df7f05f10f823ebe2203d941f5a37b0c9b019942'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context: ['{project-root}/memory/admin-ui-rebuild.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A run's `processing` placeholder and its result are two rows stamped to the second. The queries that pick a post's latest row break no tie, so a result stored in the same second (a failure n8n reports in under a second) can lose to its placeholder: the post shows Processing for good, Details stays disabled, and the reason cannot be opened (story 38, P007).

**Approach:** One rule for "the latest row": the highest `id` per post and command, since ids follow write order. The trigger also skips its placeholder when the run's result was already stored while the webhook call was returning.

## Boundaries & Constraints

**Always:** The latest row per post and command is the one with the highest `id` in every reader that picks one. A refused or failed trigger still writes nothing. The latest *result* (no `processing`, command `review`) still drives list membership, sorting and counts.

**Never:** No schema change or migration, no change to stored `reviewed_at` values, no JS change, no expiry for placeholders whose callback never arrives (deferred), no change to which posts the cron picks beyond removing tie duplicates.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Fast failure | placeholder, then an error row in the same second | `in_flight` empty; the post's review list starts with the error row; Post Reviews shows Could not complete with the reason | No error expected |
| Fast success | placeholder, then a result in the same second | the result is the latest; tiles, chips and queue count the post once | No error expected |
| Result before placeholder | the callback's row is stored before the trigger reaches its placeholder | no placeholder is written; the result shows | No error expected |
| Slow run | result a second or more after its placeholder | unchanged from 4.0.4 | No error expected |
| Newer run | a finished row, then a newer placeholder | the newer run is in flight | No error expected |
| Other command, same second | a review result and a takeaways placeholder in the same second | the review result is the post's latest result; takeaways is in flight | No error expected |
| Refused trigger | n8n answers non-2xx, or the call fails | no row written | the existing error message |
| Cron, same second | placeholder and result in the same second | the post is listed once | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-db.php` -- `save_review()` L25 (no caller passes `$reviewed_at`, so `id` order is write order); `get_reviews_for_post()` L52–64 (`ORDER BY reviewed_at DESC` only); `get_in_flight_for_posts()` L108–130 (joins on `MAX(reviewed_at)`, keeps `processing`); `latest_review_subquery()` L180–193 (inner MAX skips processing, but the outer join matches any row of that second, any command or status); `get_posts_needing_review()` L476–499 (cron; MAX includes processing, a tie joins twice). Leave `get_score_trend()` L426–431 and `has_result()` L149–155 as they are.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `trigger_review_callback()` L664–765: blocking `wp_remote_post` L724 (5 s); `WP_Error` returns with no row L730–741; on 2xx `consume_quota()` / `quota_state()`, then the placeholder L748–763 (the comment there states the contract). The only writer of `processing`.
- `post-quality-manager/includes/class-pqm-activator.php` L348–361 -- `id` AUTO_INCREMENT primary key, `KEY post_command (post_id, command)`; MAX(id) per post and command is served by it. No change.
- Readers that follow the DB fix with no change: GET /reviews/{id} (rest-api L630), GET /posts `in_flight` (L962, L985), `PostQuality_Blocks::latest_items` (blocks L182–200, the 409 when a placeholder is newest), `count_posts_by_status()` L364–399 and `get_all_posts_with_scores()` (through the subquery); JS `src/kit/use-review-wait.js` L68–87, `src/meta-box.js`, `src/post-reviews/detail-panel.js` L364–370, `src/kit/runs.js` L293–301 take the server's first row; `src/kit/use-polling.js`, `src/post-reviews-table.js` L237, L459 read `in_flight`.
- Tests: `tests/harness/rest-reviews-harness.php` (stub `$wpdb` records SQL; L156–158, L397–427 assert substrings), `tests/harness/rest-posts-harness.php` (L266–274, L563–568, L631), `tests/harness/quota-harness.php` (L113–208, trigger inserts and "no processing row" on failures), `tests/harness/insert-block-harness.php` L468 (row-order fixture), `tests/harness/cron-gate-harness.php` L63 (stubs the DB class).
- `memory/admin-ui-rebuild.md` L3 and `memory/insert-into-post.md` L12 state the in-flight rule and the 409.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-db.php` -- pick the latest row by `MAX(id)` per post and command in `get_in_flight_for_posts()`, `latest_review_subquery()` (among review results; join on `id`) and `get_posts_needing_review()` (join on `id`); order `get_reviews_for_post()` by `id DESC` alone, so its first row per command is the same row the other readers pick (time first would let a clock difference between web servers put a placeholder before its result); add `latest_id( int $post_id, string $command ): int` -- one tie-proof rule for every reader.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- read `latest_id()` before the webhook call; after a 2xx, write the placeholder only when it is unchanged; update the comment, naming `memory/admin-ui-rebuild.md` for n8n's timing instead of stating it (lessons 2026-10-16) -- a result stored first is not buried by its own placeholder.
- [x] `tests/harness/rest-reviews-harness.php`, `tests/harness/rest-posts-harness.php`, `tests/harness/quota-harness.php` (and any harness whose SQL assertion the change breaks) -- one row per matrix line the stub can show: the id order and joins in the SQL, the trigger skipping the placeholder when a newer row exists and writing it otherwise, failures writing nothing. Every new row fails on the baseline code; no row that only re-reads the stub's queued order or repeats a substring another row asserts.
- [x] `memory/admin-ui-rebuild.md`, `memory/insert-into-post.md` (and any memory line that describes a changed query) -- the in-flight and latest-row rule by `id`, plus n8n's timing as observed, dated and with its version: n8n 2.48.5 (2026-11-18) answers the webhook on receipt, and a refused Anthropic key comes back through the callback in about 0.4 s.
- [x] `docs/release-notes/unreleased.md` -- one line: a review that fails within a second now shows Could not complete and its reason.

**Acceptance Criteria:**
- Given the branch's Free build on Local WordPress 6.6 set up by hand with a refused Anthropic key, when a Review runs from Post Reviews and from the setup dialog, then Post Reviews shows Could not complete with the API's reason within seconds, Details opens it, and the dialog says the review could not complete.
- Given a placeholder and a result planted with WP-CLI in the same second, when Post Reviews, the Dashboard and the meta box load, then the result shows and the Dashboard counts the post once.

## Implementation Notes

- **Joins on `id` alone.** `get_in_flight_for_posts()`, `latest_review_subquery()` and `get_posts_needing_review()` select `MAX(id) AS max_id` per group and join `r1.id = r2.max_id`. The in-flight query's outer `GROUP BY r1.post_id, r1.command` went: it only removed tie duplicates, which an id join cannot make. The cron's `AND r1.command = %s` went too (the id already belongs to that command), so its prepare takes four values (command, min score, cutoff, limit). The cron's MAX still includes `processing` rows, as before.
- **`latest_id()`** runs `SELECT MAX(id) FROM %i WHERE post_id = %d AND command = %s` with the command through `sanitize_key()`, the form `save_review()` stores, and casts null (no row) to 0. The trigger reads it after the payload is built, just before `wp_remote_post()`, and again after a 2xx, after the quota is spent; the action is spent even when the placeholder is skipped (n8n accepted the run).
- **Memory beyond the two named files:** `memory/freemium-and-roles.md` said the cron's command sits "in the subquery and the r1 join"; it now names the id join.
- **`docs/release-notes/unreleased.md`** did not exist (renamed to `4.0.4.md` at the last release); it is re-created with the `## Upgrading from an earlier version` heading, as `/release` step 3 says.
- **Harness rows per matrix line (iteration 1):** fast failure (`rest-reviews`: the reviews query is exactly `… ORDER BY id DESC LIMIT %d`; the in-flight pick holds no `reviewed_at`), fast success (`rest-posts`: `MAX(id)` joined on `r1.id` in the items, count and counts queries, one row over the three), other command same second (`rest-posts`: no `max_date` or `r1.reviewed_at =` in those three; `rest-reviews`: the in-flight `MAX(id)` is grouped per command, a modified pre-existing row), result before placeholder (`quota`: the stubbed callback stores the error row during `wp_remote_post()` → no placeholder, the action still spent, one row), newer / slow run (`quota`: `latest_id()` read before and after the call for this post and command, unchanged → one placeholder after the finished rows), cron same second (`rest-posts`: the subquery's `MAX(id)` with no status filter, the id join, four args). `rest-reviews` also covers `latest_id()` (SQL, args, int cast, sanitized command), moved to the end of the file so the rows before it still report on the baseline, where the method does not exist.
- **Refused trigger has no new row:** its behaviour is unchanged, so any new row would pass on the baseline; the existing Row 4 rows of `quota-harness.php` ("no processing row when n8n is unreachable", "n8n 500: no processing row") hold it and pass. Iteration 0's two refused-trigger rows, its separate "spends the action" and "read twice" rows (folded into the result-before-placeholder and newer-run rows), and the rest-reviews rows named in finding 5 plus the in-flight "other command" row (it repeated `GROUP BY post_id, command`) went.
- **Every new row fails on the baseline (checked, not committed):** the three harnesses of this change were copied into a detached worktree of `df7f05f` in the session scratchpad (removed after) and run there: `quota` 2 FAIL (both new rows); `rest-posts` FAIL on the in-flight row, fast success, other command, and the cron rows (the args row ends in a fatal, `prepared_with()` finds no `MAX(id)` query); `rest-reviews` FAIL on the fast-failure order and both in-flight rows, then a fatal at `latest_id()` (undefined method). On the branch every harness passes.
- **Extra check, not committed:** the real SQL of the old and new `PostQuality_DB` was dumped with a value-inlining `prepare()` and run on SQLite (Python `sqlite3`; this PHP CLI has no `pdo_sqlite`) for fast failure, fast success, other command same second, newer run and slow run. Old: fast failure and fast success stay in flight, the placeholder sorts first, the counts list the post under two statuses and the cron lists it twice. New: no in-flight, the result sorts first, one count, one cron pick; newer run and slow run identical to old. Re-run on iteration 1 with a sixth scenario, clocks apart (placeholder stamped T+5 s, its error written after it and stamped T): baseline in flight with the placeholder first; iteration 0 not in flight but the placeholder first in the reviews (finding 1); iteration 1 not in flight and the error row first. Other command, same second: the reviews now list the takeaways placeholder (higher id) before the review result; every JS reader takes the first row per command (`find( r => r.command === … )`, the panel's per-command map), so the review result still drives the Review tab, the wait and the toast.

- **Review loopback 1 (2026-11-18, the coordinator):** the code was reverted to the baseline after the quick review found a plan defect (Plan Change Log). The first implementation is kept as a patch for the re-derivation to reuse: `C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/992bc268-40c7-441d-9083-270e5d1ba3f6/scratchpad/b137-keep-iteration0.patch` (session scratchpad, not committed; apply what the Plan Change Log's KEEP names, then the amendments).
- **Iteration 1 (2026-11-18, the implementer, a subagent standing in for `bmad-build`'s implement step):** the KEEP patch applied cleanly on `df7f05f`, then the amendments: `get_reviews_for_post()` orders `id DESC` alone (its docblock says why); the trigger's two comments drop n8n's timing and name `memory/admin-ui-rebuild.md`; the harness rows above; `memory/admin-ui-rebuild.md` gains the id-only list order, the skip's accepted limits and n8n 2.48.5's timing line (dated, with the build it was seen on), and `memory/insert-into-post.md` the id-only order.
- **Local walk (2026-11-18, an Opus agent, WordPress66, WordPress 6.6, PHP 8.1.29, n8n 2.48.5; story 38's frame W137): both acceptance criteria pass.** Build: CI's `free-build` artifact of #33 (928ae3c; its plugin code equals main's 9e1af92 after the merge), zipped, header still `Version: 4.0.4`, `latest_id()` present. Set up by hand; the "Anthropic - Claude" credential held a fixed invalid key (no real key used; Anthropic answered 401).
  - **Criterion 1:** a Review from Post Reviews: after 4 s the row read "Could not complete" and Details opened the API's reason, again after a reload; `GET /posts` had no `in_flight`; the review list started with the error row. Its placeholder and error row shared a second (15:10:54). From the setup dialog's "Run your first review": after 4 s the reason, then "The review could not complete. Open Executions in n8n to see which node stopped, fix it, then run the review again." (its pair also shared a second).
  - **Criterion 2:** a placeholder then a result (72, `needs_revision`) planted with WP-CLI in the same second: Post Reviews, Details, the meta box and REST showed the result; the Dashboard counted each post once ("All 4" for 4 posts, 4 queue rows). The reverse plant (result first, placeholder second) reads as in flight on every screen, by design: the trigger's skip prevents it in real runs, and it did not fire in this walk (every placeholder was stored before its callback).
  - **Control:** a Review whose error row came one second after its placeholder read "Could not complete" as well.
  - No `debug.log`; n8n back to its start inventory; restore: three "same", WordPress 6.6.

## Plan Change Log

- **Loopback 1 (2026-11-18), triggered by quick review finding 1 (bad_plan):** the task line ordered `get_reviews_for_post()` by `reviewed_at DESC, id DESC`, against the frozen rule that every reader picks the highest `id`; with two web servers' clocks apart, a callback stamped one second earlier than its placeholder but written after it sorts second, so GET /reviews, the setup dialog's and meta box's wait, the toast and Insert into Post would read the placeholder while `in_flight` is empty: the original symptom.
  - **Amended:** that task line (order by `id DESC` alone); the rest-api task (comments name `memory/admin-ui-rebuild.md` for n8n's timing, lessons 2026-10-16, finding 8); the harness task (every new row fails on the baseline, finding 5); the memory task (n8n 2.48.5's timing, dated); Design Notes (the skip's accepted limits, finding 3; the cron's tie-only change, finding 4; the deferred items corrected, finding 7).
  - **Known-bad state avoided:** two readers that disagree about a post's latest row; harness rows that pass on the defect; n8n behaviour stated as fact in code.
  - **KEEP:** everything else of iteration 0, as in `b137-keep-iteration0.patch`: `latest_id()` (SQL, `sanitize_key()`, int cast); the three `MAX(id)` joins and the dropped redundant `GROUP BY` and cron `AND r1.command = %s` (four prepare values); the trigger's two `latest_id()` reads around `wp_remote_post()`, with the quota spent even when the placeholder is skipped; the quota harness's `callback_first` world and its rows; the rest-posts and rest-reviews SQL rows that fail on the baseline; the memory updates (including `memory/freemium-and-roles.md`'s cron line); the release-notes line and heading.

## Review Triage Log

- **Quick review, pass 1 (2026-11-18):** 0 high, 2 medium, 5 low, 1 false, 0 maybe-false (8 findings).
  - 1 · medium · bad_plan · `get_reviews_for_post()` sorts by `reviewed_at` before `id`, unlike the in-flight pick by `MAX(id)`; `save_review()` stamps the time before the insert assigns the id, so clocks apart between web servers (or a second boundary between the two writes) can put the placeholder first in GET /reviews while `in_flight` is empty. The plan's task line chose that order, against its frozen rule. Amended (Plan Change Log).
  - 2 · medium · defer · a result stored before the next list refresh gets no outcome toast and no Dashboard refetch: `usePolling` settles only keys seen in `in_flight` (`use-polling.js` L22, L100–125; `use-single-run.js` L93–103). Pre-existing for any result faster than the refresh, not caused by this change; the list itself shows the result. Logged in `deferred-work.md`.
  - 3 · low · reject · the skip treats any row of the post and command stored during the webhook call as this run's (another run's late result, a cron run's). Real, but it needs two runs of one command on one post at the same moment, and a narrower test would need state the row does not carry. Recorded as an accepted limit in Design Notes.
  - 4 · low · reject · the cron's picks change in tie cases beyond duplicates (a good fresh result tied with its placeholder is no longer re-picked by the placeholder's score 0). The new behaviour is right and it is a tie artifact; the fix would edit this plan. Recorded in Design Notes.
  - 5 · low · moot (loopback) · two rest-reviews harness rows ("fast failure: the review list starts with the error row", "newer run: the highest id wins") pass on the baseline: the stub returns queued order, and the second repeats a substring the row before asserts. Folded into the harness task.
  - 6 · false · the acceptance criteria have no evidence yet: they are this plan's manual Local walk, which runs on the PR's CI build after review (bug 135's precedent); not a defect in the diff.
  - 7 · low · patched by the coordinator · the new `deferred-work.md` entry said the post "cannot be run again"; L237 of `post-reviews-table.js` is the bulk checkbox, Details is `disabled={ ! isOpen && isAwaited }` (L294) and `isAwaited` ends at the 3-minute give-up. The entry and Design Notes are corrected.
  - 8 · low · moot (loopback) · the rest-api comments state n8n's timing as fact, against lessons 2026-10-16; folded into the rest-api and memory tasks.
- **Quick review, pass 2 (2026-11-18, after loopback 1):** 0 high, 0 medium, 3 low, 1 false, 0 maybe-false (4 findings, 2 carried).
  - 1 · low · reject · carried: the cron's picks change where a placeholder and a good fresh result share a second (the placeholder's score 0 no longer re-picks the post; the reviewer's SQLite run: baseline `[(7,)]`, new `[]`). Same location and claim as pass 1's finding 4; the code reads as that row describes. Raised to the owner at step 05 as a reading of the frozen "beyond removing tie duplicates".
  - 2 · low · patch · `memory/admin-ui-rebuild.md` says `get_score_trend()` "picks no latest row"; it keeps each post's last result as of each week end, walking rows `ORDER BY r.reviewed_at ASC` (`class-pqm-db.php` L448–486), and the Dashboard's Average is its last point. Placeholders are excluded, so this bug does not reach it; the plan leaves the query alone. Fix: the memory line states what the trend does and why it is left.
  - 3 · low · patch · the trigger's comment (`class-pqm-rest-api.php` L756–762) and the quota harness's `callback_first` line (L231) point to `memory/admin-ui-rebuild.md` for "n8n's callback coming back before its answer", which that memory rules out (n8n answers on receipt; the callback comes about 0.4 s later). The case the skip covers is WordPress being slow between n8n's answer and the insert (the quota writes, a slow database), as the ticket's cause hypothesis says. Fix: name that case.
  - 4 · false · carried: the acceptance criteria have no evidence yet; pass 1's finding 6 (the Local walk runs on the PR's CI build after review).

## Design Notes

`id` is the tie-breaker because it is the only column finer than a second, and the plugin never back-dates `reviewed_at` (no REST route ever passed one in). Every reader uses it alone, the list order included: a reader that sorts by time first can disagree with the in-flight pick when two web servers' clocks differ. Skipping the placeholder after the fact was chosen over writing it before the webhook call: the latter would show Processing during a call that then fails, and a PHP request killed mid-call would leave a placeholder for good; the skip keeps today's "a refused trigger writes nothing". Accepted limits of the skip: any row of the same post and command stored during the call counts as this run's result (another run's late result, a cron run's), which needs two runs of one command on one post at the same moment; and a result stored between the second `latest_id()` read and the insert, a window under a millisecond.

The cron's picks change only where a result and its placeholder share a second: before, the placeholder's score 0 could re-pick a post whose fresh result was good; now the result decides.

Deferred (not this plan, `deferred-work.md`): a placeholder whose callback never arrives reads Processing for good, stays out of bulk selection, and disables Details for 3 minutes after each page load; and a result faster than the next list refresh gets no outcome toast (pre-existing).

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors.
- `for f in tests/harness/*-harness.php; do php "$f" || break; done` -- expected: every harness passes, the new rows included.
- `npm run test:unit` -- expected: pass.

**Manual checks (if no CLI):**
- Local WordPress66 frame per `docs/local-verification.md` with the branch's Free build and the disposable n8n: a hand-made setup whose Anthropic credential holds a fixed invalid key, a Review from Post Reviews and one from the setup dialog; then the planted same-second rows; restore, three "same".
