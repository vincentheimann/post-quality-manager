---
title: 'The first plugin page after the stored-names upgrade opens'
type: 'bugfix'
ticket: 94
created: '2026-10-28'
status: done
baseline_revision: '7ab43d2e0ea44eb07716bc088a540f66191bed63'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
- '{project-root}/memory/freemium-and-roles.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On a site updated from a `pqm_` build, `PostQuality_Activator::maybe_upgrade()` moves the role capabilities on `init`, but WordPress loaded the current user's capabilities in `WP::init()` just before. For the rest of that first request an administrator lacks the new capabilities, and the Dashboard, Post Reviews and n8n Workflow pages answer "Sorry, you are not allowed to access this page." A capability that `register_capabilities()` grants on a caps-version bump has the same blind spot.

**Approach:** When `maybe_upgrade()` moved or granted capabilities, it reloads the current user's capabilities with core's `WP_User::for_site()`, so the menu registered later on `admin_menu` sees them.

## Boundaries & Constraints

**Always:** reload only on a request where the names migration or the caps grant ran (a finished site keeps paying one cached option read, story 91). The migration stays as it is, and every existing stored-names and caps row passes unchanged. The code comment names the memory file instead of restating WordPress's load order (lessons 2026-10-16).

**Never:** an earlier hook for the upgrade (another plugin may load the current user first, and story 91 put the writes on `init` for the settings sanitizer); `wp_set_current_user()` (it returns early for the same user ID); changes to the menu pages or their capabilities; a release-note line (sites have nothing to do).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| First request after the update | old site, `pqm_` caps on the roles; current user loaded before `maybe_upgrade()` | the current user then holds the five `postquality_` caps and no `pqm_` one; the plugin pages open | No error expected |
| A user with own grants | current user with an own `pqm_` grant, or an own deny | moved under the new name on the current user too; a deny stays a deny | No error expected |
| Caps version behind, names done | caps version absent or lower | `register_capabilities()` runs, one reload | No error expected |
| Finished site | flag and caps version current | no reload, state identical | No error expected |
| No user (cron, WP-CLI) | current user ID 0 | the reload holds no caps and writes nothing | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-activator.php` L136–146 `maybe_upgrade()` (hooked on `init`, main file L154): the only capability change on a request that then renders a page (`activate()` and the Roles tab save redirect first). `migrate_stored_names()` returns at once when `NAMES_OPTION` ≥ `NAMES_VERSION`: read the flag first to know whether it ran. Leave `migrate_stored_names()`, `activate()` and `uninstall()` alone.
- WordPress core, read on the showcase's 7.1.3: `wp-settings.php` L766 `$GLOBALS['wp']->init()` calls `wp_get_current_user()`; L779 `do_action( 'init' )`. `WP_User::for_site()` (`class-wp-user.php` L873, since 4.9) re-reads the user's own capabilities meta and re-merges the role grants; `WP_Role::add_cap()` updates the in-memory role.
- `post-quality-manager/admin/class-pqm-admin.php` L55–98: the menu pages; Settings falls back to `manage_options`, hence spared. Unchanged.
- `tests/harness/stored-names-harness.php`: own `WP_User` stub (L58; stored caps include role names, as core), `old_site()` (user 7 own grant, 8 own deny), rows 2, 3 and 6 call `maybe_upgrade()`; no `wp_get_current_user()` yet. Keep `current_user_can()` on `tw.current_caps` (Row 5).
- `tests/harness/caps-harness.php`: own `WP_User` stub (L38); rows 8 and 9 call `maybe_upgrade()`; no `wp_get_current_user()` yet, so the fix fatals there until one exists.
- `memory/freemium-and-roles.md` L13 ("Registration & upgrade"): home of the WordPress fact.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-activator.php` -- in `maybe_upgrade()`, when the migration or the caps grant ran, call `wp_get_current_user()->for_site()`, one comment naming `memory/freemium-and-roles.md` -- root cause, fixed where both capability changes on `init` pass
- [x] `tests/harness/stored-names-harness.php` -- `WP_User` gains `allcaps` and `for_site()` (reloads `caps` from `tw.users[ID]`, the stand-in for the meta, nothing for ID 0, then merges the grants of the roles it names); `wp_get_current_user()` returns `tw.current_user_object` (an instance separate from `tw.users`, as in core) or `WP_User(0)` and counts `for_site()` calls; new row "First page after the upgrade (bug 94)" for matrix rows 1, 2, 4, 5 -- the ticket's AC3
- [x] `tests/harness/caps-harness.php` -- `wp_get_current_user()` stub counting `for_site()`; Row 8: absent and lower caps version reload once, equal never -- matrix rows 3, 4
- [x] `memory/freemium-and-roles.md` -- one dated fact: WordPress loads the current user in `WP::init()` before `init`, so a capability change on `init` reaches that user only after `for_site()` (7.1.3, 2026-10-28, bug 94)

