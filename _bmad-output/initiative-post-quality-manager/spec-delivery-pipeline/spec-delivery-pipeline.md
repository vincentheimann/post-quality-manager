---
id: SPEC-delivery-pipeline
companions:
  - brownfield.md
sources:
  - ../epic-delivery-pipeline/epic-delivery-pipeline.md
---

> **Canonical contract.** This SPEC and the files in `companions:` are the complete, preservation-validated contract for what to build, test, and validate. Source documents listed in frontmatter are for traceability — consult them only if you need narrative rationale or prose color this contract intentionally omits.

# Delivery pipeline: CI-cut releases with a checked plugin zip

## Why

A mandate and an opportunity. The plugin has never been released: no tag and no GitHub Release exist, and installing it means copying a folder by hand. The README and the `/release` skill already promise a release-please flow that does not exist. Until a release is a merge that produces a zip CI has checked, no one outside the owner can install the plugin. Distribution (UV7) and every user-path signal after A1 wait on it. The owner ranked this epic next on 2026-10-07; there are no real users yet, so the first release is also the first chance to get any.

## Capabilities

- **CAP-1**
  - **intent:** Every merge to `main` opens or updates one release PR that proposes the next version and a CHANGELOG built from the Conventional Commits since the last release.
  - **success:** After a `feat:` commit lands on `main`, the release PR exists, proposes a minor bump and lists that commit.
- **CAP-2**
  - **intent:** Merging the release PR creates the version tag and the GitHub Release, with the plugin zip built from that commit attached.
  - **success:** The attached zip installs through Plugins → Add New → Upload on a clean WordPress 6.6, activates without notices, and the Plugins screen shows the tag's version.
- **CAP-3**
  - **intent:** The plugin header `Version`, `PQM_VERSION` and `package.json` always carry the release version.
  - **success:** The release PR bumps all three, and CI fails on any branch where they differ.
- **CAP-4**
  - **intent:** A broken workflow template never reaches a release.
  - **success:** CI fails on each of four seeded breakages of `templates/n8n-workflow.json` (invalid JSON, a placeholder outside the expected set, a site-specific string, a `jsCode` syntax error in any Code node), and passes on the current template.
- **CAP-5**
  - **intent:** Translations shipped in the plugin's `languages/` folder load for both the PHP admin pages and the React pages.
  - **success:** A test `.mo` and script JSON for one locale show translated text on a PHP admin page and on a React page.
- **CAP-6**
  - **intent:** A release never promises a PHP version it does not parse on.
  - **success:** An 8.1-only syntax on a branch fails CI, because the plugin is also linted on PHP 8.0, the declared minimum.
- **CAP-7**
  - **intent:** The official WordPress Plugin Check runs in CI on every push and pull request, against the built plugin folder, so directory-review findings surface while they are cheap (backlog story 38).
  - **success:** `main` reports zero Plugin Check errors, and a seeded unescaped output on a branch fails CI.

## Constraints

- One zip for free and paid sites (ADR 0003): no build flavours.
- Built assets never enter git; the zip takes `admin/build/` from the CI build of the released commit.
- The zip's root folder is `post-quality-manager`, the plugin slug, so an upload replaces the installed plugin in place.
- `PQM_WORKFLOW_VERSION` (the n8n template handshake) is independent of the plugin version; a release never bumps it.
- release-please on Conventional Commits drives versions and the CHANGELOG; the first release is tagged **3.0.0**, the version the code already declares.
- The first release PR is merged only after backlog story 15 (silent-upgrade release notes) is done; the pipeline itself does not wait for it.
- The owner merges release PRs; agents never merge, tag or push to `main`.
- GitHub Actions with `GITHUB_TOKEN` only: no external service, no extra secret. Because `GITHUB_TOKEN`-made pull requests trigger no CI, release-please creates the GitHub Release as a **draft** and the release workflow runs the version check, builds and attaches the zip, then publishes; nothing publishes unless the check passes.
- Release notes are a committed file, `docs/release-notes/<version>.md`; the release workflow appends it to the GitHub Release body when present. Story 15 writes `3.0.0.md`; the same file later feeds the `readme.txt` changelog (story 38).
- No empty `languages/` folder ships; it appears with the first real translation.

## Non-goals

- WordPress.org packaging (`readme.txt`, plugin assets, SVN): UV7 proper, parked.
- Real Freemius licensing: `deferred-work.md`, triggered by a paid product.
- Producing translations or a `.pot` file: only the loading path is in scope.
- A JS unit-test path: an open decision in `deferred-work.md`.
- The content of the silent-upgrade release notes: backlog story 15.
- An update channel inside WordPress (new GitHub Releases under Dashboard → Updates): parked until external users exist; the WordPress.org listing (backlog story 38) provides it.
- `readme.txt`, `src/` in the zip or the repository link, trademark and asset work: backlog story 38.

## Success signal

- The owner merges a release PR and, without any other step, a tagged GitHub Release appears whose zip installs and activates on a clean WordPress 6.6; on a branch, a deliberately broken template turns CI red.

## Assumptions

- CAP-6 is in scope: the header declares PHP 8.0 but CI lints only on 8.3, so nothing verifies the floor a release promises.
