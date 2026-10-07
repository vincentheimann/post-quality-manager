---
name: delivery-pipeline
description: Nothing has ever been released (no tag, no GitHub Release); epic-delivery-pipeline incepted 2026-10-07 (8 stories, CAP-1…CAP-7) to cut 3.0.0 via release-please; WordPress.org listing is backlog story 38
metadata:
  type: project
---

As of 2026-10-07 the plugin has never been released: no git tag, no GitHub Release, no CHANGELOG; `3.0.0` is declared in the plugin header, `PQM_VERSION` and `package.json`. CI (`.github/workflows/ci.yml`) lints and builds JS, lints PHP on 8.3 and runs the harness sweep, but nothing releases.

`epic-delivery-pipeline` (initiative epic 4) was specced and incepted 2026-10-07 — spec `_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/` (CAP-1…CAP-7 + `brownfield.md`), 8 stories in its `tickets.toml`. Decisions that bind every story (owner, 2026-10-07):

- release-please on Conventional Commits; the first release is **3.0.0**; it bumps the header `Version`, `PQM_VERSION` and `package.json`, never `PQM_WORKFLOW_VERSION`.
- `GITHUB_TOKEN` only, so release-please PRs trigger no CI: release-please creates the GitHub Release as a **draft**; the release workflow runs the version check, builds and attaches `post-quality-manager-<version>.zip` (root folder `post-quality-manager/`), appends `docs/release-notes/<version>.md` when present, then publishes (tag `v<version>`).
- Release notes are a committed file (backlog story 15 writes `3.0.0.md`, and gates the 3.0.0 cut, story 4.8).
- Plugin Check runs in CI: report first (4.4), then fixed and blocking (4.5).
- No empty `languages/` folder ships.

WordPress.org distribution is backlog story 38 (after story 10 identity and story 15; also needs this epic). Its review rules — readme.txt with External services disclosure, GPL and human-readable code, no trademarks in the name, Plugin Check clean — are in that ticket.

Related: [[user-paths]], [[freemium-and-roles]], [[bmad-setup]].
