---
name: bmad-setup
description: BMad Method installation in this repo — lightweight skill set, layout, ticket tree, house overrides, how to update; adopted 2026-10-01 (ADR 0004)
metadata:
  type: project
---

BMad Method v6.13 (skills-based) is installed per project in `post-quality-manager\` since 2026-10-01 (ADR 0004); it replaced the hand-rolled `tasks/` process and closed the queued create-agentic-workspace conversion.

- **Skills** (`.claude\skills\bmad-*`, vendored copies via `npx skills add bmad-code-org/BMAD-METHOD -a claude-code --copy`, pinned in `skills-lock.json`): `bmad` (hub, setup, status), `bmad-build`, `bmad-spec`, `bmad-ticket`, `bmad-review`, `bmad-code-review`, `bmad-retrospective`, `bmad-project-context`, `bmad-customize`, plus the module records `bmod-core-tools`, `bmod-method`. Deliberately not installed: PRD / architecture / UX / agent-persona skills, `bmad-build-auto`, `bmad-walkthrough`.
- **Runtime** `_bmad\` (scripts, `config.toml`); **team overrides** `_bmad\custom\config.toml` (`active_initiative = "initiative-post-quality-manager"`), `_bmad\custom\bmad-build.toml` (persistent facts: `memory/lessons.md`, DoD, agnostic prompts, brand, never commit; `route_selection`: oneshot only ≤100 lines, ≤3 files, no schema/REST/template change), `_bmad\custom\ticketing-store-config.toml` (repo store). `*.user.toml` are personal and gitignored.
- **Ticket tree** `_bmad-output\`: `initiative-post-quality-manager\` (requirements UV1–UV7 from `docs/user-paths.md` + O1–O4 owner requests; epic envelopes `epic-freemium-and-roles`, `epic-ebm-telemetry`, `epic-yoast-context`, `epic-delivery-pipeline` in `tickets.toml`; parked items in Notes) and `backlog\` (standalone stories 1–5, story 1 is the hitl manual pass of the 2026-07-24 UX work). Read with `uv run _bmad/method/scripts/tickets.py --project-root . status|next|find`.
- **Update**: ask the `bmad` skill to run `bmad setup` (it runs `npx skills update -p -y` and re-checks); never hand-edit vendored skill files or `_bmad\scripts`.
- **Prerequisites**: `uv` (0.11+) and Node 22 are installed on the owner's machine.
- **Next refinements** (not done): `bmad-project-context adopt` to put `AGENTS.md` under the managed block; `/prioritize` → `bmad-spec` → `bmad-ticket` for the freemium/roles epic as first dogfood.
