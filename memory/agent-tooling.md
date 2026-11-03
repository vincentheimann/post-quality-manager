---
name: agent-tooling
description: Third-party agent tooling in this workspace — minimal-code ladder native in BMad, Caveman declined, Headroom machine-local only; decided 2026-10-05 (ADR 0007)
metadata:
  type: project
---

Decided 2026-10-05 in [ADR 0007](../docs/adr/0007-minimal-code-ladder-native-in-bmad.md) after evaluating the Ponytail, Caveman and Headroom plugins. No agent plugin is enabled for this project (there is no `.claude/settings.json`).

- **Minimal-code ladder** (adapted from Ponytail, MIT): `_bmad/custom/minimal-code-ladder.md`. Loaded into every `bmad-build` run through `persistent_facts` in `_bmad/custom/bmad-build.toml`; its review section drives the `overbuild` lens appended to the thorough lenses of `bmad-build` (full route) and `bmad-code-review` (`_bmad/custom/bmad-code-review.toml`). Edit the ladder file, never the lens text, to change the rule. `AGENTS.md`, `lessons.md` and `docs/brand.md` win over it.
- **Caveman:** not installed. Do not add it or its proxy without superseding ADR 0007.
- **Headroom:** dropped for this workspace on 2026-11-03 (ADR 0007 amended). Claude Code defers tool schemas natively since 2026-10-22, which removed ~95 % of the proxy's savings (~1 % left for a 100 s cold start and 1.4 GB RSS). No `ANTHROPIC_BASE_URL` override in any settings file here, committed or local; the package may stay installed on the owner's machine for other projects. Previous posture (2026-10-05): optional, machine-local only, `HEADROOM_BEACON=off`, output shaper off, `headroom learn` only to the gitignored `CLAUDE.local.md`.
- **Open measurement:** compare token use and diff size of two or three full-route builds before and after the ladder; supersede ADR 0007 if the `overbuild` lens never earns its cost.
  - Baseline (pre-ladder, full route): stories 5.2 (PR #22), 5.3 (#23), 5.4 (#24). Diff size from the merge commits, excluding `_bmad-output/`, `memory/`, `docs/` and `package-lock.json`: 5.2 10 files +451/−151, 5.3 10 files +926/−129, 5.4 14 files +1866/−41. Token use was not recorded — note it for the after-builds and compare diff size only against this baseline.
