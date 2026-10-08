---
name: delivery-pipeline
description: Nothing released yet; epic-delivery-pipeline (CAP-1…CAP-7) cuts 3.0.0 via release-please; story 4.1 (2026-10-08) built release.yml + config (draft + force-tag, release-as 3.0.0 owed removal at entry 8); WordPress.org listing is backlog story 38
metadata:
  type: project
---

As of 2026-10-08 the plugin has never been released: no git tag, no GitHub Release, no CHANGELOG; `3.0.0` is declared in the plugin header, `PQM_VERSION` and `package.json`. CI (`.github/workflows/ci.yml`) lints and builds JS, lints PHP on 8.3 and runs the harness sweep; `release.yml` (story 4.1, below) is the release path.

`epic-delivery-pipeline` (initiative epic 4) was specced and incepted 2026-10-07 — spec `_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/` (CAP-1…CAP-7 + `brownfield.md`), 8 stories in its `tickets.toml`. Decisions that bind every story (owner, 2026-10-07):

- release-please on Conventional Commits; the first release is **3.0.0**; it bumps the header `Version`, `PQM_VERSION` and `package.json`, never `PQM_WORKFLOW_VERSION`.
- `GITHUB_TOKEN` only, so release-please PRs trigger no CI: release-please creates the GitHub Release as a **draft**; the release workflow runs the version check, builds and attaches `post-quality-manager-<version>.zip` (root folder `post-quality-manager/`), appends `docs/release-notes/<version>.md` when present, then publishes (tag `v<version>`).
- Release notes are a committed file (backlog story 15 writes `3.0.0.md`, and gates the 3.0.0 cut, story 4.8).
- Plugin Check runs in CI: report first (4.4), then fixed and blocking (4.5).
- No empty `languages/` folder ships.

Built by story 4.1 (tracer bullet, 2026-10-08):

- `.github/workflows/release.yml`: job `release-please` (push to `main` only, `googleapis/release-please-action@v5`) keeps one release PR open; job `package` runs when `release_created` is true or on a manual dispatch — `npm ci` + `npm run build` on the tag (or the dispatched ref), zips `post-quality-manager/` as `post-quality-manager-<version>.zip` (version from `package.json`) and always uploads it as a workflow artifact; on a release only it attaches the zip, appends `docs/release-notes/<version>.md` when present, and publishes the draft. A manual dispatch never tags or releases.
- `release-please-config.json`: `bootstrap-sha` `237b1b9` (the first CHANGELOG starts after it, owner 2026-10-08), `include-component-in-tag: false` (tag `v3.0.0`), `draft: true` + `force-tag-creation: true` (without the latter a draft has no tag and the next CHANGELOG would pull the whole history), package `.` with `release-type: node` (`package.json`, `package-lock.json`, `CHANGELOG.md`), `release-as: "3.0.0"` and a generic extra file for the plugin main file. `.release-please-manifest.json` is `{}` until the first release.
- Annotations in `post-quality-manager.php`: a `x-release-please-start-version` / `x-release-please-end` block around the header `Version` line (an inline marker would land in the header value WordPress reads), and an inline `// x-release-please-version` on `PQM_VERSION`. `PQM_WORKFLOW_VERSION` carries none.
- **Owed by entry 8 (the 3.0.0 cut):** remove `release-as` from the config once 3.0.0 is released, or every later release PR proposes 3.0.0 again. The release PR this story opens stays unmerged until then.
- The repo setting "Allow GitHub Actions to create and approve pull requests" must be on for release-please to open its PR.
- `ci.yml`'s `admin-build` artifact is for inspection only; `release.yml` builds the released commit itself.

WordPress.org distribution is backlog story 38 (after story 10 identity and story 15; also needs this epic). Its review rules — readme.txt with External services disclosure, GPL and human-readable code, no trademarks in the name, Plugin Check clean — are in that ticket.

Related: [[user-paths]], [[freemium-and-roles]], [[bmad-setup]].
