# Project Memory Index

_Canonical, git-versioned memory for the Post Quality project. Agents: read this index at session start; one file per durable fact; update the relevant file (or add a new one + index line) when a durable fact changes. Keep any personal/local agent memory as a mirror of this._

- [Lessons log](lessons.md) — self-correction rules that override agent defaults; read every session, loaded into every build
- [BMad setup](bmad-setup.md) — lightweight BMad Method since 2026-10-01 (ADR 0004): skills, `_bmad/` overrides, ticket tree in `_bmad-output/`, how to update
- [Workspace layout](workspace-layout.md) — single repo since the API-plugin retirement 2026-07-23; n8n/ folder, turbo-drill archives
- [Agnostic pivot](pivot-agnostic.md) — niche-agnostic plugin + n8n workflow generator; MVP decisions locked 2026-07-22
- [LLM options architecture](llm-options-architecture.md) — model registry + payload-driven request params + workflow version handshake; decisions locked 2026-07-23
- [User paths](user-paths.md) — docs/user-paths.md is the EBM prioritization tool (CV/UV per journey stage) and the initiative's requirement source; read before product/UX decisions
- [Agent conventions](agent-conventions.md) — KISS, BMad delivery (bmad-build plan checkpoint), lessons loop, never commit without asking; knowledge stores: ADRs (why) / memory (what) / tickets (doing) / skills (how)
- [Admin UI rebuild](admin-ui-rebuild.md) — ADR 0005 accepted 2026-10-02: Dashboard + Post Reviews move to @wordpress/components; tracer built 2026-10-02 (wp-scripts build, `GET /posts` route, React Post Reviews shell), stories 5.2+ pending
- [Freemium & roles](freemium-and-roles.md) — free (Review+Takeaways) vs paid, five `pqm_` capabilities with the default role map + versioned upgrade, one feature gate with hidden (role) vs locked (plan) split; decided 2026-09-22 via ADR 0003, epic `epic-freemium-and-roles` in progress (stories 1.1 and 2 built 2026-10-01)
