---
type: epic
title: "Releases and the workflow template are built and checked by CI"
parent: initiative-post-quality-manager
covers: [UV7]
after: []
assignee: ""
risk: low
---

# Releases and the workflow template are built and checked by CI

<!-- Envelope. Closes three technical-debt items migrated from tasks/enhancements.md. -->

## Description

The `/release` skill and the README describe a release-please GitHub Action, but the repo has no `.github/workflows/`. Add it: release-please on Conventional Commits plus a zip artifact of `post-quality-manager/` (including `templates/`) attached to the GitHub Release. Beside it, a template regression check that validates `templates/n8n-workflow.json` — valid JSON, every expected `__PQM_*__` placeholder present, zero site-specific strings, every Code node's `jsCode` syntax-checked (a local prototype verified template v2 on 2026-07-23). Also verify `load_plugin_textdomain()` is wired so the `post-quality-manager` text domain loads translations.

## Outcome

Cutting a release is a merge, and a broken template never ships; the signal is a tagged GitHub Release carrying a CI-built zip.

## Done when

1. Merging to `main` opens or updates a release-please PR; merging that PR creates the tag, the GitHub Release and the zip.
2. The template check fails CI on an invalid placeholder set, a site-specific string or a `jsCode` syntax error.
3. Translations load (`load_plugin_textdomain()` verified with a test `.mo`).

## Boundaries

GitHub Actions and the template validator. Not WordPress.org packaging (parked, UV7 proper).

## References

- parent — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, Requirement UV7
- skill — .claude/skills/release/SKILL.md, the manual steps CI is meant to back
- template — post-quality-manager/templates/n8n-workflow.json

## Notes

- The 2026-07-23 local prototype of the template check was not committed; recreate it in the story.
