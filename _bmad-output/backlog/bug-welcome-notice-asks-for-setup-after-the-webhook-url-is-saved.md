---
id: 129
type: bug
title: "The welcome notice still asks to set up the workflow once a webhook URL is saved, beside the Dashboard's \"run your first review\""
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: true
risk: low
severity: P3
estimate: ""
---

# The welcome notice still asks to set up the workflow once a webhook URL is saved, beside the Dashboard's "run your first review"

## Description

On a site with a webhook URL saved and no review result yet, an administrator sees the warning "Welcome to Post Quality Manager! To start reviewing posts, set up the n8n workflow first" on the plugin's screens, and on the Dashboard, right under it, "You are all set — run your first review." Each message is right for its own condition, but together they contradict each other, and the warning sends the administrator back to a setup that is done. Expected (owner decision 8a): once a webhook URL is saved, the welcome notice says the next step is the first review. The owner approves the new wording in the pull request (hitl).

## Reproduction

1. Story 38's clean Local site (WordPress 6.6), the Free build of release v4.0.0, opt-in skipped, at least one published post, no review result stored.
2. Save a webhook URL (story 38 used the walk's listener, `docs/local-verification.md` step 4), without finishing the setup guide.
3. As an administrator, open the Dashboard, then Post Reviews and Settings.
4. Actual (story 38's Free walk and the owner's look, 2026-11-11): every one of these screens shows the warning "Welcome to Post Quality Manager! To start reviewing posts, set up the n8n workflow first — a guide walks you through it. Set up the workflow"; the Dashboard also shows "You are all set — run your first review. Pick a post below and click Review. …".
5. Expected: with a URL saved, no screen tells the administrator to set up the workflow first; the welcome notice names the first review as the next step.

## Cause Hypothesis

The two messages read different conditions and no spec covers their overlap. The welcome notice shows until the site counts as set up: the setup guide marked it complete, or a URL is saved and a real result is stored (story 36). The Dashboard's prompt shows as soon as a URL is saved, while no post has a review result. Between the URL being saved and the first result landing, both are true, and the welcome notice's text still assumes no workflow exists.

## Acceptance Criteria

1. **Once a URL is saved, the notice names the first review**
   **Given** an administrator, a webhook URL saved with no failed check on record ("not checked yet" included), no real result stored, and the site not marked set up
   **When** they open any screen that shows the welcome notice
   **Then** the notice says the next step is the first review, and no screen shows a message asking to set up the workflow first
2. **Tests cover the condition found and fixed**
   **Given** the PHP harness sweep
   **When** it runs
   **Then** a row that fails on 4.0.0 and passes on the fix reads the notice's text with a URL saved and no result, and the existing rows for the other states still pass
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it runs on the current code
   **Then** the two messages do not show together, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 and 2

## Boundaries

- Must not change: with no webhook URL saved, the notice asks to set up the n8n workflow first and links to the setup guide, as in 4.0.0.
- Must not change: no notice once the site counts as set up (the guide marked it complete, or a URL saved and a real result stored), for a viewer without the settings capability, or on the n8n Workflow page.
- Must not change: when the site counts as set up; the Dashboard's own prompt and its conditions; the n8n Workflow page, its states and its actions; the notice stays one notice that opens the setup guide (story 55's criterion 1).
- Copy: `docs/brand.md` (full name at first mention, then the short form; constructive, action-oriented; no "PQM"). A new string with a placeholder carries a translator note that survives Freemius's Free build (bug 125).

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md L618 (the look's triage, defect (b)), L625 (owner decision 7a) and L626 (owner decision 8a)
- spec — _bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md L58 (state 4, Set up, and its "not checked yet" variant) and L81 (the welcome notice is an entry point that links to the n8n Workflow page)
- decision — _bmad-output/backlog/story-guided-setup-dialog.md L22–25 (story 36, criterion 1: the notice offers "Set up the workflow" until setup is complete)
- decision — _bmad-output/backlog/story-one-button-setup-wizard.md L22–25 (story 55, criterion 1: one notice, which opens the wizard)
- user paths — docs/user-paths.md L32 (the CV line on the welcome notice)
- copy rules — docs/brand.md (§ Copy Rules, § Naming)
- blocks — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38; the fix ships in 4.0.1 before submission)

