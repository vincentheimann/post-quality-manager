---
title: 'Once a webhook URL is saved, the welcome notice names the next step'
type: 'bugfix'
ticket: 129
created: '2026-11-16'
status: 'built'
baseline_revision: '264f08af2d33becfb463be707d06c8a4d885c90b'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/backlog/bug-welcome-notice-asks-for-setup-after-the-webhook-url-is-saved.md'
  - '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The welcome notice says "set up the n8n workflow first" until the site counts as set up. Once a webhook URL is saved, the site still doesn't count as set up until a result arrives. In that window the notice sends the administrator back to a setup that is done, while the Dashboard says "You are all set — run your first review".

**Approach:** The notice keeps its condition and its 4.0.0 form while no URL is saved. With a URL saved, it reads the n8n Workflow page's own state (`workflow_page_state()`):
- `set-up`, "not checked yet" included: the notice names the first review and links to the Dashboard.
- `needs-attention`: the notice says the workflow needs attention and links to the n8n Workflow page, where that page names the fix.

Owner decisions (2026-11-16):
- The link goes to the Dashboard.
- A failed check points to the fix, and so does every other Needs attention problem: a mis-wired workflow, an outdated version, or the Free variant on Pro.
- On the Dashboard itself the first-review notice is hidden, because the Dashboard's own prompt says it.
- The owner approves the final wording in the pull request.

## Boundaries & Constraints

**Always:**
- With no URL saved, the notice is unchanged: the same text, the warning style, and the link to the auto-opening guide.
- No notice once the site counts as set up (`postquality_setup_complete()` is untouched), for a viewer without `postquality_manage_settings`, or on the n8n Workflow page.
- The copy follows `docs/brand.md`: the full name at first mention, constructive and action-oriented.
- A new placeholder string carries a translator note where Freemius's re-print keeps it (bug 125). The re-print harness stays green.

**Never:** a change to the Dashboard's own prompt, to the n8n Workflow page or its states, to `postquality_setup_complete()`, or to the REST API; a second notice.

**Narrows:** story 55's criterion 1, that the one notice opens the wizard, now holds only while no URL is saved. The same goes for story 36's criterion 1. EXPERIENCE.md L81 changes in this PR.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Fresh site | No URL saved, no result | 4.0.0's warning notice, unchanged: "Set up the workflow", linking to `postquality-workflow&postquality_setup=1` | — |
| URL saved, not checked | URL saved, no report, no result | Info notice naming the first review, linking to the Dashboard (`page=postquality-dashboard`); no "set up the workflow first" | — |
| URL saved, check passed | URL saved, a passing report, no result | Same as the row above | — |
| URL saved, needs attention | URL saved, a report that is `failed`, `miswired`, `outdated` (or the Free variant on Pro) | Warning notice saying the workflow needs attention, linking to the n8n Workflow page with no `postquality_setup` argument | — |
| Dashboard, first-review state | The Dashboard screen, a URL saved, no problem, no result | No notice; the Dashboard's own prompt names the first review. The needs-attention notice still shows there | — |
| Set up | Progress complete, or a URL saved and a result stored | No notice (unchanged) | — |
| Other viewers and places | Non-manager, or the n8n Workflow page | No notice (unchanged) | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` L125–154 `admin_notices()`: the only change site. `workflow_page_state()` (private, L1461) returns `state` set to `set-up`, `needs-attention` or a no-URL state, plus `problem`. Reuse it; don't re-derive the report checks. `SETUP_QUERY_ARG` stays only on the no-URL link.
- `post-quality-manager/includes/pqm-functions.php` L591 `postquality_setup_complete()`: read only, unchanged.
- `src/dashboard.js` L219–236: the Dashboard's prompt ("You are all set — run your first review."), unchanged. It shows when a URL is saved, the site has posts, none is analyzed, and the viewer can run Review.
- `tests/harness/render-matrix-harness.php` L1275–1305: the notice rows. L1296 and L1301 ("connected, no result yet… notice still shows" and the version-seen row) encode the old link for a saved URL. They change to the new behaviour, and that is the decision itself. The matrix rows go here. `$notice_for` stubs settings, options and `$wpdb->has_result`; a report comes from the `postquality_workflow_version_seen` option.
- `_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md` L81: the welcome notice entry point, to change.
- `docs/user-paths.md` L32: the CV line, a record at done (ticket Notes).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php`: in `admin_notices()`, once `postquality_setup_complete()` is false and a URL is saved, branch on `workflow_page_state()['state']`. The copy and styles are in Design Notes. Every string is escaped at output, and any placeholder note sits at statement level.
- [x] `tests/harness/render-matrix-harness.php`: one row per matrix row. Update L1296 and L1301 to the new link and text. Add a row that reads the notice's text with a URL saved and no result; it fails on 4.0.0 (ticket criterion 2).
- [x] `_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md` L81: the welcome notice links here while no URL is saved; with a URL saved it names the first review (Dashboard) or the fix (this page).
- [x] Local check on WordPress66 (the Free build of this branch, a URL saved, no result): Dashboard, Post Reviews and Settings show the new notice. Then an n8n Workflow page check that fails (an unreachable URL, then Test) makes the notice say needs attention. Freemius's Account page is not reachable with the opt-in skipped, so that is recorded as unchecked.

