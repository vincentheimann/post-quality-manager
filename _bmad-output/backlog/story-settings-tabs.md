---
id: 27
type: story
title: "The Settings page is split into tabs"
parent: none
covers: [O5]
after: [16, 19, 26]
assignee: ""
refined: false
hitl: false
risk: medium
---

# The Settings page is split into tabs

## Description

Owner request (2026-10-06). Settings is one long page: License, n8n Connection, AI Model, AI Prompts & Context, Agent-Specific Overrides, Automated Reviews and Monthly Quota. This story groups those sections into tabs so each tab shows one concern. The rules on which sections each role sees and which the plan locks stay as they are, and saving one tab never changes settings on another.

## Acceptance Criteria

Verify: each section appears under exactly one tab; a role sees only the tabs whose sections it may open; a plan-locked section still shows locked, not hidden. Saving on any tab leaves every setting on the other tabs byte-identical (sanitize-matrix cases for each tab). A link to a section anchor opens the tab that holds it, including the Dashboard's scheduled-reviews nudge link to `#pqm-cron-settings`.

## References

- code — post-quality-manager/admin/class-pqm-admin.php, `render_settings()`, `sanitize_settings()` (the per-section carry rule)
- bug — _bmad-output/backlog/bug-pro-sanitize-wipes-on-stale-post.md (a save that omits a section wipes it on a pro site)
- access — _bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/access-matrix.md
- decision — docs/adr/0005-wordpress-components-for-app-screens.md
- design — _bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md

## Notes

- Decision (2026-10-06, per ADR 0005): the page stays on the PHP Settings API with WordPress's native `nav-tab` markup. Tabs are switched by JavaScript over the one existing form, so every save still posts every section and the stored-value carry rule is untouched; with JavaScript off the page shows all sections. The native markup carries no ARIA, so the build adds tab roles and arrow-key navigation, and keeps the active tab in the URL.
- `after: [16, 19, 26]` (validation and owner order, 2026-10-06): the per-tab sanitize-matrix cases land on the carry rule bug 19 fixes; story 16 changes which role may open the agents sections, which decides the tabs each role sees; story 26 lands first so this story moves the final AI Prompts & Context section (order 26 → 27 → 25).
- Decision (2026-10-06): four tabs — Connection (License, n8n Connection), AI (AI Model, AI Prompts & Context, Agent-Specific Overrides), Automation (Automated Reviews), Usage & Limits (Monthly Quota; story 29 adds usage and the token limit here).
- Assumption: no design is drawn for the tabbed page; the builder follows WordPress's native tab look, and story 28 reviews the result.