## Notes

- Decision (owner, 2026-11-11, 7a): this defect ships in patch release 4.0.1, before story 38's submission.
- Decision (owner, 2026-11-11, 8a): once a webhook URL is saved, the welcome notice says the next step is the first review. The copy follows `docs/brand.md`; the owner approves the wording at the pull request. It narrows story 36's criterion 1 and makes the CV line in `docs/user-paths.md` L32 out of date; story 55's criterion 1 still holds.
- Fact (source read 2026-11-11, the plugin tree of v4.0.0, equal to 78c8bc8; plugin paths under `post-quality-manager/`, `src/` and `tests/` at the repository root):
  - `post-quality-manager/admin/class-pqm-admin.php` L108–137 (`admin_notices()`): shown on every screen whose id holds `postquality-` (L110–113) except the n8n Workflow page (L115–118), to users with `postquality_manage_settings` (L120–124), while `postquality_setup_complete()` is false (L126–136); the link opens the setup guide on the n8n Workflow page (L129).
  - The screens it reaches: the Dashboard, Post Reviews and Settings, and Freemius's Account page, whose slug is the menu slug plus `-account` (`post-quality-manager/vendor/freemius/includes/managers/class-fs-admin-menu-manager.php` L340–343) and which exists only once the site opted in or activated a license. Freemius's opt-in, pricing and contact screens clear every notice (`post-quality-manager/vendor/freemius/includes/class-freemius.php` L23612–23644).
  - `post-quality-manager/includes/pqm-functions.php` L590–598: set up means the guide's progress says complete, or a URL is saved and `PostQuality_DB::has_result()` finds a real result.
  - `src/dashboard.js` L219–236: the prompt shows when the Dashboard is connected (a URL saved), the site has posts, none is analyzed, and the viewer can run Review.
  - The notice's link opens the guide at its first open step (`src/setup/index.js` L105–110, L209): on the key path, Connect your n8n until an n8n key passes (`src/setup/steps.js` L515, L557–567), as on the reproduction's site. The n8n Workflow page's state 4 forces the guide's review step instead (`post-quality-manager/admin/class-pqm-admin.php` L1659); with a URL saved and no check yet, the page's primary action is "Test the connection" (L1654–1655).
  - `tests/harness/render-matrix-harness.php` L1275–1305 holds the notice's rows. L1296 and L1301 cover criterion 1's state but check only the link (L1292); only L1294 reads the text, with no URL saved.
- Assumption: severity P3. The text misleads during first use and no function is lost: the Dashboard's Review button still runs the first review.
- Assumption: risk low. A text and condition change in one notice, covered by harness rows, reverted cleanly.
- Assumption: Freemius's Account page needs its own look in a browser (it shows only after an opt-in or a license); the harness has no Freemius.
- Open question (for the owner): where the new notice's link lands. Today it opens the guide at its first open step, which on a site set up by URL alone is Connect your n8n, not the first review. If the link or its label changes, EXPERIENCE.md L81 changes in the same pull request.
- Assumption: criterion 1 takes decision 8a literally for a URL with no failed check, the "not checked yet" variant included (the reproduction's own state), although the n8n Workflow page then offers "Test the connection" first. The owner can narrow it at the pull request.
- Open question (for the owner): a URL whose last check failed (state 5, "Needs attention"), where the n8n Workflow page names a fix instead. Does the notice still name the first review there?
- Open question (for the owner): the Dashboard would then carry two messages that both point to the first review. Whether the welcome notice shows there at all in that state is decided at the pull request.
- Records at done: `docs/user-paths.md` L32's CV line says what the notice offers before and after a URL is saved.
- hitl (owner): approve the notice's new wording in the pull request.
- Handoff: bug 125 edits the same class's rendering in other places (L831–L2112); both ship in 4.0.1.