**Acceptance Criteria:**
- Given the harness sweep, then every matrix row passes, the old link rows are updated, and the re-print harness passes.
- Given the PR, the owner approves the wording (hitl). Until then, the plan stays `built`, not `done`.

## Implementation Notes

- Environment (orchestrator, 2026-11-16):
  - The plan was approved by the owner ("Approve and continue"). The Local site is **WordPress66**: running and clean, with WordPress 6.6, PHP 8.1.29 and no plugin.
  - The walk frame is `C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/475b482a-2619-489e-8c62-a683cc8811cb/scratchpad/s125/scripts/walk-lib.sh` (bug 125's). `source` it from `s125/` to get `lwp`, `lwpd`, `walk_backup`, `walk_restore` and `walk_check`, and use the same rules:
    - back up first, restore at the end;
    - check `wp core version` at the start and the end;
    - never run `cron event run --all`;
    - never print `wp-config.php`.
  - The Free build of this branch is `php bin/build-free.php <dir>`; the admin app needs `npm run build` first (`admin/build/` is git-ignored).
  - A notice's HTML can be read without a browser: `wp --user=1 eval` with `set_current_screen()` and `PostQuality_Admin::admin_notices()`.
- Build (implementer, 2026-11-16):
  - `admin_notices()` returns early once set up, then branches on `workflow_page_state()['state']`: `set-up` prints the info notice (none on a screen id ending in `postquality-dashboard`; `str_ends_with`, so Freemius's `postquality-dashboard-account` page still gets it), `needs-attention` the warning to the n8n Workflow page, any other state 4.0.0's notice unchanged. The three strings use the Design Notes copy as drafted; no placeholder, so no translator note.
  - Harness: the notice block compares whole notices (text, style, link) instead of the link alone. The old rows L1296 and L1301 became the bug 129 rows (URL saved and not checked, and a passing check, on Post Reviews and Settings; the Dashboard with no notice; the four problems on Post Reviews and the Dashboard), plus "a result stored and a failed check: no notice". With `class-pqm-admin.php` at 264f08a the harness gives 462 passed, 13 failed (exactly the 13 bug 129 rows); with the fix 475 passed. Full sweep: every harness green, the re-print and Free build harnesses included.
  - Local check on WordPress66 (`npm run build`, `php bin/build-free.php`, the Free build copied in and activated, opt-in not answered; scripts in `scratchpad/s129/scripts/`, notices read with `wp --user=1 eval-file` and `set_current_screen()` on the hooks `register_pages()` gives): no URL: 4.0.0's warning on the Dashboard, Post Reviews and Settings, none on the n8n Workflow page. URL `http://127.0.0.1:9/webhook/pqm-execute-bug129` saved, not checked: no notice on the Dashboard, the info notice on Post Reviews and Settings, linking to the Dashboard. After the page's Test path (`PostQuality_Admin::ping_webhook()` on the saved URL, cURL error 7, the failure recorded): the needs-attention warning on all three, linking to `page=postquality-workflow`. No debug.log. Restored: `walk_check` all same, `wp core version` 6.6 at start and end.
  - Review (2026-11-16): the info notice's draft "Your workflow is in place: run your first review next." claimed more than the "not checked yet" state knows; it became "Your webhook URL is saved: run your first review next." (link unchanged), in the code, the harness and the Design Notes. The Local check above ran with the earlier draft. Two harness rows were added for the new branches' early returns with a URL saved: a non-manager, and the n8n Workflow page.
  - Unchecked: Freemius's Account page (not reachable with the opt-in skipped) and the notices in a browser (read as HTML only).

## Plan Change Log

## Review Triage Log

**Pass 1 (Quick, 2026-11-16):** 7 findings. Verdicts: 1 medium, 5 low, 1 resolved by another fix. Routes: 3 patch, 2 defer, 1 rejected.

- **low, patch:** `memory/admin-ui-rebuild.md` L15 still described the old notice link. It now says what holds with no URL saved, with a URL saved, and in Needs attention.
- **low, defer:** the ticket's Boundaries and Notes, and stories 36 and 55, still state the unnarrowed "one notice opens the setup guide". A build never writes to tickets, so these go into the records at done (`deferred-work.md`).
- **medium, defer:** in Needs attention with no result, the Dashboard shows the new warning above its own "You are all set — run your first review". That prompt never reads the report, and 4.0.0 already showed the same pair contradicting each other in that state. The frozen matrix keeps the warning there and forbids changing the prompt, so this is left to the owner as a follow-up (`deferred-work.md`).
- **low, patch:** "Your workflow is in place" claimed more than is known while the URL is "not checked yet". The draft now reads "Your webhook URL is saved: run your first review next."; the owner approves the final wording at the PR.
- **low, resolved with the finding above:** the two drafts named the workflow differently ("Your workflow" and "Your n8n workflow"). The new info text no longer names it.
- **low, rejected:** `has_result()` runs twice on plugin screens in the first-review window (`postquality_setup_complete()`, then `workflow_page_state()`). It is one cheap `SELECT`, and only until the site counts as set up; avoiding it means splitting the page-state builder.
- **low, patch:** the early returns for a non-manager and for the n8n Workflow page had harness rows only with no URL saved. Two rows were added with a URL saved: on Post Reviews, a manager gets the notice and a non-manager does not; the n8n Workflow page shows none.

## Design Notes

Draft copy, for the owner to approve at the PR:

| State | Style | Text | Link |
|---|---|---|---|
| No URL (unchanged) | `notice-warning` | Welcome to Post Quality Manager! To start reviewing posts, set up the n8n workflow first — a guide walks you through it. | Set up the workflow → the guide |
| URL saved, no problem | `notice-info` | Welcome to Post Quality Manager! Your webhook URL is saved: run your first review next. | Open the Dashboard → `admin.php?page=postquality-dashboard` |
| Needs attention | `notice-warning` | Welcome to Post Quality Manager! Your n8n workflow needs attention before your first review. | See what to fix → `admin.php?page=postquality-workflow` |

`notice-info`: nothing is wrong once a URL is saved, so a warning would be alarming. A notice has no placeholder, so no translator note is needed.


## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all pass.
- `php -l post-quality-manager/admin/class-pqm-admin.php` -- expected: clean.

**Manual checks (if no CLI):**
- On WordPress66, the notice in each matrix state as the Local check task lists.
