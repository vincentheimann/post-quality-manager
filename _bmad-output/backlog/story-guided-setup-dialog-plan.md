---
title: 'A guided setup dialog walks a novice through connecting n8n, step by step'
type: 'feature'
ticket: '36'
created: '2026-10-07'
status: done
baseline_revision: '935da50e89b60e007941415282e26bab424cf6b3'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/memory/admin-ui-rebuild.md']
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The n8n Workflow page's four-step checklist assumes the user knows n8n: where the import menu is, what a credential is, which node takes which one, what "production URL" means. A novice stalls at the friction wall (user paths A4, UV4).

**Approach:** A "Set up the workflow" button (and the existing welcome notice) opens a `@wordpress/components` Modal, a small React island on the PHP Workflow page, that walks the user through seven steps in order with exact n8n labels, a Copy button on every value and a Done mark per step. Progress is stored per site and the dialog resumes at the first unfinished step. The plugin verifies what it can: the connection (save + ping, the ping also proves the webhook credential since story 35) and the first review (run from the dialog, wait for the result). The dialog creates the WordPress Application Password through WordPress's own REST API. The inline checklist goes; the connected-page sections stay.

## Boundaries & Constraints

**Always:** Dialog and all its UI from `@wordpress/components` + `@wordpress/i18n`; copy per docs/brand.md (actionable, gentle, a next step on every failure). Only `pqm_manage_settings` sees notice, button, dialog and the new routes. The `pqm_settings` contract and `sanitize_settings()` carry rule stay; setup progress is a standalone option, deleted on uninstall. One home for "save the webhook URL" and "ping": the AJAX handlers and the new route share it. n8n labels as of the docs read 2026-10-07: workflow menu (three dots, top right) → "Import from URL" / "Import from File"; credentials via "Create" (side menu) or the node's credential dropdown, default name "<node> account"; Webhook node shows "Test URL" / "Production URL" at the top of its panel; "Publish" button in the canvas header (older n8n: the Active toggle); Header Auth fields "Name" and "Value". A site already connected with a result received counts as set up (no migration, no notice for existing users).

**Never:** Screenshots or images. A round-trip ping (story 2). Creating n8n credentials from WordPress. Changing the workflow template or the save-review contract. Progress keyed per user.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Fresh site | URL empty, manager | Notice + Workflow button open the dialog at step 1; no inline checklist | — |
| Legacy connected | URL set, a result received, no progress option | Counts as complete: no notice, button reads "Setup guide", all steps open | — |
| Progress | step marked done, reload / other day | Dialog resumes at first unfinished step; unknown step name refused | 400 |
| Connect | `/webhook-test/` URL | Refused with the story 35 warning, nothing stored | — |
| Connect | production URL, n8n 2xx | Stored, "ping proved: this site reaches n8n and the webhook credential is right", step done | — |
| Connect | production URL, ping fails | Stored, message names the cause and next step, step not done | existing ping copy |
| App password | Application Passwords available | Core creates one named for the plugin; shown once with Copy | core error shown |
| App password | not available (no HTTPS / local) | Step explains why and the manual path (Users → Profile); can still be ticked | — |
| First review | published post chosen, run | Waits (poll), shows the score; marks setup complete; notice gone; last step offers Blog Profile + scheduled reviews | n8n failure: message + next step, not done; 3-min give-up copy |
| First review | no published post | Message to publish one first | — |
| Non-manager | any | No notice, no button, routes answer 403 | 403 |
| Uninstall | progress option stored | Removed | — |

</frozen-after-approval>

## Code Map

