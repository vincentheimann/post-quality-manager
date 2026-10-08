---
id: 42
type: story
title: "An Administrator sets each role's plugin rights on a Settings tab, and View as is retired"
parent: none
covers: [CAP-6, O6]
after: []
assignee: ""
refined: true
hitl: false
risk: medium
---

# An Administrator sets each role's plugin rights on a Settings tab, and View as is retired

## Description

Owner pivot (2026-10-08, ADR 0012). The toolbar "View as" switch (story 34) was reported as leaving the Administrator unable to return and as living outside the plugin. Instead of simulating a role, the plugin lets an Administrator set what each role gets: a Roles tab on the Settings page shows one row per role on the site and one column per plugin capability, and Save applies the matrix to the site's roles, so every existing gate follows at once. The matrix is kept across upgrades. "View as" is removed in the same change.

## Acceptance Criteria

1. **Only Administrators see the tab**
   **Given** the Settings page
   **When** a user holding core `manage_options` opens it
   **Then** a Roles tab exists last, after the four story-27 tabs; a user who opens Settings through `pqm_manage_agents` or `pqm_manage_settings` without `manage_options` sees no Roles tab and its panel is not in the page
2. **The matrix lists the site's roles and the five capabilities**
   **Given** the Roles tab on a Pro site
   **When** it renders
   **Then** under the headline "What each role can do" there is one row per role the site has (display name, custom roles included) and one checkbox per plugin capability under the labels View reviews, Run Review and Key Takeaways, Run paid agents, Manage agents, Manage settings — no `pqm_` slug shown — each checked when the role holds that capability now; rows of roles holding `manage_options` show every capability checked and disabled
3. **Save applies the matrix to the roles**
   **Given** the Roles tab on a Pro site with changed checkboxes
   **When** the Administrator saves the Settings form
   **Then** each listed role holds exactly its checked capabilities, roles holding `manage_options` hold all five regardless of what was posted, direct per-user grants are untouched, and a user of a changed role sees the new menu, sections, buttons and REST refusals on their next request without signing in again
   **And** the page returns on the Roles tab with the success notice (story 27 behavior)
4. **Nobody else's save touches the roles**
   **Given** roles set on the tab
   **When** a user who cannot see the Roles tab saves the Settings form, or the settings option is written programmatically (setup dialog)
   **Then** every role's capabilities are unchanged
5. **Free sites see it locked**
   **Given** the Roles tab on a free site
   **When** it renders and the form is saved
   **Then** the matrix shows the roles' current grants, locked with the plan upgrade copy; Save writes no role; when a license lapses, the roles stay as they were set
6. **Activation and upgrades keep the roles as set**
   **Given** a role stripped of a capability on the tab
   **When** the plugin is deactivated and reactivated, or its upgrade step runs on init with a stored capability version behind `CAPS_VERSION`
   **Then** that role still lacks the capability; a capability's shipped defaults are granted only when no role holding `manage_options` has it yet (a fresh install, or a capability new in this version)
7. **The tab explains two consequences**
   **Given** the Roles tab
   **When** a role loses "View reviews"
   **Then** an inline hint beside that row says the role will no longer see the plugin menu, with no confirmation dialog
   **And** the "Run paid agents" column carries a note that a role holding it is not subject to the monthly quota
8. **View as is gone**
   **Given** an Administrator who had a view on before the update
   **When** they use wp-admin after it
   **Then** no "View as" or "Viewing as" node is in the toolbar, every plugin capability resolves as the user's own, the `admin-post` switch action answers as an unknown action, and the stored choice has no effect
   **And** uninstall still deletes the stored `pqm_view_as` user meta
9. **Uninstall leaves nothing**
   **Given** roles set on the tab
   **When** the plugin is uninstalled
   **Then** every plugin capability is stripped from every role and user (as today); the tab stores nothing else
