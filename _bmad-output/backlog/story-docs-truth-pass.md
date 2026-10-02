---
id: 16
type: story
title: "README and the capability behavior tell the same truth"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# README and the capability behavior tell the same truth

## Description

Retro action A10, owner default decision 2026-10-02: align **code to the documented matrix**. Three items: (1) the Agent-Specific Overrides and Automated Reviews sections currently render editable and save for a `pqm_manage_settings`-only remapped user — gate their render and sanitize branch on `pqm_manage_agents` (carrying stored values otherwise), as the README matrix says; (2) replace README's wrong blanket claim ("pqm_* granted to Administrator/Editor/Author") with the real capability → default-roles table from `PQM_Features::capabilities()`; (3) state single-site support explicitly (the quota user meta is network-global against a per-site setting — multisite is unsupported until keyed per site).

## Acceptance Criteria

Verify: a `pqm_manage_settings`-only user can no longer edit the two agents sections (sanitize matrix case added); the README table matches `capabilities()` byte-for-byte; the single-site statement exists.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (a9, a10, a15; answered A10)
