---
name: agent-conventions
description: How agents must work in this workspace — BMad Method delivery, KISS, lessons log, never commit without asking
metadata:
  type: feedback
---

The project owner runs a disciplined agentic setup defined in `AGENTS.md` and delivered with the BMad Method since 2026-10-01 (ADR 0004, see [[bmad-setup]]).

**Why:** Agent guidelines are first-class project artifacts and must be followed, including the self-improvement loop.

**How to apply:**
- KISS principle everywhere; proven WordPress patterns only; prefix everything `PQM_`/`pqm_`.
- Work flows through BMad: `bmad-build` for a change (its "full" route's plan checkpoint is where the user approves work before code — `_bmad/custom/bmad-build.toml` sends anything beyond a small change there), `bmad-spec` + `bmad-ticket` for work bigger than a session, `bmad-retrospective` per epic. The old `tasks/todo.md` plan file no longer exists.
- Log every user correction in `memory\lessons.md`; review that file at session start (it is also loaded into every build as a persistent fact).
- Never commit to `main` directly; feature branches `feat/`/`fix/`/`chore/`; Conventional Commits; git commits/pushes and file deletions/renames require asking first.
- AI prompts in the n8n workflow must stay niche-agnostic (universal pillars: Structure, Readability, Engagement, Credibility) — see [[workspace-layout]].
- Read `docs\brand.md` before writing any admin UI copy.
- Project memory lives in-repo under `memory\` (canonical); any agent-local memory store is a redundant mirror — when a durable fact changes, update the repo copy first.
- Knowledge stores, no duplication (AGENTS.md, `docs\adr\0001`, `0004`): ADRs in `docs\adr\` = why we decided (draft via `/adr`, supersede never rewrite); `memory\` = what is true now (links its ADRs); `_bmad-output\` = what we are doing (tickets, specs, plans); skills in `.claude\skills\` = recurring how. `/prioritize` runs EBM grooming against [[user-paths]] and picks the next epic or ticket.
