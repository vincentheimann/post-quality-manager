---
title: 'A Dashboard block opens a prefilled email for feedback or a bug report'
type: 'feature'
ticket: '79'
created: '2026-10-24'
status: 'done'
baseline_revision: 'a663e67dff270c438cd8b722928001efca440bb4'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/docs/policies/support.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** a user of the plugin has no way, from inside WP Admin, to tell us what works, what does not or what broke; the support address and the facts a report needs (the support policy asks for the plugin and workflow versions, the WordPress and PHP versions) live only on postquality.com.

**Approach:** a quiet card at the bottom of the Dashboard, collapsed to one line: the user picks Feedback or Bug, writes a message, sees the environment lines that will be appended, and "Send by email" opens their mail client on a `mailto:` to hello@postquality.com with the subject and body prefilled; a Copy control copies the same text for a browser with no mail client. A Hide control stores a per-user dismissal through one new REST route; a one-line "Send feedback" link reopens it. The server sends nothing and calls nothing; `GET /dashboard` gains the environment lines and the dismissal flag.

**Decisions (owner delegation 2026-10-24, story 79 Notes):** one mailbox on both builds (no Freemius support link); no public issue-tracker link until the repository is public at entry 4.8 (a line in the launch plan adds it then), so the Bug kind shows the email path and the security sentence only; no answer-time promise in the block (the support page holds the policy); the environment lines are the plugin version and build, WordPress, PHP, the workflow version last seen and the active theme's name, nothing else.

## Boundaries & Constraints

