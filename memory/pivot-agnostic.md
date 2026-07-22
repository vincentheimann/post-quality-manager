---
name: pivot-agnostic
description: Strategic pivot (2026-07-22) — from blog-specific to a niche-agnostic plugin with an n8n workflow generator; MVP decisions locked
metadata:
  type: project
---

As of 2026-07-22 the project goal is a **niche-agnostic** WordPress plugin + n8n workflow generator, usable for any blog niche (canonical example: technical IT blog). Previously it was tailored to <owner-site>.

Locked MVP decisions (user-validated):
- **Generator** = config-injected export: bundled workflow template with `{{PLACEHOLDERS}}`, filled from plugin settings, downloaded from the admin. No wizard builder.
- **WP admin is the primary trigger** (dashboard + WP-Cron); chat entry stays possible only as a generic webhook. No kChat-specific nodes in core.
- **Niche tailoring** via blog-profile presets (marketing / technical / lifestyle / custom) that pre-fill editable fields — same lean pattern as the brand-voice presets.
- **Distribution**: GitHub Releases; WordPress.org deferred.
- Guardrails: MVP-first, KISS, lean UX (progressive disclosure, presets over blank fields). See plan in `tasks/todo.md` and the niche-hardcoding lesson in `tasks/lessons.md`. Related: [[workspace-layout]], [[agent-conventions]].
