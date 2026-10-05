# 0007. Adopt the minimal-code ladder natively in BMad; decline Caveman; Headroom stays machine-local

- Status: accepted
- Date: 2026-10-05

## Context

The owner asked to integrate three agent plugins into the BMad workspace (ADR 0004): Ponytail (minimal code: YAGNI → reuse → stdlib → platform → one line), Caveman (terse agent prose, plus an input-compression proxy) and Headroom (local proxy that compresses what the agent reads). Their outcomes were checked against the source code before deciding:

- **Ponytail plugin:** its `SubagentStart` hook injects the persona into *every* subagent, including `bmad-build`'s plan reviewer and review lenses and `bmad-code-review`'s reviewers, which biases them and shortens their output. The marketplace entry tracks the default branch, so hooks that run Node on every prompt would update unpinned.
- **Caveman:** its own benchmark on the session model gives 3% fewer output tokens than one "Answer concisely" line, while its rules add about 1,000 input tokens per call. Ponytail's agentic benchmark measured it at +7% tokens. Always-on terse chat also clashes with the owner's tone rules. Its proxy overlaps Headroom.
- **Headroom:** the only one BMad cannot reproduce (it works at the transport layer). As a project plugin its hooks call a local `headroom` binary, which breaks cloud sessions and any contributor without it. Its output shaper duplicates verbosity steering, `headroom learn` can write into `AGENTS.md` / `CLAUDE.md`, and its anonymous usage beacon is on by default.

Alternatives: install all three as plugins (the first plan), install Ponytail as a plugin with `PONYTAIL_SUBAGENT_MATCHER` scoping, or reach the same outcomes with BMad configuration.

## Decision

- **Ponytail's outcome, natively:** the ladder lives in `_bmad/custom/minimal-code-ladder.md` (adapted, MIT attribution), loaded as a `bmad-build` persistent fact so it lands in the plan, the coding subagent's only brief. An `overbuild` lens joins the thorough lens sets of `bmad-build` and `bmad-code-review`. No plugin, no hooks.
- **Caveman: declined.** Concise chat is a default, not a plugin.
- **Headroom: optional, on the owner's machine only**, never in project settings: `HEADROOM_BEACON=off`, output shaper off, `--code-memory none`, `headroom learn` only to the gitignored `CLAUDE.local.md`, with findings folded into `memory/lessons.md` by hand. Kept only if `headroom savings` shows a real gain.

## Consequences

- Easier: one rule file shared by build and review; reviewers stay neutral; nothing third-party runs in the repo or in cloud sessions; no versions to pin.
- Harder: the ladder no longer reaches work done outside `bmad-build` automatically (`CLAUDE.md` carries one line pointing to it), and upstream Ponytail improvements must be ported by hand.
- Every full-route build pays one more review subagent. The gain is unproven on this repo (Ponytail's numbers come from Haiku on a FastAPI project): compare two or three builds before and after, and supersede this ADR if the lens never earns its cost.
