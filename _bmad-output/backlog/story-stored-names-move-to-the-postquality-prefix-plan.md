---
title: 'Every stored name carries the postquality prefix, and existing sites keep their data'
type: 'feature'
ticket: '91'
created: '2026-10-26'
status: done
baseline_revision: '40366cfd383cd44dbc6b482b2e4d13440b2947a4'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/memory/naming.md', '{project-root}/memory/freemium-and-roles.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** After story 88 the code names carry `postquality`, but every name the plugin stores in the database still starts with `pqm`: options, user meta, capabilities, the cron hook and schedule, the reviews table, and the `pqm_settings[…]` form fields that follow the option.

**Approach:** Rename every stored name in code to the new prefix and add a one-time, step-wise idempotent migration that moves an existing site's data to the new names on its first load (and at activation), then records that it finished so later loads skip it. Deletion removes both prefixes. Plan kept whole at ~2,500 tokens (owner, 2026-10-26).

## Boundaries & Constraints

**Always:** Values move unchanged; a value already under its new name is never overwritten. Capabilities move before the capability version check, so a role keeps exactly the grants (and explicit denies) it had, never the defaults; users' own grants move too. The migration writes before the settings sanitizer is attached (it is registered on `admin_init`) or with it detached: a run with no user or a low-capability user must keep every admin-only setting. The cron event keeps its next run time and interval. Old names live in one legacy map, read only by the migration and the uninstall.

**Never:** change a value; touch Freemius's own stored data (keyed by the slug); rename the permanent exceptions (`X-PQM-Token`, the REST namespace and its `WP_Error` codes, `__PQM_*__`, `pqmInsert`, `pqm-execute-`, CSS classes, DOM ids, `data-pqm-*`, file names); keep reading old names after the migration; add a dependency.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Fresh install | no `pqm` name anywhere | activation, setup, a review and a cron run write only `postquality` names; the finished flag is set | — |
| Upgrade | a value under every old name, roles customised on the Roles tab, a user's own grant and deny, a weekly cron event, reviews in the table | every value under its new name, old names gone; each role and user holds the new caps exactly as it held the old ones (denies included) and none of the old; the event under the new hook and schedule at the same time and interval; every table row kept | — |
| Interrupted | a new option beside its old one; old table still present; old cap beside new | remaining steps finish; the existing new value is kept and the old one removed; later loads skip the migration | a failed table rename leaves the flag unset, so the next load retries |
| Activation on an old site | old data, the plugin re-activated | the migration runs before the table is created, so no empty new table shadows the old one | if both tables exist and the new one is empty, it is dropped and the old one renamed; if the new one has rows, both are kept and nothing is overwritten |
| Low-cap first load | a cron or REST request, or an Author, triggers the migration | every admin-only setting arrives intact | — |
| Deletion | a migrated site, or one that still holds an old name | no option, user meta, capability, cron event or table with either prefix remains | — |

</frozen-after-approval>

## Code Map

