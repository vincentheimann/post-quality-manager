---
name: release
description: Release verification workflow — review the release-please PR, verify CHANGELOG, release notes, GitHub Release, tag, and plugin zip. Use when the user wants to cut or verify a release.
---

# Release Checklist

Releases are cut by `.github/workflows/release.yml`: release-please keeps one release PR open on `main`; merging it tags `v<version>`, creates a draft GitHub Release, and the `package` job attaches the CI-built zip and publishes it. The owner merges every release PR; agents never merge, tag or push to `main`.

1. Review the open release PR ("release <version>"): the header `Version`, `PQM_VERSION`, `package.json` and `package-lock.json` all carry the proposed version; `PQM_WORKFLOW_VERSION`, `Requires at least` and `Requires PHP` are untouched.
2. Check that `CHANGELOG.md` in the PR reflects the Conventional Commits merged since the last release.
3. Check that `docs/release-notes/<version>.md` exists on `main` (story 15 writes `3.0.0.md`); the `package` job appends it to the release body.
4. The owner merges the release PR.
5. Watch the Release workflow on `main`: the `package` job builds from the tag, attaches `post-quality-manager-<version>.zip` and publishes the draft. If it fails, the release stays a draft with its tag: fix the cause and re-run the job.
6. Download the zip from the published release: its single root folder is `post-quality-manager/`, `admin/build/` is inside, and it installs and activates on a clean WordPress 6.6 showing the new version.

After 3.0.0 ships: remove `release-as` from `release-please-config.json`, so later versions follow the commits.

To check packaging without releasing, run the Release workflow by manual dispatch on any branch: it uploads the zip as a workflow artifact (unzip it once to get the upload file) and creates no tag and no release.
