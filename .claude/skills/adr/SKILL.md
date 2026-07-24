---
name: adr
description: Record a significant decision as a lightweight ADR in docs/adr/ — use when a decision is hard to reverse, shapes product direction, changes a schema or external contract, or picked between real alternatives (or when the user says "record this decision" / "write an ADR").
---

# ADR Checklist

1. Check the significance bar in `docs/adr/README.md` — routine implementation choices don't get an ADR
2. Confirm with the user that the decision (and its chosen alternative) is final before recording it
3. Create `docs/adr/NNNN-short-slug.md` — next free number, format from `docs/adr/README.md`, one screen max: Context (constraints + real alternatives), Decision, Consequences (including what got harder)
4. Add the ADR to the index list in `docs/adr/README.md`
5. Update the related `memory/` fact file to link the ADR (`memory/` says what is true now; the ADR says why) — add a new fact file + `memory/MEMORY.md` index line if none covers it
6. Reference the ADR from the active task in `tasks/todo.md`
7. Never edit an accepted ADR's decision later — supersede it with a new ADR and cross-link both statuses
