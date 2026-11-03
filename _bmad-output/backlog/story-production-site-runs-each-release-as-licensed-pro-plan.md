---
title: 'The owner''s production site runs 3.10.2 as Pro, under a free lifetime license'
type: 'chore'
ticket: '98'
created: '2026-11-03'
status: done
baseline_revision: 'c0e7b109dd71e406fe9f26d6fe49d91a86e2f424'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/local-verification.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The owner's production site runs a 3.9.0 Pro build with no license (read 2026-11-03). Story 98 moves it to 3.10.2 Pro under a free lifetime license, so the site runs what a paying customer runs.

**Approach:** The ticket's criteria, in order: license, rehearsal on the Local copy, production backup, update, license activation, records.
- **Decision (owner, 2026-11-03, after refinement):** the owner gives the license key in the session, and the agent activates it over SSH with the SDK's own `activate_migrated_license()`. This replaces the ticket's "enters the key on the SDK's license screen": criterion 5 is amended to that wording, and the key goes in no record.
- The walk runs in this session: the agent does the WP-CLI steps over the project's SSH access; the owner does the steps the ticket names.

## Boundaries & Constraints

**Always:**
- Back up before any change (the plugin folder, the database, `wp-config.php`), outside the web root.
- On a failure, restore the plugin files with `--skip-plugins` first, then the database (`docs/local-verification.md` step 10). The rollback also drops the renamed reviews table, which the dump does not hold.
- No command and no WP-Cron run on the Local copy.
- The debug log goes to a file outside the web root, and is turned off again at the end.

**Never:**
- No change to plugin code.
- No release-mode change in Freemius.
- No site address, site title, key or server path in a repository file.
- Nothing on production beyond the steps below without asking.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Rehearsal | Local copy on its current build, cron off | 3.10.2 listed; no `pqm_` option; `postquality_` options and the renamed reviews table with the same row count; Settings and n8n Workflow render for user 1; no plugin line in the debug log; copy restored and compared | a failure stops the walk before production; restore the copy |
| Production update | 3.9.0, 7 `pqm_` options, 62 reviews, setup map, workflow v7 | 3.10.2; 0 `pqm_` options; 62 rows in the renamed table; no plugin line in the debug log | restore files, then the database, drop the new table, report |
| License | the owner's key | `activate_migrated_license()` succeeds; plan `pro`; the License line as in criterion 5; no expiry notice | an error stops here: the site stays on 3.10.2 Free plan, report Freemius's message |
| Workflow + command | managed setup, workflow v7 | after the owner's **Update workflow**, version 8 recorded; one command saves a row | a failed update or command is reported, not retried blindly |

</frozen-after-approval>

## Code Map

- **Production, read 2026-11-03 over SSH:**
  - WordPress 7.1.4, PHP 8.4.
  - The plugin `post-quality-manager/`: 3.9.0, Pro build, active.
  - Options: `pqm_caps_version`, `pqm_db_version`, `pqm_n8n_api`, `pqm_n8n_setup`, `pqm_settings`, `pqm_setup_progress`, `pqm_workflow_version_seen` (`{"version":7,"via":"test"}`).
  - Table `…pqm_reviews` with 62 rows; database 73 MB.
  - Freemius: registered, not anonymous, plan free, no license, SDK 2.13.4.
- **Local copy:** the owner's Local copy of production, reached through `bin/local-wp.py --site` with its Local name (agent-local notes, never written here). Its build is the story 71 era, before 91. It is read at the start of the walk.
- **Tools:**
  - `bin/local-wp.py` (`import` drops the tables first);
  - `docs/local-verification.md` steps 1, 2, 9, 10 and 11;
  - the project's SSH wrapper (agent-local, outside the repository); WP-CLI on the server through PHP.
