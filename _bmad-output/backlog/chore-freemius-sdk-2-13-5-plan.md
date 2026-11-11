---
title: 'The vendored Freemius SDK is 2.13.5, unmodified'
type: 'chore'
ticket: '89'
created: '2026-10-24'
status: done
baseline_revision: '4a8fc000cca61cd6e9cf39b7e6bfe45d82f5b4e4'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** the plugin vendors Freemius SDK 2.13.4. Freemius released 2.13.5 on 2026-10-22 (security hardening of the admin notices and the opt-in flow) and asks every product to update, and the WordPress.org review team asks for the latest stable version of a bundled library (audit row 14).

**Approach:** replace `post-quality-manager/vendor/freemius/` with the 2.13.5 tag archive's folder contents, unmodified, the way story 44 vendored 2.13.4, and update the two records that name the version.

## Boundaries & Constraints

**Always:** the vendored folder equals the tag archive's 197 files byte for byte (`.gitattributes` already sets `-text` there); the records name 2.13.5 and the archive URL.

**Never:** edit any SDK file; change the `fs_dynamic_init()` keys or the plugin's SDK filters (`plugin_icon`, `pricing_url`); add or drop files beyond the archive's list; touch bug 90's behaviour (a separate ticket).

</frozen-after-approval>

## Code Map

- `post-quality-manager/vendor/freemius/` -- byte-identical to the 2.13.4 archive today (checked 2026-10-24: `diff -rq` empty, 197 files). 2.13.5 has the same 197 paths and changes six: `composer.json`, `start.php` (`$this_sdk_version`), `includes/class-freemius.php`, `includes/managers/class-fs-clone-manager.php`, `templates/add-ons.php`, `templates/pricing.php`.
- `includes/class-freemius.php` 2.13.5 changes -- output escaping (`esc_html` / `esc_url`) on server error messages and email addresses; the "You have purchased a … license" sticky notice after `purchase_completed` removed; one opt-in path returns early unless `is_user_admin()`. Untouched: `add_submenu_items()`'s `pricing_url` probe (bug 59's filter answers it) and `_uninstall_plugin_event()` / `opt_in()` (bug 90).
- `bin/build-free.php` -- mimics Freemius leaving the SDK's `README.md` and `composer.json` out of the Free build; the list is unchanged in 2.13.5. No change.
- `tests/harness/fs-stub.php` -- the harnesses stub the SDK (`PQM_FS_Stub`), so no harness loads the vendored code. No change.
- `docs/distribution.md` (the "SDK version and vendor folder" row) -- names 2.13.4 and its archive URL.
- `memory/freemium-and-roles.md` (the "Real licensing" bullet) -- names 2.13.4 as vendored.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/vendor/freemius/` -- replace the folder's contents with the 2.13.5 archive's (https://github.com/Freemius/wordpress-sdk/archive/refs/tags/2.13.5.zip, top folder stripped), extracted outside the repository first -- the update itself
- [x] `docs/distribution.md` -- SDK row: 2.13.5 and its archive URL -- the data sheet names what ships
- [x] `memory/freemium-and-roles.md` -- "Real licensing" bullet: 2.13.5 vendored 2026-10-24 (chore 89), and one dated line on what 2.13.5 changed that the plugin meets (the purchase sticky notice gone, admin-only opt-in) -- memory says what is true now

**Acceptance Criteria:**
- Given the 2.13.5 tag archive extracted outside the repository, when `diff -rq` compares it with `post-quality-manager/vendor/freemius/`, then it prints nothing.
- Given the updated tree, when every harness (the free-build harness included) and `npm run test:unit` run, then all pass with the same counts as on `main`.
- Given the change, when `git diff --stat` is read, then only the six SDK files, `docs/distribution.md`, `memory/freemium-and-roles.md` and this plan changed.

## Implementation Notes

- Method applied by hand from the workspace root (owner rule 2026-10-16): `bmad-build` rendered with `render_skill.py`, its steps followed in order; the implementer and the quick lens ran as subagents on Opus 5.5 (the session model).
- Implementer: the 2.13.5 archive downloaded again (SHA-256 equal to the planning copy), the folder's contents replaced; `diff -rq` against the archive prints nothing; six SDK files modified, none added or removed. Harness sweep 19 harnesses 0 failed with counts equal before and after (render-matrix 421, free-build 84, rest-posts 180, sanitize-matrix 226, setup 152, caps 284); `npm run test:unit` 10 suites, 174 tests, before and after; `php -l` clean on the five changed SDK PHP files.
- Outside the Code Map (orchestrator): `memory/MEMORY.md`'s Freemium & roles index line named "SDK 2.13.4"; it now says 2.13.5 since chore 89, because memory changes in the same change that makes a fact true (AGENTS.md). AC3's file list is one file short of that.
- Ticket 89's AC2 asks for Plugin Check on the Free build: CI's Plugin Check skips `vendor/` (`exclude-directories`), so an SDK-only change cannot move its result, and Actions are blocked until 2026-11-01. The run that includes `vendor/` is story 38 AC1's WP Admin run on the Free zip (criterion added 2026-10-24) and the owner's next Local sitting (Manual checks).
- Ticket 89's AC3 re-check: in 2.13.5 no hunk falls in `add_submenu_items()` (bug 59's `pricing_url` probe), `_uninstall_plugin_event()` or `opt_in()` (bug 90); memory says so.
- Merge order: ticket 89, the audit and bug 90 are filed by PR #191; this change merges after it.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, 2026-10-24):** 5 findings: high 0, medium 3, low 2, false 0, maybe-false 0. No loopback.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | `memory/MEMORY.md` changed, outside AC3's file list | low | patch | The edit is right (the index line named 2.13.4). Recorded in Implementation Notes. |
| 2 | The plan lacked Implementation Notes, Plan Change Log and Review Triage Log | medium | patch | Written without them at planning (lessons 2026-10-19 says fill the whole template). Sections added in this pass. |
| 3 | Ticket 89 does not exist on this branch | medium | patch | It is filed by PR #191 (`chore/sprint-golive-readiness`), cut from the same `main`; not stacked by rule. The PR states the merge order; Implementation Notes too. |
| 4 | Ticket AC3's re-check not recorded; AC2's Plugin Check moved without a record | medium | patch | Re-check verified (no 2.13.5 hunk in the three functions); memory records it; the Plugin Check reasoning is in Implementation Notes. |
| 5 | Memory says the opt-in returns early unless admin; only `_install_with_new_user()` gained the guard | low | patch | `vendor/freemius/includes/class-freemius.php:17541–17551`; `_install_with_current_user()` has no such guard. Sentence corrected. |

## Design Notes

No release-notes line: the update adds no default, permission or screen and needs no workflow re-import. A buyer no longer sees Freemius's "You have purchased a … license" notice after checkout, which is Freemius's choice in 2.13.5, not the plugin's.

## Verification

**Commands:**
- `diff -rq <scratchpad>/sdk/a5/wordpress-sdk-2.13.5 post-quality-manager/vendor/freemius` -- expected: no output
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed, counts equal to `main`
- `npm run test:unit` -- expected: green
- (the sweep above includes `tests/harness/free-build-harness.php`, which runs `bin/build-free.php` into a temporary folder itself) -- expected: green

**Manual checks (if no CLI):**
- On the next Local sitting (owner): activate the Free build, the opt-in screen renders, Skip works, the Account page loads, no PHP notice with `WP_DEBUG` on. CI's Plugin Check skips `vendor/` and Actions are blocked until 2026-11-01, so this walk and story 38's WP Admin Plugin Check run are the evidence on a live site.
