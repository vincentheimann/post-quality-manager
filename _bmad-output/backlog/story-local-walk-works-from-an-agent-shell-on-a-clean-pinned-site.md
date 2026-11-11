---
id: 126
type: story
title: "The local verification walk works from an agent's shell on a clean site kept at an old WordPress"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: true
risk: medium
estimate: ""
---

# The local verification walk works from an agent's shell on a clean site kept at an old WordPress

## Description

Story 38's Free walk (2026-11-11) on the clean WordPress 6.6 site found four gaps in the local verification guide and its entry point: walk step 4 hangs in an agent's shell, WP-CLI's downloads use the Windows temp folder instead of the session scratchpad, nothing keeps the site at 6.6 (a forced run of every cron event upgraded it), and the walk's frame assumes the plugin is already installed. After this story, the guide and the entry point run each walk on such a site without a workaround, so story 38's later walks and story 96's update check follow the guide as written. The owner starts the Local site for each check on a site (hitl).

## Acceptance Criteria

1. **Writing a setting returns in an agent's shell**
   **Given** a shell whose standard input is an open pipe, as in an agent's tool call
   **When** walk step 4's commands run as the guide gives them
   **Then** the setting is written and each command returns, as it does in a terminal
2. **WP-CLI's temporary files stay in the session's scratchpad**
   **Given** a Local site whose `php.ini` sets PHP's temporary folder to the Windows temp folder
   **When** a command that downloads (`core download`, `plugin install <slug>`) runs through the entry point the way the guide gives it
   **Then** every temporary file it writes lands under the session's scratchpad, and none in the Windows temp folder
3. **Automatic updates are off before the first walk**
   **Given** the guide's one-time setup for a site kept at a WordPress version (the pre-production site)
   **When** it is followed
   **Then** automatic updates are off on that site before any walk
4. **A version change during a walk shows**
   **Given** any walk
   **When** it ends
   **Then** it has printed the WordPress version at its start and at its end, and a difference is reported instead of "same"
5. **A walk on a site with no plugin restores it exactly**
   **Given** a clean site with no plugin installed and no must-use folder
   **When** a walk runs backup, build, restore and the final checks as the guide gives them, with the release's Free zip and with Freemius's Pro zip (folder `post-quality-manager-premium/`)
   **Then** the backup records the plugin list and the contents of the plugins and must-use folders; the restore deletes only the folders and files the walk added, each by its exact path; and the final checks read "same"
6. **Each code change has an offline check**
   **Given** the entry point's offline harness
   **When** it runs
   **Then** each changed behaviour of the entry point has a row that fails on today's script and passes on the change, and every row passes

## Boundaries