10. **The harness proves the invariants**
    **Given** the plugin's PHP harnesses
    **When** they run
    **Then** one covers that a role holding `manage_options` can never lose a plugin capability through a save, that a save changes exactly the roles it should and no per-user grant, and that activation and upgrade leave an already-registered capability's roles untouched; the View-as harness is removed

## Boundaries

- Must not change: the feature gate's role-then-plan precedence and refusal copy; the quota rule (a user lacking paid agents is a quota subject); any REST route's permission callback; the story-27 tab mechanics (one form, one save, hash survives a save, no React on this page); the hidden-versus-locked presentation rule; what deactivation keeps (capabilities stay).
- Out of scope: per-user grants, WordPress core capabilities, custom capabilities beyond the five, a per-page or per-tab capability, any "preview as a role" control, multisite.

## References

- requirement — _bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/spec-freemium-and-roles.md, CAP-6
- access — _bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/access-matrix.md, Roles tab
- requirement — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, Owner requests O6
- decision — docs/adr/0012-role-rights-set-in-the-plugin-view-as-retired.md
- predecessor — _bmad-output/backlog/story-admin-views-plugin-as-lower-role.md (story 34, what this story removes)
- tabs — _bmad-output/backlog/story-settings-tabs.md (story 27, the form and tab rules this tab joins)
- memory — memory/freemium-and-roles.md, Capabilities and View as
- copy — docs/brand.md, Copy Rules

## Notes

- Decision (owner, 2026-10-08): Pro feature, visible but locked on Free, following the presentation rule of 2026-10-01; a locked tab shows the roles as they are and never reverts them, following the free-site cron precedent.
- Decision (owner, 2026-10-08): every role on the site, custom ones included; rows pinned by holding `manage_options`, never by role name, so the plugin keeps its rule of never branching on role names.
- Decision (owner, 2026-10-08): the five existing capabilities only. Finer visibility (per page, per Settings tab) is a later story if asked.
- Decision (owner, 2026-10-08): the tab is gated on core `manage_options`, not a plugin capability, so an Administrator can never lose the tab through the tab.
- Decision (owner, 2026-10-08, plan checkpoint; supersedes the earlier "matrix in the settings option" decision): the site's roles are the only store. Save writes them directly; nothing is stored in the settings option and no carry rule is needed. `register_capabilities()` grants a capability's defaults only when no role holding `manage_options` has it yet, so activation (which also re-granted today) and upgrade never reset a set role. Ceiling: a role editor stripping a capability from every Administrator role makes the next activation re-grant its defaults.
- Decision (owner, 2026-10-08): View as is retired in this story, not a separate ticket; no "Preview as" replacement. The Exit problem reported on 2026-10-08 was not reproduced from the code (Exit is gated on `manage_options`, which the view never removes); it needs no fix once the feature is gone.
- Decision (owner, 2026-10-08): the tab is labelled **Roles**, WordPress's own word (Users screen Role column; role plugins name their screen Roles) — never "Rights" or "Access"; "Permissions" was the runner-up. It sits last, after Usage & Limits. Inside: headline "What each role can do", rows carry the role display names, columns are plain actions — View reviews · Run Review and Key Takeaways · Run paid agents · Manage agents · Manage settings — and `pqm_` slugs never appear. "Rights matrix" stays the internal name in docs and code.
- Assumption: a role deleted from the site disappears from the tab; a role added later appears with the capabilities it holds now, none if it is new. A Save writes only the cells changed on the form, so a role created or edited elsewhere after the page loaded is left alone (review loop 1, 2026-10-08).
- Release notes (story 15 pack): name the removal of View as and that a stored view choice is ignored.
- Risk medium (reason): the save writes the site's roles, which every gate reads. A mistake is visible at once and a role editor can repair it, but the pinned rows, the Pro-and-Administrator-only write and the changed-cells-only rule are the only things standing between a mis-post and an Administrator or Editor losing access; the harness in criterion 10 covers them.