**Acceptance Criteria:**
- Given the new rows against the baseline activator, when the sweep runs, then only the bug-94 checks fail; with the fix all pass, and the stored-names and caps counts grow only by the new checks.
- Given the showcase holding a 3.9.0-era build with `pqm_` names, when the fixed Free build is installed and an administrator opens `admin.php?page=postquality-workflow` as the first admin request, then the page opens and the names are moved.

## Implementation Notes

- 2026-10-28, planning: ticket 94 is unrefined, but its criteria and reproduction are complete and its cause is verified in core (Code Map); the owner's checkpoint approval stands in for a `bmad-ticket` refinement (owner asked for this build before 3.10.0, 2026-10-28). Skill rendered by hand from the workspace root; subagents on Opus.
- 2026-10-28, checkpoint 1: owner approved and continued; plan kept whole at ~1,800 tokens (owner's choice over trimming); the showcase walk in Manual checks is approved (backup, install, restore).
- 2026-10-28, implementer (Opus 5.5): the four tasks built, the activator as in Design Notes (the comment reads "Why the current user needs it: memory/freemium-and-roles.md (bug 94)."; the docblock names the reload, not WordPress's load order); no walk, WP-CLI or Local command run, the showcase walk left to the orchestrator. Baseline before any change: every `tests/harness/*.php` green, caps-harness 284 passed, stored-names-harness 50 passed. Rows written first and run against the baseline activator: only bug-94 checks failed (stored-names 53 passed, 5 failed; caps 285 passed, 2 failed). With the fix: stored-names 58 passed (+8: Row 7 "First page after the upgrade (bug 94)" — the administrator loaded before `init` holds the five `pqm_` caps, then one reload on the same instance and the five `postquality_` caps with no `pqm_` one; the next request on the finished site reloads nothing, writes nothing, state and user identical; an own grant (user 7) and an own deny (user 8) moved on the current user, the deny winning over the Editor role's grant; user 0 reloaded once, holding nothing, with the option and meta writes of the migration alone and no user 0 stored), caps 287 passed (+3 in Row 8: absent and lower reload once, equal never); every other harness count unchanged, 0 failing; `npm run test:unit` 174 passed. The stored-names admin (user 1) is added in Row 7 only, so `old_site()` and Rows 1–6 are untouched. Mutations, each turning rows red, the activator restored byte for byte after (sha256 compared): reload always (finished-site and equal-version rows), the caps grant not setting `$changed` (caps Row 8 absent and lower), the flag read after the migration (5 Row 7 checks), the user fetched without `for_site()` (5 Row 7 checks plus caps Row 8). Noted, not changed: while a failed table rename leaves the names flag unset, every request retries the migration and so reloads the current user too (one more `for_site()` on a request already doing the migration's work).
- 2026-10-28, orchestrator, step 4 pass 1 patches (findings 1, 2, 4, 5; implementer re-engaged): caps-harness `for_site()` records the administrator role's grants at the reload, Row 8 asserts the granted caps are held then; Row 7's user-0 facts taken before the world resets; `memory/naming.md` says seven rows; the three stub comments name `memory/freemium-and-roles.md`. Re-verified: `php -l` clean, 22 harness files 0 failing (caps 287, stored-names 58), Jest 174. The reload-before-grant mutant now fails caps 285/2 (absent and lower); activator restored byte for byte (sha256).
- 2026-10-28, orchestrator, the showcase walk (owner approved at checkpoint 1; `docs/local-verification.md` frame, WordPress 7.1.3, PHP 8.5): baseline a 3.9.0-era Free build with `pqm_` names, the `wp_pqm_reviews` table (30 rows), 1 user. Each pass installed a Free build from `bin/build-free.php` with `--force`, ran every WP-CLI read with `--skip-plugins`, and made the first plugin load an HTTP request as the administrator (auth cookies from `wp_generate_auth_cookie()`). Pass A, the fixed build, n8n Workflow page first: HTTP 200, title "n8n Workflow", no refusal, no PHP notice; afterwards the names flag is 1, `postquality_settings` present, `pqm_settings` gone, the table renamed, the administrator role holds 5 `postquality_` caps; no debug.log. Pass B, the fixed build, Dashboard first: HTTP 200, title "Dashboard", the same after-state. Control, main's build without the fix, Dashboard first: HTTP 403 "Sorry, you are not allowed to access this page.", the names moved: the walk reproduces the ticket. Restored after each pass; the three comparisons "same" each time; the walk's files deleted.

## Plan Change Log

## Review Triage Log

Pass 1 (2026-10-28, lens `quick`, Opus): 0 high, 0 medium, 4 low, 1 false, 0 maybe-false. Four patched, one rejected.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | The caps-bump half is never checked for what the user ends up holding: a reload before `register_capabilities()` passes every row | low | patch | Reproduced: the mutant (reload, then grant) passes caps 287/0 and stored-names 58/0. Fix: the caps-harness reload records the grants it sees, and Row 8 checks the granted cap is among them. |
| 2 | The user-0 check's `users[0]`/`user_meta[0]` part runs on a fresh world (`old_site()` resets `tw` first) and cannot fail | low | patch | Read in the diff: `old_site(); $migrate();` precede the `isset` checks. Fix: take those from the `maybe_upgrade()` world before resetting. The `caps === []` part models core's user 0 and stays. |
| 3 | Plan AC2 (the showcase walk) not met | false | reject | Not a defect of the diff: the walk is the plan's manual check, run by the orchestrator after this review (owner approved it at checkpoint 1) and recorded in Implementation Notes before step 5. |
| 4 | `memory/naming.md` L16 still says the stored-names harness holds six matrix rows | low | patch | grep: "holds the six matrix rows"; the harness now has Row 7. Fix: seven, Row 7 named. |
| 5 | Three new harness comments restate WordPress's load order and `for_site()` instead of naming the memory file | low | patch | lessons 2026-10-16; the activator's comment complies. Fix: each comment names `memory/freemium-and-roles.md` and keeps only what the stub does. |

## Design Notes

```php
$changed = (int) get_option( self::NAMES_OPTION, 0 ) < self::NAMES_VERSION;
self::migrate_stored_names();
if ( (int) get_option( 'postquality_caps_version', 0 ) < PostQuality_Features::CAPS_VERSION ) {
    self::register_capabilities();
    $changed = true;
}
if ( $changed ) {
    wp_get_current_user()->for_site(); // memory/freemium-and-roles.md (bug 94)
}
```

The first read is the autoloaded option `migrate_stored_names()` reads anyway.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 0 failed; stored-names and caps counts grow only by the new checks
- `npm run test:unit` -- expected: unchanged (no JS touched)

**Manual checks (if no CLI):**
- Agent walk on the showcase (`docs/local-verification.md` frame: backup, install, restore), after the owner's word since it acts outside the repository: the ticket's reproduction with main's Free build plus this fix, the n8n Workflow page first, then after a restore the Dashboard first; site restored after.
