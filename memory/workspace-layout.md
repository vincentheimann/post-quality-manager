---
name: workspace-layout
description: Post Quality workspace structure — two git repos: post-quality-manager (the plugin, since the API plugin retirement 2026-07-23) and the postquality.com site clone (2026-10-22, story 85)
metadata:
  type: project
---

`c:\Git\Post Quality\` holds two git repos (the root itself is not a repo):
- `post-quality-manager\` — main WP plugin; the canonical n8n workflow is the plugin template `post-quality-manager\templates\n8n-workflow.json` (placeholders `__PQM_*__`, served pre-filled by the settings download); `n8n\` holds only the Mermaid diagram. Entry docs: CLAUDE.md → AGENTS.md (WORKFLOW.md merged into it 2026-10-01). Living memory: `memory\` incl. `memory\lessons.md`. Work tracking: the BMad ticket tree in `_bmad-output\` (the `tasks\` folder was removed 2026-10-01, ADR 0004, see [[bmad-setup]]). Skills in `.claude\skills\`: BMad skills + `/adr`, `/prioritize`, `/release`.
- `postquality.com\` — clone of the public site repo `vincentheimann/postquality.com` (Jekyll 3.10 on GitHub Pages with the site's own layouts and one stylesheet, no theme: `theme: null`, story 86 build 1, from the merge of its site PR; served at https://postquality.com); policy pages copied from the plugin's `docs/policies/`, local build per its README (gems in its ignored `vendor\`). Cloned at the workspace root by owner decision 2026-10-22 (story 85); its branches and PRs are separate from the plugin's.
  - 2026-10-23, github-pages gem 232: with no `theme` key GitHub Pages falls back to `jekyll-theme-primer` (the gem's `Configuration::DEFAULTS`); `theme: null` in `_config.yml` switches the fallback off.

The former sibling repo `post-quality-api\` (single-file plugin `post_quality_api.php`) was retired 2026-07-23: local folder deleted, GitHub repo `vincentheimann/post-quality-api` archived by the user. The manager registers the same `post-quality/v1/post-score` endpoint and is the canonical source — do not re-create the standalone plugin.

Reorg of 2026-07-22 was committed on branch `chore/claude-workspace-reorg` (history preserved via `git mv`), together with a fix aligning the plugin header version to `PQM_VERSION` 3.0.0.
