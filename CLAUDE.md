# Post Quality Manager — Claude Code entry point

@AGENTS.md
@WORKFLOW.md

## Session start ritual (living memory)

Before any work, always:
1. Read `memory/MEMORY.md` — index of durable project facts; open the linked files that are relevant to the task.
2. Read `tasks/lessons.md` — self-correction log; its rules override your defaults.
3. Read `tasks/todo.md` — the active task plan; continue it rather than starting fresh.

After any user correction, append the pattern to `tasks/lessons.md` (format is documented in the file). Keep `tasks/todo.md` updated as you plan and complete work. When a durable project fact changes (structure, conventions, decisions), update `memory/` — it is the canonical memory store and travels with the repo; agent-local memory is only a mirror of it.

## Repository layout (beyond AGENTS.md)

- `post-quality-manager/templates/n8n-workflow.json` — the canonical n8n workflow, kept as a distributable template with `__PQM_*__` placeholders; the settings page serves it with site values injected ("Download n8n workflow").
- `n8n/workflow-diagram.md` — Mermaid overview of the webhook → n8n → Claude → WordPress flow.
- `.claude/skills/` — project skills: `/fix-bug`, `/new-feature`, `/release`. Use them for those recurring tasks.
- `_archives/turbo-drill/` — leftovers from an unrelated quiz-app project (old CHANGELOG included). Ignore entirely.
- The former companion "Post Quality API" plugin was retired 2026-07-23 (GitHub repo archived); this plugin's `post-quality/v1/post-score` endpoint is the canonical one. Do not re-create the standalone plugin.

## n8n workflow notes

- The n8n workflow calls the Claude API directly from Code/HTTP nodes; AI prompts must stay niche-agnostic (universal pillars: Structure, Readability, Engagement, Credibility) — see `tasks/lessons.md`.
- When plugin REST routes change, check whether `post-quality-manager/templates/n8n-workflow.json` needs a matching update, and vice versa. Never put site-specific values in the template — use `__PQM_*__` placeholders resolved in `PQM_Admin::download_workflow()`.