- **The build:** the GitHub Release asset `post-quality-manager-3.10.2.zip` (sha256 `e4ab80b1…`, after story 58's AC3 re-run). Download it and check the sha256 before each install.
- **Code read:**
  - `migrate_stored_names()` runs on the first `init` after the files change; any WP-CLI call that loads plugins triggers it (one with `--skip-plugins` does not).
  - The License line text is in `class-pqm-admin.php`.
  - `activate_migrated_license()` is in SDK 2.13.5's `class-freemius.php`.

## Tasks & Acceptance

**Execution:**
- [x] Freemius (owner) -- Licenses → Create License: the Pro plan, 1-site pricing, no expiry. Keep the key ready -- AC1.
- [x] Local copy (owner starts it in Local; agent does the rest) -- read its state; back up the plugin zip, the database and `wp-config.php`; set `DISABLE_WP_CRON` and the debug log; install the 3.10.2 zip; check the matrix's Rehearsal row; restore (step 10) and compare (step 11) -- AC2.
- [x] Production backup (agent) -- in a dated folder under the account's home, outside the site root: `wp db export` (check the size and the table count), a tar of the plugin folder, a copy of `wp-config.php` -- AC3.
- [x] Production update (agent) -- set `WP_DEBUG_LOG` to a file in the backup folder, with display off; `wp plugin install <zip> --force`; one WP-CLI call runs the upgrade; check the matrix's update row -- AC4.
- [x] License (owner gives the key; agent activates) -- run the SDK activation with the key on stdin, not on the remote command line; read the plan and render the License line as user 1 -- AC5.
- [x] Workflow and command (owner) -- the n8n Workflow page → **Update workflow** (confirm if asked); Post Reviews → one command on a post. Agent: version 8 recorded, a new row saved -- AC4.
- [x] Close (agent, then owner) -- read the debug log, then restore `wp-config.php` without the debug lines. The owner confirms the site (posts, reviews, scores). With the owner's OK, delete the backup folder and the debug log, or keep them for a stated time.
- [x] Records -- ticket AC5's wording and a Notes line; this plan's Implementation Notes with each check's result; `memory/` if an external behaviour is seen -- AC6.

**Acceptance Criteria:** the ticket's six, with AC5 as amended above.

## Implementation Notes

- **Exception to `docs/local-verification.md` § Rules:** that file keeps routine walks off production and its Local copy. This walk uses both under the owner's story 98 decisions (2026-11-03).
- **AC1 (owner, 2026-11-03):** the license was created in Freemius with an expiration in 12 months, not lifetime. That was against AC1, and was raised with the owner, who changed it to lifetime (below).
- **AC2, the rehearsal on the Local copy (2026-11-03), PASSED:**
  - **Before:** 3.9.0 active, `wp_pqm_reviews` 56 rows, 5 `pqm_` options, no debug log, no walk users, WP-Cron on, no plugin cron event in the dump.
  - **Backups:** the checksum list (219 lines), the database dump, the plugin zip and `wp-config.php`, all in the session scratchpad.
  - **Safeguards:** `DISABLE_WP_CRON`, then `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY false`. Every WP-CLI call loaded a `--require` file that refused and logged each outbound request.
  - **Install:** 3.10.2 (sha256 `e4ab80b1…`) with `--skip-plugins --force`. The first load printed "version 3.10.2, Pro build".
  - **After:** `wp_postquality_reviews` 56 rows; 0 `pqm_` options; `postquality_caps_version`, `_db_version`, `_n8n_api`, `_names_version`, `_settings` and `_setup_progress`; user 1 holds `postquality_manage_settings`.
  - **Pages, rendered as user 1 inside WP-CLI:** Settings (89 KB, "Current plan: Free…", no license on the copy) and n8n Workflow (state `connected`, the copy's recorded state: a key, no setup map).
  - **Debug log:** no line from the plugin. The 3 warnings came from core's update checks, which the blocker refused. No outbound request was attempted after the install.
  - **Restore:** step 10, then step 11, which printed "same" three times. The walk's files were deleted.

- **AC1, resolved (owner, 2026-11-03):** the owner changed the license to lifetime before the license step. The SDK then read it as lifetime, with no expiration, 1 of 1 sites. The owner then read Freemius's Licenses, Payments and Subscriptions pages: the license is listed as lifetime, with no payment and no subscription.
- **AC3, the production backup (2026-11-03):** in a dated folder under the account's home, outside the site root (checked), mode 700, each file 600.
  - **Before:** 3.9.0 active, 88 tables, `…pqm_reviews` 62 rows, 7 `pqm_` options, no plugin cron event, 3 users, debug off.
  - **Saved:** a 48.7 MB dump with 88 `CREATE TABLE`, a 218-file plugin tar, and `wp-config.php` (identical to the live file).
- **AC4, the production update (2026-11-03):**
  - **Install:** `WP_DEBUG_LOG` pointed at the backup folder and `WP_DEBUG` true. `WP_DEBUG_DISPLAY` was already defined false in production's `wp-config.php` (the before-state read), so nothing was shown to visitors. 3.10.2 (sha256 checked on the server) installed with `--skip-plugins --force`.
  - **First load:** "version 3.10.2, Pro build, SDK 2.13.5".
  - **After:** `…postquality_reviews` 62 rows; 88 tables; 0 `pqm_` options; 8 `postquality_` options (the 7 moved, plus `names_version`); the setup map kept; user 1 holds `postquality_manage_settings`.
  - **Pages, rendered as user 1:** Settings ("Current plan: Free…" before the license); n8n Workflow `outdated`, with Update workflow offered.
  - **Owner, in WP Admin:** **Update workflow**, then **Test again** (see bug 99), then one Review on a post. Row 70 was saved (`review`, score 42, `needs_revision`), superseding its `processing` placeholder (row 69, by design). The report now reads "version 8, via command".
  - **AC4 verdict:** met. The workflow runs template version 8 and the command saved its review. The page's stale report right after Update workflow is bug 99, as the ticket's boundary sends a defect to its own ticket.
  - **Debug log:** 224 lines by the end, 0 from the plugin or Freemius. The rest: Yoast SEO's PHP deprecation notices and core zlib notices from visitors' page loads.
- **Defect found → bug 99:** right after **Update workflow**, the page still reported version 7 ("checked just now by a test") and offered Update workflow again, until **Test again** showed version 8.
- **AC5 (2026-11-03):** the key arrived on stdin, through a script in the backup folder (deleted right after), into `activate_migrated_license()`: `success: true`.
  - A fresh load read plan `pro`, paying, a valid license, lifetime, 1/1 sites, and no lapsed record.
  - Settings reads the License line of AC5 exactly; there is no "Renew Pro", no lapsed record and no expiry or renewal notice.
  - `activate_migrated_license()` calls the same private `activate_license()` as the license screen's handler, then removes the SDK's "plan upgraded" sticky notice, which the screen leaves. So a customer activating on the screen also sees that success notice. AC5's check, no expiry or renewal notice, is unaffected.
- **Close (2026-11-03):** `wp-config.php` restored from the backup (byte-identical, debug off). The owner confirmed the site: posts, reviews and scores as before. The backup folder and the walk's local files were deleted (owner: "Delete everything now"), so there is no rollback from this walk any more.
- **Incident (2026-11-03):** to show that only the debug lines changed, the agent printed a `diff` of the live `wp-config.php` against its copy. WP-CLI's edit had changed the file's line endings, so the diff printed the whole file twice. That put the database credentials and the keys and salts into the session's transcript: the session's files on the owner's PC (its log and saved tool outputs under the user profile) and the model provider. Nothing went into the repository. The owner was told at once and accepted the risk; nothing was rotated. Lesson recorded in `memory/lessons.md`; chore 81 lists the exposure for the end-of-development cleanup.
- **AC2 on production too (2026-11-03, read after the update):** 0 `pqm_` user meta rows; no `pqm_` role capability; no `pqm_` capability granted to a single user; the `postquality_` capabilities on administrator, editor, author and two SEO-plugin roles.
- **Side effect found: scheduled reviews resumed.** Production's stored settings had scheduled reviews on (daily, Review, 6 posts, up to 30 days old, score under 60), locked while the site had no license. With Pro unlocked, the plugin scheduled `postquality_cron_review_posts`. Its first run at 11:19 UTC reviewed 6 posts, and it runs daily. This is plugin behaviour as designed, not a defect. The owner chose to turn it off. The agent set `cron_enabled` to false over SSH (no other key changed) and ran `PostQuality_Cron::schedule()`, which unscheduled the event; no next run.
- **AC6:** no site address, site title (with and without a separator), server path, database name, license key or key prefix appears in this plan, the ticket, bug 99, the lessons entry or the memory lines (searched before the commit).
- **BMad deviations** (workspace `CLAUDE.md`):
  - `bmad-build` was rendered by hand from the workspace root, and the Quick lens ran as a subagent on Opus.
  - The walk was run by the orchestrator, not an implementer subagent (Design Notes).
  - The I/O matrix's rows are covered by the walk's recorded checks, not by a harness: this story changes no code, so there is no harness to extend.

## Plan Change Log

## Review Triage Log

Pass 1, 2026-11-03, Quick lens (Opus): 16 findings. 1 false; the rest patched in the records, with no code involved. Two needed the owner, both answered: AC1's Freemius read confirmed; scheduled reviews turned off.
- false: "production's debug display may have been on". The before-state read printed `WP_DEBUG_DISPLAY=false`, defined in `wp-config.php`. The record now says so.
- patch, medium: the walk's notes sat between the frozen block and the Code Map, and the Implementation Notes stopped at AC2 with AC1 unresolved. Moved into the Implementation Notes; AC1's line resolved.
- patch, low: Execution tasks unticked. Ticked.
- patch, low: AC1's "no payment, no subscription" was not shown. The owner read it in Freemius: lifetime, no payment, no subscription.
- patch, low: AC2 checked options, the table and one capability only. Production now shows 0 `pqm_` user meta and no `pqm_` role or user capability.
- patch, low: AC4's verdict was missing. Recorded as met, with bug 99 for the stale report.
- patch, low: "the same activation the license screen runs" was inexact. The ticket and the plan now say the same private function, minus the "plan upgraded" notice the wrapper removes.
- patch, low: the incident note said "no file". The session's files on the owner's PC hold the values; the note and chore 81 say so.
- patch, low: chore 81 lacked this walk's exposures. Added.
- patch, low: `memory/` lacked two observed behaviours: the ping after a PUT answering the previous workflow version (now in `memory/n8n-provisioning.md`), and WP-CLI 2.12.0's `config set` rewriting line endings (now in `memory/local-verification.md`).
- patch, low: the ticket's Notes were stale (the open build question, "a hand-made n8n setup"). Updated.
- patch, low: the BMad deviation record and the matrix-to-harness deviation were missing. Added to the Implementation Notes.
- patch, low: AC6 had no recorded check. The search is recorded.
- patch, low: bug 99 had `hitl: true` with no step named, no `parent — none` line, and a Fact that put version 8 first on the command (it came first with Test again). All three fixed.
- patch, low: the Code Map's "any WP-CLI command" and "the AC3 re-run" were inaccurate or ambiguous. Fixed.

## Design Notes

Implemented by the orchestrator in-session (Opus 5.5), not by a subagent: the walk is a sequence with owner steps in the middle (the license, Update workflow, the command), which an implementer subagent cannot ask for.

Skipped: a debug log on production after the walk. It is turned off at the close.
Skipped: rehearsing the license activation on the Local copy. A 1-site license activated there would have to be deactivated before production.

## Verification

**Commands:** the WP-CLI reads in each task, with output in the Implementation Notes (no site address or title).

**Manual checks:** the owner's confirmation of the site after the update, the check outside the criteria for this high-risk story.
