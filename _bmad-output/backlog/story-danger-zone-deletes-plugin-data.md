---
id: 69
type: story
title: "A Danger Zone on Settings lets an Administrator delete the plugin's data without uninstalling"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: true
risk: high
---

# A Danger Zone on Settings lets an Administrator delete the plugin's data without uninstalling

## Description

Owner request (2026-10-15). Today the only ways to clear what the plugin stores are per-agent **Reset Defaults** (Settings → AI), **Forget key** (the n8n connection) and deleting the plugin, whose cleanup runs through Freemius's `after_uninstall` hook (`PQM_Activator::uninstall()`). There is no way to start over on a site that keeps the plugin: to drop every stored review, to reset the settings, or to wipe everything before handing a site over or re-running the setup from scratch. The uninstall path also has a known ceiling: it runs only through the SDK's hook, so `wp plugin uninstall` and a deactivation without an admin user leave the data behind (story 44 notes).

This story adds a **Danger Zone** at the end of the Settings page, following the practice of GitHub, Stripe and WordPress core's own irreversible screens: separate actions, each stating what it removes and how much, a typed confirmation for the irreversible ones, and an optional export first.

## What the plugin stores (inventory at filing, 2026-10-15)

- The reviews table `{prefix}pqm_reviews` (every stored review, takeaways, sources, SEO and audit results, rewrites).
- Options: `pqm_settings`, `pqm_db_version`, `pqm_workflow_version_seen`, `pqm_cron_nudge_dismissed`, `pqm_caps_version`, `pqm_setup_progress`, `pqm_n8n_api` (the n8n address and API key), `pqm_n8n_setup` (the setup map), `pqm_n8n_leftovers`.
- User meta: `pqm_monthly_usage` (per-user quota counters), `pqm_view_as` (retired, kept one release), and the per-author voice of story 37 (the build inventories its key).
- The `pqm_*` capabilities on roles, and the `pqm_cron_review_posts` cron event.
- The Application Password the setup created for n8n (Pro), named `Post Quality Manager (n8n) …` on the Administrator's profile.
- Outside the site: the credentials and workflow the setup created in n8n (ids in the setup map), and whatever the Administrator made by hand.
- Inside posts: the Takeaways and Sources blocks inserted into post content (story 23, marker `metadata.pqmInsert`). They are the user's content, never deleted by the plugin.

## Acceptance Criteria

1. **Three separate actions, in one Danger Zone**
   **Given** an Administrator (`manage_options`, never a `pqm_*` capability alone) on the Settings page
   **When** they open the last section, "Danger Zone", visually set apart (WordPress's `notice-error` border, not a red button wall)
   **Then** they find three actions, each with its own description and button:
   - **Delete all reviews**: empties the reviews table and the per-user quota counters; settings, connection and setup stay.
   - **Reset settings**: `pqm_settings` back to defaults, the per-author voices cleared, the setup marked not complete; reviews and the n8n connection stay.
   - **Delete all plugin data**: everything the uninstall hook removes (the table, every option, user meta, capabilities, cron), plus the setup's Application Password revoked, with the plugin left active and the site as after a fresh activation.
2. **A summary before the act**
   **Given** any of the three actions
   **When** the button is pressed
   **Then** a `@wordpress/components` `Modal` (not `window.confirm()`, lessons 2026-10-05) lists exactly what will be removed, with counts (reviews, users with counters, the n8n objects by name), and what stays.
3. **Typed confirmation for the irreversible ones**
   **Given** **Delete all reviews** or **Delete all plugin data**
   **When** the Modal is open
   **Then** the confirming button stays disabled until the Administrator types the site's host (as GitHub asks for the repository name); **Reset settings** needs the Modal's confirm only.
4. **n8n is never touched silently**
   **Given** **Delete all plugin data** on a site whose setup the plugin created (a setup map exists)
   **When** the Modal is shown
   **Then** it offers an unticked checkbox "Also remove the workflow and credentials the plugin created in n8n" that runs entry 6.5's rollback (deactivate, delete the workflow, then the credentials) on the ids in the setup map only; a hand-made setup is never offered for deletion (CAP-4: nothing manual is ever auto-deleted). The n8n API key is forgotten in both cases.
5. **Export first**
   **Given** reviews exist
   **When** the Danger Zone is shown
   **Then** a **Download reviews (JSON)** link sits above the delete actions, serving the table's rows for the site through the existing `admin-post.php` download pattern (`download_workflow()`), so nothing is lost by mistake.
6. **Safe to run**
   **Given** each action
   **When** it runs
   **Then** it is a REST route under `post-quality/v1/` with `permission_callback` on `manage_options`, a nonce, and one transaction per table change; a running review (an in-flight n8n callback) that lands after **Delete all reviews** is stored normally (the table exists), never errors; the action's outcome is reported in the Modal with counts; the page then reloads to its fresh state.
7. **Recorded**
   **Given** each action
   **When** it completes
   **Then** one line goes to the site's debug log only when `WP_DEBUG_LOG` is on (never a secret), and `docs/release-notes/unreleased.md` gets a line (a new screen existing sites meet).
8. **The owner's pass**
   **Given** the Danger Zone on the environment the owner names, with a provisioned setup and a hand-made one
   **When** the owner runs each action
   **Then** the site is as described after each, the hand-made n8n objects are untouched, and the plugin stays active and usable.

## Boundaries

- Must not change: `PQM_Activator::uninstall()`'s list (the Danger Zone reuses it: one function, two callers); the Freemius licence state (Freemius owns its own data; a licence survives **Delete all plugin data**); post content.
- Out of scope: a scheduled or automatic purge; deleting single reviews (Post Reviews owns that if ever); the `wp plugin uninstall` ceiling itself (a separate fix: an `uninstall.php` fallback, to be decided with ADR 0014's Freemius constraints); multisite.
- Never: a destructive action behind a plain `confirm()`; a GET route that deletes; deleting anything in n8n the plugin did not create.

## References

- data — `post-quality-manager/includes/class-pqm-activator.php` `uninstall()`; `class-pqm-db.php`; `class-pqm-n8n.php` `roll_back()` (entry 6.5), `SETUP_OPTION`, `LEFTOVERS_OPTION`
- precedent — Settings tabs (story 27/28), the Roles tab (story 42: Settings-API form, `sanitize_settings()` rebuilds the array); `download_workflow()` for the export link; the setup wizard's Modal (story 36)
- rules — `docs/brand.md` Copy Rules (errors gently, actionable; no accusatory framing); `memory/lessons.md` 2026-10-05 (Modal over `window.confirm()`), 2026-07-23 (confirmation only for immediate side effects)
- known ceiling — story 44 notes: no cleanup after `wp plugin uninstall` or a deactivation without an admin user (SDK hook guards)
- spec — `_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md` (CAP-4: nothing manual is ever deleted)

## Notes

- Filed 2026-10-15 at the owner's request. Unrefined; ranking is `/prioritize`'s job. Risk high: it deletes data by design, so the owner's pass (AC8) and the harness rows for every action are the gate.
- Design choice to confirm at refinement: the Danger Zone as the last panel of the Settings page (one place for site-wide administration) versus a tab of its own. Recommended: the last panel of the Connection tab's successor, visible to `manage_options` only, so it never appears for editors who reach Settings through a `pqm_*` capability.
- Possible later split: AC5 (export) as its own small story if the first slice should ship sooner.
