---
id: 151
type: bug
title: "WordPress.org refuses the upload because the plugin and author URIs are the same"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P1
estimate: ""
---

# WordPress.org refuses the upload because the plugin and author URIs are the same

## Description

WordPress.org's "Add your plugin" form refuses the v4.0.7 Free zip before its Plugin Check scan, so the plugin never reaches the review queue. The plugin header sets `Plugin URI` and `Author URI` to the same address, and the form refuses any upload where the two are equal. The header should give the plugin's home page as `Plugin URI` and a page about the author as `Author URI`, so the form moves on to its next checks.

## Reproduction

1. 2026-11-24, the owner's Claude in Chrome, signed in as `postquality`, on https://wordpress.org/plugins/developers/add/. All eight confirmations were ticked, and the file was the v4.0.7 release asset `post-quality-manager-4.0.7.zip` (sha256 `992177ba…16ea`). The Additional Information text was filled in, and Upload was clicked once.
2. Actual: the same page reloads, empty, with: "Error: Your plugin and author URIs are the same. Your plugin headers in the main plugin file headers have the same value for both the plugin and author URI (Uniform Resource Identifier). A plugin URI is a webpage that provides details about this specific plugin. An author URI is a webpage that provides information about the author of the plugin. Those two must be different. You are not required to provide both, so pick the one that best applies to your situation." No slug or plugin name is shown, and nothing is queued.
3. Expected: the header checks pass, and the upload goes on to the readme, license and Plugin Check steps.

## Cause Hypothesis

Story 45 (2026-10-09) set both headers to `https://postquality.com`. WordPress.org's upload handler compares the two values when both are set, before Plugin Check runs (`class-upload-handler.php`, `process_upload()`, the check commented "Prevent duplicate URLs", read on trunk 2026-11-24), and refuses them as equal. Story 95 read the form's Plugin Check scan in WordPress.org's source, but not the header checks that run before it.

## Acceptance Criteria

1. **The two URIs name different pages**
   **Given** the plugin header as WordPress reads it
   **When** the Free build is uploaded through WordPress.org's form
   **Then** `Plugin URI` is the plugin's home page on postquality.com, which is not a WordPress.org address, and `Author URI` is the owner's WordPress.org profile, so the two differ
2. **Tests cover the condition found and fixed**
   **Given** the CLI harnesses
   **When** they run
   **Then** a check that fails on equal `Plugin URI` and `Author URI` values, and on a `Plugin URI` on wordpress.org, passes on the fix
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** the header is read on the current code
   **Then** the two values already differ, with the evidence recorded in Notes; this supersedes 1 and 2

## References

- parent — none
- story — `_bmad-output/backlog/story-wordpress-org-directory-listing.md` (story 38: this bug blocks its submission)
- source — https://github.com/WordPress/wordpress.org, `wordpress.org/public_html/wp-content/plugins/plugin-directory/shortcodes/class-upload-handler.php`, `process_upload()`
- source — https://developer.wordpress.org/plugins/plugin-basics/header-requirements/ (Plugin URI: "You cannot use a WordPress.org URL here"; Author URI: "The author's website or profile on another website, such as WordPress.org")
- data — `docs/distribution.md` (the Plugin URI and Author URI rows), `docs/brand.md` § Naming (the domain row)

## Notes

- Decision (owner, 2026-11-24): `Author URI` becomes https://profiles.wordpress.org/heimvin/, the owner's personal WordPress.org profile. `Plugin URI` stays https://postquality.com. The header's `Author` (Vincent Heimann) is unchanged.
- Decision (owner, 2026-11-24): this ships in a patch release (4.0.8) before the submission, and the bug joins story 38's `after`. The owner then sets the Freemius deployment to Released and submits 4.0.8's Free zip.
- The fix PR also records: `docs/distribution.md`'s Author URI row and `docs/brand.md`'s domain row; story 38's pass list, where "submission: clear" becomes "submission: blocked by 151" until the release; and a `memory/lessons.md` entry about reading the whole upload gate. Of the handler's checks after this one, three pass on 4.0.7: the readme, the license (`GPL-2.0-or-later`), and Plugin Check (story 38's P068 ran the form's exact scan on 4.0.6; 4.0.7 changed only the readme's text and the version lines). The name-in-the-wild check reads WordPress.org's own install stats and cannot be seen from outside.
