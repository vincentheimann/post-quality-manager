---
title: 'The plugin header gives different Plugin and Author URIs'
type: 'bugfix'
ticket: '151'
created: '2026-11-24'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
baseline_revision: '82ebe6ed1ba9f0cdb9739ac7a8245307a74b3b11'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** WordPress.org's "Add your plugin" form refused the v4.0.7 Free zip on 2026-11-24 with "Error: Your plugin and author URIs are the same." The form does this whenever both headers are set and equal. Ours are both `https://postquality.com`, set by story 45. Story 38's submission is blocked until a release fixes the header.

**Approach:** `Author URI` becomes the owner's WordPress.org profile, `https://profiles.wordpress.org/heimvin/`, and `Plugin URI` stays `https://postquality.com` (owner, 2026-11-24). A harness check makes the form's rule fail CI. The records that name the old Author URI are updated, story 38's pass list reads "submission: blocked by 151" until 4.0.8 ships, and a lessons entry is added. Release 4.0.8 and the resubmission come after the merge, outside this plan.

## Boundaries & Constraints

**Always:** The header's `Author` stays "Vincent Heimann". `Plugin URI` stays a non-WordPress.org page (handbook: "You cannot use a WordPress.org URL here"). The version lines stay release-please's. ADR 0013 gets a dated amendment line rather than a rewrite.

**Never:** Change the readme's `Contributors`, the slug, or the Plugin Name. Hand-bump the version. Add release notes: sites see only a new author link on the Plugins screen and do nothing. Touch the site repository.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Fixed header | Plugin URI `https://postquality.com`, Author URI the WordPress.org profile | The harness's two URI checks pass | No error expected |
| Equal URIs (the defect) | both headers the same value | "Plugin URI and Author URI differ" fails | CI red until they differ |
| Plugin URI on wordpress.org | e.g. `https://wordpress.org/plugins/post-quality-manager/` | "Plugin URI is not a WordPress.org address" fails | CI red |

</frozen-after-approval>

## Code Map

- `post-quality-manager/post-quality-manager.php` L12 -- the `Author URI:` header line (L4 `Plugin URI:` unchanged). Both builds read this file; the Free build check compares it as is.
- `tests/harness/release-header-harness.php` -- Row 1 "the header parses as WordPress reads it" (L41–46): `header_value()` mimics `get_file_data()`. Add the two URI checks there, plus one docblock line naming bug 151.
- `docs/distribution.md` L16 -- the Author URI row ("set by story 45"). L10, the Plugin URI row, is unchanged.
- `docs/brand.md` L131 -- the "Domain, support mailbox, `Plugin URI` / `Author URI`" row.
- `docs/adr/0013-pro-sold-through-freemius-before-public-release.md` L26 -- "Public home … carries the plugin's URLs (Plugin URI, Author URI …)". Add an italic amendment in the D9 style (L24).
- `_bmad-output/backlog/story-wordpress-org-directory-listing-plan.md` -- the pass list's "**submission: clear**" line after the table, and the Implementation Notes "submission: clear (2026-11-24)" bullet.
- `memory/lessons.md` -- append the entry (newest-last file order).
- Not changed: `bin/directory-preview.php` L164 (the header's Author and Author URI feed neither the card nor the sidebar), `CHANGELOG.md` (release-please's).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/post-quality-manager.php` -- set `Author URI:` to `https://profiles.wordpress.org/heimvin/`, aligned like the other values -- the fix.
- [x] `tests/harness/release-header-harness.php` -- Row 1: check that both URIs are set and differ, and that Plugin URI's host is not `wordpress.org` or a subdomain of it. Add a docblock line -- the guard; run it once against a temporary equal value to see it fail, then restore.
- [x] `docs/distribution.md`, `docs/brand.md`, ADR 0013 -- name the new Author URI and the reason (the upload form's rule, bug 151).
- [x] Story 38's plan -- replace "**submission: clear**" with "**submission: blocked by 151**" and its one-line reason. Add an Implementation Notes bullet: the 2026-11-24 refusal, the handler's checks after this one pass on 4.0.7, and the next steps (4.0.8, Freemius Released, resubmit).
- [x] `memory/lessons.md` -- entry: a third party's gate (here WordPress.org's upload handler) read only at the part a story was about (the Plugin Check scan); rule: read the gate from its entry point and list every check's result on our build before submitting.

