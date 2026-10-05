---
name: agent-tooling
description: Third-party agent tooling in this workspace — minimal-code ladder native in BMad, Caveman declined, Headroom machine-local only; decided 2026-10-05 (ADR 0007)
metadata:
  type: project
---

Decided 2026-10-05 in [ADR 0007](../docs/adr/0007-minimal-code-ladder-native-in-bmad.md) after evaluating the Ponytail, Caveman and Headroom plugins. No agent plugin is enabled for this project (there is no `.claude/settings.json`).

- **Minimal-code ladder** (adapted from Ponytail, MIT): `_bmad/custom/minimal-code-ladder.md`. Loaded into every `bmad-build` run through `persistent_facts` in `_bmad/custom/bmad-build.toml`; its review section drives the `overbuild` lens appended to the thorough lenses of `bmad-build` (full route) and `bmad-code-review` (`_bmad/custom/bmad-code-review.toml`). Edit the ladder file, never the lens text, to change the rule. `AGENTS.md`, `lessons.md` and `docs/brand.md` win over it.
- **Caveman:** not installed. Do not add it or its proxy without superseding ADR 0007.
- **Headroom:** optional on the owner's machine only, never in project settings or cloud sessions. Settings if used: `HEADROOM_BEACON=off`, `HEADROOM_OUTPUT_SHAPER` unset, `headroom wrap claude --code-memory none`, install `headroom-ai[proxy,code]` (no `ml` extra unless prose-heavy traffic shows up). `headroom learn` writes only to `CLAUDE.local.md` (gitignored); accepted findings go into `lessons.md` in its format, never into `AGENTS.md` or `CLAUDE.md`.
- **Open measurement:** compare token use and diff size of two or three full-route builds before and after the ladder; supersede ADR 0007 if the `overbuild` lens never earns its cost.
