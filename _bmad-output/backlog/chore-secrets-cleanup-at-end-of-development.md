---
id: 81
type: chore
title: "No secret handed to an agent outlives the plugin's development"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# No secret handed to an agent outlives the plugin's development

## Description

For the n8n-provisioning walks and the small-fixes builds, the owner handed agents credentials to their own instances: an SSH key and an Application Password for the production site, n8n API keys with a 30-day expiry, an Anthropic API key, plus the Application Passwords and n8n credentials every provisioning walk creates on the Local sandbox and the owner's n8n. They live outside the public repository, in a private workspace folder and on the instances. The owner's rule (2026-10-20): none of it is left living once the plugin's development ends. This chore is the checklist that makes it so, run by the owner with an agent at the end, and consulted by every walk that uses a secret so its record names what it created and where.

## Acceptance Criteria

1. **The private folder is gone**
   **Given** the workspace's private secrets folder (outside the repository)
   **When** the chore is run
   **Then** the folder and every file in it are deleted, and the git-ignored local settings allow rule for its SSH wrapper is removed
2. **Every agent credential is revoked at its source**
   **Given** the agent's SSH key in the production site's authorized keys, the agent's Application Password on the production site, every n8n API key created for agents and the Anthropic API key handed over
   **When** the chore is run
   **Then** each is revoked where it was created and the revocation is noted in the chore's Notes with the date
3. **Walk-made objects are removed**
   **Given** the workflows, credentials and Application Passwords the walks created on the sandbox and on the owner's n8n (named for the sandbox host or recorded by id in the walks' plans)
   **When** the chore is run
   **Then** they are removed by id, the production workflow and its credentials untouched
4. **The records stay generic**
   **Given** the repository
   **When** the chore is done
   **Then** no secret, host or account name entered a plan, ticket, memory or PR (story 66's rule); the walks' records name only what was created and that it is gone

## References

- rule — the owner's instruction of 2026-10-20 (agent memory `secrets-cleanup`); the production-site access rule of 2026-10-14 in `memory/lessons.md`
- sibling — _bmad-output/backlog/story-repository-records-name-no-owner-host.md (story 66: no owner host in the tree)
- walks — the plans under `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/` and `_bmad-output/backlog/bug-two-provisioning-runs-orphan-the-first-plan.md` (what each walk created)

## Notes

- Filed 2026-10-20 at the owner's request during the small-fixes walk (bugs 72/73/75/80). Owner-run (hitl): the revocations need the owner's accounts.
- Until then, every walk ends with its own cleanup (objects by id, the walk's n8n key revoked by the owner) and says so in its record.