**Acceptance Criteria:**
- Given the 4.0.8 Free build, when the header is read as WordPress reads it, then `Plugin URI` and `Author URI` differ and `Plugin URI` is not a WordPress.org address.
- Given the records, when they are searched for the Author URI, then none names `https://postquality.com` as the Author URI any more, except history (CHANGELOG, older plan logs).

## Implementation Notes

- 2026-11-24, implementer (Opus subagent): the five tasks done, uncommitted on `fix/151-plugin-and-author-uri-differ`.
  - Header: `Author URI:        https://profiles.wordpress.org/heimvin/`, aligned like the other values; `Plugin URI` unchanged.
  - Harness, Row 1: "Plugin URI and Author URI differ" (both set and not equal, the form's `==` on the raw values) and "Plugin URI is not a WordPress.org address" (host from `parse_url()`, lowercased, neither `wordpress.org` nor `*.wordpress.org`). Each label prints the values it read. Seen failing once each, the file restored by copy (sha256 the same): equal URIs → "38 passed, 1 failed" on the first check; `https://wordpress.org/plugins/post-quality-manager/` as Plugin URI → "38 passed, 1 failed" on the second.
  - `docs/brand.md`: the domain row keeps `Plugin URI`, and a new row below it gives `Author URI` (the profile, never the Plugin URI, the reason). One row per header reads more clearly than one row with two values.
  - Story 38's plan: the bullet's "handler's checks after this one" were read in the upload handler itself (`class-upload-handler.php`, trunk, 2026-11-24): after "Prevent duplicate URLs" come the name in the wild (WordPress.org's install stats, not readable from outside, so the bullet names it as such), the readme, the readme's title clash, the name in the wild again for the readme's name, the readme's license, then Plugin Check. 4.0.7 differs from 4.0.6 only in `readme.txt` text and the version lines (`git diff v4.0.6 v4.0.7`), so P068's 4.0.6 scan stands for it.
  - Outside the Code Map: `memory/release-readiness.md` L38 (story 45's 2026-10-09 build log) gains "the Author URI moved to the owner's WordPress.org profile on 2026-11-24, bug 151", so the memory store no longer reads as if the old value were current. History left as is: CHANGELOG, story 45's ticket and plan, story 38's 2026-10-24 practices audit (row 28), story 70's plan (its first derivation's log).

## Plan Change Log

## Review Triage Log

- **Pass 1 (2026-11-24, lens `quick`, Opus):** 3 findings: high 0, medium 0, low 3, false 0, maybe-false 0. All three are records, none is code; all patched.
  - low, patch (fixed by bmad-ticket, a ticket file is never the build's): the ticket's last Notes bullet claimed the name-in-the-wild check passes on 4.0.7, against this plan's and story 38's "cannot be read from outside", and called P068's scan 4.0.7's. Evidence: handler L439–455 and L499–514 read `wporg_stats_get_plugin_name_install_count()`, private stats. The bullet now names three passes, the unreadable check, and P068 on 4.0.6.
  - low, patch (implementer): the lessons entry said no one read the upload handler; story 38's plan L614 (F1) and bug 125's References cite `class-upload-handler.php` at `check_plugin()`. The entry now says the file was read only at `check_plugin()`, never from `process_upload()`.
  - low, patch (implementer): story 38's bullet gave "no listed plugin carries its name, the slug is free" for the readme check, which searches unlisted statuses too (handler L471–476). It now cites the earlier title check (L336–347, same seven statuses, same string "Post Quality Manager"), which passed since the form reached the URI check.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness green, release-header's two new checks among them
- `php -l post-quality-manager/post-quality-manager.php tests/harness/release-header-harness.php` -- expected: no syntax errors
- `git grep -n "Author URI"` -- expected: no current record pairs Author URI with postquality.com
