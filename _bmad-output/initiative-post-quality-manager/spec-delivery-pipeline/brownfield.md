# Brownfield — delivery pipeline (scanned 2026-10-07, `main` at 24fb793)

What already exists, what the epic envelope got wrong, and what downstream must not break.

## CI today — `.github/workflows/ci.yml` (story 5.1, extended in 5.8)

| Job | Does | Gap for this spec |
|---|---|---|
| `build` (Node 22) | `npm ci`, `npm run lint:js`, `npm run build`, asserts `admin/build/index.js` + `index.asset.php`, uploads artifact `admin-build` | Artifact is per-run; the release job must build the released commit itself or download that run's artifact |
| `php` (PHP 8.3) | `php -l` on every plugin file, full `tests/harness/*.php` sweep | No 8.0 lint (CAP-6) |

Triggers: every push and PR. The envelope's line "the repo has no `.github/workflows/`" is stale.

## Versions

- `3.0.0` in three places: plugin header `Version:` (`post-quality-manager/post-quality-manager.php:6`), `PQM_VERSION` (`:20`), `package.json` `"version"`. Commit a05b3b5 once fixed a header/constant mismatch by hand — CAP-3 makes that a check.
- `PQM_WORKFLOW_VERSION` (`:28`, currently 4) is the template handshake, not the plugin version.
- No git tag, no GitHub Release, no `CHANGELOG.md`, no release-please config. release-please therefore needs a bootstrap point: the first release is 3.0.0 (owner, 2026-10-07).

## Zip contents

- The distributable is the `post-quality-manager/` subfolder: `admin/` (incl. CI-built `admin/build/`, git-ignored), `includes/`, `templates/`, `post-quality-manager.php`, `uninstall.php`. Repo-root files (`src/`, `tests/`, `_bmad*`, `docs/`, `memory/`, `package*.json`) stay out.
- `languages/` does not exist yet, and git does not track an empty folder.

## Translations

- Header `Text Domain: post-quality-manager`; no `Domain Path`, no `load_plugin_textdomain()` call — PHP strings only load from WordPress's global language directory today.
- `class-pqm-admin.php:233` already calls `wp_set_script_translations('pqm-app', 'post-quality-manager', PQM_PLUGIN_DIR . 'languages')`. There is one React bundle: `src/index.js` mounts the Dashboard, Post Reviews and the setup dialog (`pqm-setup-root`) from the same `pqm-app` handle, so the React half of CAP-5 is wired and only needs verifying; the PHP half (`Domain Path` + a loading call) is the work.
- A pseudo-locale generator (`pseudo-l10n.js`) was used for the 5.9 manual pass but lives outside the repo.

## Template checks today

- `tests/harness/template-failure-harness.php` (bug 31) parses the template as JSON and runs selected Code nodes' `jsCode` through `node`; it needs Node on PATH (present on `ubuntu-latest`).
- Expected placeholder set today: `__PQM_SITE_HOST__` only (3 uses), filled by `pqm_workflow_json()`. The 2026-07-23 prototype of a full check was never committed.

## Docs to align when the pipeline lands

- `.claude/skills/release/SKILL.md` step 1 says the action is "still to be added".
- `README.md:101` says releases are "cut by hand with the `/release` checklist until the planned release-please…".
- `docs/user-paths.md` A3 UV: "no update channel" stays open (update channel parked, owner 2026-10-07).
