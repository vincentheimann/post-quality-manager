---
title: "Builds and code reviews apply the minimal-code ladder natively"
ticket: 21
status: done
---

# Plan — minimal-code ladder native in BMad

Approved by the owner in chat on 2026-10-05 ("go ahead with your recommendation").

## Changes

- `_bmad/custom/minimal-code-ladder.md` (new): ladder for building, never-simplify list, plan/comment rules, `overbuild` review recipe; MIT attribution to Ponytail.
- `_bmad/custom/bmad-build.toml`: `file:` persistent fact for the ladder; `overbuild` appended to `thorough_lenses`.
- `_bmad/custom/bmad-code-review.toml` (new): same `overbuild` lens appended to `thorough_lenses`.
- `docs/adr/0007-…md` + index line; `memory/agent-tooling.md` + `MEMORY.md` line; `memory/bmad-setup.md` override note.
- `CLAUDE.md`: one line pointing work outside `bmad-build` to the ladder (outside the `AGENTS.md` managed block; a later `bmad-project-context refresh` may fold it in).
- `.gitignore`: `CLAUDE.local.md`.

Skipped: installing any of the three plugins, a `.claude/settings.json`, an `overbuild` lens for `bmad-review` — add when a review on request is wanted for non-diff content.

## Verification

- `uv run _bmad/scripts/resolve_customization.py --skill .claude/skills/<skill> --project-root . --key workflow` for `bmad-build` and `bmad-code-review` shows the lens and the fact.
- No PHP or JS touched: `php -l`, `node --check` and the harnesses are unaffected.
