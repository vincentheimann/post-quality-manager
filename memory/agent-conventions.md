---
name: agent-conventions
description: How agents must work in this workspace — KISS, plan-first, lessons log, never commit to main or without asking
metadata:
  type: feedback
---

The project owner runs a disciplined agentic setup defined in `AGENTS.md` and `WORKFLOW.md`.

**Why:** Agent guidelines are first-class project artifacts and must be followed, including the self-improvement loop.

**How to apply:**
- KISS principle everywhere; proven WordPress patterns only; prefix everything `PQM_`/`pqm_`.
- Plan non-trivial work in `tasks\todo.md` first and validate with the user; log every user correction in `tasks\lessons.md`; review that file at session start.
- Never commit to `main` directly; feature branches `feat/`/`fix/`/`chore/`; Conventional Commits; git commits/pushes and file deletions/renames require asking first.
- AI prompts in the n8n workflow must stay niche-agnostic (universal pillars: Structure, Readability, Engagement, Credibility) — see [[workspace-layout]].
- Read `docs\brand.md` before writing any admin UI copy.
- Project memory lives in-repo under `memory\` (canonical); any agent-local memory store is a redundant mirror — when a durable fact changes, update the repo copy first.
- Three knowledge stores, no duplication (since 2026-07-24, see AGENTS.md "Documentation & Decision Practices" and `docs\adr\0001`): ADRs in `docs\adr\` = why we decided (draft via `/adr`, supersede never rewrite); `memory\` = what is true now (links its ADRs); skills in `.claude\skills\` = recurring how (capture a workflow once it's happened twice). `/prioritize` runs EBM grooming against [[user-paths]].
