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

Retro action A8, owner default decision 2026-10-02: unknown commands fail open to the free tier in the feature registry, so a future paid agent added to the REST enum without a registry entry would run for free users. (Updated 2026-10-06: the AJAX and GET paths that bypassed the enum were deleted in epic-ui-rebuild story 5.7; the REST trigger route is the only trigger path left.) Enforce the command whitelist at the single chokepoint (`trigger_review_callback()`), give `score` and `apply`/`apply_*` explicit **free**-tier registry entries (they operate on already-stored output; quota keeps counting them per the story-4 decision), and add a registry ⊇ enum harness assertion.

## Acceptance Criteria

Verify: a command with no registry entry is refused 400 at the chokepoint; `score`/`apply*` behave exactly as today; the harness fails if the enum and registry drift apart.

## Notes

- Stale since 2026-10-05: the "land before story 5.5" sequencing; 5.5 shipped on per-post REST refusals.
- Story 11 builds its shared command→label map on this story's whitelist, so it lands after this one.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (adversarial a3/a4, edge-case 2, answered A8)