- Must not change: the entry point's site lookup by Local name and its refusals (an unknown or doubled name, the `db` subcommands, the `import` guard); nothing site-specific in the repository (guide, Rules); one walk per site at a time; the disposable n8n recipe; the Add Plugins preview recipe apart from the frame it shares with the walk.
- Must not change: the routine walk on the showcase keeps its steps and expected outputs, apart from what criteria 1, 2 and 4 add to every walk.
- Out of scope: the guide's Plugin Check line and its arguments (bug 125's open question on recurrence); story 38's own walks and records.

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md, Implementation Notes: "Tooling findings" 1–3, "Frame on a clean site", "F1, first run" (the automatic update and its recovery), "Builds" (the Pro zip's folder)
- guide — docs/local-verification.md (§ One-time setup, § The entry point, § Installing a build, § The walk steps 1, 4, 8, 10 and 11, § Other checks on the same site)
- facts — memory/local-verification.md
- lesson — memory/lessons.md, 2026-11-11 (forcing every cron event on a site kept at an old WordPress) and 2026-10-08 (confirm a mechanism in the tool's source before choosing it)
- origin — _bmad-output/backlog/story-local-wp-cli-test-environment.md (story 9, which wrote the guide and the entry point)

## Notes

- Paths (named in the brief that filed this ticket): the guide `docs/local-verification.md`; the entry point `bin/local-wp.py`; its offline harness `tests/harness/local-wp-harness.py` (Python, Windows only, run by hand with `python -I`; neither CI nor the PHP sweep runs it).
- Scope, item by item (story 38's findings, 2026-11-11):
  - (a) Step 4 hangs. Fact: WP-CLI 2.12.0's `option patch` reads the value with `get_value_from_arg_or_stdin( $args, -1 )`, which never finds index -1, so it reads standard input to its end; with an open pipe that end never comes. The entry point passes its own standard input on to WP-CLI. `</dev/null` on the command fixes it. **Docs**, unless the open question below picks the code fix.
  - (b) Temporary files. Fact: Local's site `php.ini` pins `sys_temp_dir` to `C:\Windows\TEMP`; `TEMP` and `TMP` do not move it, and the entry point cannot pass PHP a `-d` option. `core download` downloads and extracts there (story 38's recovery hit "access denied" on extraction and left two half-extracted folders, removed by their exact names); `plugin install <slug>` downloads its zip there, and WordPress unpacks it under the site's `wp-content/upgrade/`. The owner's rule wants temporary files in the session scratchpad. **Code** in the entry point, a harness row, and a docs line.
  - (c) Automatic updates. Fact: new installs have major automatic updates on; `wp cron event run --all` ran core's `wp_maybe_auto_update` and upgraded the 6.6 site to 7.1.4, files and database, and step 11 still read "same". Story 38's site now carries `AUTOMATIC_UPDATER_DISABLED` true. **Docs only**: the one-time setup, the version lines at each walk's start and end, and a rule that a walk on a pinned site runs only the cron events a check names, never `--all`.
  - (d) Frame on a clean site. Fact: story 38's walks back up the plugin list and a checksum list of `wp-content/plugins/` and `wp-content/mu-plugins/` instead of zipping a plugin folder; the restore deletes the walk's folders and files by exact path (`post-quality-manager/`, `post-quality-manager-premium/`, Plugin Check's folder, the logger file), then imports the dump and copies `wp-config.php` back; commands that name the slug take `post-quality-manager-premium` in the Pro walk, since Freemius's Pro zip unpacks to that folder (the guide's own Pro build, `npm run zip`, keeps `post-quality-manager/`). **Docs only**; § Installing a build gains the release's Free zip and Freemius's Pro zip as sources.
- Fact (WP-CLI 2.12.0, read 2026-11-11): `core download` runs before WordPress loads and uses WP-CLI's own temporary folder, PHP's `sys_get_temp_dir()`, so WordPress's `WP_TEMP_DIR` cannot reach it.
- Fact: no command in the guide pipes anything into the entry point today.
- Open question: (a) in code or in the guide. The entry point could close WP-CLI's standard input for every command, which fixes every walk at once but stops any command that reads standard input on purpose (a value or a file piped in); the guide's `</dev/null` fixes only the lines that carry it.
- Open question: (b)'s mechanism. The entry point passes PHP a temporary folder; where it takes that folder from (`TEMP`, which the walk sets, or an option of its own) is not decided.
- Open question: one frame or two. The routine walk zips the installed plugin folder; a clean site has none. Either the guide keeps both frames (zip on the showcase, checksum lists on a clean site) or one checksum frame serves both.
- Open question: the one-time setup's step 2 still says the way to WordPress 6.6 "is not exercised yet". Story 38's site reached 6.6 before the walk (how the owner made it is not recorded), and its recovery used `core download --version=6.6 --force --skip-content` with the temporary folder in the scratchpad. The guide records the way that worked once the owner says how the site was made.
- Fact: the offline harness runs the entry point against an empty fake `php.exe`, which stops at an `OSError`, so today it cannot see the options or the standard input PHP receives.
- Open question: how criterion 6's rows observe what the entry point hands PHP, given the fact above.
- Assumption: criteria 1, 2 and 4 are checked on the showcase, and criteria 3 and 5 on story 38's clean site between two of its frames, never during one, with the owner's word (one walk per site at a time). The owner starts each site in Local (hitl).
- Assumption: risk medium. The guide and the entry point are repository tooling, not in the zip, but a wrong restore path deletes files on a site, and a missing update guard shows only when WordPress next offers an update (the 2026-11-11 upgrade needed the core files put back by hand).
- Records at done: `memory/local-verification.md` holds the WP-CLI behaviour behind (a) and the Local behaviour behind (b), each dated with the version it was seen on; the guide's steps name that file instead of restating the cause.