Stored names on main 40366cf (implementer re-derives; `tests/harness/prefix-harness.php` `STORED_LITERALS` is the list):
- Options (11): `pqm_settings`, `_db_version`, `_workflow_version_seen`, `_cron_nudge_dismissed`, `_caps_version`, `_setup_progress` (`POSTQUALITY_SETUP_OPTION`), `_n8n_api`, `_n8n_setup`, `_n8n_leftovers`, `_n8n_provisioning` (constants in `includes/class-pqm-n8n.php` L39–90), `_ping` (`PostQuality_Admin::PING_OPTION` L2157). Writes in `admin/class-pqm-admin.php` L2076/2090, `class-pqm-n8n.php` L1490, `class-pqm-rest-api.php` L1467, `class-pqm-activator.php` L27; `register_setting` L316 (on `admin_init`, main file L148); form fields `name="pqm_settings[…]"` in `class-pqm-admin.php`.
- User meta (3): `pqm_monthly_usage` (`PostQuality_Features::USAGE_META_KEY`), `pqm_feedback_dismissed` (`PostQuality_REST_API::FEEDBACK_META`), `pqm_view_as` (retired by story 42, kept "one release": the migration deletes it instead of moving it). Author voice meta does not exist yet (story 37): nothing to move.
- Capabilities (5): keys of `PostQuality_Features::capabilities()` and the `capability` values in `registry()` / `paid_registry__premium_only()`; `register_capabilities()` grants defaults only while no `manage_options` role holds a cap (so order matters); `remove_capabilities()` strips roles and users' own grants (`get_users(['capability__in'])`).
- Cron: hook `PostQuality_Cron::HOOK` (`pqm_cron_review_posts`), schedule key `pqm_weekly` (`class-pqm-cron.php` L18, L38); `wp_get_scheduled_event()` gives an event's timestamp, schedule and interval.
- Table: `PostQuality_DB::table_name()` and `PostQuality_Activator::create_table()` / `uninstall()` (`pqm_reviews`); direct queries carry the existing `phpcs:ignore WordPress.DB.DirectDatabaseQuery` comment.
- Upgrade hook: `PostQuality_Activator::maybe_upgrade()` on `init` (main file L137), today only the caps version check; `activate()` creates the table, then registers caps.
- Tests: `tests/harness/caps-harness.php` stubs `WP_Role`/`WP_User`/`get_users` (reuse for cap moves); `lib/wp-stubs.php` holds the runner; options and cron are stubbed per harness. `caps-harness.php` Row 7 parses the README capability table. Prefix harness Rows 10–11 hold the stored and permanent lists.
- Records: `docs/brand.md` § Naming (stored row), `AGENTS.md` L43 (managed block: add a dated hand-edit note to its header), `README.md` capability table, `memory/naming.md`, `memory/freemium-and-roles.md`, other `memory/` lines naming stored names, `docs/release-notes/unreleased.md`.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/stored-names-harness.php` (new) -- one row per matrix line, with stubbed options, user meta, roles/users, cron and a stub `$wpdb` for the table; run it before the migration exists and record that it fails -- proof first.
- [x] `includes/class-pqm-activator.php` -- the legacy map and `migrate_stored_names()`: options, user meta (`pqm_view_as` deleted), capabilities (roles and users, grant value kept), cron (hook and schedule), table, then the finished flag; called first in `maybe_upgrade()` and in `activate()`; `uninstall()` removes both prefixes -- the upgrade.
- [x] Every stored-name literal in `post-quality-manager/`, `src/`, `tests/` → `postquality_…` (table `postquality_reviews`, schedule `postquality_weekly`, form fields `postquality_settings[…]`) -- the new names.
- [x] `tests/harness/prefix-harness.php` -- Row 10 asserts the stored names carry the new prefix and no `pqm_` stored name appears outside the legacy map; Row 11 unchanged -- the durable check.
- [x] Records listed in the Code Map, plus one release-notes line: settings, reviews, roles and the schedule carry over on update; a role editor plugin now lists the capabilities as `postquality_…` -- AC5, DoD.

**Acceptance Criteria:**
- Given the renamed tree, when every harness runs, then all pass, and the stored-names harness rows fail with the migration removed.
- Given a Local site on the previous release with data under every old name, when this build replaces it, then the person sees every page, setting, review, role choice and the schedule as before (hitl walk).

## Implementation Notes

- Plan approved by the owner 2026-10-26 (approve and continue). Commits, push and a draft PR are allowed this sprint (owner, 2026-10-26), overriding the house "user commits" rule. Implementer subagent on Fable 5.1 (`model: "fable"`, owner rule 2026-10-16).
- **Built 2026-10-26 (implementer subagent, Fable 5.1), on `feat/postquality-stored-names` over main 40366cf.** Proof first: `tests/harness/stored-names-harness.php` on the unmigrated tree — 3 passed, 43 failed, exit 1; with the migration call commented out of `maybe_upgrade()` and `activate()` after the build — 27 passed, 19 failed (AC1's second half); restored — 46 passed, 0 failed.
- The rename was one `sed` over the 21 stored literals (word-bounded, so `pqm_settings[` and `wp_pqm_reviews` followed and `pqm_ping_unknown`, DOM ids and the `WP_Error` codes did not) across the plugin, `src/`, `tests/`, `README.md`, `AGENTS.md` and `memory/`; `_bmad-output/`, `CHANGELOG.md`, past release notes and ADRs stay as written (story 88's convention). The activator and prefix-harness were rewritten by hand afterwards; three comments in the admin class and the features docblocks that said `pqm_` caps now say `postquality_`.
- Design, as planned, with these particulars: the legacy map is `PostQuality_Activator::LEGACY_NAMES` (one private const, keyed by kind; `user_meta_deleted` holds `pqm_view_as`); `uninstall()` iterates it, so no old literal exists outside the map (prefix-harness Row 10 strips the map's text from the tree and proves it) — `PostQuality_Features::remove_capabilities()` took an optional `$caps` list for that, default unchanged. Options keep their autoload flag through `wp_load_alloptions()`. The sanitizer is detached by callable (`['PostQuality_Admin', 'sanitize_settings']` on `sanitize_option_postquality_settings`) and re-attached with its priority. Cron: a recurrence this build does not know (the Free build registers no weekly schedule) makes `wp_schedule_event()` refuse, and the old event, callback-less on either build, is cleared all the same; the migration still finishes. Table: `SHOW TABLES LIKE` with `esc_like()`, `%i` for the identifiers; a new table with rows beside the old one leaves both (uninstall drops both).
- Harness adjustments: `caps-harness.php` seeds `postquality_names_version = 1` in its worlds (the migration is the new harness's), checks `postquality_` keys, expects both drops, both hook clears and the interleaved option deletes; `render-matrix-harness.php` reads the Roles checkbox values as `postquality_` and checks neither slug in visible text; `free-build-harness.php`, `rest-posts-harness.php`, `rest-reviews-harness.php` static checks read the map and the loop instead of a literal `delete_*` line; `lib/wp-stubs.php` gained guarded `has_filter` / `remove_filter` / `wp_load_alloptions`; `lib/wp-admin/includes/upgrade.php` (new) stubs `dbDelta()` so a harness defining `ABSPATH` as `lib/` can call `activate()`.
- Verification 2026-10-26: `php -l` clean on every touched PHP file; 21 harnesses, 0 failed (stored-names 46/46, prefix 22/22, caps 284/284); `npm run test:unit` 174/174, `lint:js` clean, `build` compiled; `git diff --stat -- post-quality-manager/vendor` empty; the old-literal `git grep` hits only the legacy map (`class-pqm-activator.php` L36–62).
- Review fixes 2026-10-26 (six findings, all applied): `uninstall()` runs `migrate_stored_names()` first, so a never-migrated site (deactivated, updated, deleted) has its setup map under the new name when the Application Password is revoked; `migrate_cron()` offers an unregistered schedule (the Free build's weekly) through a one-call `cron_schedules` filter with the event's own interval, so the event moves everywhere; `migrate_table()` drops a new table only on a count that answered exactly `'0'` (a failed read keeps both); three `phpcs:ignore … -- reason` lines (`meta_key` lookup, the two core `the_content` applications); the release note names no `pqm_`; `memory/MEMORY.md`, `CONTRIBUTING.md`, `memory/freemium-and-roles.md` say the stored names moved and the View as meta goes on first load. Harness: Free-build row asserts the move, a failed-count row, a never-migrated uninstall row with a revoke (stored-names 50/50); `setup-harness.php`'s uninstall row seeds the names flag like caps-harness does.
- Records: `docs/brand.md` stored row, `AGENTS.md` convention + header note, `README.md` capability table and prose, `memory/naming.md` (story 91 bullet), `memory/freemium-and-roles.md` and the other memory files' literals, `docs/release-notes/unreleased.md` one paragraph. The Local hitl walk (AC2 given/when/then and the delete check) is the owner's; not run here.

- Orchestrator check 2026-10-26: Verification re-run (`php -l` clean; 21 harnesses 0 failed, stored-names 46/0, prefix 22/0; Jest 174; `lint:js`, `build` exit 0; `vendor/` diff empty; old stored literals only in the activator's map; no CRLF). Matrix audit: every row has a stored-names harness block that ran.
- Local walk 2026-10-26 on the owner's showcase site (the owner started it and asked the agent to test; Free build of this tree, WP 7.1.3, Freemius skipped/anonymous), seeded with a value under every old stored name (11 options incl. dummy n8n values, 3 user meta, an editor without `pqm_run_paid_agents`, an author with an explicit `pqm_manage_agents` deny, a user with an own grant and deny, a weekly event, 30 reviews), DB backed up first and replayed per walk. A (Free, first load, no user): every value under its new name, old names gone, `pqm_view_as` deleted, roles and the user's grants and denies exact, table renamed with 30 rows, flag set; the dead weekly event cleared (Free registers no schedule). B (weekly schedule registered on the first load, the Pro path): the event moved to `postquality_cron_review_posts` at the same timestamp, schedule `postquality_weekly`, interval 604800. C (plugin re-activated on the old site): one `postquality_reviews` table with 30 rows, no empty table shadowing it. D (half-migrated: a different `postquality_settings`, a new deny beside an old grant): the new value and the deny kept, the old ones removed, the rest moved. E (deactivate, then uninstall, as user 1): no option, user meta, capability, cron hook or table with either prefix left; no Freemius uninstall reason stored. Pages render with no JS errors. Note: Freemius registers its uninstall callback only when an admin deactivates (`_deactivate_plugin_hook()`) and runs it only for a user who can `activate_plugins`; a WP-CLI uninstall with no user removes nothing.
- Plugin Check on this Free build: 0 errors, 25 warnings. Against the 3.9.0 baseline (22) the three new ones are `PrefixAllGlobals.NonPrefixedHooknameFound` on `apply_filters('the_content')` (`pqm-functions.php`, `class-pqm-rest-api.php`; Plugin Check runs its prefix check only once the prefix is valid, i.e. since story 88) and `SlowDBQuery.slow_db_query_meta_key` on the migration's one-time `get_users(['meta_key' => …])`. Story 88's AC4 ("no new error or warning") is met only once these carry justified ignore comments.

- Orchestrator re-verification after the review patch round (2026-10-26): `php -l` clean; 21 harnesses 0 failed (stored-names 50/0); Jest 174; `lint:js`, `build` exit 0; `vendor/` diff empty; old stored literals only in the activator's map; no CRLF.
- Re-walk on the showcase site with the patched Free build, each from the same seeded copy: A2 (first load, no weekly schedule registered) all values moved, and the weekly event moved too, same timestamp, `postquality_weekly`, interval 604800 (finding 2 fixed); B2 (schedule registered), C2 (re-activation, one table with 30 rows), D2 (half-migrated: new value and new deny kept), E2 (migrated site, deactivate and delete as user 1: nothing of either prefix left), F (finding 1: the old build deactivated, the inactive copy updated to this build, deleted without ever loading: nothing of either prefix left, no table, no cron hook) all pass. Visible content before (main build on the seeded data) and after (this build, first load) is identical on Dashboard, Post Reviews (73 lines, 22 fields), n8n Workflow and Settings (18 field values), no JS errors (the review's finding 7: what a person sees is the same). Plugin Check on this Free build: 22 warnings, 0 errors, the 3.9.0 baseline exactly, so story 88's AC4 is met too. The Application Password revoke is Pro-only code: covered by stored-names Row 6, not walkable on the Free build.
- Step 5: commit, push and the draft PR by the orchestrator on the owner's sprint grant (2026-10-26), overriding the house "user commits" rule and the step's "never auto-push".

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-10-26, lens `quick`; agent walk A–E on the showcase site ran before it).** Verdicts: high 0 · medium 2 · low 5 · false 0 · maybe-false 0. Routes: patch 6, reject 1.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `uninstall()` on a never-migrated site leaves the n8n Application Password: `revoke_setup_password__premium_only()` reads only the new setup option, then the loop deletes the old one with the password's record | medium | patch | Real: plugin deletion includes the main file after `init`, so `maybe_upgrade()` never runs in that request (deactivate 3.9.0, update the inactive copy, delete). Fix: `uninstall()` runs `migrate_stored_names()` first; stored-names Row 6 seeds an `application_password` under the old map. |
| 2 | On the Free build the weekly event is cleared instead of moved (frozen Always: "keeps its next run time and interval") | low | patch | Real: `wp_schedule_event()` refuses the unregistered `postquality_weekly`. WordPress 5.1+ keeps an event whose schedule is unregistered by its stored interval (`wp_reschedule_event()` fallback, read in the showcase's 7.1.3 core), so the move registers the mapped schedule key with the event's own interval for the one call. The plan already says it; the build deviated, so no plan amendment: corrected in place, harness L358–362 asserts the move. |
| 3 | The release note names `pqm_` | low | patch | Real: `docs/brand.md` § Naming, "PQM never reaches a user … release notes". |
| 4 | Records still say stored names are `pqm_`: `memory/MEMORY.md` L18, `CONTRIBUTING.md` L27, `memory/freemium-and-roles.md` L24 (`pqm_view_as` "deleted by uninstall for one release") | low | patch | Real: each line read. |
| 5 | New Plugin Check warning `SlowDBQuery.slow_db_query_meta_key` (activator L200) with no justified ignore; story 88's two `the_content` warnings the same | low | patch | Real: the showcase Plugin Check run lists all three; the class's direct queries carry `phpcs:ignore` already. One justified ignore each. |
| 6 | `migrate_table()`: a failed `COUNT(*)` reads as 0 and the new table is dropped | medium | patch | Real: `$wpdb->get_var()` returns null on error, `(int) null === 0`, then `DROP TABLE`; a table with rows could be lost. Drop only on a successful count of `'0'`. |
| 7 | AC2 walk differs from its wording and the notes contradict (owner's walk "not run" vs the agent walk) | low | reject | The owner started the showcase site and asked the agent to test (2026-10-26); the site ran the 3.9.0 Free build with real data before it, the old names were seeded on top. The fix is a plan-record edit (rejected by rule); the re-walk after the patches compares the pages' visible content before and after, and the owner's own look stays offered. |

## Design Notes

Step-wise idempotence carries the interruption case: each option, meta key, cap and the cron event moves only when its new name is absent, else the old one is dropped; the table renames only when the new table is absent (or present and empty). The finished flag (`postquality_names_version` = 1, autoloaded) is written last, so a crash retries on the next load and a finished site pays one cached option read.

Options and meta move through the WordPress API (`get_option`/`add_option`/`delete_option`, `get_user_meta`/`add_user_meta`), so caches stay right; only the table needs SQL (`SHOW TABLES LIKE`, `RENAME TABLE`).

Skipped: a lock against two simultaneous first loads — every step is idempotent and WordPress refuses a duplicate `add_option`; add one if a doubled cron event is ever seen. Skipped: clearing Freemius's stored sticky opt-in notice (Boundaries: Freemius data).

## Verification

**Commands:**
- `php -l` on every touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*-harness.php; do php "$f"; done` -- expected: 21 harnesses, 0 failed
- `npm run test:unit`, `npm run lint:js`, `npm run build` -- expected: green
- `git diff --stat -- post-quality-manager/vendor` -- expected: empty
- `git grep -n -E "'pqm_(settings|db_version|workflow_version_seen|cron_nudge_dismissed|caps_version|setup_progress|n8n_api|n8n_setup|n8n_leftovers|n8n_provisioning|ping|monthly_usage|feedback_dismissed|view_reviews|run_free_agents|run_paid_agents|manage_agents|manage_settings|cron_review_posts|weekly|reviews)'" -- post-quality-manager src ':!post-quality-manager/vendor'` -- expected: only the legacy map and `uninstall()`

**Manual checks (if no CLI):**
- Owner walk on Local (ticket hitl): a site on the 3.9.0 release with settings, an n8n setup, reviews, a customised role, a dismissed feedback card, usage this month and a weekly schedule; replace the plugin with this build; every page, a review, the Roles tab, the Dashboard card state and Tools → Cron (or WP-CLI `wp cron event list`) show the same; then delete the plugin and check no `pqm_`/`postquality_` option, meta, capability or table is left (WP-CLI).
- Plugin Check on the Free build: CI after 2026-11-01.
