---
name: release
description: Release verification workflow — review the release-please PR, verify CHANGELOG, release notes, GitHub Release, tag, and plugin zip. Use when the user wants to cut or verify a release.
---

# Release Checklist

Releases are cut by `.github/workflows/release.yml`: release-please keeps one release PR open on `main`; merging it tags `v<version>`, creates a draft GitHub Release, and the `package` job attaches the CI-built zip and publishes it. The owner merges every release PR; agents never merge, tag or push to `main`.

1. Review the open release PR ("release <version>"): the header `Version`, `PQM_VERSION`, `package.json` and `package-lock.json` all carry the proposed version; `PQM_WORKFLOW_VERSION`, `Requires at least` and `Requires PHP` are untouched.
2. Check that `CHANGELOG.md` in the PR reflects the Conventional Commits merged since the last release.
3. Check that `docs/release-notes/<version>.md` exists on `main` (story 15 writes the first one); the `package` job appends it to the release body.
4. The owner merges the release PR.
5. Watch the Release workflow on `main`: the `package` job builds from the tag, attaches `post-quality-manager-<version>.zip` and publishes the draft. If it fails, the release stays a draft with its tag: fix the cause and re-run the job.
6. Download the zip from the published release: its single root folder is `post-quality-manager/`, `admin/build/` is inside, and it installs and activates on a clean WordPress 6.6 showing the new version.

Versions follow the commits (`feat` → minor, `fix` → patch). To force a version, set `release-as` in `release-please-config.json` for that one release, and remove it as soon as the release is out.

Until the public launch every release is a GitHub **pre-release** (`"prerelease": true` in `release-please-config.json`, owner 2026-10-08; the publish step in `release.yml` applies it, because release-please alone ignores it for a non-suffixed version): the plugin is in development, so releases are test builds and none is shown as "Latest". A published version number is never reused or re-tagged, even for a test build. At the public launch: remove `"prerelease": true` in the same PR that sets `release-as` to the launch version (above the highest version already released, so installed test builds are offered the update), then remove `release-as` after the release.

To check packaging without releasing, run the Release workflow by manual dispatch on any branch: it uploads the zip as a workflow artifact (unzip it once to get the upload file) and creates no tag and no release.
