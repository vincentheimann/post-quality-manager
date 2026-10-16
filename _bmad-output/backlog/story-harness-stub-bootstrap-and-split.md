---
id: 78
type: story
title: "The CLI harnesses share one WordPress stub bootstrap, and the n8n harness is split by concern"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# The CLI harnesses share one WordPress stub bootstrap, and the n8n harness is split by concern

## Description

Every harness in the tests folder re-declares its own WordPress stubs, and they drift: the text sanitizer strips tags in one and casts in two others, the options store is one global in two harnesses and another in the third. The n8n API harness grew to one 1,660-line file over ten entries, with per-entry world builders inside it, where the older harnesses are one file per concern. The freemium retrospective asked for a shared stub bootstrap on 2026-10-02 and it never landed. This story extracts one bootstrap every harness loads, normalizes the drift, and splits the n8n harness into client, provisioning and rollback, diagnosis and adoption, and managed actions, with the world builders shared.

## Acceptance Criteria

1. **One bootstrap**
   **Given** the harness folder
   **When** any harness runs
   **Then** it loads the shared stub file for the WordPress functions and globals it needs, declares only stubs specific to it, and the sanitizer and options store behave the same in every harness
2. **The n8n harness split**
   **Given** the n8n API harness
   **When** the split is done
   **Then** four files cover what the one covered, each runnable alone, the world builders in a shared include, and the check count across the four equals the one file's count before
3. **Counts equal, CI runs them**
   **Given** every harness
   **When** the sweep runs locally and in CI
   **Then** the total check count is unchanged and the CI workflow runs each new file

## Boundaries

- Must not change: what any harness asserts; the plugin code.
- Does not add a test framework; the harnesses stay plain PHP (ADR 0004's house rule).

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A13 (pattern divergence 6, 7)
- previous retrospective — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md, Action items A3 (the same item, not landed)
- CI — .github/workflows (the harness sweep job)

## Notes

- Filed 2026-10-16 from the epic's retrospective; the second retrospective to ask for it. Natural slot: before the next harness-heavy epic, or as the first ticket a new harness lands with.
