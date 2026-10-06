---
id: 12
type: story
title: "The untested enforcement paths get harness coverage"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# The untested enforcement paths get harness coverage

## Description

Retro action A6 (verification-gap lens, pre-verified findings 1–3 and 5). Add to the CLI harness suite: the REST permission callbacks (`check_can_trigger` true for either run cap and false capless; `check_view_reviews` false for `edit_posts`-only), `PQM_Activator::maybe_upgrade()` (absent option grants + stores; equal version is a no-op byte-identically; lower version re-grants), a free-save `agent_prompts` smuggle case in the sanitize matrix, the REST `quota_remaining` passthrough pin (the AJAX path it was written for was deleted in epic-ui-rebuild story 5.7), and a recording `add_filter` stub that replaces the regex-over-source check for `option_page_capability_pqm_settings_group`.

## Acceptance Criteria

Verify: each named path is executed (not grepped) by at least one green harness check, and mutating any of them (typo'd cap, inverted version comparison, posted-else-stored agent_prompts refactor) makes the suite fail.

## Notes

- Decision (validation, 2026-10-06): retro A3 (extract the shared WP-stub bootstrap) is no longer folded in. About ten backlog tickets extend that bootstrap; folding it in would make this story their unlisted shared-setup owner. A3 stays a retro action until it gets its own ticket.
- Scope OUT (sequencing vs epic-ui-rebuild): no `render_posts`/`render_dashboard` captures — story 5.7 deletes that PHP rendering; coverage for the rebuilt pages belongs to the epic's stories.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (verification-gap 1–3, 5; duplication map 1)
