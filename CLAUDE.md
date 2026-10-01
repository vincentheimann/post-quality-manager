# Post Quality Manager — Claude Code entry point

@AGENTS.md

## Session start ritual (living memory)

Before any work, always:
1. Read `memory/MEMORY.md` — index of durable project facts; open the linked files that are relevant to the task.
2. Read `memory/lessons.md` — self-correction log; its rules override your defaults.
3. Find the active work in the BMad ticket tree: `uv run _bmad/method/scripts/tickets.py --project-root . next` (ready / in-progress tickets of the active initiative) and `status _bmad-output/backlog` for standalone tickets. Resume an `in-progress` plan rather than starting fresh; ask the `bmad` skill when unsure what comes next.

After any user correction, append the pattern to `memory/lessons.md` (format is documented in the file). When a durable project fact changes (structure, conventions, decisions), update `memory/` in the same change — it is the canonical memory store and travels with the repo; agent-local memory is only a mirror of it.

## How work flows (BMad, ADR 0004)

- A small, clear change → `bmad-build` directly. It clarifies, plans, implements, reviews; the house overrides in `_bmad/custom/bmad-build.toml` route anything beyond a small change to a plan the user approves first.
- A change bigger than one session → `bmad-spec` to fix the WHAT, `bmad-ticket` to incept the epic into stories, one `bmad-build` per story, `bmad-retrospective` when the epic is done.
- `/prioritize` (EBM, `docs/user-paths.md`) decides which epic or backlog ticket is next; `/adr` records significant decisions; `/release` verifies a release.
- Ask for a review only with `bmad-review` / `bmad-code-review`; they never run uninvited.

## Repository layout (beyond AGENTS.md)

- `post-quality-manager/templates/n8n-workflow.json` — the canonical n8n workflow, kept as a distributable template with `__PQM_*__` placeholders; the n8n Workflow page serves it with site values injected ("Download n8n workflow").
- `n8n/workflow-diagram.md` — Mermaid overview of the webhook → n8n → Claude → WordPress flow.
- `_bmad-output/` — initiative, epics, backlog tickets and build plans (git is the ticket store). `_bmad/` — BMad runtime; `_bmad/custom/` holds the committed team overrides.
- `docs/adr/` — Architecture Decision Records; `docs/user-paths.md` — the EBM prioritization tool. See "Where things are" in AGENTS.md.
- `_archives/turbo-drill/` — leftovers from an unrelated quiz-app project (old CHANGELOG included). Ignore entirely.
- The former companion "Post Quality API" plugin was retired 2026-07-23 (GitHub repo archived); this plugin's `post-quality/v1/post-score` endpoint is the canonical one. Do not re-create the standalone plugin.

## n8n workflow notes

- The n8n workflow calls the Claude API directly from Code/HTTP nodes; AI prompts must stay niche-agnostic (universal pillars: Structure, Readability, Engagement, Credibility) — see `memory/lessons.md`.
- When plugin REST routes change, check whether `post-quality-manager/templates/n8n-workflow.json` needs a matching update, and vice versa. Never put site-specific values in the template — use `__PQM_*__` placeholders resolved in `PQM_Admin::download_workflow()`.
