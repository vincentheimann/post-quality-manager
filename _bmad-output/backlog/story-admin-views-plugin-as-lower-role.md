---
id: 34
type: story
title: "An Administrator views the plugin as a lower role and switches back"
parent: none
covers: [O6]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
---

# An Administrator views the plugin as a lower role and switches back

## Description

Owner request (2026-10-06). An Administrator holds every plugin capability, so they see every page, section and button. To check what an Editor, an Author or a Contributor actually sees — which menus exist, which buttons are missing, the quota note, the locked sections — they have to log in as such a user. This story gives Administrators a "View as" switch: the plugin then renders and answers exactly as it would for the chosen role, a bar says which view is on, and one action returns to the Administrator view.

## Acceptance Criteria

1. **Only Administrators get the switch**
   **Given** a signed-in user
   **When** they are in wp-admin
   **Then** a "View as" control listing the plugin's other roles (Editor, Author, Contributor) is shown to Administrators, and to nobody else
2. **The plugin reads as the chosen role**
   **Given** an Administrator who chose a role
   **When** they open any plugin page or the plugin's menu
   **Then** the menu entries, pages, Settings sections, action buttons, quota note and locked states are the ones a user of that role (with that role's current capabilities, remaps included) would get, and nothing the real role cannot see is shown
3. **Refusals match the role too**
   **Given** an Administrator viewing as a role
   **When** a plugin action that role may not run is requested, in the pages or through the plugin's REST routes
   **Then** it is refused with the same answer and copy that role gets
4. **The view is announced and reversible from anywhere in wp-admin**
   **Given** a view is on
   **When** the Administrator is on any wp-admin page, including a plugin page the chosen role cannot open
   **Then** a bar names the role being viewed and offers "Exit", and Exit restores the Administrator view at once
5. **The view sticks to one Administrator**
   **Given** a view is on
   **When** that Administrator opens other pages or signs in again, or another user signs in
   **Then** the view is still on for that Administrator until they exit, and no other user is affected
6. **WordPress outside the plugin is untouched**
   **Given** a view is on
   **When** the Administrator uses any non-plugin screen (Posts, Users, Plugins, …)
   **Then** their WordPress capabilities are exactly as without the view, and the toolbar differs only by the bar

## Boundaries

- Must not change: what any real Editor, Author or Contributor sees or may run; the gate, the quota accounting and the REST answers for every non-Administrator; the cron; the stored settings, capabilities and role map of the site.
- Out of scope: WordPress core capabilities. Insert into Post also checks the core right to edit the post, so an Administrator in the Author view still sees Insert on every post where a real Author sees it on their own posts only.

## References

- requirement — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, Owner requests O6
- access — _bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/access-matrix.md, Capabilities and Features tables
- memory — memory/freemium-and-roles.md, Hidden vs locked and Author quota
- decision — docs/adr/0003-freemium-plan-and-role-based-access.md, Decision
- copy — docs/brand.md, Copy Rules

## Notes

- Assumption: the bar is a node in the WordPress toolbar (the top admin bar), which is visible on every wp-admin screen. It is the only place that stays reachable when the chosen role cannot open any plugin page (Contributor), so Exit can never be lost. The alternative, a notice at the top of the plugin's pages only, loses Exit in the Contributor view.
- Assumption: only the plugin's own capabilities are simulated — the five `pqm_` ones — for the Administrator who chose the view. No WordPress core capability changes, so the Administrator keeps editing posts and users as usual; nothing is written to the site's roles.
- Assumption: a simulated view is real, not read-only — an action the role may run (an Author's Review, an Editor's Settings save) runs with that role's rights. The one side effect: a trigger in the Author view spends the Administrator's own monthly quota, because the quota applies to anyone without the paid-agents capability. Alternative: a "look, don't touch" view where every write is refused with a note; more code, less faithful.
- Assumption: the choice persists per Administrator until Exit (survives page loads and new sign-ins), stored on the user, deleted on uninstall. The alternative, a per-page-load query argument, is lost on every click.
- Assumption: the switch exists on the free plan as well. ADR 0003 lists "role management" among paid features; this switch manages no role, it only lets the Administrator check the site's current mapping.
- Decision (owner, 2026-10-06): the control lists the three default roles only — Editor, Author, Contributor — never custom roles.
- Decision (owner, 2026-10-06): no plan simulation; the view never changes the site's Free/Pro state.
- Decision (owner, 2026-10-06): the switch is for Administrators only, not for every holder of the settings capability.
- Decision (owner, 2026-10-06): recorded as owner request O6 in the initiative, following the O5 convention, so the story ranks on its own.
- Risk medium (reason): the view changes how capability checks resolve for one user. A mistake is visible at once and a revert is clean, but it is auth-adjacent code; the build adds a harness (none covers capability filtering today) proving the view can only remove capabilities, never add one, and that Exit works from the Contributor view.
