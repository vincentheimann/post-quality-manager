---
id: 73
type: bug
title: "The Application Password provisioning creates is never revoked and is bound to whoever ran the wizard"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P2
---

# The Application Password provisioning creates is never revoked and is bound to whoever ran the wizard

## Description

On the Pro build, provisioning creates a WordPress Application Password for the user who pressed the button and sends it to n8n inside the WordPress credential. Its identifier is kept only long enough to revoke it when the run fails; a successful run drops it. Nothing revokes the password afterwards: not Forget key, not a later provisioning, not uninstall. It carries the full capabilities of the user who ran the wizard, who may be an Editor if the Roles tab granted them the settings capability.

**Reproduction:** provision on a Pro site, then open the user's profile: the password "Post Quality Manager (n8n) <date>" is listed. Uninstall the plugin: the password is still listed. Provision again on a fresh map: a second password is added beside the first.

**Cause hypothesis:** the setup map stores the credential's n8n id but not the password's uuid and user, so no later action can find it.

## Acceptance Criteria

1. **The map keeps what it needs to revoke**
   **Given** a successful provisioning on the Pro build
   **When** the map is read
   **Then** it carries the password's uuid and the user it belongs to, and the n8n reply still carries neither
2. **Revoked where the setup ends**
   **Given** a map with a password
   **When** the setup is started over (story 71), replaced by a later provisioning, or the plugin is uninstalled
   **Then** the password is revoked and the user's profile no longer lists it
3. **The wizard says whose it is**
   **Given** the Set up the workflow step on the Pro build
   **When** it is shown
   **Then** it says n8n will edit posts as the current user through an Application Password made for it
4. **Harness and the other outcome**
   **Given** the n8n API harness
   **When** it runs
   **Then** rows assert the stored uuid, the revocation on each path and that the uuid is in no reply; if the investigation finds a revocation path already exists, the plan records it and the ticket closes without a change

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A3 (adversarial 7)
- roles — docs/adr/0012-role-rights-set-in-the-plugin-view-as-retired.md (who can hold the settings capability)
- uninstall — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-plugin-provisions-n8n-on-the-server-side-plan.md (what the activator deletes today)

## Notes

- Filed 2026-10-16 from the epic's retrospective. Severity P2: a standing credential with a user's full rights, invisible to the plugin.
- Decision needed at refinement: whether a later provisioning revokes the previous password or reuses it (the n8n credential is recreated either way).
