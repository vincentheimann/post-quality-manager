---
title: 'npm run zip builds an installable plugin zip from a clean tree'
type: 'chore'
ticket: ''
created: '2026-10-15'
status: 'built'
baseline_revision: '3b866638f84042b151e230afc21e4b0a63d8af45'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The owner tests builds by copying `post-quality-manager/` over FTP: deleted files stay on the server, the plugin is half-updated during the upload, whatever is checked out ships (uncommitted work included), and the git-ignored React bundle may belong to other code. Zipping by hand on Windows has its own trap: PowerShell's `Compress-Archive` can write backslash paths that Linux hosts unpack wrongly (`docs/distribution.md`).

**Approach:** One command, `npm run zip`, that does what the release's package job does, locally: refuse a dirty working tree, run `npm run build`, check the bundle exists, and zip the `post-quality-manager/` folder (root folder kept, so WordPress's **Replace current with uploaded** applies) as `dist/post-quality-manager-<branch>.zip`, with Windows' own `tar.exe` on Windows and `zip -r` elsewhere. `dist/` is git-ignored. CONTRIBUTING.md says how to install the zip.

</frozen-after-approval>

## Implementation Notes

- Files: `bin/zip.js` (new), `package.json` (`zip` script), `.gitignore` (`/dist/`), `CONTRIBUTING.md` (how to install the zip), `memory/delivery-pipeline.md` (the tool's line).
- Verified by hiding this uncommitted change from git for one run (`git update-index --assume-unchanged` on the edited files, the new ones in `.git/info/exclude`, both restored after): a clean run built and wrote `dist/post-quality-manager-chore-npm-run-zip.zip` = the 214 tracked plugin files + the 4 bundle files, all under `post-quality-manager/`, no backslash. A dirty tree and a git-ignored file in the plugin folder (`__pycache__/probe.pyc`, removed after) each exit 1 with no build and no zip.
- The `.gitignore` working copy carries CRLF (git warns); the diff is the added lines only and git stores LF.

## Review Triage Log

Pass 1 (quick lens): 4 findings, all real, all patched.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | Git-ignored files inside the plugin folder ship (`git status --porcelain` lists none), e.g. `.claude/settings.local.json` from the global ignore, leftovers wp-scripts' clean keeps in `admin/build/` | medium | patch | Verified: `~/.config/git/ignore` holds `**/.claude/settings.local.json`. Now refused (`--ignored --untracked-files=all -- post-quality-manager`, only `admin/build/` allowed), `admin/build/` emptied before the build, and the first check counts untracked files whatever `status.showUntrackedFiles` says. |
| 2 | `dist/` unanchored in `.gitignore` | low | patch | One-character correction: `/dist/`. |
| 3 | CONTRIBUTING's Replace note leaves out that the activation code does not run (the reviews table's `dbDelta` lives only in `activate()`) | medium | patch | Verified `create_table()` is called from `activate()` only. One sentence: deactivate and reactivate after a table change; uninstall runs only on Delete. |
| 4 | No `memory/` line for the new tool and `dist/` (AGENTS.md / CLAUDE.md rule) | low | patch | Line added to `memory/delivery-pipeline.md`. |

## Verification

**Commands:**
- `npm run zip` on a clean tree -- expected: build runs, `dist/post-quality-manager-chore-npm-run-zip.zip` exists, its entries start with `post-quality-manager/`, use forward slashes, and include `post-quality-manager/admin/build/index.js`.
- `npm run zip` with an uncommitted change -- expected: exit code 1, no build, no zip, a message naming the fix.
- `node --check bin/zip.js` -- expected: no errors.
