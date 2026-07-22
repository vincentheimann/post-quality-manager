---
name: workspace-layout
description: Post Quality workspace structure — two sibling git repos (post-quality-manager, post-quality-api) and where things live after the 2026-07-22 reorg
metadata:
  type: project
---

`c:\Git\Post Quality\` holds two independent git repos (the root itself is not a repo):
- `post-quality-manager\` — main WP plugin + n8n workflow exports in `n8n\` (v2 is current). Entry docs: CLAUDE.md → AGENTS.md + WORKFLOW.md. Living memory: `memory\`, `tasks\lessons.md`, and `tasks\todo.md`. Skills in `.claude\skills\` (fix-bug, new-feature, release).
- `post-quality-api\` — single-file plugin `post_quality_api.php`, canonical source (a stale copy in the manager repo was removed 2026-07-22).

`_archives\turbo-drill\` inside the manager repo contains unrelated leftover files from a finished PSPO quiz-app project (Turbo Drill), including its old CHANGELOG — ignore them.

Reorg of 2026-07-22 was staged with `git mv`/`git rm` but left uncommitted per [[agent-conventions]] (never commit without asking).
