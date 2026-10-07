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
- **Niche tailoring** via blog-profile presets (marketing / technical / lifestyle / custom) that are the setting — a chosen profile is resolved from code when a command runs, Custom opens the text field (story 26, 2026-10-07); the Editorial Standards presets follow the same pattern.
- **Distribution**: GitHub Releases; WordPress.org deferred.
- Guardrails: MVP-first, KISS, lean UX (progressive disclosure, presets over blank fields). The original plan lived in the retired `tasks/todo.md` (git history before 2026-10-01); the niche-hardcoding lesson is in `memory/lessons.md`. Related: [[workspace-layout]], [[agent-conventions]].
