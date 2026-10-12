# Post Quality Manager — Claude Code entry point

@AGENTS.md

## Session start ritual (living memory)

Before any work, always:
1. Read `memory/MEMORY.md` — index of durable project facts; open the linked files that are relevant to the task.
2. Read `memory/lessons.md` — self-correction log; its rules override your defaults.
3. Find the active work in the BMad ticket tree: `uv run _bmad/method/scripts/tickets.py --project-root . status _bmad-output/backlog` (standalone tickets — where all ready work lives until an epic is incepted) and `… next` (the active initiative's stories; empty while its epics are still envelopes). Resume an `in-progress` plan rather than starting fresh; ask the `bmad` skill when unsure what comes next.

After any user correction, append the pattern to `memory/lessons.md` (format is documented in the file). When a durable project fact changes (structure, conventions, decisions), update `memory/` in the same change — it is the canonical memory store and travels with the repo; agent-local memory is only a mirror of it.

## How work flows (BMad, ADR 0004)

- A small, clear change → `bmad-build` directly. It clarifies, plans, implements, reviews; the house overrides in `_bmad/custom/bmad-build.toml` route anything beyond a small change to a plan the user approves first.
- A change bigger than one session → `bmad-spec` to fix the WHAT, `bmad-ticket` to incept the epic into stories, one `bmad-build` per story, `bmad-retrospective` when the epic is done.
- Work where the screens and flows matter → `bmad-ux` first; `bmad-spec` adopts its `DESIGN.md` / `EXPERIENCE.md` as companions. A mid-epic change that reaches across the spec and its stories → `bmad-correct-course`, then apply its list with `bmad-ticket` (both added 2026-10-12, ADR 0004 amendment).
- `/prioritize` (EBM, `docs/user-paths.md`) decides which epic or backlog ticket is next; `/adr` records significant decisions; `/release` verifies a release.
- Ask for a review only with `bmad-review` / `bmad-code-review`; they never run uninvited.
- Code written outside `bmad-build` follows the same minimal-code ladder: `_bmad/custom/minimal-code-ladder.md` (ADR 0007; it loads automatically inside builds).
