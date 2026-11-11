# Story 5.9 — Manual pass of the epic's Done when (checklist)

The owner walks this on the real site and ticks each box, or files a bug in `_bmad-output/backlog/` and writes its id next to the box. The same walk also counts as story 5.8's site check ("nothing differs from today", LTR and RTL). When every box is ticked or has a bug id, close 5.8 and 5.9.

**Same sitting (2026-10-06, `/prioritize`):** backlog stories 1 and 6 run in the same walk; their criteria skip what this checklist covers. Suggested order: section 0, story 6 (fresh install, wizard and round trip, so later sections have real reviews), story 1, then sections 1–6.

## Pre-checked by the agent (2026-10-05, `main` at `160bd62`)

These are the parts of Done when 3 and 5 that don't need a browser. Each was verified against the code:

- [x] **Assets are not in git.** `git ls-files` has no `admin/build/`, and `.gitignore:16` ignores it.
- [x] **CI builds the assets.** `ci.yml` runs `npm ci`, then `lint:js`, `build`, and an asset check, and uploads the `admin-build` artifact. The last run on `main` is green. (The release zip itself belongs to `epic-delivery-pipeline`.)
- [x] **PHP ships only the shell.** `class-pqm-admin.php` renders the two pages only through `render_dashboard_app()` / `render_posts_app()` → `render_app_shell()`. No legacy name is left in PHP or JS: `render_posts`, `render_dashboard(`, `ajax_trigger_command`, `pqm_trigger_command`, `get_dashboard_stats`, `format_status`, `render_yoast_badges`.
- [x] **Brownfield inventory.** All 13 items are deleted or re-deferred with a recorded reason (5.7 plan, disposition table). The two re-deferrals (effective-model resolution and upgrade copy) both serve the Settings page or the REST layer, which ADR 0005 keeps out of this epic. Owner: note that Done when 3 lists "effective model" among the single-source items; the 5.7 table re-defers it for the reason above.
- [x] **Backlog story 5** is `dropped` (subsumed).
- [x] **Gates are green.** `npm run lint:js` exit 0; `npm run build` exit 0. Harnesses: caps 173, cron-gate 24, license 21, quota 40, render-matrix 38, rest-posts 133, rest-reviews 24, sanitize-matrix 86 (all passing, 0 failing).
- [x] **i18n coverage of the bundle.** All 119 `__()` / `_n()` calls in `admin/build/index.js` carry the `post-quality-manager` domain. That is all of them: the domain appears exactly 119 times.

## 0. Setup

- [ ] The site runs the current `main` build: `npm run build` at `160bd62` or later, or the CI `admin-build` artifact.
- [ ] Users exist for Administrator, Editor and Author. The site has published posts that are Ship, Needs revision, Major revision and Not analyzed, plus at least one post Pending Review.
- [ ] Browser DevTools console is open on every page. Any React key, prop or i18n warning fails the step it shows up in.

## 1. Dashboard (Done when 1, CAP-1)

- [ ] **Fix-next queue** shows at most 5 posts. Order: Major revision → Needs revision → Not analyzed, then lowest score first, then oldest review first (never-reviewed: oldest post). Ready-to-ship posts never appear.
- [ ] **Per-row Review** in the queue starts a run. The row shows progress, a toast reports the result, and the queue refreshes. An Author without access to Review sees no Review button.
- [ ] **Each status tile** (Major revision, Needs revision, Not analyzed, Ready to ship) opens Post Reviews with that status chip active, and its count matches the list.
- [ ] **The Average score tile** opens Post Reviews sorted by score, lowest first. Known: never-reviewed posts sort first. This is deferred-work's NULL-sort entry; do not file it again.
- [ ] **The sparkline** shows the average-score trend, and hovering or focusing it gives the week and value. With less than two weeks of analyzed posts it shows the "once analyzed posts span two weeks" note instead.
- [ ] **Nudges** show when their condition holds, and dismissing one keeps it dismissed after a reload.

## 2. Post Reviews (Done when 2, CAP-2)

