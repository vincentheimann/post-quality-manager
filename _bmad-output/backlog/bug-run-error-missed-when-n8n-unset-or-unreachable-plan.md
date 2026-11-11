---
title: 'A failed Run start is reported in place, and Post Reviews says when n8n is not connected'
type: 'bugfix'
ticket: '32'
created: '2026-10-06'
status: 'done'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '6da813408eb344202f8762a7787079d148358baa'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** When the webhook URL is unset or n8n does not answer, a Run click on Post Reviews or the Dashboard fails correctly on the server, but the client reports it only as a 5-second bottom-right toast that looks like a success toast. Post Reviews also has no "not connected" signal at all: `GET /posts` `viewer` does not carry it, and the PHP banner shows to `pqm_manage_settings` holders only, so Editors and Authors click into silence.

**Approach:** Keep the kit's one trigger path, but make a failed start a persistent `Notice` where the user clicked (the detail panel on Post Reviews, the queue row on the Dashboard) instead of a toast. Add `connected` and `can_manage_settings` to the `viewer` block so Post Reviews shows a persistent not-connected notice before any click, with the n8n Workflow link for admins and "ask an administrator" for everyone else, and disables the Run and bulk buttons while not connected. The server's not-configured copy names the next step per role.

**Decisions:**
- The failure toast is replaced by the inline notice, not duplicated. The success toast ("requested") stays.
- Not connected on Post Reviews (owner, 2026-10-06, default proposal): the Run buttons and Review selected are disabled, and the page notice explains why. Mirrors the Dashboard's ConnectCard and the gated-button precedent.

## Boundaries & Constraints

**Always:** `@wordpress/components` `Notice` for the inline messages (lessons 2026-10-05); copy per `docs/brand.md` (gentle, names the next step); `viewer` stays additive (new keys appended, existing keys and order untouched); the Dashboard keeps its ConnectCard; `requestRun()` / `startRun()` keep resolving `{ ok, message }` and never reject; the bulk bar's existing persistent report stays as is.

