---
id: 19
type: bug
title: "A pro-site save without the cron/overrides sections wipes them"
parent: none
covers: []
after: []
assignee: ""
refined: true
hitl: false
risk: medium
severity: P2
---

# A pro-site save without the cron/overrides sections wipes them

## Description

Verified 2026-10-02 (epic retro, edge-case 8; re-checked at source `admin/class-pqm-admin.php:290-302+`). The pro branch of `sanitize_settings()` recomputes the plan-gated sections from `$input`: a save whose POST carries no cron/overrides keys flips `cron_enabled` to false (`!empty(absent)`) and rebuilds `agent_prompts` to `[]` — stored per-agent overrides silently wiped. The free branch carries stored values in this situation; the pro branch does not.

## Reproduction

1. On a pro site, open Settings in tab A as an Editor whose view rendered while the site was free (sections locked, nothing POSTs), or craft an options.php POST with only `license_key`.
2. Save.
3. Observe: `cron_enabled` false, `agent_prompts` empty — overrides gone.

## Cause hypothesis

Story 1.5's carry rule was written section-wise for the *free* branch (sections locked → absent → carry); the pro branch assumed the sections always POST, which a stale form or direct POST violates — exactly the 1.3 × 1.5 boundary (a key entered between render and save flips the branch).

## Acceptance Criteria

1. **Given** a pro-site save whose POST lacks the cron and `agent_prompts` sections entirely, **when** it sanitizes, **then** every key of those sections carries the stored value byte-identically (sanitize-matrix case added).
2. **Given** a pro-site save that posts the sections, **when** it sanitizes, **then** today's behavior (including the per-knob stored fallback) is unchanged.
3. Other valid outcome: none expected — the free branch already encodes the correct rule for absent sections.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (e8, action A11)
