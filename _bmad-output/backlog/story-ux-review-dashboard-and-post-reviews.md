---
id: 7
type: story
title: "Dashboard and Post Reviews set the UX/UI standard every other page follows"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# Dashboard and Post Reviews set the UX/UI standard every other page follows

## Description

A full UX review of the two core pages — the Dashboard and Post Reviews (journey stages B2 triage and A5 first review) — producing an agreed set of findings and redesign decisions; every other admin page (Settings, n8n Workflow) is then aligned to the UX/UI patterns those two pages establish, not reviewed separately.

## Acceptance Criteria

Verify: a reviewed UX findings-and-decisions document exists for Dashboard and Post Reviews, approved by the owner, and the redesign work it implies is sliced into tickets (likely its own epic via `bmad-spec` + `bmad-ticket`).

## References

- design — `_bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/` (DESIGN.md findings-and-decisions + mockup.html, owner-approved 2026-10-02)
- decision — docs/adr/0005-wordpress-components-for-app-screens.md (accepted)
- docs/user-paths.md — B2 (triage: "the dashboard reports, but doesn't prioritize"), A5–A6 (first review, habit formation)
- docs/brand.md — copy rules any redesign must keep
- code — post-quality-manager/admin/ (class-pqm-admin.php, js/pqm-admin.js, css)

## Notes

- Owner request (2026-10-01): "a huge UX review will be needed, especially for the Dashboard and Post Reviews pages (all the other pages will then be aligned with the UX and UI)."
- "Huge" says this is probably an epic wearing a story's coat: when `/prioritize` picks it, expect `bmad-spec` to distill it and `bmad-ticket` to incept it rather than one `bmad-build`.
- Adjacent backlog work to fold in or sequence: story 1 (manual pass of the 2026-07-24 lean-UX changes — its findings are direct input) and story 5 (CSS classes for JS markup — natural part of any redesign).
- Sequencing consideration: epic-freemium-and-roles adds locked buttons, quota counters and gated settings sections to these exact pages — reviewing after that epic lands avoids designing against a moving target.
