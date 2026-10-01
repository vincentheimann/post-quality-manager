---
name: workspace-layout
description: Post Quality workspace structure — single git repo (post-quality-manager) since the API plugin retirement on 2026-07-23
metadata:
  type: project
---

`c:\Git\Post Quality\` holds one git repo (the root itself is not a repo):
- `post-quality-manager\` — main WP plugin; the canonical n8n workflow is the plugin template `post-quality-manager\templates\n8n-workflow.json` (placeholders `__PQM_*__`, served pre-filled by the settings download); `n8n\` holds only the Mermaid diagram. Entry docs: CLAUDE.md → AGENTS.md (WORKFLOW.md merged into it 2026-10-01). Living memory: `memory\` incl. `memory\lessons.md`. Work tracking: the BMad ticket tree in `_bmad-output\` (the `tasks\` folder was removed 2026-10-01, ADR 0004, see [[bmad-setup]]). Skills in `.claude\skills\`: BMad skills + `/adr`, `/prioritize`, `/release`.

The former sibling repo `post-quality-api\` (single-file plugin `post_quality_api.php`) was retired 2026-07-23: local folder deleted, GitHub repo `vincentheimann/post-quality-api` archived by the user. The manager registers the same `post-quality/v1/post-score` endpoint and is the canonical source — do not re-create the standalone plugin.

`_archives\turbo-drill\` inside the manager repo contains unrelated leftover files from a finished PSPO quiz-app project (Turbo Drill), including its old CHANGELOG — ignore them.

Reorg of 2026-07-22 was committed on branch `chore/claude-workspace-reorg` (history preserved via `git mv`), together with a fix aligning the plugin header version to `PQM_VERSION` 3.0.0.