**Always:** visible to every user who can open the Dashboard (`pqm_view_reviews`, the route's existing permission); identical on the Free and the Pro build except the build word in the environment line; the site URL, the user's name or email, keys, tokens and settings never in the environment lines; the user sees the exact text before sending; strings translatable and worded per `docs/brand.md`; WordPress components (`Card`, `RadioControl` or `ToggleGroupControl`, `TextareaControl`, `Button`); the dismissal route checks a nonce through `apiFetch` (core's REST nonce) and the same capability; uninstall deletes the user meta for every user; the Free build strip leaves the block whole.

**Never:** `wp_mail` or any server-side send; a request to anything but the plugin's own REST route; an admin notice; tracking of opens, clicks or sends; a Freemius contact form; a WordPress.org forum link (story 38); a change to the Dashboard's other cards.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Collapsed | Dashboard loads, not dismissed | one line "Send feedback or report a bug" + "Write a message" button + Hide, below the other cards | — |
| Empty message | open, kind chosen, textarea empty or whitespace | "Send by email" and Copy disabled | — |
| Feedback mail | kind Feedback, message "Love the queue" | `mailto:hello@postquality.com?subject=Post%20Quality%20Manager%3A%20feedback&body=Love%20the%20queue%0A%0A---%0A<environment lines>` | — |
| Bug mail | kind Bug | subject "Post Quality Manager: bug report"; the block shows "A security problem? Email it, never post it in public." | — |
| Environment | Pro build 3.9.0, WP 6.8, PHP 8.3, workflow v7 seen, theme Twenty Twenty-Five | lines: `Post Quality Manager 3.9.0 (Pro)`, `WordPress 6.8`, `PHP 8.3.x`, `Workflow version 7` (`Workflow version: not seen yet` when none), `Theme: Twenty Twenty-Five`; Free build says `(Free)` | no workflow report: "not seen yet" |
| Copy | message present | clipboard holds `Subject: …` line, a blank line, the body exactly as in the mailto | clipboard refused: the text stays selectable in a read-only field |
| Hide | user presses Hide | `POST /post-quality/v1/dashboard/feedback {dismissed:true}` → 200; card replaced by the one-line "Send feedback" link | request fails: the card stays, an inline notice says it could not be hidden |
| Reopen | dismissed user presses "Send feedback" | `POST … {dismissed:false}` → 200; the card shows open | same error handling |
| Other user | user A dismissed | user B still sees the card | — |
| Permission | user without `pqm_view_reviews` | `POST /dashboard/feedback` → 403 (route's permission callback) | — |
| Uninstall | meta set for two users | `delete_metadata('user', 0, 'pqm_feedback_dismissed', '', true)` removes both | — |

</frozen-after-approval>

## Code Map

- `src/dashboard.js` -- the Dashboard app (313 lines): loads `GET /post-quality/v1/dashboard` into `dash`, renders the cards (`pqm-dash-card`), the cron nudge at L277–300 shows the dismiss-link pattern; mount the new `<FeedbackCard>` last, after the nudge, fed from `dash.feedback`.
- `src/dashboard/feedback.js` (new) -- the card component and two pure exports used by Jest: `mailText(kind, message, environment)` → `{ subject, body }` and `mailtoUrl(kind, message, environment)` → the `mailto:` string (`encodeURIComponent` for subject and body; line breaks as `%0A`). Copy through `useCopyToClipboard` from `@wordpress/compose` (installed); dismissal through `apiFetch({ path: '/post-quality/v1/dashboard/feedback', method: 'POST', data: { dismissed } })`.
- `src/dashboard/style.css` -- a few lines for the card's collapsed row and the environment block (`<pre>`-like, muted, wraps); reuse the existing `pqm-dash-card` spacing.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `dashboard_callback()` (~L1027): add `feedback` => `[ 'dismissed' => (bool) get_user_meta( get_current_user_id(), 'pqm_feedback_dismissed', true ), 'environment' => self::feedback_environment() ]`; new private static `feedback_environment(): array` (strings: `sprintf` with `PQM_VERSION`, the build word — default `'Free'`, set to `'Pro'` inside `if ( pqm_fs()->is__premium_only() )` so the Free strip leaves `'Free'` —, `get_bloginfo( 'version' )`, `PHP_VERSION`, `pqm_workflow_report()['version']` or "not seen yet", `wp_get_theme()->get( 'Name' )`); register `/dashboard/feedback` (CREATABLE, `permission_callback` `check_view_reviews`, arg `dismissed` boolean required, `sanitize_callback` `rest_sanitize_boolean`) → `update_user_meta(..., 'pqm_feedback_dismissed', 1)` or `delete_user_meta(...)`, returns `{ dismissed }`. Never `intval` as a sanitize callback (lessons 2026-10-05).
- `post-quality-manager/includes/class-pqm-activator.php` ~L58 -- uninstall: one `delete_metadata( 'user', 0, 'pqm_feedback_dismissed', '', true );` beside `pqm_monthly_usage`.
- `tests/harness/rest-posts-harness.php` -- already exercises `dashboard_callback()`: add rows for the `feedback` key (dismissed flag per user, the environment lines, "not seen yet", no URL or email in any line) and for the new route (true stores, false deletes, 403 without `pqm_view_reviews`).
- `tests/harness/free-build-harness.php` -- add a row: the stripped `class-pqm-rest-api.php` still has `feedback_environment()` and its build word is `Free`.
- `tests/js/dashboard-feedback.test.js` (new) -- Jest + jsdom like the other `tests/js/*.test.js`: the pure builders (subject per kind, body = message + `\n\n---\n` + lines, encoding of `&`, `?`, `#`, line breaks, non-ASCII), the disabled state on an empty message, Hide calls `apiFetch` once with `{dismissed:true}`.
- `docs/distribution.md` § Launch plan -- the day-0 GitHub row gains "add the public issues link to the Dashboard feedback block (story 79)".
- `docs/release-notes/unreleased.md` -- one line: the Dashboard has a feedback card that opens an email to hello@postquality.com; nothing is sent without the user.
- `memory/admin-ui-rebuild.md` or `memory/freemium-and-roles.md` -- one line on the feedback card and its user meta key (pick the one that lists Dashboard features).

## Tasks & Acceptance

**Execution:**
- [x] `class-pqm-rest-api.php` -- the `feedback` key and the route -- the data and the one request
- [x] `class-pqm-activator.php` -- uninstall line
- [x] `src/dashboard/feedback.js`, `src/dashboard.js`, `src/dashboard/style.css` -- the card
- [x] `tests/harness/rest-posts-harness.php`, `tests/harness/free-build-harness.php`, `tests/js/dashboard-feedback.test.js` -- the matrix rows
- [x] `docs/distribution.md`, `docs/release-notes/unreleased.md`, the memory line
- [x] `npm run build`, `npm run lint:js` (restore `pqm-admin.js` and `bin/zip.js` if `--fix` reformats them), `npm run test:unit`, every harness, `php -l` on touched PHP

**Acceptance Criteria:**
- Given the built Dashboard, when a user with `pqm_view_reviews` loads it, then the card is the last card, collapsed, on both builds
- Given the page's network activity during a full use (open, type, Copy, Send by email, Hide, reopen), then the only requests are the two `POST /dashboard/feedback` calls (and the page's existing `GET`s)

## Implementation Notes

- Plan written 2026-10-24 (Opus 5.5, bmad-build by hand from the workspace root). Checkpoint 1 taken under the owner's advance approval ("approve and continue, make choices based on best practices"). Branch `feat/dashboard-feedback` from main `a663e67` in the main clone. Implementer on Fable 5.1 (owner's 2026-10-16 decision).
- Tooling (memory/lessons.md 2026-10-24): edit files with the Edit tool, which keeps text literal and the files LF; a script that writes a file uses `read_bytes()` / `write_bytes()` with no backslash escapes in its text. `wp-scripts lint-js --fix` reformats `post-quality-manager/admin/js/pqm-admin.js` and `bin/zip.js` outside its scope: restore them before handing back.
- Deviation (2026-10-24): the Fable 5.1 implementer stopped on the account's Fable usage limit with every file written and one Jest row failing. The orchestrator (Opus 5.5, the session model since the owner's switch) finished the tail directly instead of re-dispatching, which the workflow allows when the implementer cannot continue. Two fixes: the `dismissed` arg keeps the schema type alone, like the setup route's flags (`rest_sanitize_boolean` failed the harness's three-argument rule from lessons 2026-10-05, and its own row was updated to match); the Send by email row prevents jsdom's navigation to the `mailto:`, which jsdom cannot perform.
- Files: `class-pqm-rest-api.php` (`FEEDBACK_META`, the `feedback` key in `GET /dashboard`, `feedback_environment()` with the build word set inside the premium block, `POST /dashboard/feedback`), `class-pqm-activator.php` (uninstall line), `src/dashboard/feedback.js` (new; pure `mailText` / `mailtoUrl` / `copyText` and the card), `src/dashboard.js` (the card last, also on the not-connected Dashboard), `src/dashboard/style.css`, `tests/harness/rest-posts-harness.php`, `tests/harness/free-build-harness.php`, `tests/js/dashboard-feedback.test.js` (new, 13 rows), `docs/distribution.md` (day-0 row adds the issues link), `docs/release-notes/unreleased.md`, `memory/admin-ui-rebuild.md`.
- Verification: `php -l` clean on the touched PHP; every harness 0 failed (rest-posts 179); `npm run test:unit` 10 suites, 171 tests passed; `npm run lint:js` exit 0, no out-of-scope reformat; `npm run build` compiled, the card's title in `admin/build/index.js`; no CR byte in any touched file.
- Correction to the Deviation bullet (review row 7): the harness rejected `rest_sanitize_boolean` because no harness stub defines it, so the call failed on an undefined function. The WordPress helper itself ignores extra arguments, which is what lessons 2026-10-05 recommends. The schema-only arg stays: it matches the setup route's flags and needs no stub.
- After the review (orchestrator, Opus 5.5): Copy calls `navigator.clipboard.writeText()` itself and reads copied or refused from its promise (`useCopyToClipboard` in the installed `@wordpress/compose` 8.9.0 awaits the clipboard after React's click handler has run, so the old check raced); without the Clipboard API the card refuses and shows the read-only field. Focus moves to the Send feedback link after Hide and to the open card's heading after reopening. The reopen link and its notice sit in a `div`. The two outcome sentences take the address as a `%s` placeholder. The workflow line reads an older v1–v4 workflow as "an older one that does not report its number", the n8n Workflow page's reading. Jest 16 rows (174 in the suite), rest-posts 180; every harness 0 failed; lint and build clean.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-10-24): 7 findings — high 0, medium 3, low 3, rejected 1; 6 → patch (the orchestrator applied them: the Fable implementer is at its usage limit).**

1. medium → patch — `CopyButton` assumed `useCopyToClipboard`'s success callback runs before React's `onClick`; in `@wordpress/compose` 8.9.0 the hook's click handler awaits `navigator.clipboard.writeText()`, so on HTTPS the refusal flashed on a successful copy and the ref stayed set, hiding later refusals. Verified in `node_modules/@wordpress/compose/src/hooks/use-copy-to-clipboard/index.ts`. Fixed with a direct `writeText()` call; the Jest mock now settles in a later task like the real API, and a row copies twice (success, then refusal).
2. medium → patch — `feedback_environment()` reported a v1–v4 workflow that answered (version 0, a time) as "not seen yet". Verified against `pqm_workflow_report()` and the n8n Workflow page (`version > 0 || at > 0` is seen). Three cases now; the harness fixture labelled "a failed test" also carried a time, which a failure alone never stores (`pqm_record_workflow_failure()`), so it was corrected and a row added for the older workflow.
3. low → patch — the dismissed state put the `Notice` (a `div`) inside a `p`. Now a `div`; a Jest row asserts no notice inside a paragraph.
4. low → patch — the Reopen row's error handling had no test. Added ("Reopen fails").
5. medium → patch — focus fell to the page after Hide and after reopening (accessibility basics, never simplified away). Focus moves to the replacing element; asserted in the Hide row.
6. low → patch — the support address sat inside two translatable strings. `%s` placeholders with translators comments.
7. low → rejected by rule — the Deviation bullet's cause was wrong; the only fix edits this plan. A correction line is appended above, since Implementation Notes are append-only.

## Design Notes

Copy (draft, brand.md tone): card title "Send feedback or report a bug"; collapsed button "Write a message"; kind control "Feedback" / "Bug"; textarea label "Your message"; environment heading "Added to your email so we can reproduce a problem:"; primary "Send by email"; secondary "Copy the email"; tertiary "Hide"; reopen link "Send feedback"; bug-kind line "A security problem? Email it, never post it in public."; after Send: "Your email app should open now. If nothing happens, copy the email and send it to hello@postquality.com."

The `mailto:` body stays short by design (message + five lines); some clients cut long URLs, which is what Copy is for. No `target` on the mailto link; it is a `Button` with `href`.

## Verification

**Commands:**
- `npm run build` -- expected: success, `admin/build/` updated
- `npm run test:unit` -- expected: all pass, the new file included
- `for h in tests/harness/*-harness.php; do php "$h" | tail -1; done` -- expected: 0 failed everywhere
- `php -l` on the touched PHP files -- expected: no syntax errors

**Manual checks (if no CLI):**
- Owner (hitl at the PR, optional): the card on a Local site at 1280 and 390 px; Send by email opens the mail client with the text shown.
