# 0012. Role rights are set in the plugin; "View as" is retired

- Status: accepted
- Date: 2026-10-08

## Context

ADR 0003 mapped WordPress roles to five plugin capabilities and left remapping to third-party role-editor tools; the freemium spec listed "an in-plugin role editor" as a non-goal. To let an Administrator check what a lower role gets, story 34 (2026-10-07) built "View as": a WordPress toolbar node that narrows the Administrator's own plugin capabilities to a chosen role's, with Exit in the toolbar because a Contributor cannot open any plugin page. On 2026-10-08 the owner reported that leaving the Administrator view left no way back and that the control lives outside the plugin. Reading the code did not reproduce the first point (Exit is gated on core `manage_options`, which the view never removes), but the second is by design and cannot be fixed without keeping a plugin page open for a user the view says may open none. Real alternatives: fix and keep View as in the toolbar; move it inside the plugin with a sticky banner and a Dashboard that stays open for Administrators in a view; replace it with a rights screen; or build both.

## Decision

Replace the simulation with the setting. A **Roles tab** on the plugin's Settings page lets an Administrator set which of the five plugin capabilities each role on the site holds (spec CAP-6, backlog story 42):

- **Labelled "Roles"**, WordPress's own word (the Users screen's Role column; role plugins name their screen Roles); "Rights" and "Access" are off-system, "Permissions" was the runner-up. Inside: "What each role can do", role display names as rows, plain-action column labels, no `pqm_` slug. The table is called the rights matrix internally.
- **Pro feature**, visible but locked on Free (presentation rule of 2026-10-01); a locked tab shows the roles as they are and never reverts them.
- **Every role on the site**, custom roles included. Rows of roles holding core `manage_options` are fixed with every capability; pinning is capability-keyed, never a role-name check.
- **The five existing capabilities only**; no per-page or per-tab capability until a site asks.
- **Gated on core `manage_options`**, not on a plugin capability, so the tab can never be delegated away or hidden from an Administrator.
- **The saved matrix is the source of truth**: it lives in the settings option, is applied to roles on save through the one Settings form, and is what an upgrade re-applies; the shipped default map is only the fallback when nothing is saved.
- **"View as" is removed** in the same story: class, toolbar nodes, handler, capability filter and harness. No "Preview as" replacement. Story 34 stays done as history; the spec non-goal "an in-plugin role editor" is retired.

## Consequences

- Easier: an Administrator sees and changes what each role gets in one table, on the plugin's own page; custom roles get first-class access; no second sign-in and no simulation code in the capability path.
- Harder: the plugin now writes the site's roles, so the pinned-row and carry rules (a save by someone who cannot see the tab keeps the matrix) must hold, and the harness must prove them; the upgrade step changes from "re-grant defaults" to "re-apply the saved matrix"; a Free site keeps whatever rights a lapsed Pro license set.
- Not done until a site reports a need: finer capabilities, per-user grants in the tab, a live preview of a role.
- Supersedes the "in-plugin role editor" non-goal of the freemium spec; ADR 0003's role map and gate are unchanged.
