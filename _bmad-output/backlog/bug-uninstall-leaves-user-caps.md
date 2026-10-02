---
id: 20
type: bug
title: "Uninstall leaves per-user pqm_* capability grants behind"
parent: none
covers: []
after: []
assignee: ""
refined: true
hitl: false
risk: low
severity: P3
---

# Uninstall leaves per-user pqm_* capability grants behind

## Description

From the epic retro (edge-case 6). `PQM_Features::remove_capabilities()` walks role objects only; a `pqm_*` capability granted directly to a user (`WP_User::add_cap`, a remap path the quota docblock names as supported) survives `uninstall.php` in `wp_usermeta`. A later reinstall (or reuse of a cap name) silently re-activates those grants.

## Reproduction

1. `wp user add-cap <author-id> pqm_run_paid_agents` (or any role-editor plugin's per-user grant).
2. Uninstall the plugin.
3. Inspect `wp_usermeta` `wp_capabilities` for the user: the `pqm_*` key remains.

## Cause hypothesis

`remove_capabilities()` was written for the role map (its own registry); per-user grants live in user meta and need a separate sweep, which uninstall never got.

## Acceptance Criteria

1. **Given** users holding direct `pqm_*` grants, **when** the plugin is uninstalled, **then** no `pqm_*` key remains in any user's capabilities (harness case with a user-grant stub).
2. **Given** a user with non-pqm direct grants, **when** uninstalled, **then** those grants survive untouched.
3. Other valid outcome: declaring per-user grants unsupported — rejected by the existing quota docblock, which names them as the supported quota-lift path.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (e6, action A11)
