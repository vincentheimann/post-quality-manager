---
id: 26
type: story
title: "AI Prompts & Context is reviewed for UX and behavior, and the Blog Profile / Brand Voice / Website Context overlap is resolved"
parent: none
covers: [O5]
after: [6]
assignee: ""
refined: false
hitl: true
risk: low
---

# AI Prompts & Context is reviewed for UX and behavior, and the Blog Profile / Brand Voice / Website Context overlap is resolved

## Description

Owner request (2026-10-06). The section has three controls that feel redundant: a Blog Profile radio set, Brand Voice & Tone (its own presets plus a textarea), and Website Context. A profile pre-fills both other fields, a voice preset rewrites the voice textarea, and editing a field switches the profile back to Custom. So an owner who changes one option cannot easily tell what changed elsewhere, or what the AI will actually receive. This story reviews the section's UX and behavior, proposes a clearer model, and builds the version the owner approves. The person step: the owner approves the findings and the target design before the build.

## Acceptance Criteria

Verify: a findings document approved by the owner covers every control in the section, how the controls depend on each other, and the text each one sends to the agents. After the build, an owner can tell from the page alone which text the AI receives. Changing one option never silently overwrites text the owner typed. Stored `blog_profile`, `brand_voice_preset`, `brand_voice` and `website_context` values survive the upgrade with no change to what the agents receive.

## References

- code — post-quality-manager/admin/class-pqm-admin.php, `blog_profiles()` and the AI Prompts & Context markup; `sanitize_settings()`
- code — post-quality-manager/admin/js/pqm-admin.js (profile and voice preset pre-fill)
- code — post-quality-manager/templates/n8n-workflow.json (`website_context` and `brand_voice` in the agent system prompts)
- design — _bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md (the UX standard other pages follow, backlog story 7)
- copy — docs/brand.md

## Notes

- Decision (owner, 2026-10-06): the review runs on Claude Fable 5.1.
- `after: [6]` (2026-10-06): story 6's criterion 5 walks this section's presets on a real site; its findings are input to this review.
- Open question: whether Blog Profile stays as a starting-point shortcut, merges into Brand Voice, or is removed.
- Decision (owner, 2026-10-06): the Settings stories build in the order 26 → 27 → 25, so this story lands before the tabs and story 27 moves the final version of the section.
