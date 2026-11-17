---
title: 'An opted-out site stays opted out when the plugin is activated again'
type: 'bugfix'
ticket: '135'
created: '2026-11-17'
status: 'built'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/memory/freemium-and-roles.md']
baseline_revision: 'b3c6330485280b2996d71262a426718af639242f'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On the Free build, a site that opted in to Freemius and later opted out of "Communication" comes back registered and sharing usage data when the same plugin version is activated again (after a deletion and reinstall, and by the SDK's code also after a plain deactivate and reactivate), with no consent click. The SDK's activation step clears its "skipped" flag for a same-version activation (SDK 2.13.5 `_activate_plugin_event_hook()` L7487–7515 → `reset_anonymous_mode()`), and the kept account makes `is_registered()` true again. In story 38's frame the re-armed Free build sent an install update (`PUT /v1/installs/<id>.json`) and an update check to Freemius on the next admin load, before any new consent.

**Approach:** The plugin notes, just before the SDK's activation step, whether the site is an opted-in site that opted out of Communication since (anonymous, account kept), and puts that opt-out back right after the step if the SDK cleared it, through the SDK's public `skip_connection()` (stores the flag, sends nothing). The vendored SDK stays unmodified. A site that only skipped the opt-in keeps the SDK's behaviour (the opt-in screen comes back, criterion 2).

**Decisions (owner, 2026-11-17):** the plugin-side guard plus a report to Freemius, which the agent drafts and the owner sends; sites already re-armed before the fix are left as they are (only Local test sites are known, Free is not yet in the directory); the plan is kept whole above the size guideline.

## Boundaries & Constraints

**Always:** only the SDK's public instance methods (`is_anonymous()`, `is_registered( true )`, `skip_connection()`); the restore sends no request; a site that opted in again through the Plugins row's dialog is left opted in (criterion 3); hygiene: no Freemius ids or emails in any committed record (lessons 2026-11-17).

**Never:** edit `vendor/freemius/`; clear `fs_accounts` at deletion; touch the Diagnostic Info or Extensions permissions; change the deactivation feedback filter or the uninstall hook of bug 90; change the privacy policy (its promise already matches the fix).

## I/O & Edge-Case Matrix

| Scenario | Before activation | After the SDK's step | Plugin's restore |
|----------|-------------------|----------------------|------------------|
| Communication opted out, same version reactivated or reinstalled | anonymous, account kept | not anonymous (reset) | `skip_connection()` once: anonymous again, not registered, `shares_usage_data()` false |
| Communication and Diagnostic opted out | anonymous, account kept | reset | restored as above; site permission stays off |
| Diagnostic Info only opted out | not anonymous, account kept | unchanged | nothing |
| Skipped the opt-in (no account) | anonymous, no account | reset (opt-in screen returns) | nothing (SDK behaviour, criterion 2) |
| Opted in and sharing | not anonymous | unchanged | nothing |
| Pro build, Communication opted out | anonymous, account kept | unchanged (Pro counts as registered, SDK skips the reset) | nothing |
| Never answered (first install) | not anonymous, no account | unchanged | nothing |

</frozen-after-approval>

## Code Map

- `post-quality-manager/post-quality-manager.php` L113–116, L160–164 -- activation and uninstall hooks; add two `add_action( 'activate_' . plugin_basename( __FILE__ ), …, 9 / 11 )` beside them. The SDK registers its `_activate_plugin_event_hook` at priority 10 on both builds' basenames during `fs_dynamic_init()` (class-freemius.php L1448–1456), before this file's hooks.
- `post-quality-manager/includes/class-pqm-license.php` -- add a private static flag and two public static methods (note at 9, restore at 11), next to `shares_usage_data()`; reuse its `function_exists( 'postquality_fs' )` guard.
- SDK facts (2.13.5, read 2026-11-17, not to change): `skip_connection()` L8609 (public, storage only); `is_registered( $ignore_anonymous_state )` L10713 (true with an account; Pro ignores anonymity); Communication opt-out = `toggle_user_permission( false )` → `skip_connection()` L4980–4987; the row's "Opt In" for a site with an account opens the permissions dialog, which resets anonymity through `toggle_user_permission( true )` (L24470–24476), never through activation; `_deactivate_plugin_hook()` L8186 leaves `is_anonymous` stored.
- `tests/harness/fs-stub.php` -- add `$anonymous`, `$account`, `is_anonymous()`, `is_registered( $ignore = false )` honouring the flag, and `skip_connection()` recording its calls.
- `tests/harness/setup-harness.php` L708–752 -- bug 90's rows read callbacks from `$GLOBALS['tw']['actions']`; add bug 135 rows there in the same style.
- `memory/freemium-and-roles.md` L34 -- the "A reinstall comes back with the earlier consent" bullet.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/fs-stub.php`, `tests/harness/setup-harness.php` -- stub additions; one row per matrix line (the SDK's reset simulated between the two callbacks), plus the two registrations at priority 9 and 11 on `activate_post-quality-manager.php` -- write first; the Communication row fails before the fix (criterion 4).
- [x] `post-quality-manager/includes/class-pqm-license.php` -- `note_opt_out()` sets the flag to `is_anonymous() && is_registered( true )`; `keep_opt_out()` calls `skip_connection()` when the flag is set and `! is_anonymous()` -- the narrowest undo of the SDK's reset.
- [x] `post-quality-manager/post-quality-manager.php` -- the two hooks with a comment that names `memory/freemium-and-roles.md`.
- [x] `memory/freemium-and-roles.md` -- the bullet records the fix, the frame's 13:02 install update, and that a plain reactivation takes the same path.
- [x] `docs/release-notes/unreleased.md` -- one line (see Design Notes).
- [x] Upstream report (session scratchpad, not committed) -- a short Freemius issue: SDK 2.13.5, the steps, L7487–7515, expected vs actual; no site, install, user or license ids, no emails; the owner sends it.

**Acceptance Criteria:**
- Given the Free build on Local WordPress 6.6 with the request logger, when a site opts in (owner), opts out of Communication, then is deactivated and activated, and separately deleted and reinstalled, then the SDK reads `is_anonymous` true and `shares_usage_data()` false each time, the row reads "Opt In", and the logger shows no Freemius request from activation until the next opt-in.
- Given that site, when the owner opts in again through the row's dialog, then it is registered and sharing.
- Given the change, then `php -l`, every harness, `npm run test:unit`, the Free build check and the Free build harness pass.

## Implementation Notes

- 2026-11-17, implementer subagent (Opus, standing in for bmad-build's dispatch; session run from the workspace root). Harness rows written first: all eight bug 135 rows in `setup-harness.php` failed before the fix (no callback registered), all pass after.
- Stub: `is_registered( $ignore )` keeps the `registered` override the bug 90 rows use and otherwise follows the Free rule (`account && ( ignore || ! anonymous )`); `skip_connection()` records its argument in `skipped` and sets `anonymous`. The Pro row reads only `skipped` and `anonymous`, since the stub's `is_registered()` follows the Free rule; the harness plays the SDK's branch (Pro: any account counts as registered).
- `keep_opt_out()` relies on the flag for the `function_exists( 'postquality_fs' )` guard (the flag is false without it).
- `docs/release-notes/unreleased.md` re-created with the `## Upgrading from an earlier version` heading (`/release` step 3), since 4.0.3 renamed the last one.
- Upstream report drafted at the session scratchpad (`freemius-issue-reset-anonymous-mode.md`), not committed; the owner sends it.
- Verified: `php -l` on the four touched PHP files, every `tests/harness/*-harness.php` green (`free-build-harness.php` included), `npm run test:unit` 214/214; `bin/build-free.php` keeps both hooks and methods in the Free tree. The Local walk (acceptance criteria 1–2) is open.
- Review pass 1 patches (2026-11-17, the same implementer): a `free-build-harness.php` row proves the stripped Free tree registers both activation hooks; the memory bullet's SDK citation corrected. Re-verified by the coordinator: `php -l` clean, every harness green, `npm run test:unit` 214/214, no carriage returns in the touched files, no Freemius ids or emails in the diff.
- **Local walk (2026-11-17, WordPress66, WordPress 6.6, PHP 8.1.29): acceptance criteria 1 and 2 pass.** Build: CI's `free-build` artifact for PR #27 zipped (version 4.0.3, both hooks present). One frame per `docs/local-verification.md`: backup, a must-use request logger (method, host, path, caller, context; no bodies), `WP_DEBUG_LOG`, user 1's email and `admin_email` set to the project mailbox for the frame; the coordinator ran stage A, an Opus agent stage B; the owner gave both opt-ins through Claude in Chrome.
  - **Skipped row (matrix):** activate → opt-in screen → Skip → deactivate → activate: the opt-in screen came back (the SDK's behaviour, unchanged); no Freemius request.
  - **Opt-in (owner):** "Allow & Continue", no confirmation email this time; registered and sharing.
  - **Communication opt-out** on the Plugins row: not registered, anonymous, `shares_usage_data()` false; one `PUT …/permissions.json` (the opt-out itself).
  - **Deactivate and activate:** no feedback form; landed on the Plugins screen, no opt-in screen, row "Opt In | Deactivate"; anonymous, not registered, not sharing. **Delete and reinstall** (same zip) **and activate:** the same. After each, the Dashboard, Plugins and Post Reviews loaded; no `fs_` cron event; **no request to any Freemius host from the opt-out to the owner's next opt-in** (only WordPress.org update checks).
  - **The guard acted:** the SDK's stored opt-out carried the reinstall's activation time (96 s after the opt-out), which only the restore's `skip_connection()` writes there.
  - **Opt-in again (owner):** the row's dialog, "Opt In" beside Communication; the page reloaded itself; row "Opt Out | Deactivate"; registered and sharing; one `PUT …/permissions.json`.
  - **`debug.log`:** nothing from the plugin; one vendor SDK warning ("foreach() argument must be of type array|object, null given", `class-freemius.php` L18758) between the opt-in and stage B.
  - **Restore:** the plugin, the logger and its folder removed (the deletion of the opted-in plugin sent its usual uninstall update), the dump imported, `wp-config.php` back; plugin list, tables/options/users/posts and files "same"; WordPress 6.6, both emails the site's own.
  - **Open:** criterion 5 of the ticket (re-walk of story 38's opt-in steps on the 4.0.x release with the owner's dashboard read); the Freemius report, drafted, for the owner to send.

## Plan Change Log

## Review Triage Log

- Pass 1 (2026-11-17, lens `quick`, Opus; session run from the workspace root, a subagent stands in for the lens): 4 findings — high 0, medium 0, low 2, false 2, maybe-false 0.
  - false — acceptance criteria 1–2 not walked yet. Not a defect in the diff: the Local walk is this plan's Verification manual check and needs the owner's opt-in, so it runs on the branch build after review; the result goes in Implementation Notes.
  - false — the Free build check not run. CI's `free-build` job runs it on the PR; read with `gh pr checks`.
  - low, patch — no row proves the stripped Free tree (the only build with the bug) keeps the activation hooks; `free-build-harness.php` already records the stripped main file's hooks (story 65). Fix: one row on `$GLOBALS['free_actions']`.
  - low, patch (memory only) — `memory/freemium-and-roles.md` cites L24470–24476 (the tracking link's text) for the dialog's reset; the real path is `_toggle_permission_tracking_callback()` → `toggle_permission_tracking()` L4891 → `toggle_user_permission( true )` L4961 → `reset_anonymous_mode()` L4984. The same slip in this plan's Code Map is left as written (a review never edits its plan).

## Design Notes

Skipped: the Free-to-Pro swap while the Free build owns the SDK (this file returns early, L71–80); Pro counts as registered either way. Add when a walk shows Pro sharing more after the swap.
Release notes: `docs/release-notes/unreleased.md` gets one line (an opted-out site stays opted out when the plugin is activated again; nothing to do), per AGENTS.md's Definition of Done.
Skipped: repairing a site re-armed before the fix (owner decision). Add when a released site reports it: the SDK's stored `is_user_tracking_allowed` flag stays false through the reset.

## Verification

**Commands:**
- `php -l` on the three PHP files -- expected: no syntax errors.
- `for f in tests/harness/*-harness.php; do php "$f"; done` -- expected: every harness passes, including the new rows.
- `npm run test:unit` -- expected: pass.

**Manual checks (if no CLI):**
- Local WordPress66 frame per `docs/local-verification.md` on the branch's Free build: the backup, the logger, the owner's opt-in and confirmation (Claude in Chrome), a Communication opt-out, then a deactivate and reactivate and a delete and reinstall, reading the SDK booleans and the logger after each; restore, three "same". The re-walk with the dashboard read (ticket criterion 5) follows the 4.0.x release.
