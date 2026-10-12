---
name: release
description: Release verification workflow — review the release-please PR, verify CHANGELOG, release notes, GitHub Release, tag, and plugin zip. Use when the user wants to cut or verify a release.
---

# Release Checklist

Releases are cut by `.github/workflows/release.yml`: release-please keeps one release PR open on `main`; merging it tags `v<version>`, creates a draft GitHub Release, and the `package` job attaches the CI-built zip and publishes it. The owner merges every release PR; agents never merge, tag or push to `main`.

1. Review the open release PR ("release <version>"): the header `Version`, `PQM_VERSION`, `package.json` and `package-lock.json` all carry the proposed version; `PQM_WORKFLOW_VERSION`, `Requires at least` and `Requires PHP` are untouched.
2. Check that `CHANGELOG.md` in the PR reflects the Conventional Commits merged since the last release.
3. Check the release notes on `main` (the `package` job appends `docs/release-notes/<version>.md`, when present, below the changelog in the release body). Pre-release (`"prerelease": true` still in `release-please-config.json`): only report that `docs/release-notes/unreleased.md` exists and is left as it is; it is not renamed. Public release: stop before the merge and report it to the owner if `docs/release-notes/<version>.md` does not exist for the proposed version, or if `docs/release-notes/unreleased.md` still holds lines (it must be empty or gone). Report the gap; never fix it yourself. The fix is a separate `docs:` PR to `main`, opened once the release PR shows the version and merged before it (a `docs:` commit does not change the proposed version): it renames `unreleased.md` to `<version>.md`, or, for a public release with nothing silent to announce, adds a `<version>.md` saying there are no upgrade steps. After a rename, the next change that needs a line re-creates `unreleased.md`, starting with the `## Upgrading from an earlier version` heading.
4. The owner merges the release PR.
5. Watch the Release workflow on `main`: the `package` job builds from the tag, attaches `post-quality-manager-<version>.zip` and publishes the draft. If it fails, the release stays a draft with its tag: fix the cause and re-run the job.
6. Download the zip from the published release: its single root folder is `post-quality-manager/`, `admin/build/` is inside, and it installs and activates on a clean WordPress 6.6 showing the new version.
7. Freemius first (ADR 0014; details in `docs/distribution.md` § Release order): the owner uploads that zip, unchanged, in Freemius → Deployment → Add New Version. It is the Pro build; Freemius generates the Free build from it. Agents never upload.
8. Download the Free build Freemius generated and check it: `grep -rn __premium_only --exclude-dir=vendor` finds nothing, the header `Version` matches, and its n8n template is `templates/n8n-workflow-free.json` alone. Report any hit to the owner and stop; never fix a Freemius build by hand (the fix is a marker in the source, then a new release).
9. Once the WordPress.org listing exists (story 38), that Free build, not the GitHub zip, is what the SVN deploy pushes. Then the owner releases the version in Freemius (release mode Released) for licensed sites; while "Release plans" is OFF (story 44) it stays unreleased.

Versions follow the commits (`feat` → minor, `fix` → patch). To force a version, set `release-as` in `release-please-config.json` for that one release, and remove it as soon as the release is out.

Until the public launch every release is a GitHub **pre-release** (`"prerelease": true` in `release-please-config.json`, owner 2026-10-08; the publish step in `release.yml` applies it, because release-please alone ignores it for a non-suffixed version): the plugin is in development, so releases are test builds and none is shown as "Latest". A published version number is never reused or re-tagged, even for a test build. At the public launch: remove `"prerelease": true` in the same PR that sets `release-as` to the launch version (above the highest version already released, so installed test builds are offered the update), then remove `release-as` after the release. That same launch PR renames `docs/release-notes/unreleased.md` to `docs/release-notes/<version>.md` for the launch version; after the launch, the rename follows step 3 (the release PR belongs to release-please).

To check packaging without releasing, run the Release workflow by manual dispatch on any branch: it uploads the zip as a workflow artifact (unzip it once to get the upload file) and creates no tag and no release.