- `src/index.js:15-25` -- `pages` array `[rootId, Component]`; add `['pqm-setup-root', SetupIsland]`. New `src/setup/` (island: button + Modal + steps; `style.css` imported like `src/dashboard/style.css`).
- `src/post-reviews/bulk-bar.js:152-210` -- the one Modal in use (`title`, `onRequestClose`, `.pqm-confirm-actions`); match it.
- `src/kit/runs.js` -- `POSTS_PATH`, `reviewsPath(id)`, `requestRun(postId, command)` (resolves `{ok, message}`); `src/kit/use-polling.js` POLL_INTERVAL 3000 / GIVE_UP_AFTER 3 min, `giveUpMessage`; `src/kit/copy.js`. apiFetch: namespaced paths, no nonce handling (core middleware).
- `post-quality-manager/admin/class-pqm-admin.php` -- `enqueue_assets()` L128-190: `pqm-` guard, L139 early return for posts/dashboard hooks → `enqueue_app()` L200-245; capture the Workflow submenu hook (L72-79, like L43/63) and load both `pqm-admin.js` and the app there. `admin_notices()` L96-124: welcome notice, plugin screens only, skipped on `pqm-workflow`; keep, point its link at the Workflow page with a query arg the island reads to auto-open. `render_workflow()` L1009-1151: wizard block L1021-1072 goes; print the root div + keep connected sections (Workflow File, credentials list, import URL, hint, version line, agent preview, Connection Test) always. `ajax_save_webhook_url()` L1252-1277 and `ajax_test_connection()` L1285-1337: extract `save_webhook_url(string $url): true|WP_Error` and `ping_webhook(string $url): array{ok,message}` (public static), AJAX stays a thin wrapper. i18n keys `saving`, `savedPingFailed` L183-184 become dead.
- `post-quality-manager/admin/js/pqm-admin.js` -- `initWizard()` L279-359 + boot call L21: delete. Keep `initTestConnection`, `initImportUrlCopy`, `showTestResult`.
- `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-wizard*` L66-110 and `@keyframes pqmFade`: delete; `.pqm-wizard-list` L99-101 stays, renamed `.pqm-credentials-list` (used by `render_credentials_list`).
- `post-quality-manager/includes/class-pqm-rest-api.php` -- routes L27-95; `check_view_reviews` L111; `save_review_callback` sets `pqm_workflow_version_seen` L375-380 (first-result signal); `/posts` viewer L785-797. New: `GET /setup` (progress, complete, token, import URL, download URL, app-passwords availability via `wp_is_application_passwords_available_for_user()`, connected, first result), `POST /setup` (`{step, done}` or `{complete:true}`), `POST /setup/connect` (`{webhook_url}` → `{saved, ping_ok, message}`), all `pqm_manage_settings`.
- `post-quality-manager/post-quality-manager.php` -- `pqm_webhook_url_warning()` L389-397, `pqm_n8n_headers()`; option name constant beside them.
- `post-quality-manager/uninstall.php:17-21` -- option list; add the progress option (autoload false, like `pqm_cron_nudge_dismissed`).
- Core REST for the Application Password: `POST /wp/v2/users/me/application-passwords` `{name}` → `password` once; 501 `application_passwords_disabled` when unavailable. No plugin code.
- `tests/harness/render-matrix-harness.php` -- `$render_workflow` L362-367, wizard regex checks L368-375 / L397-411 (replace), AJAX stubs L421-468 (`tw_pings`, `tw_saved`, `PQM_Json_Exit`). `tests/harness/rest-posts-harness.php` -- route-registration pattern and `tw.options`. No JS tests exist; the dialog copy is checked by the owner's walk-through.
- Docs: `README.md` Installation step 2; `docs/user-paths.md` A3–A5 (UV4 → pending validation), "Notes for the future setup wizard"; `memory/admin-ui-rebuild.md` (island on a PHP page); `n8n/workflow-diagram.md` untouched.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- extract `save_webhook_url()` / `ping_webhook()`; capture the Workflow hook and enqueue the app there too; `render_workflow()` prints the root div and the connected sections always, wizard block removed; notice links to the Workflow page with the auto-open arg; drop the two dead i18n keys -- one home for save/ping, island mounts, criteria 1 and 6.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- `GET /setup`, `POST /setup`, `POST /setup/connect` with `pqm_manage_settings`; step names whitelisted; `complete` = option flag or (connected and first result received) -- criteria 2, 4, legacy row.
- [x] `post-quality-manager/post-quality-manager.php`, `uninstall.php` -- option constant; delete on uninstall.
- [x] `src/setup/` + `src/index.js` -- island: button ("Set up the workflow" / "Setup guide"), Modal with the seven steps, per-step Done, Copy buttons (`ClipboardButton` or `Button` + `navigator.clipboard` as the kit's import-URL copy does), connect step calls `/setup/connect`, app-password sub-step calls core, first-review step lists published posts from `GET /posts`, runs via `requestRun`, polls `reviewsPath(id)` until the newest row leaves `processing`, then `POST /setup {complete:true}`; auto-open on the query arg; strings via `@wordpress/i18n`, copy per brand.md -- criteria 2, 3, 5.
- [x] `post-quality-manager/admin/js/pqm-admin.js`, `admin/css/pqm-admin.css` -- delete the inline wizard JS/CSS, rename `.pqm-wizard-list` -- dead weight.
- [x] `tests/harness/render-matrix-harness.php`, new `tests/harness/setup-harness.php` -- matrix rows: root div + button, no `#pqm-wizard`, notice link; `/setup` routes (permissions, legacy-complete derivation, progress validation, connect: test URL refused and not stored / production stored + pinged / ping failure stored), uninstall list -- I/O matrix.
- [x] `README.md`, `docs/user-paths.md`, `memory/admin-ui-rebuild.md` -- setup via the dialog; UV4 pending validation; island pattern recorded.

**Acceptance Criteria:**
- Given the built bundle, when `npm run build` and `npm run lint:js` run, then both pass and `index.asset.php` lists no new dependency.
- Given the Workflow page after setup, when the connected sections render, then the Connection Test and import-URL copy still work (pqm-admin.js kept).

## Implementation Notes

- `GET /setup` also carries `webhook_url` (prefills the connect step) and `user_login` (the Basic Auth credential's User field); both additive to the Code Map's list.
- Completion helper `pqm_setup_complete()` sits beside `PQM_SETUP_OPTION` in the main plugin file: the notice and `GET /setup` share it. `POST /setup` accepts by hand only the five n8n-side steps; `connect` is set by `/setup/connect` (true on a 2xx ping, false on a failed one), `review` reads completion.
- The ping's success copy now says what it proved ("this site reaches n8n and the webhook credential is right") on both paths, the Workflow page's Connection Test included — one home, one wording.
- `PQM_Admin::render_app_root()` extracted from `render_app_shell()` so the island's root div gets the same unbuilt fallback (build notice); `PQM_Admin::workflow_import_url()` shared by the page and `GET /setup`.
- `POLL_INTERVAL` / `GIVE_UP_AFTER` exported from `src/kit/use-polling.js` for the dialog's wait (no `usePolling`: it watches list `in_flight`, the dialog watches one post's rows).
- Copy button = `Button` + `navigator.clipboard` with the `execCommand` fallback (`ClipboardButton` is deprecated; `useCopyToClipboard` would add `wp-compose`). The Application Password name carries the local time, since core refuses a duplicate name on a re-run.
- Review fixes: "a result received" = `PQM_DB::has_result()` (a stored row not `processing` / `error` / empty), not `pqm_workflow_version_seen` (set on failed rows too, never by pre-handshake imports); the dialog resumes waiting on a review already `in_flight`; a failed run shows its stored `result_data.error`; the ping names a 401/403 credential refusal and says a pre-v4 import's 2xx proves reachability only; the unused `ajax_save_webhook_url()` + hook deleted; credentials are listed under the template's names (`Post Quality webhook - <host>`, `WordPress - <host>`, `Anthropic - Claude`, via `pqm_site_host()`; `GET /setup` adds `site_host`).
- `render-matrix-harness.php` gained a `wp_kses` stub: CI's php job runs unbuilt, where the Workflow page prints the build notice.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens): high 1, medium 3, low 7, false 0, maybe-false 0.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1+2 | "A result received" read from `pqm_workflow_version_seen`: a failed first review completes setup; pre-handshake legacy sites see the notice | high | patch | Verified: `save_review_callback` updates the option before saving any row, error rows included; pre-handshake imports never send a version. The Code Map named this signal. Fixed with one query: a stored review that completed (status not processing, error or empty). Patch, not bad_plan: one function plus a small query, no new public surface. |
| 3 | First-review wait lost on step change, Back or close; Run re-enabled, second paid run possible | medium | patch | Verified: `phase` is component state, interval cleared on unmount. Resume from the post's `in_flight` on mount. |
| 4 | Failed first review shows fixed text, hides `result_data.error` | medium | patch | Bug-31 contract stores the cause; show it. |
| 5 | 401/403 from n8n (credential refused) gets generic copy | medium | patch | Matrix row "message names the cause". Branch added. |
| 6 | Ping success claims credential check on v3 imports | low | patch | v3 ignores the header; claim only when seen version is 0 or current. |
| 7 | `ajax_save_webhook_url` has no caller | low | patch | Only caller was the deleted `initWizard()`. Deleted. |
| 8 | Nav CSS on the `<ol>`, flex item is the `<nav>` | low | patch | Class moved. |
| 9 | App-password fallback always blames HTTPS | low | patch | Core availability also filterable by plugins; copy names both. |
| 10 | Two names for the Claude credential on one page | low | patch | Both lists use the template's credential names. |
| 11a | `enqueue_app()` docblock names `render_app_shell()` | low | patch | Comment fixed. |
| 11b | AGENTS.md says Workflow page is vanilla JS only | low | defer | Agent-context file (managed block); deferred-work.md entry. |

## Design Notes

- Island, not a rebuild: ADR 0005 keeps the Workflow page on PHP; the dialog is the only React on it, mounted by the existing `pages` loop. Both scripts load on that hook; they touch different DOM.
- `complete` derived for legacy sites so nobody who already runs reviews sees a setup notice or a reset guide.
- Application Password through core's own REST route: rung 4 of the ladder; the response carries the password exactly once, which is the "shown once" rule for free.
- Credentials copy exists twice (PHP list on the connected page, JS in the dialog): the PHP one keeps the harness checks, the dialog's is the owner's walk-through. Skipped: serving the copy from one PHP source — add when a third place needs it.
- Skipped: a redirect on activation (the notice already greets); JS unit tests (none exist in the repo; a deferred item).

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all pass, including the new setup harness.
- `php -l` on each touched PHP file; `node --check post-quality-manager/admin/js/pqm-admin.js`; `npm run lint:js`; `npm run build` -- expected: no errors, bundle built.

**Manual checks (hitl, owner):**
- On a real n8n Cloud: walk all seven steps from a fresh site; every label matches n8n; the Application Password step works over HTTPS and explains itself over HTTP; the first review lands and the notice disappears; reopen the guide afterwards and confirm settings are untouched.