- [ ] **Status chips** filter the list, the counts match, and "All" resets. The chip state survives a reload (it is in the URL).
- [ ] **Score range** narrows the list (try a min, a max, and both). This is the request that returned 500 in story 5.3, so watch for "Could not load your posts".
- [ ] **Sorting** works ascending and descending on every sortable column (Post, Score, Status, Last reviewed), and the sort survives a reload.
- [ ] **Pagination** works with a filter active.
- [ ] **Details** opens and closes the detail panel. Its tabs switch, the pillar meters and top edits render, and the action buttons respect the role (hidden for a role that can't run the command; locked with upgrade copy for a plan that doesn't include it).
- [ ] **Bulk "Review selected"** opens one `Modal` confirmation (not a browser dialog). The posts then run one after another with progress, and posts past the quota are refused with a per-post message (test as an Author near the limit).
- [ ] **Quota note** appears only next to a rendered Review button, so an Editor sees none.

## 3. Yoast column and Pending marker (entry 10 / story 5.10)

- [ ] **Yoast active:** a Yoast SEO column shows SEO and readability badges for scored posts and a placeholder for unscored ones. The Details row still spans the full width.
- [ ] **Yoast deactivated:** the column is gone and the layout holds.
- [ ] A Pending Review post shows core's "— Pending" marker after its title.
- [ ] **Narrow window:** columns hide in this order (Agents run → Yoast → Last reviewed), and the Post title stays readable.
- [ ] Check all of the above both with the bulk checkbox column (Admin) and without it (a role that cannot Review).

## 4. Translated site and auto-resume (Done when 4, CAP-4)

The plugin ships no translations, so use the agent's pseudo-translation pack. It wraps every plugin string as ⟦String⟧, so **plugin text on the two React pages without brackets is a string that skipped i18n**.

- [ ] **Install the pack.** Copy `post-quality-manager-fr_FR-pqm-app.json` (and `-he_IL-` for section 6) into `wp-content/plugins/post-quality-manager/languages/`. Create the folder if it isn't there. The files are in the agent's session scratchpad under `languages/`; the generator is `pseudo-l10n.js`, also in the scratchpad.
- [ ] **Switch language.** Settings → General → Site Language = Français installs the core pack. Then set your user Profile → Language = Français. The site language can go back to English afterwards.
- [ ] **Every plugin string on both pages shows brackets.** Cover tiles, queue, chips, table headers, badges, details tabs, toasts, the bulk Modal, the quota note, empty states and error notices. Component chrome (Modal close, Notice dismiss) shows French. Expected and not a bug: the page title and the admin menu are PHP strings with no `.mo`, so they stay English.
- [ ] **Long strings wrap.** Status badges, "Hide details" and buttons wrap inside their cells instead of overflowing.
- [ ] **Auto-resume:** start a Review, then reload while it runs. The row comes back in progress, polling resumes, and the result lands without another click. Check this on both pages.

## 5. Accessibility and status rendering (Done when 4, CAP-4, CAP-5)

- [ ] **Tabs** (details panel): Arrow keys move between tabs and Tab moves into the panel, with a visible focus ring.
- [ ] **Toggles:** the Details button carries `aria-expanded` (it flips) and `aria-controls`. Status chips carry `aria-pressed`.
- [ ] **Toasts and table refreshes** are announced: snackbar messages go through `speak()`, and the "updating" line has `role="status"`. Check with a screen reader or the Accessibility tab in DevTools.
- [ ] **Sort state:** the active column header carries `aria-sort` (`ascending` / `descending`), and the others carry none.
- [ ] **Keyboard only:** every control on both pages can be reached and used, and the bulk Modal traps focus and returns it to the button on close.
- [ ] **Status is never color alone.** Every status (table, queue, chips, tiles, detail panel) shows icon + label + color, with no emoji, matching `ux-dashboard-post-reviews/mockup.html`. Pillar scores are single-hue meters.

## 6. RTL (story 5.8 site check)

- [ ] With your Profile language set to עברית (he_IL; install via Site Language first), repeat a quick pass of sections 1–3. The layout mirrors, the quota note sits on the correct side, the sparkline and meters render, and nothing overlaps or clips.
- [ ] Back in LTR, nothing differs from before 5.8 (the refactor was meant to change nothing visible).

## Result

- Date / site / walker:
- Bugs filed (ids):
- Closing actions once green:
  - Mark 5.8 and 5.9 done.
  - In deferred-work, the owner closes three entries (the file is append-only): the 5.2 `error` badge (resolved by 5.4), the AGENTS.md jQuery line (resolved by `0994d77`), and the lint-crash half of the 5.5 entry (resolved by 5.8; the Jest question stays open).
  - The n8n template failure-path entry is already filed as backlog bug 31 (2026-10-06); nothing to fold.
  - Remove the pseudo pack from the site's `languages/` folder.
  - Run `bmad-retrospective` for `epic-ui-rebuild`.
