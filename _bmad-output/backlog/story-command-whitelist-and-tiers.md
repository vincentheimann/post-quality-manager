---
id: 14
type: story
title: "Only whitelisted commands reach the webhook; score/apply have explicit tiers"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: medium
---

# Only whitelisted commands reach the webhook; score/apply have explicit tiers

## Description

Retro action A8, owner default decision 2026-10-02: unknown commands currently fail open to the free tier and the AJAX/GET paths bypass the REST enum, so any `sanitize_key` string a free-cap user posts is forwarded to n8n — and a future paid agent without a registry entry would leak. Enforce the command whitelist at the single chokepoint (`trigger_review_callback()`), give `score` and `apply`/`apply_*` explicit **free**-tier registry entries (they operate on already-stored output; quota keeps counting them per the story-4 decision), and add a registry ⊇ enum harness assertion.

## Acceptance Criteria

Verify: a non-whitelisted command is refused 400 at the chokepoint on every path (REST, AJAX, GET); `score`/`apply*` behave exactly as today; the harness fails if the enum and registry drift apart.

## Notes

- Sequencing: land before epic-ui-rebuild story 5.5 (bulk triggers) so bulk relies on hardened per-post refusals.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (adversarial a3/a4, edge-case 2, answered A8)
