---
id: 94
type: bug
title: "After updating from a build before story 91, the first plugin page an administrator opens says they are not allowed"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
---

# After updating from a build before story 91, the first plugin page an administrator opens says they are not allowed

## Description

On a site updated from a build that still used the `pqm_` stored names (3.9.0 and every earlier build), the first admin request after the update runs the one-time stored-names upgrade (story 91). When that first request is the plugin's Dashboard, Post Reviews or n8n Workflow page, WordPress answers "Sorry, you are not allowed to access this page." to an administrator. Reloading opens the page; Settings opens at once. It happens once per site, and it is the first thing an existing site sees of the release that renames the stored names.

## Reproduction

Walked 2026-10-28 on the showcase site (WordPress 7.1.3, PHP 8.5, a 3.9.0-era Free build with `pqm_` options, capabilities and the `wp_pqm_reviews` table):
1. Install the Free build of main ff517c5 over it (`bin/local-wp.py … plugin install <zip> --force`, the plugin stays active; no admin page loaded in between).
2. As the administrator, open `admin.php?page=postquality-workflow` as the first admin request.
3. Reload it.

What happens: step 2 shows "Sorry, you are not allowed to access this page."; step 3 opens the n8n Workflow page. The same with `admin.php?page=postquality-dashboard` first. With `admin.php?page=postquality-settings` first, Settings opens at once. After step 2 the stored names are moved (`postquality_settings` present, `pqm_settings` gone) and the administrator holds the five `postquality_` capabilities.
What should happen: the first plugin page opens like any later one.

## Cause Hypothesis

`PostQuality_Activator::maybe_upgrade()` runs `migrate_stored_names()` on `init`, which renames the role capabilities from `pqm_` to `postquality_`. The current user's capabilities were already read for that request before `init`, so the menu pages registered with `postquality_view_reviews` or `postquality_manage_settings` find the user without them and WordPress refuses the page. Settings is spared because `PostQuality_Admin::settings_page_capability()` falls back to core `manage_options`. Where the user's capabilities are first read on that request is unverified.

## Acceptance Criteria

1. **The first plugin page after the update opens**
   **Given** a site whose stored names and capabilities still carry `pqm_`, updated to the current build without an admin page loaded in between
   **When** an administrator opens the Dashboard, Post Reviews or n8n Workflow page as the first admin request
   **Then** the page opens, and the stored names are moved as before
2. **Nothing changes for a site already upgraded**
   **Given** a site whose stored names already carry `postquality_`
   **When** any role opens any plugin page
   **Then** it gets the same answer as today
3. **Tests cover the condition found and fixed**
   **Given** the harness sweep
   **When** it runs
   **Then** a row fails on the first-request refusal and passes on the fix, and the stored-names rows pass unchanged
4. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the page opens on the first request, with the evidence recorded in Notes — this supersedes 1 to 3

## References

- parent — none
- found by — _bmad-output/backlog/story-n8n-workflow-page-lean-ux-rework-plan.md, Implementation Notes (agentic walk on a real n8n, 2026-10-28)
- source — _bmad-output/backlog/story-stored-names-move-to-the-postquality-prefix.md (story 91, the one-time upgrade)
- memory — memory/naming.md (stored names), memory/local-verification.md (the walk frame)

## Notes

- Who meets it: every site running a build before story 91 that updates to the first release carrying it: today the owner's own sites only, since no build was sold or listed.
- Assumption: the refusal also reaches a non-administrator role holding `pqm_view_reviews` before the update; not walked.
- Done (owner, 2026-10-28): merged in PR #204. Before the merge the agent walked it on the showcase site: the fixed Free build opened the n8n Workflow page and the Dashboard as the first request after the update, and main's build without the fix answered 403 there (plan Implementation Notes). The assumption above about a non-administrator role is covered by the harness only (stored-names Row 7, an Author's own grant).
