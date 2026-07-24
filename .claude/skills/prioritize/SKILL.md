---
name: prioritize
description: EBM-based product grooming for the Post Quality Manager — rank what to build next from the user paths' Unrealized Value and the enhancements backlog. Use when the user asks "what next", wants to groom the backlog, plan an iteration, or re-assess priorities after a release.
---

# Prioritize Checklist (EBM)

1. Read `docs/user-paths.md` (the ranked Unrealized Value table + per-stage CV/UV) and `tasks/enhancements.md`; skim `memory/MEMORY.md` for constraints and `docs/adr/` for decisions already made
2. Ask the user for any new evidence since last time (real usage observations, signals, feedback) — evidence beats the existing ranking; note contradictions
3. Map each backlog candidate to the journey stage and UV item it converts to Current Value; a candidate serving no stage is a scope-creep flag, not a priority
4. Propose a shortlist (max 3) ranked by UV size × effort, each with: the stage it serves, the value it converts, and the signal that would prove it worked — recommend one
5. Validate the pick with the user before planning anything
6. Update the artifacts to match reality: move realized items UV → CV in `docs/user-paths.md`, re-groom `tasks/enhancements.md` (grooming note at the top), record any direction change via `/adr`
7. Hand off to `/new-feature` (or `/fix-bug`) for the selected item
