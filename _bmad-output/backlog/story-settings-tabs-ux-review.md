---
id: 28
type: story
title: "The tabbed Settings page passes a full UX and behavior review"
parent: none
covers: [O5]
after: [27, 30]
assignee: ""
refined: false
hitl: true
risk: low
---

# The tabbed Settings page passes a full UX and behavior review

## Description

Owner request (2026-10-06). Once Settings is split into tabs (story 27), the whole page is reviewed against the UX standard set for the Dashboard and Post Reviews. The review covers tab order and naming, labels and help text, locked and hidden states per role and plan, save feedback, unsaved-change handling when switching tabs, keyboard use and ARIA, and behavior on a translated site. Findings become fixes in this story, or follow-up tickets when a fix outgrows it. The person step: the owner approves the findings and walks the result on the real site.

## Acceptance Criteria

Verify: a findings document approved by the owner exists, and each finding is fixed, filed as a backlog ticket, or declined with a reason. The owner's walk on the real site passes as Admin and Editor, on a free site and a pro site, and on a translated site.

## References

- design — _bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md
- story — _bmad-output/backlog/story-ux-review-dashboard-and-post-reviews.md (same review shape, applied to Dashboard and Post Reviews)
- copy — docs/brand.md
- access — _bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/access-matrix.md

## Notes

- Decision (owner, 2026-10-06): the review runs on Claude Fable 5.1.
- Story 26 reviews the AI Prompts & Context section and lands before story 27 (owner, 2026-10-06), so this review only checks that section's tab placement.
- Decision (owner, 2026-10-08, `/prioritize` D4): also waits on story 30, so the tabs are reviewed once — after the AI Model and Usage tabs exist.