**Never:** change the `/trigger-review` route, args or response shape (only the not-configured message text); touch the n8n template or payload; add a JS test framework; introduce `window.confirm()` or browser-native dialogs; put a PHP internal in a `sanitize_callback`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Viewer, connected | `n8n_webhook_url` set, admin | `viewer.connected: true`, `viewer.can_manage_settings: true`; no notice on Post Reviews | — |
| Viewer, not connected, admin | URL empty, `pqm_manage_settings` | `connected: false`, `can_manage_settings: true`; Post Reviews shows the not-connected notice with the "Set up the n8n workflow" action; Run and bulk buttons disabled | — |
| Viewer, not connected, author | URL empty, `pqm_run_free_agents` only | `connected: false`, `can_manage_settings: false`; notice says to ask an administrator; buttons disabled | — |
| Trigger, URL unset, admin | `POST /trigger-review` | 400 `pqm_not_configured`, message ends with the n8n Workflow page hint | Inline warning notice in the clicked panel / row |
| Trigger, URL unset, non-admin | same, no `pqm_manage_settings` | 400 `pqm_not_configured`, message says to ask an administrator | Inline warning notice |
| Trigger, n8n unreachable | `wp_remote_post` returns `WP_Error` | 502 `pqm_n8n_error` (unchanged) | Inline warning notice after the 5 s timeout; button busy meanwhile |
| Trigger, n8n non-2xx | n8n answers 500 | 200 `success:false` (unchanged) | Inline warning notice "could not start — n8n answered HTTP 500" |
| Trigger, success | n8n 2xx | unchanged: processing row, "requested" toast | A previous inline notice for that post is cleared on the new click |
| Dismiss | user closes the inline notice | notice gone until the next failed start | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` -- `trigger_review_callback()` L457-466: the `pqm_not_configured` copy, branch on `current_user_can( 'pqm_manage_settings' )`. `list_posts_callback()` L768-776: append `'connected' => ! empty( $settings['n8n_webhook_url'] )` and `'can_manage_settings' => current_user_can( 'pqm_manage_settings' )` to `viewer` (needs `$settings = pqm_get_settings()` there; `dashboard_callback()` L835-842 is the model). Do not touch the n8n error / non-2xx branches.
- `src/kit/runs.js` -- gate helpers L34-55: add `isConnected( viewer )` (`! viewer || viewer.connected !== false`, so an older response without the key still renders). `requestRun()` unchanged.
- `src/kit/use-single-run.js` -- `trigger()` L104-117: on `! ok` set `startError = { postId, command, message }` instead of `addToast( …, 'warning' )`; clear it at the start of every `trigger()`; expose `startError` and `dismissStartError()`. `startRun()` (bulk) untouched.
- `src/post-reviews/detail-panel.js` -- `CommandButton` L178-207: `disabled={ disabled || ! isConnected( viewer ) }`, `isBusy={ disabled }`. `DetailPanel` L385-508: new props `startError` (message or null) + `onDismissStartError`, rendered as `<Notice status="warning" isDismissible onRemove>` at the top of `.pqm-detail-panel`, above the tabs. Reuse the `Notice` import already there.
- `src/post-reviews-table.js` -- page L279-300 destructures `useSingleRun`; add `startError`, `dismissStartError`. Before `<FilterBar>` (L451) render the not-connected `Notice` (warning, not dismissible, `actions` with `url: 'admin.php?page=pqm-workflow'` like `src/dashboard.js` L270-285 when `viewer.can_manage_settings`). Pass `startError` for the open post to `DetailPanel` (L580-600).
- `src/post-reviews/bulk-bar.js` -- `Review selected` button L103-106: also `disabled` when `! isConnected( viewer )`.
- `src/dashboard.js` -- L97-160: take `startError` / `dismissStartError` from `useSingleRun`, pass to `FixNextQueue` (L239-245).
- `src/dashboard/fix-next-queue.js` -- row L29-92: `isBusy` on the Review button; render the `Notice` (warning, dismissible) inside the matching row when `startError.postId === item.post_id`. Import `Notice`.
- `tests/harness/rest-posts-harness.php` -- `viewer_block()` L483 and the key check L493: extend; settings override via `$GLOBALS['tw']['options']['pqm_settings']` (see the dashboard rows L577-628 for the pattern).
- `tests/harness/quota-harness.php` -- `trigger()` world L129, rows at L140-200: add a "not configured" row for both copies (empty URL in the settings override; no webhook call, no processing row).
- `memory/admin-ui-rebuild.md` -- durable fact: `viewer` carries `connected` / `can_manage_settings`; failed starts are inline notices, toasts are for success and polling outcomes only.
- Not touched: `post-quality-manager/admin/class-pqm-admin.php` banner (admin-only by design, stays); `src/kit/toasts.js`; `templates/n8n-workflow.json`.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- role-aware `pqm_not_configured` copy; `viewer.connected` + `viewer.can_manage_settings` -- AC2 signal, AC1 next step
- [x] `src/kit/runs.js`, `src/kit/use-single-run.js` -- `isConnected()`; `startError` state replaces the failure toast -- one source for both pages
- [x] `src/post-reviews-table.js`, `src/post-reviews/detail-panel.js`, `src/post-reviews/bulk-bar.js` -- not-connected notice, disabled buttons, inline failed-start notice in the panel -- AC1, AC2 on Post Reviews
- [x] `src/dashboard.js`, `src/dashboard/fix-next-queue.js` -- inline failed-start notice in the queue row, busy button -- AC1 on the Dashboard
- [x] `tests/harness/rest-posts-harness.php`, `tests/harness/quota-harness.php` -- matrix rows for the viewer keys and both not-configured copies -- AC3
- [x] `memory/admin-ui-rebuild.md` -- durable fact -- DoD

**Acceptance Criteria:**
- Given the webhook URL is unset or n8n does not answer, when any user clicks Run in the detail panel or Review in the Dashboard queue, then a warning `Notice` naming the reason and the next step appears in that panel / row and stays until dismissed or the next run; no warning toast is shown.
- Given no webhook URL, when a user who can run agents opens Post Reviews, then a not-connected notice is visible above the table (Workflow link for `pqm_manage_settings` holders, "ask an administrator" otherwise) and Run / Review selected are disabled.
- Given the URL is set, when Post Reviews loads, then nothing changes versus today.

## Implementation Notes

- Two small CSS rules beyond the code map, for layout only: `.pqm-connect-notice` shares the bulk report's `margin: 0 0 12px` and `.pqm-detail-panel > .pqm-start-error` gets the same spacing (`src/post-reviews/style.css`); `.pqm-queue-row > .pqm-start-error` takes `flex-basis: 100%` so the notice sits on its own line in the flex-wrap row (`src/dashboard/style.css`).
- The Post Reviews not-connected notice shows only for viewers with `can_trigger` (view-only users have no Run buttons to explain).
- `startError` is one value per page (the last failed single start). The DetailPanel receives it only when `startError.postId` matches the open post, so it reappears if that panel is reopened before the next click or a dismiss.
- Admins also still see the PHP banner on Post Reviews (unchanged by design).

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 0 medium, 3 low, 0 false, 0 maybe-false. One patched, one rejected, one deferred.
- low, patch: `isBusy` followed the panel's `busy` prop (`isAwaited || pendingPostId`), so after a successful start every Run button and the queue's Review button animated for the whole run (up to the 3-minute give-up), a visible change for connected sites. Caused by the Code Map's literal `isBusy={ disabled }`. Patched: `isBusy` now follows the pending start only (`pendingPostId === postId`); `disabled` unchanged.
- low, reject: a failed start is not seen if the user hides the panel or changes filter / sort / page during the pending request (up to 5 s when n8n is unreachable). Verified reachable (the Hide toggle stays enabled while open), but rare in everyday use, and the fix adds a page-level fallback branch. Reopening the panel shows the notice.
- low, defer: the unreachable (502) and non-2xx copy names Settings / n8n steps that users without `pqm_manage_settings` cannot take. Pre-existing copy; the frozen Never list allows only the not-configured text to change. Logged in `deferred-work.md`; leaves ticket AC1's "next step" open for non-admins on those two paths.
- Harness hygiene (own read of the diff, not a lens finding): two harness `echo` lines carried a literal line break instead of `\n`; fixed.

## Verification

**Commands:**
- `php -l post-quality-manager/includes/class-pqm-rest-api.php` -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green, including the new rows
- `npm run lint:js` -- expected: clean
- `npm run build` -- expected: bundle builds

**Manual checks (if no CLI):**
- Settings: clear the webhook URL, save. Post Reviews as admin: warning notice "n8n is not connected yet…" with the "Set up the n8n workflow" button; Run buttons and Review selected disabled. Same page as an Author: notice says to ask an administrator; buttons disabled.
- Set the URL to `https://127.0.0.1:9/webhook`. Post Reviews → Details → Run Review: button busy for ~5 s, then a warning notice at the top of the panel "Could not connect to n8n (…). Check your Webhook URL in Settings."; it stays after switching tabs and closes with its ×. Dashboard → Review on a queue row: the same notice inside that row.
- Restore the real URL, Run Review: the "requested" toast as before, no notice.
