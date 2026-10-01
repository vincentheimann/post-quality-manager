---
name: prioritize
description: EBM-based product grooming for the Post Quality Manager — rank what to build next from the user paths' Unrealized Value and the BMad ticket tree. Use when the user asks "what next", wants to groom the backlog, pick the next epic or ticket, or re-assess priorities after a release.
---

# Prioritize Checklist (EBM)

1. Read `docs/user-paths.md` (the ranked Unrealized Value table + per-stage CV/UV) and the ticket tree: `_bmad-output/initiative-post-quality-manager/` (initiative Requirements UV1–UV7 / O1–O4, epic envelopes in `tickets.toml`, parked items in Notes) and `uv run _bmad/method/scripts/tickets.py --project-root . status _bmad-output/backlog`; skim `memory/MEMORY.md` for constraints and `docs/adr/` for decisions already made
2. Ask the user for any new evidence since last time (real usage observations, signals, feedback) — evidence beats the existing ranking; note contradictions
3. Map each epic and backlog ticket to the journey stage and UV item it converts to Current Value (its `covers`); a candidate serving no stage is a scope-creep flag, not a priority
4. Propose a shortlist (max 3) ranked by UV size × effort, each with: the stage it serves, the value it converts, and the signal that would prove it worked — recommend one
5. Validate the pick with the user before planning anything
6. Update the artifacts to match reality: move realized items UV → CV in `docs/user-paths.md`, reorder the epics in the initiative's `tickets.toml` (table order is build order); drop a ticket with `uv run _bmad/method/scripts/tickets.py --project-root . mark _bmad-output/backlog <id> dropped` (a Notes line alone leaves it listed as ready) and record *why* as a `Parked:`/`Decision:` line in the initiative Notes; record any direction change via `/adr`
7. Hand off: an epic → `bmad-spec` (when the WHAT is not yet fixed) then `bmad-ticket` to incept it; a backlog ticket → `bmad-build`
