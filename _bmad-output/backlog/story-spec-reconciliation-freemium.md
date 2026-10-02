---
id: 17
type: story
title: "spec-freemium-and-roles says what was actually built"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# spec-freemium-and-roles says what was actually built

## Description

Retro action A2: the epic's accepted deviations live in memory/plans but not in the spec, so the next consumer of spec-freemium-and-roles reads a stale contract. Run a `bmad-spec` update folding in: CAP-1's recorded reinterpretations (`score`/`apply*` free fallback — superseded by backlog ticket 14's explicit tiers once built; `ping` outside the gate; cron uses the plan half only), CAP-2's stub era (ADR 0006), CAP-4's capability-keyed exemption + 0-unlimited + apply-consumes, CAP-3's `pqm_view_reviews`, `/save-review` WP-auth cap and Editor Settings-page access; update the access-matrix companion to match.

## Acceptance Criteria

Verify: the spec's self-validation passes and none of the retro's reconciliation items remains unrecorded in spec or companion; memlog carries each as a decision.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (spec-to-implementation reconciliation 1–5)
