---
id: 27
type: story
title: "The Settings page is split into tabs"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: medium
---

# The Settings page is split into tabs

## Description

Owner request (2026-10-06). Settings is one long page: License, n8n Connection, AI Model, AI Prompts & Context, Agent-Specific Overrides, Automated Reviews and Monthly Quota. This story groups those sections into tabs so each tab shows one concern. The rules on which sections each role sees and which the plan locks stay as they are, and saving one tab never changes settings on another.

## Acceptance Criteria

Verify: each section appears under exactly one tab; a role sees only the tabs whose sections it may open; a plan-locked section still shows locked, not hidden. Saving on any tab leaves every setting on the other tabs byte-identical (sanitize-matrix cases for each tab). Deep links to `#pqm-cron-settings` and `#pqm-quota-settings`, used by the Dashboard and the notices, open the right tab.

## References

- code — post-quality-manager/admin/class-pqm-admin.php, `render_settings()`, `sanitize_settings()` (the per-section carry rule)
- bug — _bmad-output/backlog/bug-pro-sanitize-wipes-on-stale-post.md (a save that omits a section wipes it on a pro site)
- access — _bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/access-matrix.md
- decision — docs/adr/0005-wordpress-components-for-app-screens.md
- design — _bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md

## Notes

- Decision (2026-10-06, per ADR 0005): the page stays on the PHP Settings API with WordPress's native `nav-tab` markup. Tabs are switched by JavaScript over the one existing form, so every save still posts every section and the stored-value carry rule is untouched; with JavaScript off the page shows all sections. The native markup carries no ARIA, so the build adds tab roles and arrow-key navigation, and keeps the active tab in the URL.
- Bug 19 is not triggered by this design, since a save still posts every section; it stays open on its own.
- Decision (2026-10-06): four tabs — Connection (License, n8n Connection), AI (AI Model, AI Prompts & Context, Agent-Specific Overrides), Automation (Automated Reviews), Usage & Limits (Monthly Quota; story 29 adds usage and the token limit here).
- No design exists for the tabbed page. Without one, the builder will guess the layout; story 28 reviews the result.
