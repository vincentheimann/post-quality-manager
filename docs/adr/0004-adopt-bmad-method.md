# 0004. Adopt the BMad Method as the delivery process

- Status: accepted
- Date: 2026-10-01

## Context

Since 2026-07 the repo ran a hand-rolled agentic process: `AGENTS.md` + `WORKFLOW.md` for rules, `tasks/todo.md` as the active plan, `tasks/enhancements.md` as the backlog, `tasks/lessons.md` as the self-correction log, project skills `/fix-bug` and `/new-feature` as the delivery checklists. It worked for one developer but had no notion of epics, no plan file per change, no built-in review, and the backlog was prose. On 2026-09-22 the owner queued a conversion to their own scaffold, [create-agentic-workspace](https://github.com/vincentheimann/create-agentic-workspace) (Scrum loop: `/kickoff`, `/sprint-planning`, `/retrospective`, `scrum/PRODUCT-BACKLOG.md`), ranked HIGH. On 2026-10-01 the owner asked instead for the [BMad Method](https://github.com/bmad-code-org/BMAD-METHOD) (v6.13, skills-based: `bmad-build` / `bmad-spec` / `bmad-ticket` / `bmad-review` / `bmad-retrospective`, optional PRD–architecture–UX agents). Alternatives weighed: keep the hand-rolled process; create-agentic-workspace as planned; BMad full method with the PM/Architect/UX agent layer; BMad lightweight. BMad's own brownfield guidance: keep a maintained `AGENTS.md`, archive greenfield documents, let `bmad-build` discover conventions from code.

## Decision

Adopt BMad **lightweight** — core tools plus build, spec, ticket, code-review, retrospective, project-context and customize — and retire the hand-rolled process; the create-agentic-workspace item is closed, not deferred.

- Install per project with `npx skills add bmad-code-org/BMAD-METHOD --copy` (Claude Code only, no symlinks on Windows); `_bmad/` runtime, `_bmad-output/` artifacts, git is the ticket store.
- One initiative for the whole product (`initiative-post-quality-manager`), its requirements are the UV table of `docs/user-paths.md` plus owner requests from ADRs; backlog themes became epic envelopes, small items standalone tickets. `active_initiative` is committed in `_bmad/custom/config.toml`.
- House rules are BMad overrides, not prose: `_bmad/custom/bmad-build.toml` carries the Definition of Done, the lessons log and the brand/agnostic rules as persistent facts, and routes anything beyond a small change (>100 lines, >3 files, schema / REST / template) to the "full" route whose plan checkpoint replaces the old "verify plan with user" step.
- `WORKFLOW.md` merged into `AGENTS.md` (BMad's block shape: policy, where things are, running and verifying, conventions, pitfalls); `tasks/lessons.md` became `memory/lessons.md` and keeps its "rules override defaults" contract; `tasks/todo.md` and `tasks/enhancements.md` deleted after migration (git history keeps them). `/fix-bug` and `/new-feature` retired in favour of `bmad-build`; `/adr`, `/prioritize`, `/release` stay and point at the ticket tree. ADRs, `memory/` and `docs/user-paths.md` unchanged in role.
- No PRD or architecture layer: a KISS WordPress plugin with one owner does not need it; add `bmad-prd` / `bmad-architecture` only if an epic forces a decision the existing patterns do not cover.

## Consequences

- Every non-trivial change leaves a plan file beside its ticket with a baseline revision and a dated review block; retrospectives have evidence to read.
- Updates come from upstream (`bmad setup` → `npx skills update`); the ~130 skill files under `.claude/skills/bmad-*` and `_bmad/scripts` are vendored and must not be hand-edited — customizations go in `_bmad/custom/`.
- `uv` becomes a prerequisite for the agent (not for the plugin); `__pycache__` is ignored.
- The Scrum vocabulary of the owner's other workspaces (`/standup`, sprints) does not exist here; cross-workspace tooling built on create-agentic-workspace will not apply to this repo.
- First dogfood: the freemium/roles epic (ADR 0003) goes through `/prioritize`, then `bmad-spec`, then `bmad-ticket` inception. Running `bmad-project-context adopt` on `AGENTS.md` is the recommended next refinement; the file was merged by hand and is not yet under the skill's managed block.
- Extends ADR 0001 (ADRs stay the "why" store); supersedes nothing.
