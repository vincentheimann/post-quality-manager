# 0001. Record significant decisions as lightweight ADRs

- Status: accepted
- Date: 2026-07-24

## Context

Decisions were captured in three overlapping places — `tasks/todo.md` task logs, `memory/` fact files, and commit messages — none of which preserves the *why* and the rejected alternatives once the task log rotates or the fact is updated in place. The project owner asked for ADR, skill, and living-memory practices to be explicit, first-class parts of the agentic workspace.

## Decision

Adopt lightweight ADRs in `docs/adr/` (format and triggers in [README.md](README.md)), with a clear division of labor: ADRs hold the immutable *why*, `memory/` holds the mutable *what is true now* (linking to ADRs), skills hold the recurring *how*. Agents draft an ADR via the `/adr` skill whenever a decision meets the significance bar; past decisions (agnostic pivot, LLM options architecture) stay in `memory/` and are backfilled as ADRs only if revisited.

## Consequences

- Future "why did we do it this way?" questions have a stable answer that survives memory updates.
- One more artifact to maintain — mitigated by the significance bar and the one-screen format.
- Superseding a decision now requires a new ADR rather than silently editing history.
