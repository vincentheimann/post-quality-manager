---
name: delivery-pipeline
description: 3.0.0 released 2026-10-08 via release-please (first release; cut ahead of entries 4.2–4.7 and story 15); epic-delivery-pipeline (CAP-1…CAP-7) continues into the next release; WordPress.org listing is backlog story 38
metadata:
  type: project
---

**3.0.0 is the first release (2026-10-08), a pre-release test build:** tag `v3.0.0`, GitHub Release with `post-quality-manager-3.0.0.zip` (single root `post-quality-manager/`, `admin/build/` inside), published by `release.yml`. The owner merged release PR #64 before entries 4.2–4.7 and story 15, so 3.0.0 shipped without the version check, the template guard, Plugin Check, translation loading, release notes and the WP 6.6 install test of the zip. No external users had it at that point. Those stories continue and ship in the next release. CI (`.github/workflows/ci.yml`) lints and builds JS, runs the JS unit tests, lints PHP on 8.3 and runs the harness sweep; `release.yml` (story 4.1, below) is the release path.

`epic-delivery-pipeline` (initiative epic 4) was specced and incepted 2026-10-07 — spec `_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/` (CAP-1…CAP-7 + `brownfield.md`), 8 stories in its `tickets.toml`. Decisions that bind every story (owner, 2026-10-07):

- release-please on Conventional Commits; the first release is **3.0.0**; it bumps the header `Version`, `PQM_VERSION` and `package.json`, never `PQM_WORKFLOW_VERSION`.
- `GITHUB_TOKEN` only, so release-please PRs trigger no CI: release-please creates the GitHub Release as a **draft**; the release workflow runs the version check, builds and attaches `post-quality-manager-<version>.zip` (root folder `post-quality-manager/`), appends `docs/release-notes/<version>.md` when present, then publishes (tag `v<version>`).
- Release notes are a committed file (backlog story 15 writes `3.0.0.md`, and gates the 3.0.0 cut, story 4.8).
- Plugin Check runs in CI: report first (4.4), then fixed and blocking (4.5).
- No empty `languages/` folder ships.

Built by story 4.1 (tracer bullet, 2026-10-08):

- `.github/workflows/release.yml`: job `release-please` (push to `main` only, `googleapis/release-please-action@v5`) keeps one release PR open; job `package` runs when `release_created` is true or on a manual dispatch — `npm ci` + `npm run build` on the tag (or the dispatched ref), zips `post-quality-manager/` as `post-quality-manager-<version>.zip` (version from `package.json`) and always uploads it as a workflow artifact; on a release only it attaches the zip, appends `docs/release-notes/<version>.md` when present, and publishes the draft. A manual dispatch never tags or releases.
- `release-please-config.json`: `include-component-in-tag: false` (tags `v<version>`), `draft: true` + `force-tag-creation: true` (without the latter a draft has no tag and the next CHANGELOG would pull the whole history), package `.` with `release-type: node` (`package.json`, `package-lock.json`, `CHANGELOG.md`) and a generic extra file for the plugin main file. `release-as: "3.0.0"` and `bootstrap-sha` `237b1b9` (the first CHANGELOG started after it, owner 2026-10-08) were removed after 3.0.0 shipped; release-please ignores `bootstrap-sha` once a release PR has merged. `.release-please-manifest.json` carries the released version (`3.0.0`); versions now follow the commits.
- Annotations in `post-quality-manager.php`: a `x-release-please-start-version` / `x-release-please-end` block around the header `Version` line (an inline marker would land in the header value WordPress reads), and an inline `// x-release-please-version` on `PQM_VERSION`. `PQM_WORKFLOW_VERSION` carries none.
- **Done after 3.0.0 (2026-10-08):** `release-as` removed, so the next release PR proposes 3.1.0 on a `feat`, 3.0.1 on a `fix`.
- **Pre-releases until the public launch (owner, 2026-10-08).** The repo is private and 3.0.0 had 0 downloads, but its tag stays: v3.0.0 is flagged as a GitHub pre-release, and `"prerelease": true` in the config makes every release a pre-release until launch. A published version number is never reused or re-tagged. A site holding test build 3.0.0 would not be offered a different "real" 3.0.0, because WordPress offers no update for the same version. The launch PR removes `"prerelease": true` and sets `release-as` to the launch version, above the highest one released so far. Entry 4.8 therefore means "first public release", no longer "3.0.0" (rename it through `bmad-ticket`).
- The repo setting "Allow GitHub Actions to create and approve pull requests" must be on for release-please to open its PR. Owner turned it on 2026-10-08; it lets the workflow open its PR, and nothing in the repo approves or merges.
- **Rebase merging only (owner, 2026-10-08).** "Allow merge commits" and "Allow squash merging" are off. GitHub's default merge-commit message carries the PR title in its body, and release-please splits a commit message at every paragraph starting with a Conventional type, so each merged PR's `feat:`/`fix:` was counted twice (once from the branch commit, once from the merge commit). Rebase merging lands the branch commits as they are, with no merge commit. Consequences: commit SHAs change on `main`, so after a merge a local branch reads as unmerged; check that its commits landed, then delete it with `git branch -D`. Every branch commit must follow Conventional Commits, because each one reaches the CHANGELOG.
- **CHANGELOG fix (2026-10-08):** release PR #64 listed `feat(release): release PR and a CI-built plugin zip` twice, because merge commit `a5edbce` (PR #63) predates rebase-only merging. The duplicate line was deleted from `CHANGELOG.md` and from the v3.0.0 release text after the release; release-please never rewrites a released section.
- `ci.yml`'s `admin-build` artifact is for inspection only; `release.yml` builds the released commit itself.

WordPress.org distribution is backlog story 38 (after story 10 identity — done 2026-10-08, see [[naming]]; story 41 assets and copy sweep; story 15; also needs this epic). Entry 4.6 (text domain) is unblocked: the slug `post-quality-manager` is confirmed. Its review rules — readme.txt with External services disclosure, GPL and human-readable code, no trademarks in the name, Plugin Check clean — are in that ticket.

Related: [[user-paths]], [[freemium-and-roles]], [[bmad-setup]].
