---
id: 21
type: story
title: "Builds and code reviews apply the minimal-code ladder natively"
parent: none
covers: []
after: []
assignee: ""
refined: true
hitl: false
risk: low
---

# Builds and code reviews apply the minimal-code ladder natively

## Description

Owner request 2026-10-05: integrate Ponytail, Caveman and Headroom into the BMad workspace. Evaluation against their source code showed that BMad configuration reaches Ponytail's outcome without its conflicts, that Caveman adds almost nothing over a concise default, and that Headroom only fits as an optional machine-local proxy (ADR 0007). Deliver: the adapted ladder as one house file, loaded by `bmad-build` as a persistent fact and driving an `overbuild` lens in the thorough review sets of `bmad-build` and `bmad-code-review`; the Headroom guardrails recorded in memory; `CLAUDE.local.md` gitignored.

## Acceptance Criteria

Verify: `resolve_customization.py` lists `overbuild` in the thorough lenses of `bmad-build` and `bmad-code-review` and `file:_bmad/custom/minimal-code-ladder.md` in `bmad-build`'s persistent facts; the ladder file carries Ponytail's MIT attribution and the repo's never-simplify list (sanitize/escape, nonces, `permission_callback`, capability gates, Custom paths, harness checks); no `.claude/settings.json` or third-party skill is added; ADR 0007, `memory/agent-tooling.md` and the `MEMORY.md` index line exist.

## References

- decision — docs/adr/0007-minimal-code-ladder-native-in-bmad.md
